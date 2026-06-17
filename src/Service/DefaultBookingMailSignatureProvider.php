<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Contract\BookingMailSignatureProviderInterface;

final class DefaultBookingMailSignatureProvider implements BookingMailSignatureProviderInterface
{
    public function getSignature(string $locale): array
    {
        return [
            'siteName' => null,
            'siteUrl' => null,
            'logoUrl' => null,
        ];
    }
}
