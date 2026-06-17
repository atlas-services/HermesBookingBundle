<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('hermes_booking');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                ->end()
                ->scalarNode('timezone')
                    ->defaultValue('Europe/Paris')
                ->end()
                ->scalarNode('admin_email')
                    ->defaultValue('%env(default::string:HERMES_BOOKING_ADMIN_EMAIL)%')
                ->end()
                ->scalarNode('from_email')
                    ->defaultValue('%env(default::string:MAILER_FROM)%')
                ->end()
                ->arrayNode('section_resolver')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('entity')->defaultNull()->end()
                        ->scalarNode('template_code')->defaultValue('booking')->end()
                        ->scalarNode('template_relation')->defaultValue('template')->end()
                        ->scalarNode('menu_relation')->defaultValue('menu')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
