<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Contract;

/**
 * Fournit les éléments de signature affichés en bas des e-mails de réservation.
 *
 * @phpstan-type BookingMailSignature array{siteName: ?string, siteUrl: ?string, logoUrl: ?string}
 */
interface BookingMailSignatureProviderInterface
{
    /**
     * @return BookingMailSignature
     */
    public function getSignature(string $locale): array;
}
