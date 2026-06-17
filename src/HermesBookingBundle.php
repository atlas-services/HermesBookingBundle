<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle;

use AtlasServices\HermesBookingBundle\DependencyInjection\Compiler\BookingIntegrationCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class HermesBookingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new BookingIntegrationCompilerPass());
    }
}
