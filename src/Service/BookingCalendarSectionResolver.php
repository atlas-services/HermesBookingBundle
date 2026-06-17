<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Contract\BookingSectionResolverInterface;
use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;

final class BookingCalendarSectionResolver implements BookingSectionResolverInterface
{
    public function __construct(
        private readonly BookingCalendarRepository $calendarRepository,
    ) {
    }

    public function listBookingCalendars(): array
    {
        $sections = [];
        foreach ($this->calendarRepository->findAll() as $calendar) {
            $sections[] = [
                'key' => $calendar->getBookingKey(),
                'label' => $calendar->getLabel(),
            ];
        }

        return $sections;
    }
}
