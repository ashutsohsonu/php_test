<?php
class KitchenService {
    private $order;
    private $maxCapacity;
    
    public function __construct($maxCapacity = 5) {
        $this->order = new Order();
        $this->maxCapacity = $maxCapacity;
    }
    
    public function canAcceptOrder($isVip = false) {
        if ($isVip) {
            return true; // VIP orders bypass capacity
        }
        
        $activeCount = $this->order->getActiveOrderCount();
        return $activeCount < $this->maxCapacity;
    }
    
    public function getNextAvailablePickupTime() {
        $activeCount = $this->order->getActiveOrderCount();
        
        if ($activeCount < $this->maxCapacity) {
            // Kitchen has capacity, suggest current time + prep time
            $avgPrepTime = $this->order->getAveragePreparationTime();
            return date('Y-m-d\TH:i:s\Z', strtotime("+{$avgPrepTime} minutes"));
        }
        
        // Kitchen is full, calculate based on queue
        $ordersAhead = $activeCount - $this->maxCapacity + 1;
        $avgPrepTime = $this->order->getAveragePreparationTime();
        $waitTime = $ordersAhead * ($avgPrepTime / $this->maxCapacity);
        
        return date('Y-m-d\TH:i:s\Z', strtotime("+{$waitTime} minutes"));
    }
    
    public function createOrder($items, $pickupTime, $isVip = false) {
        if (!$this->canAcceptOrder($isVip)) {
            return [
                'success' => false,
                'error' => 'Kitchen at capacity',
                'next_available' => $this->getNextAvailablePickupTime()
            ];
        }
        
        $orderId = $this->order->create($items, $pickupTime, $isVip);
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'message' => 'Order accepted'
        ];
    }
}
