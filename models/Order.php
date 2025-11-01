<?php
// services/KitchenService.php
class Order {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($items, $pickupTime, $isVip = false) {
        $stmt = $this->db->prepare("
            INSERT INTO orders (items, pickup_time, is_vip, status)
            VALUES (:items, :pickup_time, :is_vip, 'active')
        ");
        
        $stmt->execute([
            ':items' => json_encode($items),
            ':pickup_time' => $pickupTime,
            ':is_vip' => $isVip ? 1 : 0
        ]);
        
        return $this->db->lastInsertId();
    }
    
    public function getActiveOrders() {
        $stmt = $this->db->query("
            SELECT id, items, pickup_time, is_vip, created_at
            FROM orders
            WHERE status = 'active'
            ORDER BY is_vip DESC, created_at ASC
        ");
        
        return array_map(function($row) {
            $row['items'] = json_decode($row['items']);
            $row['is_vip'] = (bool)$row['is_vip'];
            return $row;
        }, $stmt->fetchAll());
    }
    
    public function getActiveOrderCount($excludeVip = false) {
        $sql = "SELECT COUNT(*) FROM orders WHERE status = 'active'";
        if ($excludeVip) {
            $sql .= " AND is_vip = 0";
        }
        return (int)$this->db->query($sql)->fetchColumn();
    }
    
    public function complete($id) {
        $stmt = $this->db->prepare("
            UPDATE orders
            SET status = 'completed', completed_at = NOW()
            WHERE id = :id AND status = 'active'
        ");
        
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function getAveragePreparationTime() {
        $stmt = $this->db->query("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at)) as avg_minutes
            FROM orders
            WHERE status = 'completed'
            AND completed_at IS NOT NULL
            ORDER BY completed_at DESC
            LIMIT 50
        ");
        
        $result = $stmt->fetchColumn();
        return $result ? (int)$result : 20; // Default to 20 minutes
    }
}