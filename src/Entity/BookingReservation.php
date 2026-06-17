<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Entity;

use AtlasServices\HermesBookingBundle\Repository\BookingReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingReservationRepository::class)]
#[ORM\Table(name: 'booking_reservation')]
class BookingReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'booking_key', length: 190)]
    private string $bookingKey;

    #[ORM\Column(name: 'starts_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'duration_minutes')]
    private int $durationMinutes;

    #[ORM\Column(name: 'customer_name', length: 120)]
    private string $customerName;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): self
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
