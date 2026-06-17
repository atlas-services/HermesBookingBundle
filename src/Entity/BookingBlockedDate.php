<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Entity;

use AtlasServices\HermesBookingBundle\Repository\BookingBlockedDateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingBlockedDateRepository::class)]
#[ORM\Table(name: 'booking_blocked_date')]
#[ORM\UniqueConstraint(name: 'uniq_booking_blocked_key_date', columns: ['booking_key', 'blocked_date'])]
class BookingBlockedDate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'booking_key', length: 190)]
    private string $bookingKey;

    #[ORM\Column(name: 'blocked_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $blockedDate;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBookingKey(): string
    {
        return $this->bookingKey;
    }

    public function setBookingKey(string $bookingKey): self
    {
        $this->bookingKey = trim($bookingKey);

        return $this;
    }

    public function getBlockedDate(): \DateTimeImmutable
    {
        return $this->blockedDate;
    }

    public function setBlockedDate(\DateTimeImmutable $blockedDate): self
    {
        $this->blockedDate = $blockedDate;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }
}
