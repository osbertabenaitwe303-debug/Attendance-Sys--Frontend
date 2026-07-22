<?php
// includes/auth.php - COMPLETE FIXED VERSION
require_once __DIR__ . '/../config/db.php';

class Auth {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }
    
    /**
     * Login user with email and password
     */
    public function login($email, $password) {
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                return ['success' => true, 'role' => $user['role']];
            }
            return ['success' => false, 'message' => 'Invalid email or password'];
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user role
     */
    public function getCurrentUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Get current user name
     */
    public function getCurrentUserName() {
        return $_SESSION['user_name'] ?? null;
    }
    
    /**
     * Require login - redirect if not logged in
     */
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header("Location: ../views/login.php");
            exit();
        }
    }
    
    /**
     * Require specific role - redirect if role doesn't match
     */
    public function requireRole($role) {
        $this->requireLogin();
        if ($_SESSION['user_role'] !== $role) {
            // Redirect to appropriate dashboard based on role
            if ($_SESSION['user_role'] === 'admin') {
                header("Location: ../modules/admin/dashboard.php");
            } elseif ($_SESSION['user_role'] === 'teacher') {
                header("Location: ../modules/teacher/dashboard.php");
            } else {
                header("Location: ../views/login.php");
            }
            exit();
        }
    }
    
    /**
     * Require admin role specifically
     */
    public function requireAdmin() {
        $this->requireRole('admin');
    }
    
    /**
     * Require teacher role specifically
     */
    public function requireTeacher() {
        $this->requireRole('teacher');
    }
    
    /**
     * Change user password
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        try {
            // Get current password
            $query = "SELECT password FROM users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }
            
            if (strlen($newPassword) < 4) {
                return ['success' => false, 'message' => 'New password must be at least 4 characters'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $updateQuery = "UPDATE users SET password = :password WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':password', $hashedPassword);
            $updateStmt->bindParam(':id', $userId);
            
            if ($updateStmt->execute()) {
                return ['success' => true, 'message' => 'Password changed successfully'];
            }
            return ['success' => false, 'message' => 'Failed to change password'];
        } catch(PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Reset password for a user (admin function)
     */
    public function resetPassword($userId, $newPassword) {
        try {
            if (strlen($newPassword) < 4) {
                return ['success' => false, 'message' => 'Password must be at least 4 characters'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
            $query = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':id', $userId);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Password reset successfully'];
            }
            return ['success' => false, 'message' => 'Failed to reset password'];
        } catch(PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        try {
            $query = "SELECT id, name, email, role, created_at FROM users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all users (admin function)
     */
    public function getAllUsers() {
        try {
            $query = "SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get users error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new user (admin function)
     */
    public function createUser($name, $email, $password, $role = 'teacher') {
        try {
            // Check if email already exists
            $checkQuery = "SELECT id FROM users WHERE email = :email";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            $query = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':role', $role);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'User created successfully', 'user_id' => $this->conn->lastInsertId()];
            }
            return ['success' => false, 'message' => 'Failed to create user'];
        } catch(PDOException $e) {
            error_log("Create user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Delete a user (admin function)
     */
    public function deleteUser($userId) {
        try {
            // Don't allow deleting own account
            if ($userId == $this->getCurrentUserId()) {
                return ['success' => false, 'message' => 'Cannot delete your own account'];
            }
            
            $query = "DELETE FROM users WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $userId);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'User deleted successfully'];
            }
            return ['success' => false, 'message' => 'Failed to delete user'];
        } catch(PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $name, $email) {
        try {
            // Check if email is taken by another user
            $checkQuery = "SELECT id FROM users WHERE email = :email AND id != :id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $email);
            $checkStmt->bindParam(':id', $userId);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            $query = "UPDATE users SET name = :name, email = :email WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $userId);
            
            if ($stmt->execute()) {
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                return ['success' => true, 'message' => 'Profile updated successfully'];
            }
            return ['success' => false, 'message' => 'Failed to update profile'];
        } catch(PDOException $e) {
            error_log("Update profile error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
}
?>