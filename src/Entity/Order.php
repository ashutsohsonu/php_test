<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public const STATUS_COMPLETED = 'Completed';

    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $pickupTime = null;

    #[ORM\Column]
    private bool $vip = false;

    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function setItems(array $items): self
    {
        $this->items = $items;
        return $this;
    }

    public function getPickupTime(): ?\DateTimeInterface
    {
        return $this->pickupTime;
    }

    public function setPickupTime(\DateTimeInterface $pickupTime): self
    {
        $this->pickupTime = $pickupTime;
        return $this;
    }

    public function isVip(): bool
    {
        return $this->vip;
    }

    public function setVip(bool $vip): self
    {
        $this->vip = $vip;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'items' => $this->items,
            'pickup_time' => $this->pickupTime->format('c'),
            'vip' => $this->vip,
            'status' => $this->status,
            'created_at' => $this->createdAt->format('c'),
            'completed_at' => $this->completedAt ? $this->completedAt->format('c') : null,
        ];
    }

    public function markAsActive(): self
    {
        $this->status = 'Active';
        return $this;
    }

    public function markAsCompleted() : self {

        $this->status = 'Completed';
        $this->completedAt = new \DateTime();
        return $this;
        
    }
}