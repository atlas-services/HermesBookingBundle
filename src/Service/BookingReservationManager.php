<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Entity\BookingReservation;
use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;
use AtlasServices\HermesBookingBundle\Repository\BookingReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

final class BookingReservationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingCalendarRepository $calendarRepository,
        private readonly BookingReservationRepository $reservationRepository,
        private readonly BookingAvailabilityService $availabilityService,
        private readonly BookingDateTimeHelper $dateTimeHelper,
        private readonly BookingMailer $mailer,
    ) {
    }

    /**
     * @param array{firstname: string, lastname: string, email: string, phone?: ?string, message?: ?string, date: string, time: string, locale?: string} $data
     */
    public function createReservation(string $bookingKey, array $data): BookingReservation
    {
        $calendar = $this->calendarRepository->findOrCreate($bookingKey);
        $startsAt = $this->dateTimeHelper->fromFormParts($data['date'], $data['time']);
        if (null === $startsAt) {
            throw new \InvalidArgumentException('Invalid date or time.');
        }

        if (!$this->availabilityService->isSlotAvailable($bookingKey, $startsAt)) {
            throw new \RuntimeException('Slot is no longer available.');
        }

        $reservation = (new BookingReservation())
            ->setBookingKey($bookingKey)
            ->setStartsAt($startsAt)
            ->setDurationMinutes($calendar->getSlotDurationMinutes())
            ->setFirstName($data['firstname'])
            ->setLastName($data['lastname'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'] ?? null)
            ->setMessage($data['message'] ?? null);

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        try {
            $this->mailer->sendConfirmation($reservation, $data['locale'] ?? 'fr');
        } catch (\Throwable) {
            // La réservation est enregistrée même si l'envoi d'e-mail échoue.
        }

        return $reservation;
    }

    /**
     * @param array{firstname: string, lastname: string, email: string, phone?: ?string, message?: ?string, date: string, time: string, locale?: string} $data
     */
    public function updateReservation(BookingReservation $reservation, array $data): BookingReservation
    {
        $previousDateLabel = $this->dateTimeHelper
            ->fromStorage($reservation->getStartsAt())
            ->format('d/m/Y H:i');

        $startsAt = $this->dateTimeHelper->fromFormParts($data['date'], $data['time']);
        if (null === $startsAt) {
            throw new \InvalidArgumentException('Invalid date or time.');
        }

        if (!$this->availabilityService->isSlotAvailable(
            $reservation->getBookingKey(),
            $startsAt,
            $reservation->getId(),
        )) {
            throw new \RuntimeException('Slot is no longer available.');
        }

        $reservation
            ->setStartsAt($startsAt)
            ->setFirstName($data['firstname'])
            ->setLastName($data['lastname'])
            ->setEmail($data['email'])
            ->setPhone($data['phone'] ?? null)
            ->setMessage($data['message'] ?? null);

        $this->entityManager->flush();

        try {
            $this->mailer->sendUpdateNotification(
                $reservation,
                $previousDateLabel,
                $data['locale'] ?? 'fr',
            );
        } catch (\Throwable) {
            // Mise à jour enregistrée même si l'e-mail échoue.
        }

        return $reservation;
    }

    public function deleteReservation(BookingReservation $reservation): void
    {
        $this->entityManager->remove($reservation);
        $this->entityManager->flush();
    }

    public function findReservation(int $id): ?BookingReservation
    {
        return $this->reservationRepository->find($id);
    }
}
