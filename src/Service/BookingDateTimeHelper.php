<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

/**
 * Doctrine persists naive datetimes while PHP often runs in UTC.
 * Booking slots are wall-clock times in the configured application timezone.
 */
final class BookingDateTimeHelper
{
    public function __construct(
        private readonly string $timezone,
    ) {
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function timezone(): \DateTimeZone
    {
        return new \DateTimeZone($this->timezone);
    }

    /**
     * Re-attaches the application timezone to a value read from the database
     * without shifting the stored clock time (11:00 stays 11:00 local).
     */
    public function fromStorage(\DateTimeImmutable $stored): \DateTimeImmutable
    {
        return new \DateTimeImmutable($stored->format('Y-m-d H:i:s'), $this->timezone());
    }

    public function fromFormParts(string $date, string $time): ?\DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $time, $this->timezone());
        if (false === $parsed) {
            return null;
        }

        return $parsed;
    }

    public function fromDateString(string $date): ?\DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date, $this->timezone());
        if (false === $parsed) {
            return null;
        }

        return $parsed->setTime(0, 0);
    }
}
