<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\DependencyInjection;

use AtlasServices\HermesBookingBundle\Contract\BookingSectionResolverInterface;
use AtlasServices\HermesBookingBundle\Integration\DoctrineBookingSectionResolver;
use AtlasServices\HermesBookingBundle\Service\BookingCalendarSectionResolver;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class HermesBookingExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $enabled = filter_var($_ENV['HERMES_BOOKING_ENABLED'] ?? $_SERVER['HERMES_BOOKING_ENABLED'] ?? true, \FILTER_VALIDATE_BOOL);

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'HermesBooking' => [
                        'type' => 'attribute',
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'AtlasServices\\HermesBookingBundle\\Entity',
                        'alias' => 'HermesBooking',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('twig', [
            'paths' => [
                \dirname(__DIR__, 2) . '/templates' => 'HermesBooking',
            ],
            'globals' => [
                'hermes_booking_enabled' => '%hermes_booking.enabled%',
            ],
        ]);

        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [
                    \dirname(__DIR__, 2) . '/translations',
                ],
            ],
            'asset_mapper' => [
                'paths' => [
                    \dirname(__DIR__, 2) . '/assets/',
                ],
            ],
        ]);

        if ($enabled) {
            $container->prependExtensionConfig('stimulus', [
                'controller_paths' => [
                    \dirname(__DIR__, 2) . '/assets/controllers',
                ],
            ]);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (!$container->hasParameter('hermes_booking.enabled')) {
            $container->setParameter('hermes_booking.enabled', $config['enabled']);
        }

        $container->setParameter('hermes_booking.timezone', $config['timezone']);
        $container->setParameter('hermes_booking.admin_email', $config['admin_email']);
        $container->setParameter('hermes_booking.from_email', $config['from_email']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $this->configureSectionResolver($container, $config['section_resolver']);
    }

    /**
     * @param array{entity: ?string, template_code: string, template_relation: string, menu_relation: string} $resolverConfig
     */
    private function configureSectionResolver(ContainerBuilder $container, array $resolverConfig): void
    {
        $entityClass = $resolverConfig['entity'];

        if (\is_string($entityClass) && $entityClass !== '' && class_exists($entityClass)) {
            $container->register(DoctrineBookingSectionResolver::class)
                ->setAutowired(true)
                ->setArgument('$entityClass', $entityClass)
                ->setArgument('$templateCode', $resolverConfig['template_code'])
                ->setArgument('$templateRelation', $resolverConfig['template_relation'])
                ->setArgument('$menuRelation', $resolverConfig['menu_relation']);

            $container->setAlias(BookingSectionResolverInterface::class, DoctrineBookingSectionResolver::class);

            return;
        }

        $container->setAlias(BookingSectionResolverInterface::class, BookingCalendarSectionResolver::class);
    }
}
