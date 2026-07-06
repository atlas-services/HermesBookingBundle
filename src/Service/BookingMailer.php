<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Contract\BookingMailSignatureProviderInterface;
use AtlasServices\HermesBookingBundle\Contract\BookingNotificationRecipientResolverInterface;
use AtlasServices\HermesBookingBundle\Entity\BookingReservation;
use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BookingMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly BookingDateTimeHelper $dateTimeHelper,
        private readonly BookingCalendarRepository $calendarRepository,
        private readonly BookingNotificationRecipientResolverInterface $recipientResolver,
        private readonly BookingMailSignatureProviderInterface $signatureProvider,
    ) {
    }

    public function sendConfirmation(BookingReservation $reservation, string $locale = 'fr'): void
    {
        $context = $this->buildContext($reservation, $locale);

        $this->sendCustomer(
            $reservation,
            $locale,
            'booking.mail.customer_subject',
            '@HermesBooking/email/booking_customer.html.twig',
            $context,
        );

        $this->sendAdmin(
            $reservation,
            $locale,
            'booking.mail.admin_subject',
            '@HermesBooking/email/booking_admin.html.twig',
            $context,
        );
    }

    public function sendUpdateNotification(
        BookingReservation $reservation,
        string $previousDateLabel,
        string $locale = 'fr',
    ): void {
        $context = $this->buildContext($reservation, $locale, $previousDateLabel, true);

        $this->sendCustomer(
            $reservation,
            $locale,
            'booking.mail.customer_updated_subject',
            '@HermesBooking/email/booking_customer.html.twig',
            $context,
        );

        $this->sendAdmin(
            $reservation,
            $locale,
            'booking.mail.admin_updated_subject',
            '@HermesBooking/email/booking_admin.html.twig',
            $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendCustomer(
        BookingReservation $reservation,
        string $locale,
        string $subjectKey,
        string $template,
        array $context,
    ): void {
        $subject = $this->translator->trans($subjectKey, [
            '%name%' => $reservation->getDisplayName(),
            '%date%' => $context['dateLabel'],
        ], 'booking', $locale);

        $fromName = $this->resolveFromName($context['signature']);

        $email = (new TemplatedEmail())
            ->from(new Address($this->recipientResolver->resolveFromEmail(), $fromName))
            ->to($reservation->getEmail())
            ->locale($locale)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(array_merge($context, ['subject' => $subject]));

        $this->mailer->send($email);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendAdmin(
        BookingReservation $reservation,
        string $locale,
        string $subjectKey,
        string $template,
        array $context,
    ): void {
        $adminEmail = $this->recipientResolver->resolveAdminEmail();
        if ($adminEmail === '') {
            return;
        }

        $subject = $this->translator->trans($subjectKey, [
            '%name%' => $reservation->getDisplayName(),
            '%date%' => $context['dateLabel'],
        ], 'booking', $locale);

        $fromName = $this->resolveFromName($context['signature']);

        $email = (new TemplatedEmail())
            ->from(new Address($this->recipientResolver->resolveFromEmail(), $fromName))
            ->to($adminEmail)
            ->replyTo($reservation->getEmail())
            ->locale($locale)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context(array_merge($context, ['subject' => $subject]));

        $this->mailer->send($email);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(
        BookingReservation $reservation,
        string $locale,
        ?string $previousDate = null,
        bool $updated = false,
    ): array {
        $calendar = $this->calendarRepository->findOneBy(['bookingKey' => $reservation->getBookingKey()]);
        $calendarLabel = $calendar?->getLabel();

        return [
            'reservation' => $reservation,
            'locale' => $locale,
            'dateLabel' => $this->formatReservationDate($reservation),
            'calendarLabel' => $calendarLabel !== '' ? $calendarLabel : null,
            'previousDate' => $previousDate,
            'updated' => $updated,
            'signature' => $this->signatureProvider->getSignature($locale),
        ];
    }

    /**
     * @param array{siteName: ?string, siteUrl: ?string, logoUrl: ?string} $signature
     */
    private function resolveFromName(array $signature): string
    {
        $siteName = trim((string) ($signature['siteName'] ?? ''));

        return $siteName !== '' ? $siteName : 'Hermes';
    }

    private function formatReservationDate(BookingReservation $reservation): string
    {
        return $this->dateTimeHelper->fromStorage($reservation->getStartsAt())->format('d/m/Y H:i');
    }
}
