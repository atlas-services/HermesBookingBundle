<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Entity\BookingCalendar;
use AtlasServices\HermesBookingBundle\Repository\BookingBlockedDateRepository;
use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;
use AtlasServices\HermesBookingBundle\Repository\BookingReservationRepository;

final class BookingAvailabilityService
{
    public function __construct(
        private readonly BookingCalendarRepository $calendarRepository,
        private readonly BookingBlockedDateRepository $blockedDateRepository,
        private readonly BookingReservationRepository $reservationRepository,
        private readonly BookingDateTimeHelper $dateTimeHelper,
    ) {
    }

    public function getCalendarForKey(string $bookingKey, ?string $label = null): BookingCalendar
    {
        return $this->calendarRepository->findOrCreate($bookingKey, $label);
    }

    public function getTimezone(): string
    {
        return $this->dateTimeHelper->getTimezone();
    }

    /**
     * @return list<string> Y-m-d
     */
    public function getAvailableDates(string $bookingKey, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null): array
    {
        $calendar = $this->getCalendarForKey($bookingKey);
        $tz = $this->dateTimeHelper->timezone();
        $now = new \DateTimeImmutable('now', $tz);
        $from ??= $now->setTime(0, 0);
        $to ??= $from->modify(sprintf('+%d days', $calendar->getHorizonDays()));

        $blocked = $this->blockedDateRepository->findBlockedDateMapByBookingKey($bookingKey, $from, $to);
        $reservations = $this->reservationRepository->findByBookingKeyBetween(
            $bookingKey,
            $from->setTime(0, 0),
            $to->setTime(23, 59, 59),
        );

        $reservedSlotsByDay = $this->groupReservedSlotsByDay($reservations, $calendar);
        $dates = [];
        $cursor = $from;

        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            if ($this->isDateSelectable($cursor, $calendar, $blocked, $reservedSlotsByDay[$key] ?? [], $now)) {
                $dates[] = $key;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $dates;
    }

    /**
     * @return list<string> HH:MM
     */
    public function getAvailableSlots(string $bookingKey, \DateTimeImmutable $day, ?int $excludeReservationId = null): array
    {
        $calendar = $this->getCalendarForKey($bookingKey);
        $tz = $this->dateTimeHelper->timezone();
        $day = $day->setTimezone($tz)->setTime(0, 0);
        $now = new \DateTimeImmutable('now', $tz);

        $blocked = $this->blockedDateRepository->findBlockedDateMapByBookingKey($bookingKey, $day, $day);
        $reservations = $this->reservationRepository->findByBookingKeyBetween(
            $bookingKey,
            $day->setTime(0, 0),
            $day->setTime(23, 59, 59),
        );
        $reserved = $this->groupReservedSlotsByDay($reservations, $calendar, $excludeReservationId)[$day->format('Y-m-d')] ?? [];

        if (!$this->isDateSelectable($day, $calendar, $blocked, $reserved, $now)) {
            return [];
        }

        $allSlots = $this->generateSlots($calendar);
        $available = [];
        foreach ($allSlots as $slot) {
            if (in_array($slot, $reserved, true)) {
                continue;
            }
            $slotStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $day->format('Y-m-d') . ' ' . $slot, $tz);
            if (false === $slotStart) {
                continue;
            }
            if ($slotStart < $now) {
                continue;
            }
            $available[] = $slot;
        }

        return $available;
    }

    /**
     * @return array{year: int, month: int, availableDates: list<string>, dateMin: string, dateMax: string}
     */
    public function getMonthAvailability(string $bookingKey, int $year, int $month): array
    {
        $tz = $this->dateTimeHelper->timezone();
        $monthStart = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month), $tz);
        if (false === $monthStart) {
            return [
                'year' => $year,
                'month' => $month,
                'availableDates' => [],
                'dateMin' => '',
                'dateMax' => '',
            ];
        }

        $monthEnd = $monthStart->modify('last day of this month');
        $allDates = $this->getAvailableDates($bookingKey);
        $dateMin = $allDates[0] ?? '';
        $dateMax = $allDates !== [] ? $allDates[array_key_last($allDates)] : '';

        $availableInMonth = array_values(array_filter(
            $allDates,
            static fn (string $date): bool => $date >= $monthStart->format('Y-m-d') && $date <= $monthEnd->format('Y-m-d'),
        ));

        return [
            'year' => $year,
            'month' => $month,
            'availableDates' => $availableInMonth,
            'dateMin' => $dateMin,
            'dateMax' => $dateMax,
        ];
    }

    public function isSlotAvailable(string $bookingKey, \DateTimeImmutable $startsAt, ?int $excludeReservationId = null): bool
    {
        $tz = $this->dateTimeHelper->timezone();
        $startsAt = $startsAt->setTimezone($tz);
        $slots = $this->getAvailableSlots($bookingKey, $startsAt, $excludeReservationId);

        return in_array($startsAt->format('H:i'), $slots, true);
    }

    /**
     * @param array<string, true> $blocked
     * @param list<string> $reservedSlots
     */
    private function isDateSelectable(
        \DateTimeImmutable $day,
        BookingCalendar $calendar,
        array $blocked,
        array $reservedSlots,
        \DateTimeImmutable $now,
    ): bool {
        $key = $day->format('Y-m-d');
        if (isset($blocked[$key])) {
            return false;
        }
        if ($calendar->isBlockWeekends() && in_array((int) $day->format('N'), [6, 7], true)) {
            return false;
        }
        if ($day < $now->setTime(0, 0)) {
            return false;
        }

        $allSlots = $this->generateSlots($calendar);
        if ([] === $allSlots) {
            return false;
        }

        $tz = $day->getTimezone();
        foreach ($allSlots as $slot) {
            if (in_array($slot, $reservedSlots, true)) {
                continue;
            }
            $slotStart = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $key . ' ' . $slot, $tz);
            if (false !== $slotStart && $slotStart >= $now) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function generateSlots(BookingCalendar $calendar): array
    {
        $start = $this->parseTime($calendar->getWorkStart());
        $end = $this->parseTime($calendar->getWorkEnd());
        if (null === $start || null === $end || $start >= $end) {
            return [];
        }

        $slots = [];
        $duration = $calendar->getSlotDurationMinutes();
        $cursor = $start;
        while ($cursor < $end) {
            $next = $cursor->modify(sprintf('+%d minutes', $duration));
            if ($next > $end) {
                break;
            }
            $slots[] = $cursor->format('H:i');
            $cursor = $next;
        }

        return $slots;
    }

    private function parseTime(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);
        foreach (['H:i:s', 'H:i'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            if ($parsed instanceof \DateTimeImmutable) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param list<object> $reservations
     *
     * @return array<string, list<string>>
     */
    private function groupReservedSlotsByDay(array $reservations, BookingCalendar $calendar, ?int $excludeReservationId = null): array
    {
        $map = [];
        foreach ($reservations as $reservation) {
            if (!method_exists($reservation, 'getStartsAt')) {
                continue;
            }
            if (
                null !== $excludeReservationId
                && method_exists($reservation, 'getId')
                && (int) $reservation->getId() === $excludeReservationId
            ) {
                continue;
            }
            $startsAt = $this->dateTimeHelper->fromStorage($reservation->getStartsAt());
            $day = $startsAt->format('Y-m-d');
            $map[$day] ??= [];
            $map[$day][] = $startsAt->format('H:i');
        }

        return $map;
    }
}
