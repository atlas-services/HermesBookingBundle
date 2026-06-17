<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\EventListener;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class BookingAdminAccessListener
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'booking_calendar',
        'booking_blocked_date',
        'booking_reservation',
    ];

    private ?bool $schemaReady = null;

    public function __construct(
        #[Autowire('%hermes_booking.enabled%')]
        private readonly bool $bookingEnabled,
        private readonly Connection $connection,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!$this->isBookingPath($path)) {
            return;
        }

        if (!$this->bookingEnabled || !$this->hasDatabaseSchema()) {
            throw new NotFoundHttpException();
        }
    }

    private function isBookingPath(string $path): bool
    {
        return str_starts_with($path, '/admin/booking')
            || preg_match('#^/[a-z]{2,3}/booking(/|$)#', $path) === 1;
    }

    private function hasDatabaseSchema(): bool
    {
        if ($this->schemaReady !== null) {
            return $this->schemaReady;
        }

        try {
            $this->schemaReady = $this->connection
                ->createSchemaManager()
                ->tablesExist(self::REQUIRED_TABLES);
        } catch (\Throwable) {
            $this->schemaReady = false;
        }

        return $this->schemaReady;
    }
}
