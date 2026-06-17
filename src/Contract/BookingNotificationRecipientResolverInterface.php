<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Contract;

/**
 * Résout les adresses d'expédition / notification admin (configurable par l'hôte CMS).
 */
interface BookingNotificationRecipientResolverInterface
{
    public function resolveAdminEmail(): string;

    public function resolveFromEmail(): string;
}
