<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Repository;

use AtlasServices\HermesBookingBundle\Entity\BookingReservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingReservation>
 */
class BookingReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingReservation::class);
    }

    /**
     * @return list<BookingReservation>
     */
    public function findByBookingKeyBetween(string $bookingKey, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.bookingKey = :bookingKey')
            ->andWhere('r.startsAt BETWEEN :from AND :to')
            ->setParameter('bookingKey', $bookingKey)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.startsAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<BookingReservation>
     */
    public function findRecent(?string $bookingKey = null, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.startsAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $bookingKey && $bookingKey !== '') {
            $qb->andWhere('r.bookingKey = :bookingKey')
                ->setParameter('bookingKey', $bookingKey);
        }

        return $qb->getQuery()->getResult();
    }
}
