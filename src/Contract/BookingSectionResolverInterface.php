<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Contract;

interface BookingSectionResolverInterface
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public function listBookingCalendars(): array;
}
