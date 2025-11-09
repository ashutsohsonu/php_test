<?php
// src/Controller/OrderController.php
namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Service\KitchenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/orders')]
class OrderController extends AbstractController
{
    
    public function __construct(
        private OrderRepository $orderRepository,
        private KitchenService $kitchenService,
        private ValidatorInterface $validator
    ) {}

    /**
     * Create a new order
     */
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'error' => 'Invalid JSON'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            return $this->json([
                'error' => 'Items array is required and cannot be empty'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($data['pickup_time'])) {
            return $this->json([
                'error' => 'Pickup time is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $pickupTime = new \DateTimeImmutable($data['pickup_time']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Invalid pickup_time format. Use ISO 8601 format (e.g., 2025-09-26T12:30:00Z)'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create order
        $order = new Order();
        $order->setItems($data['items']);
        $order->setPickupTime($pickupTime);
        $order->setVip($data['VIP'] ?? false);

        // Validate entity
        $errors = $this->validator->validate($order);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Try to process the order
        $this->orderRepository->save($order);
        
        if ($this->kitchenService->processOrder($order)) {
            return $this->json([
                'message' => 'Order created successfully',
                'order' => $order->toArray(),
                'kitchen_status' => $this->kitchenService->getKitchenStatus()
            ], Response::HTTP_CREATED);
        } else {
            // Kitchen is full - order is kept as pending
            $kitchenStatus = $this->kitchenService->getKitchenStatus();
            
            return $this->json([
                'error' => 'Kitchen is at full capacity',
                'message' => 'Your order has been queued and will be processed when capacity becomes available',
                'order' => $order->toArray(),
                'suggested_pickup_time' => $kitchenStatus['next_available_pickup_time'],
                'kitchen_status' => $kitchenStatus
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }
    }

    /**
     * List all active orders
     */
    #[Route('/active', methods: ['GET'])]
    public function listActive(): JsonResponse
    {
        $activeOrders = $this->kitchenService->getActiveOrders();

        $data = array_map(fn($order) => $order->toArray(), $activeOrders);

        return $this->json([
            'active_orders' => $data,
            'count' => count($data),
            'kitchen_status' => $this->kitchenService->getKitchenStatus()
        ]);
    }

    /**
     * Get single order
     */
    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return $this->json([
                'error' => 'Order not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'order' => $order->toArray()
        ]);
    }

    /**
     * Complete an order
     */
    #[Route('/{id}/complete', methods: ['POST'])]
    public function complete(int $id): JsonResponse
    {
        $order = $this->orderRepository->find($id);

        if (!$order) {
            return $this->json([
                'error' => 'Order not found'
            ], Response::HTTP_NOT_FOUND);
        }

        if ($order->getStatus() === Order::STATUS_COMPLETED) {
            return $this->json([
                'error' => 'Order is already completed'
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->kitchenService->completeOrder($order);

        return $this->json([
            'message' => 'Order completed successfully',
            'order' => $order->toArray(),
            'kitchen_status' => $this->kitchenService->getKitchenStatus()
        ], Response::HTTP_OK);
    }

    /**
     * Get kitchen status
     */
    #[Route('/status/kitchen', methods: ['GET'])]
    public function kitchenStatus(): JsonResponse
    {
        return $this->json($this->kitchenService->getKitchenStatus());
    }

    /**
     * List all orders (for debugging)
     */
    #[Route('', methods: ['GET'])]
    public function listAll(): JsonResponse
    {
        $orders = $this->orderRepository->findBy([], ['createdAt' => 'DESC']);

        $data = array_map(fn($order) => $order->toArray(), $orders);

        return $this->json([
            'orders' => $data,
            'count' => count($data)
        ]);
    }
}