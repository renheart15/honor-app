<?php
class NotificationManager {
    private $conn;
    private $table_name = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function createNotification($user_id, $title, $message, $type = 'info', $category = 'general') {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET user_id=:user_id, title=:title, message=:message, 
                      type=:type, category=:category";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $title = htmlspecialchars(strip_tags($title));
        $message = htmlspecialchars(strip_tags($message));
        $type = htmlspecialchars(strip_tags($type));
        $category = htmlspecialchars(strip_tags($category));

        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);
        $stmt->bindParam(":category", $category);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserNotifications($user_id, $limit = 10) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUnreadCount($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND is_read = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_read = 1, read_at = NOW() 
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $notification_id);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return false;
        }
    }

    public function markAllAsRead($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET is_read = 1, read_at = NOW() 
                  WHERE user_id = :user_id AND is_read = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Mark all as read error: " . $e->getMessage());
            return false;
        }
    }

    public function deleteNotification($notification_id, $user_id) {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id = :id AND user_id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $notification_id);
        $stmt->bindParam(":user_id", $user_id);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Delete notification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendSystemNotification($title, $message, $type = 'system') {
        // Send notification to all active users
        $query = "INSERT INTO " . $this->table_name . " (user_id, title, message, type, category)
                  SELECT id, :title, :message, :type, 'system' 
                  FROM users WHERE status = 'active'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":message", $message);
        $stmt->bindParam(":type", $type);

        try {
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("System notification error: " . $e->getMessage());
            return false;
        }
    }
}
?>
