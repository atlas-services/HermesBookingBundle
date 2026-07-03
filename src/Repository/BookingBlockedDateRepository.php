<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Repository;

use AtlasServices\HermesBookingBundle\Entity\BookingBlockedDate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingBlockedDate>
 */
class BookingBlockedDateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingBlockedDate::class);
    }

    /**
     * @return array<string, true> dates Y-m-d
     */
    public function findBlockedDateMapByBookingKey(string $bookingKey, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('b')
            ->andWhere('b.bookingKey = :bookingKey')
            ->andWhere('b.blockedDate BETWEEN :from AND :to')
            ->setParameter('bookingKey', $bookingKey)
            ->setParameter('from', $from->format('Y-m-d'))
            ->setParameter('to', $to->format('Y-m-d'))
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            if ($row instanceof BookingBlockedDate) {
                $map[$row->getBlockedDate()->format('Y-m-d')] = true;
            }
        }

        return $map;
    }

    public function findOneByBookingKeyAndDate(string $bookingKey, \DateTimeImmutable $day): ?BookingBlockedDate
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.bookingKey = :bookingKey')
            ->andWhere('b.blockedDate = :day')
            ->setParameter('bookingKey', $bookingKey)
            ->setParameter('day', $day->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<BookingBlockedDate>
     */
    public function findByBookingKeyOrdered(string $bookingKey): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.bookingKey = :bookingKey')
            ->setParameter('bookingKey', $bookingKey)
            ->orderBy('b.blockedDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
