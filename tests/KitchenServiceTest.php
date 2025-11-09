<?php

namespace App\Tests\Service;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\KitchenService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class KitchenServiceTest extends TestCase
{
    private OrderRepository|MockObject $orderRepository;
    private KitchenService $kitchenService;
    private int $maxCapacity = 5;

    protected function setUp(): void
    {
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->kitchenService = new KitchenService(
            $this->orderRepository,
            $this->maxCapacity
        );
    }

    // ===== canAcceptOrder Tests =====

    public function testCanAcceptOrderWithVipOrderAlwaysReturnsTrue(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(true);

        // Even if kitchen is full, VIP orders should be accepted
        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(10);

        $result = $this->kitchenService->canAcceptOrder($order);

        $this->assertTrue($result);
    }

    public function testCanAcceptOrderWithNonVipOrderWhenCapacityAvailable(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(3); // Less than max capacity (5)

        $result = $this->kitchenService->canAcceptOrder($order);

        $this->assertTrue($result);
    }

    public function testCanAcceptOrderWithNonVipOrderWhenCapacityFull(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(5); // Equal to max capacity

        $result = $this->kitchenService->canAcceptOrder($order);

        $this->assertFalse($result);
    }

    public function testCanAcceptOrderWithNonVipOrderAtExactCapacityLimit(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(4); // One less than max capacity

        $result = $this->kitchenService->canAcceptOrder($order);

        $this->assertTrue($result);
    }

    // ===== processOrder Tests =====

    public function testProcessOrderSuccessfullyAcceptsOrder(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(2);

        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([]);

        $order->expects($this->once())
            ->method('markAsActive');

        $this->orderRepository
            ->expects($this->once())
            ->method('save')
            ->with($order, true);

        $result = $this->kitchenService->processOrder($order);

        $this->assertTrue($result);
    }

    public function testProcessOrderRejectsWhenCapacityFull(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(5); // At capacity

        $order->expects($this->never())
            ->method('markAsActive');

        $this->orderRepository
            ->expects($this->never())
            ->method('save');

        $result = $this->kitchenService->processOrder($order);

        $this->assertFalse($result);
    }

    public function testProcessOrderAcceptsVipOrderAndProcessesPending(): void
    {
        $vipOrder = $this->createMock(Order::class);
        $vipOrder->method('isVip')->willReturn(true);

        $pendingOrder = $this->createMock(Order::class);
        $pendingOrder->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([$pendingOrder]);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(3);

        $vipOrder->expects($this->once())
            ->method('markAsActive');

        $pendingOrder->expects($this->once())
            ->method('markAsActive');

        $result = $this->kitchenService->processOrder($vipOrder);

        $this->assertTrue($result);
    }

    // ===== completeOrder Tests =====

    public function testCompleteOrderMarksOrderAsCompleted(): void
    {
        $order = $this->createMock(Order::class);

        $order->expects($this->once())
            ->method('markAsCompleted');

        // flush() is called twice: once in completeOrder() and once in processPendingOrders()
        $this->orderRepository
            ->expects($this->exactly(2))
            ->method('flush');

        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([]);

        $this->kitchenService->completeOrder($order);
    }

    public function testCompleteOrderProcessesPendingOrders(): void
    {
        $completedOrder = $this->createMock(Order::class);
        $pendingOrder = $this->createMock(Order::class);
        $pendingOrder->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([$pendingOrder]);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(3);

        $pendingOrder->expects($this->once())
            ->method('markAsActive');

        // flush() is called twice: once in completeOrder() and once in processPendingOrders()
        $this->orderRepository
            ->expects($this->exactly(2))
            ->method('flush');

        $this->kitchenService->completeOrder($completedOrder);
    }

    // ===== processPendingOrders Tests =====

    public function testProcessPendingOrdersWithNoPendingOrders(): void
    {
        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([]);

        $this->orderRepository
            ->expects($this->once())
            ->method('flush');

        $this->kitchenService->processPendingOrders();
    }

    public function testProcessPendingOrdersActivatesMultipleOrders(): void
    {
        $pending1 = $this->createMock(Order::class);
        $pending1->method('isVip')->willReturn(false);

        $pending2 = $this->createMock(Order::class);
        $pending2->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([$pending1, $pending2]);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(2); // Room for both orders

        $pending1->expects($this->once())
            ->method('markAsActive');

        $pending2->expects($this->once())
            ->method('markAsActive');

        $this->kitchenService->processPendingOrders();
    }

    public function testProcessPendingOrdersStopsWhenCapacityReached(): void
    {
        $pending1 = $this->createMock(Order::class);
        $pending1->method('isVip')->willReturn(false);

        $pending2 = $this->createMock(Order::class);
        $pending2->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('findPendingOrders')
            ->willReturn([$pending1, $pending2]);

        // First call returns 4, second call returns 5 (capacity reached)
        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturnOnConsecutiveCalls(4, 5);

        $pending1->expects($this->once())
            ->method('markAsActive');

        // Second order should not be activated
        $pending2->expects($this->never())
            ->method('markAsActive');

        $this->kitchenService->processPendingOrders();
    }

    // ===== getNextAvailablePickupTime Tests =====

    public function testGetNextAvailablePickupTimeWithNoActiveOrders(): void
    {
        $this->orderRepository
            ->method('getLatestPickupTime')
            ->willReturn(null);

        $result = $this->kitchenService->getNextAvailablePickupTime();

        $now = new \DateTimeImmutable();
        $expected = $now->modify('+15 minutes');

        // Allow 1 second tolerance for test execution time
        $this->assertLessThanOrEqual(1, abs($result->getTimestamp() - $expected->getTimestamp()));
    }

    public function testGetNextAvailablePickupTimeWithExistingOrders(): void
    {
        $latestPickupTime = new \DateTimeImmutable('2024-01-15 14:00:00');

        $this->orderRepository
            ->method('getLatestPickupTime')
            ->willReturn($latestPickupTime);

        $result = $this->kitchenService->getNextAvailablePickupTime();

        $expected = $latestPickupTime->modify('+15 minutes');

        $this->assertEquals($expected, $result);
    }

    // ===== getKitchenStatus Tests =====

    public function testGetKitchenStatusWhenNotFull(): void
    {
        $this->orderRepository
            ->method('countActiveOrders')
            ->willReturn(5); // Including VIP

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(3);

        $this->orderRepository
            ->method('getLatestPickupTime')
            ->willReturn(new \DateTimeImmutable('2024-01-15 14:00:00'));

        $status = $this->kitchenService->getKitchenStatus();

        $this->assertEquals(5, $status['max_capacity']);
        $this->assertEquals(5, $status['active_orders']);
        $this->assertEquals(3, $status['active_non_vip_orders']);
        $this->assertEquals(2, $status['available_capacity']);
        $this->assertFalse($status['is_full']);
        $this->assertIsString($status['next_available_pickup_time']);
    }

    public function testGetKitchenStatusWhenFull(): void
    {
        $this->orderRepository
            ->method('countActiveOrders')
            ->willReturn(7); // Including VIP

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(5); // At capacity

        $this->orderRepository
            ->method('getLatestPickupTime')
            ->willReturn(null);

        $status = $this->kitchenService->getKitchenStatus();

        $this->assertEquals(5, $status['max_capacity']);
        $this->assertEquals(7, $status['active_orders']);
        $this->assertEquals(5, $status['active_non_vip_orders']);
        $this->assertEquals(0, $status['available_capacity']);
        $this->assertTrue($status['is_full']);
    }

    public function testGetKitchenStatusAvailableCapacityNeverNegative(): void
    {
        $this->orderRepository
            ->method('countActiveOrders')
            ->willReturn(10);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(10); // Over capacity

        $this->orderRepository
            ->method('getLatestPickupTime')
            ->willReturn(null);

        $status = $this->kitchenService->getKitchenStatus();

        $this->assertEquals(0, $status['available_capacity']);
    }

    // ===== getActiveOrders Tests =====

    public function testGetActiveOrdersReturnsAllActiveOrders(): void
    {
        $order1 = $this->createMock(Order::class);
        $order2 = $this->createMock(Order::class);

        $expectedOrders = [$order1, $order2];

        $this->orderRepository
            ->method('findActiveOrders')
            ->willReturn($expectedOrders);

        $result = $this->kitchenService->getActiveOrders();

        $this->assertEquals($expectedOrders, $result);
    }

    public function testGetActiveOrdersReturnsEmptyArrayWhenNoOrders(): void
    {
        $this->orderRepository
            ->method('findActiveOrders')
            ->willReturn([]);

        $result = $this->kitchenService->getActiveOrders();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ===== Edge Cases and Integration Tests =====

    public function testCustomMaxCapacityIsRespected(): void
    {
        $customCapacity = 10;
        $customService = new KitchenService($this->orderRepository, $customCapacity);

        $order = $this->createMock(Order::class);
        $order->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(9);

        $result = $customService->canAcceptOrder($order);

        $this->assertTrue($result);
    }

    public function testZeroCapacityOnlyAcceptsVipOrders(): void
    {
        $zeroCapacityService = new KitchenService($this->orderRepository, 0);

        $vipOrder = $this->createMock(Order::class);
        $vipOrder->method('isVip')->willReturn(true);

        $regularOrder = $this->createMock(Order::class);
        $regularOrder->method('isVip')->willReturn(false);

        $this->orderRepository
            ->method('countActiveNonVipOrders')
            ->willReturn(0);

        $this->assertTrue($zeroCapacityService->canAcceptOrder($vipOrder));
        $this->assertFalse($zeroCapacityService->canAcceptOrder($regularOrder));
    }
}