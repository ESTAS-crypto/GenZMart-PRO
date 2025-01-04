<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['items']) && is_array($input['items'])) {
        $conn->begin_transaction();
        
        try {
            $stmt = $conn->prepare("UPDATE items SET stock = stock - ? WHERE id = ? AND stock >= ?");
            
            foreach ($input['items'] as $item) {
                $stmt->bind_param("iii", $item['quantity'], $item['id'], $item['quantity']);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to update stock for item " . $item['id']);
                }
            }
            
            $conn->commit();
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log($e->getMessage());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
?>