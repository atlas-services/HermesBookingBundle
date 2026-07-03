<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Entity\BookingBlockedDate;
use AtlasServices\HermesBookingBundle\Repository\BookingBlockedDateRepository;
use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Planificateur admin : ouvrir / fermer des jours dans l’horizon via {@code booking_blocked_date}.
 */
final class BookingDayPlannerService
{
    public function __construct(
        private readonly BookingCalendarRepository $calendarRepository,
        private readonly BookingBlockedDateRepository $blockedDateRepository,
        private readonly BookingDateTimeHelper $dateTimeHelper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *   bookingKey: string,
     *   dateMin: string,
     *   dateMax: string,
     *   blockWeekends: bool,
     *   horizonDays: int,
     *   days: list<array{date: string, weekday: int, state: string}>
     * }
     */
    public function getPlannerState(string $bookingKey): array
    {
        $calendar = $this->calendarRepository->findOrCreate($bookingKey);
        [$from, $to] = $this->horizonRange($calendar);
        $blocked = $this->blockedDateRepository->findBlockedDateMapByBookingKey($bookingKey, $from, $to);

        $days = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $days[] = [
                'date' => $cursor->format('Y-m-d'),
                'weekday' => (int) $cursor->format('N'),
                'state' => $this->resolveDayState($cursor, $calendar, $blocked),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return [
            'bookingKey' => $bookingKey,
            'dateMin' => $from->format('Y-m-d'),
            'dateMax' => $to->format('Y-m-d'),
            'blockWeekends' => $calendar->isBlockWeekends(),
            'horizonDays' => $calendar->getHorizonDays(),
            'days' => $days,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{bookingKey: string, days: list<array{date: string, weekday: int, state: string}>}
     */
    public function applyAction(string $bookingKey, array $payload): array
    {
        $calendar = $this->calendarRepository->findOrCreate($bookingKey);
        $action = (string) ($payload['action'] ?? '');

        match ($action) {
            'toggle' => $this->toggleDate(
                $bookingKey,
                (string) ($payload['date'] ?? ''),
                (bool) ($payload['open'] ?? false),
                $calendar,
            ),
            'set_all' => $this->setAllInHorizon($bookingKey, (bool) ($payload['open'] ?? false), $calendar),
            'set_weekdays' => $this->setWeekdaysInHorizon(
                $bookingKey,
                $this->normalizeWeekdays($payload['weekdays'] ?? []),
                (bool) ($payload['open'] ?? true),
                $calendar,
            ),
            'set_range' => $this->setRange(
                $bookingKey,
                (string) ($payload['from'] ?? ''),
                (string) ($payload['to'] ?? ''),
                (bool) ($payload['open'] ?? false),
                $calendar,
            ),
            'only_weekdays' => $this->onlyWeekdaysInHorizon(
                $bookingKey,
                $this->normalizeWeekdays($payload['weekdays'] ?? []),
                $calendar,
            ),
            default => throw new \InvalidArgumentException(sprintf('Action planificateur inconnue : %s', $action)),
        };

        $this->entityManager->flush();

        $state = $this->getPlannerState($bookingKey);

        return [
            'bookingKey' => $state['bookingKey'],
            'days' => $state['days'],
        ];
    }

    /**
     * @param array<string, true> $blocked
     */
    private function resolveDayState(\DateTimeImmutable $day, object $calendar, array $blocked): string
    {
        $key = $day->format('Y-m-d');
        $today = $this->today();

        if ($day < $today) {
            return 'past';
        }

        if ($calendar->isBlockWeekends() && in_array((int) $day->format('N'), [6, 7], true)) {
            return 'weekend_rule';
        }

        if (isset($blocked[$key])) {
            return 'closed';
        }

        return 'open';
    }

    private function toggleDate(string $bookingKey, string $dateRaw, bool $open, object $calendar): void
    {
        $day = $this->parsePlannerDate($dateRaw);
        if (!$this->isUserToggleable($day, $calendar)) {
            return;
        }

        if ($open) {
            $this->unblockDate($bookingKey, $day);
        } else {
            $this->blockDate($bookingKey, $day);
        }
    }

    private function setAllInHorizon(string $bookingKey, bool $open, object $calendar): void
    {
        [$from, $to] = $this->horizonRange($calendar);
        $cursor = $from;

        while ($cursor <= $to) {
            if ($this->isUserToggleable($cursor, $calendar)) {
                if ($open) {
                    $this->unblockDate($bookingKey, $cursor);
                } else {
                    $this->blockDate($bookingKey, $cursor);
                }
            }
            $cursor = $cursor->modify('+1 day');
        }
    }

    /**
     * @param list<int> $weekdays ISO-8601 (1=lundi … 7=dimanche)
     */
    private function setWeekdaysInHorizon(string $bookingKey, array $weekdays, bool $open, object $calendar): void
    {
        [$from, $to] = $this->horizonRange($calendar);
        $cursor = $from;

        while ($cursor <= $to) {
            if (!$this->isUserToggleable($cursor, $calendar)) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            $matches = in_array((int) $cursor->format('N'), $weekdays, true);
            if (!$matches) {
                $cursor = $cursor->modify('+1 day');
                continue;
            }

            if ($open) {
                $this->unblockDate($bookingKey, $cursor);
            } else {
                $this->blockDate($bookingKey, $cursor);
            }

            $cursor = $cursor->modify('+1 day');
        }
    }

    /**
     * @param list<int> $weekdays ISO-8601 (1=lundi … 7=dimanche)
     */
    private function onlyWeekdaysInHorizon(string $bookingKey, array $weekdays, object $calendar): void
    {
        $this->setAllInHorizon($bookingKey, false, $calendar);
        if ($weekdays !== []) {
            $this->setWeekdaysInHorizon($bookingKey, $weekdays, true, $calendar);
        }
    }

    private function setRange(string $bookingKey, string $fromRaw, string $toRaw, bool $open, object $calendar): void
    {
        $from = $this->parsePlannerDate($fromRaw);
        $to = $this->parsePlannerDate($toRaw);
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        [$horizonFrom, $horizonTo] = $this->horizonRange($calendar);
        if ($from < $horizonFrom) {
            $from = $horizonFrom;
        }
        if ($to > $horizonTo) {
            $to = $horizonTo;
        }

        $cursor = $from;
        while ($cursor <= $to) {
            if ($this->isUserToggleable($cursor, $calendar)) {
                if ($open) {
                    $this->unblockDate($bookingKey, $cursor);
                } else {
                    $this->blockDate($bookingKey, $cursor);
                }
            }
            $cursor = $cursor->modify('+1 day');
        }
    }

    private function blockDate(string $bookingKey, \DateTimeImmutable $day): void
    {
        $existing = $this->blockedDateRepository->findOneBy([
            'bookingKey' => $bookingKey,
            'blockedDate' => $day->setTime(0, 0),
        ]);

        if ($existing instanceof BookingBlockedDate) {
            return;
        }

        $blocked = (new BookingBlockedDate())
            ->setBookingKey($bookingKey)
            ->setBlockedDate($day->setTime(0, 0))
            ->setLabel(null);

        $this->entityManager->persist($blocked);
    }

    private function unblockDate(string $bookingKey, \DateTimeImmutable $day): void
    {
        $existing = $this->blockedDateRepository->findOneBy([
            'bookingKey' => $bookingKey,
            'blockedDate' => $day->setTime(0, 0),
        ]);

        if ($existing instanceof BookingBlockedDate) {
            $this->entityManager->remove($existing);
        }
    }

    private function isUserToggleable(\DateTimeImmutable $day, object $calendar): bool
    {
        if ($day < $this->today()) {
            return false;
        }

        [$from, $to] = $this->horizonRange($calendar);
        if ($day < $from || $day > $to) {
            return false;
        }

        if ($calendar->isBlockWeekends() && in_array((int) $day->format('N'), [6, 7], true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    private function horizonRange(object $calendar): array
    {
        $from = $this->today();
        $to = $from->modify(sprintf('+%d days', $calendar->getHorizonDays()));

        return [$from, $to];
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', $this->dateTimeHelper->timezone());
    }

    private function parsePlannerDate(string $value): \DateTimeImmutable
    {
        $day = \DateTimeImmutable::createFromFormat('Y-m-d', trim($value), $this->dateTimeHelper->timezone());
        if (false === $day) {
            throw new \InvalidArgumentException(sprintf('Date invalide : %s', $value));
        }

        return $day->setTime(0, 0);
    }

    /**
     * @param mixed $raw
     *
     * @return list<int>
     */
    private function normalizeWeekdays(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $weekdays = [];
        foreach ($raw as $value) {
            $day = (int) $value;
            if ($day >= 1 && $day <= 7) {
                $weekdays[] = $day;
            }
        }

        return array_values(array_unique($weekdays));
    }
}
