<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Service;

use AtlasServices\HermesBookingBundle\Contract\BookingNotificationRecipientResolverInterface;

final class DefaultBookingNotificationRecipientResolver implements BookingNotificationRecipientResolverInterface
{
    public function __construct(
        private readonly string $adminEmail,
        private readonly string $fromEmail,
    ) {
    }

    public function resolveAdminEmail(): string
    {
        return $this->normalizeEmail($this->adminEmail)
            ?? $this->resolveFromEmail();
    }

    public function resolveFromEmail(): string
    {
        return $this->normalizeEmail($this->fromEmail) ?? 'noreply@localhost';
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = trim((string) $value);
        if ($email === '' || $email === '~') {
            return null;
        }

        return filter_var($email, \FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
