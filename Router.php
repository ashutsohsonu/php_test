<?php


// api/router.php
class Router {
    private $kitchenService;
    
    public function __construct() {
        $this->kitchenService = new KitchenService(5);
    }
    
    public function route() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        header('Content-Type: application/json');
        
        try {
            if ($method === 'POST' && $path === '/orders') {
                $this->createOrder();
            } elseif ($method === 'GET' && $path === '/orders/active') {
                $this->listActiveOrders();
            } elseif ($method === 'POST' && preg_match('/^\/orders\/(\d+)\/complete$/', $path, $matches)) {
                $this->completeOrder($matches[1]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    private function createOrder() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['items']) || !is_array($input['items']) || empty($input['items'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Items are required and must be a non-empty array']);
            return;
        }
        
        if (!isset($input['pickup_time'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Pickup time is required']);
            return;
        }
        
        $isVip = isset($input['VIP']) && $input['VIP'] === true;
        
        $result = $this->kitchenService->createOrder(
            $input['items'],
            $input['pickup_time'],
            $isVip
        );
        
        if ($result['success']) {
            http_response_code(201);
            echo json_encode([
                'id' => $result['order_id'],
                'message' => $result['message']
            ]);
        } else {
            http_response_code(429);
            echo json_encode([
                'error' => $result['error'],
                'next_available_pickup_time' => $result['next_available']
            ]);
        }
    }
    
    private function listActiveOrders() {
        $order = new Order();
        $orders = $order->getActiveOrders();
        
        http_response_code(200);
        echo json_encode(['orders' => $orders]);
    }
    
    private function completeOrder($id) {
        $order = new Order();
        
        if ($order->complete($id)) {
            http_response_code(200);
            echo json_encode(['message' => 'Order completed successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found or already completed']);
        }
    }
}
