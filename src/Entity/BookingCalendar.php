<?php

declare(strict_types=1);

namespace AtlasServices\HermesBookingBundle\Entity;

use AtlasServices\HermesBookingBundle\Repository\BookingCalendarRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingCalendarRepository::class)]
#[ORM\Table(name: 'booking_calendar')]
#[ORM\UniqueConstraint(name: 'uniq_booking_calendar_key', columns: ['booking_key'])]
class BookingCalendar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'booking_key', length: 190)]
    private string $bookingKey;

    #[ORM\Column(length: 255)]
    private string $label;

    #[ORM\Column(name: 'block_weekends', options: ['default' => true])]
    private bool $blockWeekends = true;

    #[ORM\Column(name: 'slot_duration_minutes', options: ['default' => 60])]
    private int $slotDurationMinutes = 60;

    #[ORM\Column(name: 'work_start', length: 5, options: ['default' => '09:00'])]
    private string $workStart = '09:00';

    #[ORM\Column(name: 'work_end', length: 5, options: ['default' => '18:00'])]
    private string $workEnd = '18:00';

    #[ORM\Column(name: 'horizon_days', options: ['default' => 90])]
    private int $horizonDays = 90;

    #[ORM\Column(name: 'max_participants_per_slot', options: ['default' => 1])]
    private int $maxParticipantsPerSlot = 1;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = trim($label);

        return $this;
    }

    public function isBlockWeekends(): bool
    {
        return $this->blockWeekends;
    }

    public function setBlockWeekends(bool $blockWeekends): self
    {
        $this->blockWeekends = $blockWeekends;

        return $this;
    }

    public function getSlotDurationMinutes(): int
    {
        return $this->slotDurationMinutes;
    }

    public function setSlotDurationMinutes(int $slotDurationMinutes): self
    {
        $this->slotDurationMinutes = max(15, min(240, $slotDurationMinutes));

        return $this;
    }

    public function getWorkStart(): string
    {
        return $this->workStart;
    }

    public function setWorkStart(string $workStart): self
    {
        $this->workStart = $workStart;

        return $this;
    }

    public function getWorkEnd(): string
    {
        return $this->workEnd;
    }

    public function setWorkEnd(string $workEnd): self
    {
        $this->workEnd = $workEnd;

        return $this;
    }

    public function getHorizonDays(): int
    {
        return $this->horizonDays;
    }

    public function setHorizonDays(int $horizonDays): self
    {
        $this->horizonDays = max(7, min(365, $horizonDays));

        return $this;
    }

    public function getMaxParticipantsPerSlot(): int
    {
        return $this->maxParticipantsPerSlot;
    }

    public function setMaxParticipantsPerSlot(int $maxParticipantsPerSlot): self
    {
        $this->maxParticipantsPerSlot = max(1, min(999, $maxParticipantsPerSlot));

        return $this;
    }
}
