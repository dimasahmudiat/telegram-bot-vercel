<?php
// config.php - Configuration file with database functions for Vercel
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

// Database Configuration - Using environment variables for Vercel
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USERNAME', $_ENV['DB_USERNAME'] ?? 'dimc6971_Dimas1120');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'dimasahm12');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'dimc6971_Dimas_db');

// API Configuration
define('API_KEY', $_ENV['API_KEY'] ?? 'AjnQBMAhSZ4kJhqp');
define('MERCHANT_CODE', $_ENV['MERCHANT_CODE'] ?? 'DIMZ1945');

// Bot Configuration
define('BOT_TOKEN', $_ENV['BOT_TOKEN'] ?? '8068557641:AAFtzz8RQznufSuMaZq7NTA7oDwXcNi2huc');
define('ADMIN_CHAT_ID', $_ENV['ADMIN_CHAT_ID'] ?? '6201552432');

// Path Configuration
define('WELCOME_IMAGE', $_ENV['WELCOME_IMAGE'] ?? 'https://dimzmods.my.id/demobot/img/contoh1.jpg');

// Timeout Configuration - 10 minutes (600 seconds)
define('ORDER_TIMEOUT', 600);

// Real-time check interval - 20 seconds
define('PAYMENT_CHECK_INTERVAL', 20);

// Price Configuration
$prices = [
    '1' => 15000,
    '2' => 30000,
    '3' => 40000,
    '4' => 50000,
    '6' => 70000,
    '8' => 90000,
    '10' => 100000,
    '15' => 150000,
    '20' => 180000,
    '30' => 250000
];

// Point Configuration
$point_rules = [
    '1' => 1,   // 1 day = 1 point
    '2' => 1,   // 2 days = 1 point  
    '3' => 2,   // 3 days = 2 points
    '4' => 3,   // 4 days = 3 points
    '6' => 4,   // 6 days = 4 points
    '8' => 5,   // 8 days = 5 points
    '10' => 6,  // 10 days = 6 points
    '15' => 8,  // 15 days = 8 points
    '20' => 10, // 20 days = 10 points
    '30' => 15  // 30 days = 15 points
];

// Point redemption rates (points needed per day)
define('POINTS_PER_DAY', 12);

/**
 * Basic logging function - Modified for Vercel
 */
function logMessage($message) {
    // For Vercel, use error_log instead of file_put_contents
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] " . $message);
}

/**
 * Simple message sending function
 */
function sendSimpleMessage($chatId, $text, $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    logMessage("Message sent to $chatId: " . substr($text, 0, 50));
    return $result;
}

/**
 * Send notification to admin
 */
function notifyAdmin($message) {
    return sendSimpleMessage(ADMIN_CHAT_ID, $message);
}

/**
 * Database connection with error handling
 */
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if ($conn->connect_error) {
            logMessage("Database connection failed: " . $conn->connect_error);
            return false;
        }
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        logMessage("Database exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if username exists in database
 */
function isUsernameExists($username, $table = null) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        if ($table) {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $count = $row['count'];
            $stmt->close();
        } else {
            // Check both tables
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM freefire WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $countFF = $row['count'];
            $stmt->close();
            
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ffmax WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $countFFMax = $row['count'];
            $stmt->close();
            
            $count = $countFF + $countFFMax;
        }
        
        $conn->close();
        return ($count > 0);
    } catch (Exception $e) {
        logMessage("Error in isUsernameExists: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

/**
 * Generate random credentials - 4 characters (letters + numbers)
 */
function generateRandomCredentials() {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    
    // Username: 4 characters (letters + numbers)
    $username = '';
    for ($i = 0; $i < 2; $i++) {
        $username .= $letters[rand(0, strlen($letters) - 1)];
    }
    for ($i = 0; $i < 2; $i++) {
        $username .= $numbers[rand(0, strlen($numbers) - 1)];
    }
    
    // Password: 2 digit numbers
    $password = '';
    for ($i = 0; $i < 2; $i++) {
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
    }
    
    return ['username' => $username, 'password' => $password];
}

/**
 * Generate redeem credentials with "redeem" prefix
 */
function generateRedeemCredentials() {
    $letters = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    
    // Username: "redeem" + 1 random number + 2 random letters
    $username = 'redeem';
    $username .= $numbers[rand(0, strlen($numbers) - 1)]; // 1 digit
    for ($i = 0; $i < 2; $i++) {
        $username .= $letters[rand(0, strlen($letters) - 1)]; // 2 letters
    }
    
    // Password: 1 digit random number
    $password = $numbers[rand(0, strlen($numbers) - 1)];
    
    return ['username' => $username, 'password' => $password];
}

/**
 * Get user by username and password
 */
function getUserByUsernameAndPassword($username, $password, $gameType = null) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        if ($gameType == 'ff') {
            $stmt = $conn->prepare("SELECT *, 'ff' as game_type FROM freefire WHERE username = ? AND password = ?");
            $stmt->bind_param("ss", $username, $password);
        } elseif ($gameType == 'ffmax') {
            $stmt = $conn->prepare("SELECT *, 'ffmax' as game_type FROM ffmax WHERE username = ? AND password = ?");
            $stmt->bind_param("ss", $username, $password);
        } else {
            // Check both tables
            $stmt = $conn->prepare("SELECT *, 'ff' as game_type FROM freefire WHERE username = ? AND password = ? 
                                   UNION ALL 
                                   SELECT *, 'ffmax' as game_type FROM ffmax WHERE username = ? AND password = ? 
                                   LIMIT 1");
            $stmt->bind_param("ssss", $username, $password, $username, $password);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            return $user;
        }
        
        $stmt->close();
        $conn->close();
        return false;
    } catch (Exception $e) {
        logMessage("Error in getUserByUsernameAndPassword: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

/**
 * Extend user license
 */
function extendUserLicense($username, $password, $duration, $gameType) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        // First, get current expiration date
        if ($gameType == 'ff') {
            $stmt = $conn->prepare("SELECT expDate FROM freefire WHERE username = ? AND password = ?");
        } elseif ($gameType == 'ffmax') {
            $stmt = $conn->prepare("SELECT expDate FROM ffmax WHERE username = ? AND password = ?");
        } else {
            return false;
        }
        
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $currentExpDate = $user['expDate'];
            $stmt->close();
            
            // Determine new expiration date
            $now = date('Y-m-d H:i:s');
            
            // If account is already expired, start from now
            if (strtotime($currentExpDate) < time()) {
                $newExpDate = date('Y-m-d H:i:s', strtotime("+$duration days"));
                logMessage("Extend from NOW - Username: $username, Current: $currentExpDate, New: $newExpDate");
            } else {
                // If account still active, add from current expiration
                $newExpDate = date('Y-m-d H:i:s', strtotime("$currentExpDate +$duration days"));
                logMessage("Extend from EXISTING - Username: $username, Current: $currentExpDate, New: $newExpDate");
            }
            
            // Update the expiration date
            if ($gameType == 'ff') {
                $stmt = $conn->prepare("UPDATE freefire SET expDate = ? WHERE username = ? AND password = ?");
            } elseif ($gameType == 'ffmax') {
                $stmt = $conn->prepare("UPDATE ffmax SET expDate = ? WHERE username = ? AND password = ?");
            }
            
            $stmt->bind_param("sss", $newExpDate, $username, $password);
            $result = $stmt->execute();
            $affected = $stmt->affected_rows;
            
            $stmt->close();
            $conn->close();
            
            return ($affected > 0);
        }
        
        $stmt->close();
        $conn->close();
        return false;
        
    } catch (Exception $e) {
        logMessage("Error in extendUserLicense: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

/**
 * USER STATE MANAGEMENT FUNCTIONS - Modified for Vercel compatibility
 */
function saveUserState($chatId, $state, $data = []) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $jsonData = json_encode($data);
        
        // Check if state exists
        $stmt = $conn->prepare("SELECT id FROM user_states WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE user_states SET state = ?, data = ?, error_count = 0, updated_at = NOW() WHERE chat_id = ?");
            $stmt->bind_param("sss", $state, $jsonData, $chatId);
        } else {
            $stmt = $conn->prepare("INSERT INTO user_states (chat_id, state, data, error_count, created_at, updated_at) VALUES (?, ?, ?, 0, NOW(), NOW())");
            $stmt->bind_param("sss", $chatId, $state, $jsonData);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        $conn->close();
        
        logMessage("User state saved - Chat: $chatId, State: $state");
        return $result;
    } catch (Exception $e) {
        logMessage("Error in saveUserState: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

function getUserState($chatId) {
    $conn = getDBConnection();
    if (!$conn) return null;
    
    try {
        $stmt = $conn->prepare("SELECT state, data, error_count FROM user_states WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $state = [
                'state' => $row['state'],
                'data' => json_decode($row['data'], true),
                'error_count' => $row['error_count']
            ];
        } else {
            $state = null;
        }
        
        $stmt->close();
        $conn->close();
        
        return $state;
    } catch (Exception $e) {
        logMessage("Error in getUserState: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return null;
    }
}

function clearUserState($chatId) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("DELETE FROM user_states WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        logMessage("User state cleared - Chat: $chatId");
        return $result;
    } catch (Exception $e) {
        logMessage("Error in clearUserState: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

/**
 * ERROR COUNT FUNCTIONS
 */
function updateUserErrorCount($chatId, $errorCount) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("UPDATE user_states SET error_count = ?, updated_at = NOW() WHERE chat_id = ?");
        $stmt->bind_param("is", $errorCount, $chatId);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        logMessage("User error count updated - Chat: $chatId, Error Count: $errorCount");
        return $result;
    } catch (Exception $e) {
        logMessage("Error in updateUserErrorCount: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

function resetUserErrorCount($chatId) {
    return updateUserErrorCount($chatId, 0);
}

/**
 * POINT/REWARD SYSTEM FUNCTIONS
 */
function getUserPoints($chatId) {
    $conn = getDBConnection();
    if (!$conn) return 0;
    
    try {
        $stmt = $conn->prepare("SELECT points FROM user_points WHERE chat_id = ?");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $points = $row['points'];
        } else {
            $points = 0;
            // Create record if not exists
            $stmt2 = $conn->prepare("INSERT INTO user_points (chat_id, points, created_at, updated_at) VALUES (?, 0, NOW(), NOW())");
            $stmt2->bind_param("s", $chatId);
            $stmt2->execute();
            $stmt2->close();
        }
        
        $stmt->close();
        $conn->close();
        
        return $points;
    } catch (Exception $e) {
        logMessage("Error in getUserPoints: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return 0;
    }
}

function addUserPoints($chatId, $points, $reason = '') {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        // Update or insert user points
        $stmt = $conn->prepare("INSERT INTO user_points (chat_id, points, created_at, updated_at) 
                               VALUES (?, ?, NOW(), NOW()) 
                               ON DUPLICATE KEY UPDATE points = points + ?, updated_at = NOW()");
        $stmt->bind_param("sii", $chatId, $points, $points);
        $result = $stmt->execute();
        $stmt->close();
        
        // Log point transaction
        if ($result && !empty($reason)) {
            $stmt2 = $conn->prepare("INSERT INTO point_transactions (chat_id, points, type, reason, created_at) VALUES (?, ?, 'earn', ?, NOW())");
            $stmt2->bind_param("sis", $chatId, $points, $reason);
            $stmt2->execute();
            $stmt2->close();
        }
        
        $conn->close();
        
        logMessage("Points added - Chat: $chatId, Points: $points, Reason: $reason");
        return $result;
    } catch (Exception $e) {
        logMessage("Error in addUserPoints: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

function redeemUserPoints($chatId, $points, $reason = '') {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        // Check if user has enough points
        $currentPoints = getUserPoints($chatId);
        if ($currentPoints < $points) {
            logMessage("Insufficient points - Chat: $chatId, Current: $currentPoints, Needed: $points");
            return false;
        }
        
        // Deduct points
        $stmt = $conn->prepare("UPDATE user_points SET points = points - ?, updated_at = NOW() WHERE chat_id = ?");
        $stmt->bind_param("is", $points, $chatId);
        $result = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        // Log point transaction
        if ($result && $affected > 0 && !empty($reason)) {
            $stmt2 = $conn->prepare("INSERT INTO point_transactions (chat_id, points, type, reason, created_at) VALUES (?, ?, 'redeem', ?, NOW())");
            $stmt2->bind_param("sis", $chatId, $points, $reason);
            $stmt2->execute();
            $stmt2->close();
        }
        
        $conn->close();
        
        logMessage("Points redeemed - Chat: $chatId, Points: $points, Reason: $reason");
        return ($result && $affected > 0);
    } catch (Exception $e) {
        logMessage("Error in redeemUserPoints: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

function getPointRules() {
    global $point_rules;
    return $point_rules;
}

function calculatePointsForDuration($duration) {
    $point_rules = getPointRules();
    return isset($point_rules[$duration]) ? $point_rules[$duration] : 0;
}

function calculatePointsNeededForDays($days) {
    return $days * POINTS_PER_DAY;
}

/**
 * PAYMENT FUNCTIONS
 */
function createPayment($orderId, $amount) {
    $url = "https://cvqris-ariepulsa.my.id/qris/?action=get-deposit&kode=" . urlencode($orderId) . "&nominal=" . $amount . "&apikey=" . API_KEY;
    
    logMessage("Creating payment: " . $url);
    $response = file_get_contents($url);
    logMessage("Payment response: " . $response);
    
    return json_decode($response, true);
}

function checkPaymentStatus($depositCode) {
    $url = "https://cvqris-ariepulsa.my.id/qris/?action=get-mutasi&kode=" . urlencode($depositCode) . "&apikey=" . API_KEY;
    
    logMessage("Checking payment status: " . $url);
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    
    logMessage("Payment status response: " . $response);
    
    if ($data && $data['status'] && isset($data['data']['status']) && $data['data']['status'] == 'Success') {
        return $data['data'];
    }
    
    return false;
}

/**
 * PENDING ORDERS FUNCTIONS
 */
function savePendingOrder($orderId, $chatId, $gameType, $duration, $amount, $depositCode, $keyType, $manualUsername = '', $manualPassword = '') {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("INSERT INTO pending_orders (order_id, chat_id, game_type, duration, amount, deposit_code, key_type, manual_username, manual_password, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
        $stmt->bind_param("ssssissss", $orderId, $chatId, $gameType, $duration, $amount, $depositCode, $keyType, $manualUsername, $manualPassword);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        logMessage("Pending order saved - Order: $orderId, Chat: $chatId, Type: $gameType, Duration: $duration, Amount: $amount");
        return $result;
    } catch (Exception $e) {
        logMessage("Error in savePendingOrder: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

function getPendingOrder($chatId) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("SELECT * FROM pending_orders WHERE chat_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("s", $chatId);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();
        
        $stmt->close();
        $conn->close();
        
        return $order;
    } catch (Exception $e) {
        logMessage("Error in getPendingOrder: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

function updateOrderStatus($depositCode, $status) {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $stmt = $conn->prepare("UPDATE pending_orders SET status = ?, updated_at = NOW() WHERE deposit_code = ?");
        $stmt->bind_param("ss", $status, $depositCode);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        logMessage("Order status updated - Deposit Code: $depositCode, Status: $status");
        return $result;
    } catch (Exception $e) {
        logMessage("Error in updateOrderStatus: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

/**
 * LICENSE MANAGEMENT FUNCTIONS
 */
function saveLicenseToDatabase($table, $username, $password, $duration, $reference) {
    $conn = getDBConnection();
    if (!$conn) {
        logMessage("ERROR: Database connection failed in saveLicenseToDatabase");
        return false;
    }
    
    try {
        // Check if username exists
        if (isUsernameExists($username, $table)) {
            logMessage("ERROR: Username already exists in table $table: " . $username);
            $conn->close();
            return false;
        }
        
        $expDate = date('Y-m-d H:i:s', strtotime("+$duration days"));
        $uuid = ""; // Empty UUID as requested
        $status = "2"; // Status 2 as requested
        
        logMessage("DEBUG: Attempting to save license - Table: $table, Username: $username, Password: $password, Duration: $duration, Reference: $reference");
        
        $stmt = $conn->prepare("INSERT INTO $table (username, password, uuid, expDate, status, reference, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt) {
            logMessage("ERROR: Prepare failed - " . $conn->error);
            $conn->close();
            return false;
        }
        
        $stmt->bind_param("ssssss", $username, $password, $uuid, $expDate, $status, $reference);
        $result = $stmt->execute();
        
        if (!$result) {
            logMessage("ERROR: Execute failed - " . $stmt->error);
            $stmt->close();
            $conn->close();
            return false;
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        
        if ($result && $affected > 0) {
            logMessage("SUCCESS: License saved to $table - Username: $username, Password: $password, Duration: $duration days, Status: $status, Reference: $reference");
            return true;
        } else {
            logMessage("ERROR: Failed to save license to $table - Affected rows: $affected");
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("EXCEPTION in saveLicenseToDatabase: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

/**
 * CLEANUP FUNCTIONS
 */
function cleanupExpiredOrders() {
    $conn = getDBConnection();
    if (!$conn) return false;
    
    try {
        $expiredTime = date('Y-m-d H:i:s', time() - ORDER_TIMEOUT);
        
        // Delete expired pending orders
        $stmt = $conn->prepare("DELETE FROM pending_orders WHERE status = 'pending' AND created_at < ?");
        $stmt->bind_param("s", $expiredTime);
        $result = $stmt->execute();
        $deleted = $stmt->affected_rows;
        
        $stmt->close();
        $conn->close();
        
        if ($deleted > 0) {
            logMessage("Cleaned up $deleted expired orders (older than $expiredTime)");
        }
        
        return $deleted;
    } catch (Exception $e) {
        logMessage("Error in cleanupExpiredOrders: " . $e->getMessage());
        if (isset($stmt)) $stmt->close();
        if ($conn) $conn->close();
        return false;
    }
}

// Run cleanup when config is loaded
cleanupExpiredOrders();
?>
