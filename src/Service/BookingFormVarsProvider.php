<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Form\BookingReservationFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class BookingFormVarsProvider
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly BookingAvailabilityService $availabilityService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Variables Twig pour le formulaire front (sans dépendance au modèle de l'hôte).
     *
     * @param array<string, string> $presentation Styles optionnels : bgcolor, color, bgcolor_btn,
     *                                            bgcolor_input, color_input, border_color_input,
     *                                            rounded_input, py_input, my_input
     *
     * @return array<string, mixed>
     */
    public function provide(
        string $bookingKey,
        string $locale,
        array $presentation = [],
        ?string $presentationText = null,
        ?string $userText = null,
    ): array {
        $presentation = $this->normalizePresentation($presentation);
        $inputClass = sprintf(
            'form-control hermes-form-field rounded-%s py-%s my-%s',
            $presentation['rounded_input'],
            $presentation['py_input'],
            $presentation['my_input'],
        );

        $availableDates = $this->availabilityService->getAvailableDates($bookingKey);
        $dateMin = $availableDates[0] ?? '';
        $dateMax = $availableDates !== [] ? $availableDates[array_key_last($availableDates)] : '';

        $form = $this->formFactory->create(BookingReservationFormType::class, null, [
            'input_class' => $inputClass,
            'booking_key' => $bookingKey,
            'date_min' => $dateMin,
            'date_max' => $dateMax,
        ]);

        return [
            'booking_key' => $bookingKey,
            'booking_form' => $form->createView(),
            'form_presentation' => $presentation,
            'booking_slots_url' => $this->urlGenerator->generate('hermes_booking_slots', [
                '_locale' => $locale,
                'bookingKey' => $bookingKey,
            ]),
            'booking_calendar_url' => $this->urlGenerator->generate('hermes_booking_calendar', [
                '_locale' => $locale,
                'bookingKey' => $bookingKey,
            ]),
            'booking_submit_url' => $this->urlGenerator->generate('hermes_booking_submit', [
                '_locale' => $locale,
                'bookingKey' => $bookingKey,
            ]),
            'booking_available_dates' => $availableDates,
            'booking_date_min' => $dateMin,
            'booking_date_max' => $dateMax,
            'booking_date_invalid_message' => 'booking.form.date_unavailable',
            'booking_presentation_text' => $this->nullableString($presentationText),
            'booking_user_text' => $this->nullableString($userText),
        ];
    }

    /**
     * @param array<string, string> $presentation
     *
     * @return array<string, string>
     */
    private function normalizePresentation(array $presentation): array
    {
        $rounded = (int) ($presentation['rounded_input'] ?? 0);
        $py = (int) ($presentation['py_input'] ?? 2);
        $my = (int) ($presentation['my_input'] ?? 2);

        return [
            'bgcolor' => (string) ($presentation['bgcolor'] ?? 'transparent'),
            'color' => (string) ($presentation['color'] ?? '#000000'),
            'bgcolor_btn' => (string) ($presentation['bgcolor_btn'] ?? 'btn-outline-primary'),
            'bgcolor_input' => (string) ($presentation['bgcolor_input'] ?? '#ffffff'),
            'color_input' => (string) ($presentation['color_input'] ?? '#000000'),
            'border_color_input' => (string) ($presentation['border_color_input'] ?? '#dee2e6'),
            'rounded_input' => (string) max(0, min(5, $rounded)),
            'py_input' => (string) max(0, min(5, $py)),
            'my_input' => (string) max(0, min(5, $my)),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' || $s === '~' ? null : $s;
    }
}
