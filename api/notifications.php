<?php
require_once '../config/config.php';

header('Content-Type: application/json');

requireLogin();

$database = new Database();
$db = $database->getConnection();
$notificationManager = new NotificationManager($db);

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $notifications = $notificationManager->getUserNotifications($user_id, $limit);
        $unread_count = $notificationManager->getUnreadCount($user_id);
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'mark_read') {
            $notification_id = $input['notification_id'] ?? 0;
            $result = $notificationManager->markAsRead($notification_id, $user_id);
            echo json_encode(['success' => $result]);
        } elseif ($action === 'mark_all_read') {
            $result = $notificationManager->markAllAsRead($user_id);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'DELETE':
        $input = json_decode(file_get_contents('php://input'), true);
        $notification_id = $input['notification_id'] ?? 0;
        $result = $notificationManager->deleteNotification($notification_id, $user_id);
        echo json_encode(['success' => $result]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>
