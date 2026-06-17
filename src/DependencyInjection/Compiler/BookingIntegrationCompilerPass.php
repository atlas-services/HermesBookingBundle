<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class BookingIntegrationCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$this->isBookingEnabled($container)) {
            return;
        }

        if (!$container->hasParameter('templates')) {
            return;
        }

        /** @var array<string, array<string, mixed>> $templates */
        $templates = $container->getParameter('templates');

        if (isset($templates['booking'])) {
            return;
        }

        $templates['booking'] = [
            'type' => 'formulaire',
            'summary' => 'Formulaire Réservation',
            'code' => 'booking',
            'name' => 'Réservation',
        ];

        $container->setParameter('templates', $templates);
    }

    private function isBookingEnabled(ContainerBuilder $container): bool
    {
        if (!$container->hasParameter('hermes_booking.enabled')) {
            return false;
        }

        $enabled = $container->getParameter('hermes_booking.enabled');

        if (\is_bool($enabled)) {
            return $enabled;
        }

        $raw = $_ENV['HERMES_BOOKING_ENABLED'] ?? $_SERVER['HERMES_BOOKING_ENABLED'] ?? getenv('HERMES_BOOKING_ENABLED');

        if ($raw === false || $raw === null || $raw === '') {
            return false;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOL);
    }
}
