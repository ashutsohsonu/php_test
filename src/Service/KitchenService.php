<?php
// src/Service/KitchenService.php
namespace App\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class KitchenService
{
    private OrderRepository $orderRepository;
    private int $maxCapacity;

    public function __construct(
        OrderRepository $orderRepository,
        #[Autowire(param: 'app.kitchen.max_capacity')]
        int $maxCapacity = 5
    ) {
        $this->orderRepository = $orderRepository;
        $this->maxCapacity = $maxCapacity;
    }

    /**
     * Check if kitchen can accept a new order
     */
    public function canAcceptOrder(Order $order): bool
    {
        // VIP orders always bypass the limit
        if ($order->isVip()) {
            return true;
        }

        $activeCount = $this->orderRepository->countActiveNonVipOrders();
        return $activeCount < $this->maxCapacity;
    }

    /**
     * Process an order (add to kitchen if capacity allows)
     */
    public function processOrder(Order $order): bool
    {
        if (!$this->canAcceptOrder($order)) {
            return false;
        }

        $order->markAsActive();
        $this->orderRepository->save($order, true);

        // Try to process pending orders
        $this->processPendingOrders();

        return true;
    }

    /**
     * Complete an order and free up capacity
     */
    public function completeOrder(Order $order): void
    {
        $order->markAsCompleted();
        $this->orderRepository->flush();

        // Try to activate pending orders
        $this->processPendingOrders();
    }

    /**
     * Process pending orders when capacity becomes available
     */
    public function processPendingOrders(): void
    {
        $pendingOrders = $this->orderRepository->findPendingOrders();

        foreach ($pendingOrders as $pendingOrder) {
            if ($this->canAcceptOrder($pendingOrder)) {
                $pendingOrder->markAsActive();
                $this->orderRepository->save($pendingOrder);
            }
        }

        $this->orderRepository->flush();
    }

    /**
     * Get next available pickup time
     */
    public function getNextAvailablePickupTime(): \DateTimeImmutable
    {
        $latestPickupTime = $this->orderRepository->getLatestPickupTime();

        if ($latestPickupTime === null) {
            // If no active orders, return current time + 15 minutes
            return (new \DateTimeImmutable())->modify('+15 minutes');
        }

        // Add 15 minutes to the latest pickup time
        return $latestPickupTime->modify('+15 minutes');
    }

    /**
     * Get current kitchen status
     */
    public function getKitchenStatus(): array
    {
        $activeCount = $this->orderRepository->countActiveOrders();
        $activeNonVipCount = $this->orderRepository->countActiveNonVipOrders();

        return [
            'max_capacity' => $this->maxCapacity,
            'active_orders' => $activeCount,
            'active_non_vip_orders' => $activeNonVipCount,
            'available_capacity' => max(0, $this->maxCapacity - $activeNonVipCount),
            'is_full' => $activeNonVipCount >= $this->maxCapacity,
            'next_available_pickup_time' => $this->getNextAvailablePickupTime()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Get all active orders
     */
    public function getActiveOrders(): array
    {
        return $this->orderRepository->findActiveOrders();
    }
}