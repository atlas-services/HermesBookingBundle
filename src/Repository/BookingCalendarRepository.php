<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Repository;

use AtlasServices\HermesBookingBundle\Entity\BookingCalendar;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingCalendar>
 */
class BookingCalendarRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingCalendar::class);
    }

    public function findOrCreate(string $bookingKey, ?string $label = null): BookingCalendar
    {
        $bookingKey = trim($bookingKey);
        $label = null !== $label ? trim($label) : null;

        $calendar = $this->findOneBy(['bookingKey' => $bookingKey]);
        if ($calendar instanceof BookingCalendar) {
            if (null !== $label && $label !== '' && $calendar->getLabel() !== $label) {
                $calendar->setLabel($label);
                $this->getEntityManager()->flush();
            }

            return $calendar;
        }

        $calendar = (new BookingCalendar())
            ->setBookingKey($bookingKey)
            ->setLabel($label !== null && $label !== '' ? $label : $bookingKey);
        $this->getEntityManager()->persist($calendar);
        $this->getEntityManager()->flush();

        return $calendar;
    }
}
