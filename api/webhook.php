<?php
// webhook.php - Main Telegram Bot webhook for Vercel
require_once 'config.php';

// Set headers for webhook response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log incoming request
$input = file_get_contents('php://input');
logMessage("Webhook input: " . $input);

$update = json_decode($input, true);

if (!$update) {
    logMessage("No update data received");
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'No update']);
    exit;
}

// Extract basic info
$chatId = $update['message']['chat']['id'] ?? ($update['callback_query']['message']['chat']['id'] ?? '');
$text = $update['message']['text'] ?? '';
$firstName = $update['message']['chat']['first_name'] ?? 'User';
$messageId = $update['message']['message_id'] ?? '';

logMessage("Processing - ChatID: $chatId, Text: $text, Name: $firstName");

/**
 * MAIN BOT FUNCTIONS
 */
function sendMessage($chatId, $text, $replyMarkup = null, $disableWebPagePreview = true) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => $disableWebPagePreview
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    logMessage("Sent message to $chatId: " . substr($text, 0, 100));
    return $result;
}

function sendMessageWithImage($chatId, $text, $replyMarkup = null) {
    // Try to send photo first, if fails send plain message
    $photoResult = sendPhoto($chatId, WELCOME_IMAGE, $text, $replyMarkup);
    $photoData = json_decode($photoResult, true);
    
    if ($photoData && $photoData['ok']) {
        return $photoResult;
    } else {
        // Fallback to plain message if photo fails
        logMessage("Failed to send photo, falling back to text message");
        return sendSimpleMessage($chatId, $text, $replyMarkup);
    }
}

/**
 * SMART MESSAGE EDITING - Detects if message contains photo or text
 */
function editMessageSmart($chatId, $messageId, $text, $replyMarkup = null) {
    // Try editing as caption first (for photos)
    $captionResult = editMessageCaption($chatId, $messageId, $text, $replyMarkup);
    $captionData = json_decode($captionResult, true);
    
    if ($captionData && $captionData['ok']) {
        logMessage("Successfully edited message caption - Chat: $chatId, Message: $messageId");
        return $captionResult;
    }
    
    // If failed, try editing as text
    $textResult = editMessageText($chatId, $messageId, $text, $replyMarkup);
    $textData = json_decode($textResult, true);
    
    if ($textData && $textData['ok']) {
        logMessage("Successfully edited message text - Chat: $chatId, Message: $messageId");
        return $textResult;
    }
    
    // If both methods fail, send new message
    logMessage("Both edit methods failed, sending new message - Chat: $chatId, Message: $messageId");
    return sendMessageWithImage($chatId, $text, $replyMarkup);
}

function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageText";
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result;
}

function editMessageCaption($chatId, $messageId, $caption, $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageCaption";
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result;
}

function deleteMessage($chatId, $messageId) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/deleteMessage?chat_id=$chatId&message_id=$messageId";
    $result = file_get_contents($url);
    logMessage("Deleted message $messageId from $chatId: " . $result);
    return $result;
}

function answerCallbackQuery($callbackId, $text = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
    $data = ['callback_query_id' => $callbackId];
    
    if (!empty($text)) {
        $data['text'] = $text;
    }
    
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    logMessage("Answered callback query: " . $result);
    return $result;
}

function sendPhoto($chatId, $photoUrl, $caption = '', $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    // Save message_id from photo
    $resultArray = json_decode($result, true);
    if ($resultArray && $resultArray['ok']) {
        $photoMessageId = $resultArray['result']['message_id'];
        logMessage("Photo sent to $chatId with message_id: $photoMessageId");
    }
    
    return $result;
}

function showMainMenu($chatId, $text = null, $messageId = null) {
    $userPoints = getUserPoints($chatId);
    
    if ($text) {
        $message = $text;
    } else {
        $message = "🏠 <b>Menu Utama</b>\n\n";
        $message .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
        $message .= "Silakan pilih menu yang diinginkan:";
    }
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🛒 Beli Lisensi Baru', 'callback_data' => 'new_order']
            ],
            [
                ['text' => '⏰ Extend Masa Aktif', 'callback_data' => 'extend_user']
            ],
            [
                ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
            ],
            [
                ['text' => 'ℹ️ Bantuan', 'callback_data' => 'help']
            ]
        ]
    ];
    
    if ($messageId) {
        $result = editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
    } else {
        $result = sendMessageWithImage($chatId, $message, json_encode($keyboard));
    }
    
    return $result;
}

function getBackButton($previousAction = '') {
    $buttons = [];
    
    if ($previousAction) {
        $buttons[] = [['text' => '↩️ Kembali', 'callback_data' => $previousAction]];
    }
    
    $buttons[] = [['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']];
    
    return json_encode([
        'inline_keyboard' => $buttons
    ]);
}

function sendLicenseToUser($chatId, $gameType, $duration, $credentials, $keyType = 'random') {
    $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
    $expiryDate = date('d-m-Y H:i:s', strtotime("+$duration days"));
    
    // Add points for user
    $pointsEarned = calculatePointsForDuration($duration);
    addUserPoints($chatId, $pointsEarned, "Pembelian lisensi $duration hari");
    
    $userPoints = getUserPoints($chatId);
    
    $message = "🎉 <b>PEMBAYARAN BERHASIL!</b>\n\n";
    $message .= "Terima kasih telah membeli lisensi <b>$gameName</b>\n";
    $message .= "Durasi: <b>$duration Hari</b>\n";
    $message .= "Tipe Key: <b>" . ($keyType == 'manual' ? 'MANUAL' : 'RANDOM') . "</b>\n\n";
    $message .= "📱 <b>AKUN ANDA:</b>\n";
    $message .= "Username: <code>" . $credentials['username'] . "</code>\n";
    $message .= "Password: <code>" . $credentials['password'] . "</code>\n\n";
    $message .= "⏰ <b>MASA AKTIF:</b>\n";
    $message .= "Berlaku hingga: <b>$expiryDate WIB</b>\n\n";
    $message .= "🎁 <b>REWARD POINT:</b>\n";
    $message .= "Anda mendapatkan <b>$pointsEarned points</b>\n";
    $message .= "Total point Anda: <b>$userPoints points</b>\n\n";
    $message .= "✨ <b>Selamat bermain!</b> 🎮\n\n";
    $message .= "📁 <b>Untuk file dan tutorial instalasi:</b>\n";
    $message .= "Klik tombol '📁 File & Cara Pasang' di bawah";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📁 File & Cara Pasang', 'url' => 'https://t.me/+RY2yMHn_jts3YzA1']
            ],
            [
                ['text' => '🔄 Beli Lagi', 'callback_data' => 'new_order'],
                ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
            ],
            [
                ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
            ]
        ]
    ];
    
    // Send success message
    $result = sendPhoto($chatId, WELCOME_IMAGE, $message, json_encode($keyboard));
    
    // Send notification to admin
    $adminMessage = "💰 <b>PEMBELIAN BERHASIL!</b>\n\n";
    $adminMessage .= "User ID: <code>$chatId</code>\n";
    $adminMessage .= "Jenis Game: <b>$gameName</b>\n";
    $adminMessage .= "Durasi: <b>$duration Hari</b>\n";
    $adminMessage .= "Tipe Key: <b>" . ($keyType == 'manual' ? 'MANUAL' : 'RANDOM') . "</b>\n";
    $adminMessage .= "Username: <code>" . $credentials['username'] . "</code>\n";
    $adminMessage .= "Password: <code>" . $credentials['password'] . "</code>\n";
    $adminMessage .= "Point Diberikan: <b>$pointsEarned points</b>\n";
    $adminMessage .= "Masa Aktif: <b>$expiryDate WIB</b>\n";
    $adminMessage .= "Waktu: " . date('d-m-Y H:i:s');
    
    notifyAdmin($adminMessage);
    
    return $result;
}

function sendExtendSuccess($chatId, $userData, $duration, $newExpDate) {
    $gameName = ($userData['game_type'] == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
    
    // Get current expiration for display
    $currentExp = date('d-m-Y H:i:s', strtotime($userData['expDate']));
    
    // Add points for user
    $pointsEarned = calculatePointsForDuration($duration);
    addUserPoints($chatId, $pointsEarned, "Extend lisensi $duration hari");
    
    $userPoints = getUserPoints($chatId);
    
    $message = "🎉 <b>EXTEND BERHASIL!</b>\n\n";
    $message .= "Akun Anda berhasil di-extend\n";
    $message .= "Jenis: <b>$gameName</b>\n";
    $message .= "Username: <code>" . $userData['username'] . "</code>\n";
    $message .= "Durasi Tambahan: <b>$duration Hari</b>\n";
    $message .= "Masa Aktif Lama: <b>$currentExp WIB</b>\n";
    $message .= "Masa Aktif Baru: <b>$newExpDate WIB</b>\n\n";
    $message .= "🎁 <b>REWARD POINT:</b>\n";
    $message .= "Anda mendapatkan <b>$pointsEarned points</b>\n";
    $message .= "Total point Anda: <b>$userPoints points</b>\n\n";
    $message .= "✨ <b>Selamat bermain!</b> 🎮\n\n";
    $message .= "📁 <b>Untuk file dan tutorial instalasi:</b>\n";
    $message .= "Klik tombol '📁 File & Cara Pasang' di bawah";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '📁 File & Cara Pasang', 'url' => 'https://t.me/+RY2yMHn_jts3YzA1']
            ],
            [
                ['text' => '🔄 Extend Lagi', 'callback_data' => 'extend_user'],
                ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
            ],
            [
                ['text' => '🔄 Beli Baru', 'callback_data' => 'new_order']
            ],
            [
                ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
            ]
        ]
    ];
    
    // Send success message
    $result = sendPhoto($chatId, WELCOME_IMAGE, $message, json_encode($keyboard));
    
    // Send notification to admin
    $adminMessage = "⏰ <b>EXTEND BERHASIL!</b>\n\n";
    $adminMessage .= "User ID: <code>$chatId</code>\n";
    $adminMessage .= "Jenis Game: <b>$gameName</b>\n";
    $adminMessage .= "Username: <code>" . $userData['username'] . "</code>\n";
    $adminMessage .= "Durasi: <b>$duration Hari</b>\n";
    $adminMessage .= "Point Diberikan: <b>$pointsEarned points</b>\n";
    $adminMessage .= "Masa Aktif Baru: <b>$newExpDate WIB</b>\n";
    $adminMessage .= "Waktu: " . date('d-m-Y H:i:s');
    
    notifyAdmin($adminMessage);
    
    return $result;
}

/**
 * POINT REDEMPTION FUNCTIONS
 */
function showRedeemPointsMenu($chatId, $messageId = null) {
    $userPoints = getUserPoints($chatId);
    
    $message = "🎁 <b>TUKAR POINT</b>\n\n";
    $message .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
    $message .= "📊 <b>Rate Penukaran:</b>\n";
    $message .= "• 1 Hari = 12 points\n";
    $message .= "• 2 Hari = 24 points\n";
    $message .= "• 3 Hari = 36 points\n";
    $message .= "• 7 Hari = 84 points\n\n";
    $message .= "Pilih durasi yang ingin ditukar:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '1 Hari - 12 points', 'callback_data' => 'redeem_1'],
                ['text' => '2 Hari - 24 points', 'callback_data' => 'redeem_2']
            ],
            [
                ['text' => '3 Hari - 36 points', 'callback_data' => 'redeem_3'],
                ['text' => '7 Hari - 84 points', 'callback_data' => 'redeem_7']
            ],
            [
                ['text' => '↩️ Kembali', 'callback_data' => 'main_menu']
            ]
        ]
    ];
    
    if ($messageId) {
        $result = editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
    } else {
        $result = sendMessageWithImage($chatId, $message, json_encode($keyboard));
    }
    
    return $result;
}

function processPointRedemption($chatId, $duration, $messageId) {
    $userPoints = getUserPoints($chatId);
    $pointsNeeded = calculatePointsNeededForDays($duration);
    
    if ($userPoints < $pointsNeeded) {
        $message = "❌ <b>Point tidak cukup!</b>\n\n";
        $message .= "Point yang dibutuhkan: <b>$pointsNeeded points</b>\n";
        $message .= "Point Anda: <b>$userPoints points</b>\n\n";
        $message .= "Silakan kumpulkan point lebih banyak dengan melakukan pembelian.";
        
        editMessageSmart($chatId, $messageId, $message, getBackButton('redeem_points'));
        return;
    }
    
    // Ask for game type
    $message = "🎮 <b>PILIH JENIS GAME</b>\n\n";
    $message .= "Anda akan menukar <b>$pointsNeeded points</b> untuk lisensi <b>$duration hari</b>\n\n";
    $message .= "Pilih jenis Free Fire:";
    
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🎮 FREE FIRE', 'callback_data' => "redeem_ff"],
                ['text' => '⚡ FREE FIRE MAX', 'callback_data' => "redeem_ffmax"]
            ],
            [
                ['text' => '↩️ Kembali', 'callback_data' => 'redeem_points']
            ]
        ]
    ];
    
    // Save state for the next step
    saveUserState($chatId, 'waiting_redeem_game', [
        'duration' => $duration,
        'points_needed' => $pointsNeeded
    ]);
    
    editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
}

function completePointRedemption($chatId, $gameType, $duration, $messageId) {
    $pointsNeeded = calculatePointsNeededForDays($duration);
    $userPoints = getUserPoints($chatId);
    
    logMessage("DEBUG: Starting point redemption - Chat: $chatId, Game: $gameType, Duration: $duration, Points Needed: $pointsNeeded, User Points: $userPoints");
    
    // Check points again
    if ($userPoints < $pointsNeeded) {
        $message = "❌ <b>Point tidak cukup!</b>\n\n";
        $message .= "Point yang dibutuhkan: <b>$pointsNeeded points</b>\n";
        $message .= "Point Anda: <b>$userPoints points</b>";
        
        logMessage("ERROR: Insufficient points for redemption - Chat: $chatId, Needed: $pointsNeeded, Has: $userPoints");
        editMessageSmart($chatId, $messageId, $message, getBackButton('redeem_points'));
        return;
    }
    
    // Generate redeem credentials
    $credentials = generateRedeemCredentials();
    $table = ($gameType == 'ff') ? 'freefire' : 'ffmax';
    
    logMessage("DEBUG: Generated credentials - Username: " . $credentials['username'] . ", Password: " . $credentials['password']);
    
    // Check if username exists, if yes regenerate
    $maxAttempts = 10;
    $attempts = 0;
    while (isUsernameExists($credentials['username'], $table) && $attempts < $maxAttempts) {
        $credentials = generateRedeemCredentials();
        $attempts++;
        logMessage("DEBUG: Username exists, regenerating... Attempt: $attempts, New Username: " . $credentials['username']);
    }
    
    if ($attempts >= $maxAttempts) {
        $message = "❌ <b>Gagal generate username unik!</b>\n\n";
        $message .= "Silakan coba lagi.";
        
        logMessage("ERROR: Failed to generate unique username after $maxAttempts attempts");
        editMessageSmart($chatId, $messageId, $message, getBackButton('redeem_points'));
        return;
    }
    
    $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
    
    // Deduct points FIRST before saving license
    logMessage("DEBUG: Attempting to redeem points - Chat: $chatId, Points: $pointsNeeded");
    if (!redeemUserPoints($chatId, $pointsNeeded, "Penukaran lisensi $duration hari")) {
        $message = "❌ <b>Gagal menukar point!</b>\n\n";
        $message .= "Terjadi kesalahan sistem. Silakan coba lagi.";
        
        logMessage("ERROR: Failed to redeem points - Chat: $chatId, Points: $pointsNeeded");
        editMessageSmart($chatId, $messageId, $message, getBackButton('redeem_points'));
        return;
    }
    
    logMessage("SUCCESS: Points redeemed successfully - Chat: $chatId, Points: $pointsNeeded");
    
    // Save to database
    logMessage("DEBUG: Attempting to save license to database - Table: $table");
    if (saveLicenseToDatabase($table, $credentials['username'], $credentials['password'], $duration, 'DIMZ1945')) {
        $expiryDate = date('d-m-Y H:i:s', strtotime("+$duration days"));
        $newUserPoints = getUserPoints($chatId);
        
        $message = "🎉 <b>PENUKARAN POINT BERHASIL!</b>\n\n";
        $message .= "Anda berhasil menukar <b>$pointsNeeded points</b>\n";
        $message .= "Untuk lisensi <b>$gameName</b> selama <b>$duration hari</b>\n\n";
        $message .= "📱 <b>AKUN ANDA:</b>\n";
        $message .= "Username: <code>" . $credentials['username'] . "</code>\n";
        $message .= "Password: <code>" . $credentials['password'] . "</code>\n";
        $message .= "Tipe Key: <b>REDEEM (AUTO RANDOM)</b>\n\n";
        $message .= "⏰ <b>MASA AKTIF:</b>\n";
        $message .= "Berlaku hingga: <b>$expiryDate WIB</b>\n\n";
        $message .= "🎮 <b>JENIS GAME:</b> $gameName\n";
        $message .= "💰 <b>SISA POINT:</b> $newUserPoints points\n\n";
        $message .= "✨ <b>Selamat bermain!</b> 🎮\n\n";
        $message .= "📁 <b>Untuk file dan tutorial instalasi:</b>\n";
        $message .= "Klik tombol '📁 File & Cara Pasang' di bawah";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📁 File & Cara Pasang', 'url' => 'https://t.me/+RY2yMHn_jts3YzA1']
                ],
                [
                    ['text' => '🎁 Tukar Lagi', 'callback_data' => 'redeem_points'],
                    ['text' => '🛒 Beli Lisensi', 'callback_data' => 'new_order']
                ],
                [
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        logMessage("SUCCESS: License created successfully - Chat: $chatId, Username: " . $credentials['username'] . ", Table: $table");
        
        // Send success message
        sendPhoto($chatId, WELCOME_IMAGE, $message, json_encode($keyboard));
        
        // Send notification to admin
        $adminMessage = "🎁 <b>PENUKARAN POINT BARU!</b>\n\n";
        $adminMessage .= "User ID: <code>$chatId</code>\n";
        $adminMessage .= "Jenis Game: <b>$gameName</b>\n";
        $adminMessage .= "Durasi: <b>$duration Hari</b>\n";
        $adminMessage .= "Tipe Key: <b>REDEEM (AUTO RANDOM)</b>\n";
        $adminMessage .= "Username: <code>" . $credentials['username'] . "</code>\n";
        $adminMessage .= "Password: <code>" . $credentials['password'] . "</code>\n";
        $adminMessage .= "Point Ditukar: <b>$pointsNeeded points</b>\n";
        $adminMessage .= "Masa Aktif: <b>$expiryDate WIB</b>\n";
        $adminMessage .= "Waktu: " . date('d-m-Y H:i:s');
        
        notifyAdmin($adminMessage);
        
    } else {
        // Refund points if failed
        logMessage("ERROR: Failed to save license, refunding points - Chat: $chatId, Points: $pointsNeeded");
        addUserPoints($chatId, $pointsNeeded, "Refund gagal penukaran");
        
        $message = "❌ <b>Gagal membuat lisensi!</b>\n\n";
        $message .= "Point telah dikembalikan. Silakan coba lagi.";
        
        editMessageSmart($chatId, $messageId, $message, getBackButton('redeem_points'));
    }
}

/**
 * HANDLE TEXT MESSAGES
 */
if (!empty($text)) {
    if (strpos($text, '/start') === 0) {
        clearUserState($chatId);
        logMessage("User $chatId started bot");
        
        $userPoints = getUserPoints($chatId);
        
        $welcomeMessage = "🎮 <b>Selamat Datang, $firstName!</b>\n\n";
        $welcomeMessage .= "✨ <b>BOT PEMBELIAN LISENSI FREE FIRE</b> ✨\n\n";
        $welcomeMessage .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
        $welcomeMessage .= "🛒 <b>Fitur yang tersedia:</b>\n";
        $welcomeMessage .= "• Beli lisensi baru (Random/Manual)\n";
        $welcomeMessage .= "• Extend masa aktif akun\n";
        $welcomeMessage .= "• Tukar point dengan lisensi gratis\n";
        $welcomeMessage .= "• Support Free Fire & Free Fire MAX\n";
        $welcomeMessage .= "• Pembayaran QRIS otomatis\n\n";
        $welcomeMessage .= "💰 <b>Harga mulai dari Rp 15.000</b>\n";
        $welcomeMessage .= "🎁 <b>Dapatkan point untuk setiap pembelian!</b>\n\n";
        $welcomeMessage .= "⏰ <b>Pembayaran otomatis terdeteksi dalam 10 menit!</b>\n\n";
        $welcomeMessage .= "Silakan pilih menu di bawah:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🛒 Beli Lisensi Baru', 'callback_data' => 'new_order']
                ],
                [
                    ['text' => '⏰ Extend Masa Aktif', 'callback_data' => 'extend_user'],
                    ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                ],
                [
                    ['text' => 'ℹ️ Bantuan', 'callback_data' => 'help']
                ]
            ]
        ];
        
        sendMessageWithImage($chatId, $welcomeMessage, json_encode($keyboard));
    }
    elseif (strpos($text, '/menu') === 0) {
        showMainMenu($chatId, "🏠 <b>Menu Utama</b>\n\nSilakan pilih menu yang diinginkan:");
    }
    elseif (strpos($text, '/points') === 0) {
        $userPoints = getUserPoints($chatId);
        $message = "💰 <b>POINT ANDA</b>\n\n";
        $message .= "Total Point: <b>$userPoints points</b>\n\n";
        $message .= "📊 <b>Cara mendapatkan point:</b>\n";
        $message .= "• Beli lisensi 1 hari = 1 point\n";
        $message .= "• Beli lisensi 3 hari = 2 point\n";
        $message .= "• Beli lisensi 7 hari = 5 point\n";
        $message .= "• Dan seterusnya...\n\n";
        $message .= "🎁 <b>Tukar point dengan lisensi gratis!</b>\n";
        $message .= "12 points = 1 hari lisensi gratis";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                ],
                [
                    ['text' => '🛒 Beli Lisensi', 'callback_data' => 'new_order']
                ],
                [
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        sendMessageWithImage($chatId, $message, json_encode($keyboard));
    }
    else {
        // Handle state-based messages (manual input for username/password)
        $userState = getUserState($chatId);
        
        if ($userState && $userState['state'] == 'waiting_manual_input') {
            if (strpos($text, '/') === 0) {
                $input = substr($text, 1);
                $parts = explode('-', $input, 2);
                
                if (count($parts) == 2) {
                    $username = trim($parts[0]);
                    $password = trim($parts[1]);
                    
                    if (empty($username) || empty($password)) {
                        sendMessageWithImage($chatId, "❌ <b>Username dan password tidak boleh kosong!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", getBackButton('new_order'));
                        exit;
                    }
                    
                    $gameType = $userState['data']['game_type'];
                    $table = ($gameType == 'ff') ? 'freefire' : 'ffmax';
                    
                    if (isUsernameExists($username, $table)) {
                        $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                        $errorMessage = "❌ <b>Username sudah digunakan di $gameName!</b>\n\n";
                        $errorMessage .= "Username <code>$username</code> sudah terdaftar di <b>$gameName</b>.\n\n";
                        $errorMessage .= "💡 <b>Tips:</b> Gunakan username yang berbeda\n\n";
                        $errorMessage .= "📝 <b>Format:</b> <code>/username-password</code>\n";
                        $errorMessage .= "🎯 <b>Contoh:</b> <code>/player123-1</code>";
                        
                        sendMessageWithImage($chatId, $errorMessage, getBackButton('new_order'));
                        exit;
                    }
                    
                    $duration = $userState['data']['duration'];
                    $amount = $GLOBALS['prices'][$duration];
                    $orderId = 'DIMZ' . time() . rand(100, 999);
                    
                    $payment = createPayment($orderId, $amount);
                    
                    if ($payment && $payment['status']) {
                        $paymentData = $payment['data'];
                        $gameName = strtoupper($gameType);
                        
                        $message = "💳 <b>PEMBAYARAN $gameName (MANUAL)</b>\n\n";
                        $message .= "Jenis: <b>$gameName</b>\n";
                        $message .= "Durasi: <b>$duration Hari</b>\n";
                        $message .= "Tipe: <b>KEY MANUAL</b>\n";
                        $message .= "Username: <code>$username</code>\n";
                        $message .= "Password: <code>$password</code>\n";
                        $message .= "Harga: <b>Rp " . number_format($amount, 0, ',', '.') . "</b>\n";
                        $message .= "Order ID: <code>$orderId</code>\n\n";
                        $message .= "📱 <b>INSTRUKSI PEMBAYARAN:</b>\n";
                        $message .= "1. Scan QR Code di bawah\n";
                        $message .= "2. Bayar sesuai amount\n";
                        $message .= "3. Pembayaran akan terdeteksi otomatis\n\n";
                        $message .= "⏰ <b>Batas Waktu: 10 MENIT</b>\n";
                        $message .= "🔄 <b>Cek Otomatis: Setiap 20 detik</b>\n";
                        $message .= "QR akan otomatis terhapus setelah 10 menit jika tidak bayar\n";
                        $message .= "Expired: " . $paymentData['expired'] . "\n\n";
                        $message .= "🚀 <b>Pembayaran akan diproses otomatis!</b>";
                        
                        $keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => '🔍 Cek Status Manual', 'callback_data' => 'check_payment']
                                ],
                                [
                                    ['text' => '❌ Batalkan Pesanan', 'callback_data' => 'cancel_order']
                                ]
                            ]
                        ];
                        
                        savePendingOrder($orderId, $chatId, $gameType, $duration, $amount, $paymentData['kode_deposit'], 'manual', $username, $password);
                        clearUserState($chatId);
                        
                        sendPhoto($chatId, $paymentData['link_qr'], $message, json_encode($keyboard));
                    } else {
                        sendMessageWithImage($chatId, "❌ Gagal membuat pembayaran. Silakan coba lagi.", getBackButton('new_order'));
                        clearUserState($chatId);
                    }
                } else {
                    sendMessageWithImage($chatId, "❌ <b>Format tidak valid!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", getBackButton('new_order'));
                }
            } else {
                sendMessageWithImage($chatId, "❌ <b>Gunakan format command!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", getBackButton('new_order'));
            }
        }
        elseif ($userState && $userState['state'] == 'waiting_extend_credentials') {
            if (strpos($text, '/') === 0) {
                $input = substr($text, 1);
                $parts = explode('-', $input, 2);
                
                if (count($parts) == 2) {
                    $username = trim($parts[0]);
                    $password = trim($parts[1]);
                    $gameType = $userState['data']['game_type'];
                    
                    $userData = getUserByUsernameAndPassword($username, $password, $gameType);
                    
                    if ($userData) {
                        resetUserErrorCount($chatId);
                        
                        saveUserState($chatId, 'waiting_extend_duration', [
                            'username' => $username,
                            'password' => $password,
                            'user_data' => $userData,
                            'game_type' => $gameType
                        ]);
                        
                        $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                        $currentExp = date('d-m-Y H:i:s', strtotime($userData['expDate']));
                        
                        $message = "✅ <b>USERNAME DAN PASSWORD COCOK!</b>\n\n";
                        $message .= "Username: <code>$username</code>\n";
                        $message .= "Jenis: <b>$gameName</b>\n";
                        $message .= "Masa Aktif Saat Ini: <b>$currentExp WIB</b>\n\n";
                        $message .= "💰 <b>Pilih Durasi Extend:</b>";
                        
                        $keyboard = [
                            'inline_keyboard' => [
                                [
                                    ['text' => '1 Hari - 15k', 'callback_data' => "extend_duration_1"],
                                    ['text' => '2 Hari - 30k', 'callback_data' => "extend_duration_2"],
                                    ['text' => '3 Hari - 40k', 'callback_data' => "extend_duration_3"]
                                ],
                                [
                                    ['text' => '4 Hari - 50k', 'callback_data' => "extend_duration_4"],
                                    ['text' => '6 Hari - 70k', 'callback_data' => "extend_duration_6"],
                                    ['text' => '8 Hari - 90k', 'callback_data' => "extend_duration_8"]
                                ],
                                [
                                    ['text' => '10 Hari - 100k', 'callback_data' => "extend_duration_10"],
                                    ['text' => '15 Hari - 150k', 'callback_data' => "extend_duration_15"]
                                ],
                                [
                                    ['text' => '20 Hari - 180k', 'callback_data' => "extend_duration_20"],
                                    ['text' => '30 Hari - 250k', 'callback_data' => "extend_duration_30"]
                                ],
                                [
                                    ['text' => '↩️ Kembali', 'callback_data' => 'extend_type_' . $gameType],
                                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                                ]
                            ]
                        ];
                        
                        sendMessageWithImage($chatId, $message, json_encode($keyboard));
                    } else {
                        $currentErrorCount = $userState['error_count'] ?? 0;
                        $newErrorCount = $currentErrorCount + 1;
                        updateUserErrorCount($chatId, $newErrorCount);
                        
                        $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                        $errorMessage = "❌ <b>Username dan Password tidak cocok di $gameName!</b>\n\n";
                        
                        if ($newErrorCount >= 2) {
                            $errorMessage .= "⚠️ <b>Anda telah 2 kali melakukan kesalahan.</b>\n";
                            $errorMessage .= "Silakan mulai ulang dari menu utama.\n\n";
                            clearUserState($chatId);
                            sendMessageWithImage($chatId, $errorMessage, getBackButton());
                        } else {
                            $errorMessage .= "Silakan coba lagi dengan username dan password yang benar:\n\n";
                            $errorMessage .= "Format: <code>/username-password</code>\n";
                            $errorMessage .= "Contoh: <code>/kambing-1</code>";
                            sendMessageWithImage($chatId, $errorMessage, getBackButton('extend_user'));
                        }
                    }
                } else {
                    sendMessageWithImage($chatId, "❌ <b>Format tidak valid!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", getBackButton('extend_user'));
                }
            } else {
                sendMessageWithImage($chatId, "❌ <b>Gunakan format command!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", getBackButton('extend_user'));
            }
        }
    }
}

/**
 * HANDLE CALLBACK QUERIES
 */
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $callbackId = $callback['id'];
    
    logMessage("Callback received: $data from $chatId");
    
    try {
        // Answer callback first
        answerCallbackQuery($callbackId);
        
        if ($data == 'main_menu') {
            clearUserState($chatId);
            showMainMenu($chatId, null, $messageId);
        }
        elseif ($data == 'new_order') {
            $message = "👋 <b>Halo!</b>\n\n";
            $message .= "Silakan pilih jenis Free Fire yang ingin Anda beli:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 FREE FIRE', 'callback_data' => 'type_ff'],
                        ['text' => '⚡ FREE FIRE MAX', 'callback_data' => 'type_ffmax']
                    ],
                    [
                        ['text' => '↩️ Kembali', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        }
        elseif ($data == 'extend_user') {
            $message = "🎮 <b>EXTEND MASA AKTIF</b>\n\n";
            $message .= "Pilih jenis Free Fire yang ingin di-extend:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎮 FREE FIRE', 'callback_data' => 'extend_type_ff'],
                        ['text' => '⚡ FREE FIRE MAX', 'callback_data' => 'extend_type_ffmax']
                    ],
                    [
                        ['text' => '↩️ Kembali', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        }
        elseif ($data == 'redeem_points') {
            showRedeemPointsMenu($chatId, $messageId);
        }
        elseif ($data == 'help') {
            $userPoints = getUserPoints($chatId);
            
            $helpMessage = "ℹ️ <b>BANTUAN</b>\n\n";
            $helpMessage .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
            $helpMessage .= "📝 <b>Cara Penggunaan:</b>\n";
            $helpMessage .= "1. Pilih 'Beli Lisensi Baru' untuk pembelian baru\n";
            $helpMessage .= "2. Pilih 'Extend Masa Aktif' untuk memperpanjang\n";
            $helpMessage .= "3. Pilih 'Tukar Point' untuk lisensi gratis\n";
            $helpMessage .= "4. Ikuti instruksi yang diberikan\n\n";
            $helpMessage .= "🔧 <b>Fitur:</b>\n";
            $helpMessage .= "• Support Free Fire & Free Fire MAX\n";
            $helpMessage .= "• Pembayaran QRIS otomatis\n";
            $helpMessage .= "• Extend masa aktif\n";
            $helpMessage .= "• Key random & manual\n";
            $helpMessage .= "• Sistem point/reward\n\n";
            $helpMessage .= "🎁 <b>Sistem Point:</b>\n";
            $helpMessage .= "• Dapatkan point dari setiap pembelian\n";
            $helpMessage .= "• 12 points = 1 hari lisensi gratis\n";
            $helpMessage .= "• Point tidak memiliki masa kedaluwarsa\n\n";
            $helpMessage .= "⏰ <b>Pembayaran Otomatis:</b>\n";
            $helpMessage .= "• QR berlaku selama 10 menit\n";
            $helpMessage .= "• Cek pembayaran otomatis setiap 20 detik\n";
            $helpMessage .= "• QR terhapus otomatis jika tidak dibayar\n";
            $helpMessage .= "• Pesan sukses tidak akan dihapus\n\n";
            $helpMessage .= "❓ <b>Pertanyaan?</b>\n";
            $helpMessage .= "Hubungi admin jika ada kendala @dimasvip1120";
            
            showMainMenu($chatId, $helpMessage, $messageId);
        }
        elseif (strpos($data, 'type_') === 0) {
            $type = str_replace('type_', '', $data);
            
            $message = "💰 <b>Pilih Durasi Lisensi " . strtoupper($type) . ":</b>\n\n";
            $message .= "Silakan pilih durasi:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '1 Hari - 15k', 'callback_data' => "duration_{$type}_1"],
                        ['text' => '2 Hari - 30k', 'callback_data' => "duration_{$type}_2"],
                        ['text' => '3 Hari - 40k', 'callback_data' => "duration_{$type}_3"]
                    ],
                    [
                        ['text' => '4 Hari - 50k', 'callback_data' => "duration_{$type}_4"],
                        ['text' => '6 Hari - 70k', 'callback_data' => "duration_{$type}_6"],
                        ['text' => '8 Hari - 90k', 'callback_data' => "duration_{$type}_8"]
                    ],
                    [
                        ['text' => '10 Hari - 100k', 'callback_data' => "duration_{$type}_10"],
                        ['text' => '15 Hari - 150k', 'callback_data' => "duration_{$type}_15"]
                    ],
                    [
                        ['text' => '20 Hari - 180k', 'callback_data' => "duration_{$type}_20"],
                        ['text' => '30 Hari - 250k', 'callback_data' => "duration_{$type}_30"]
                    ],
                    [
                        ['text' => '↩️ Kembali', 'callback_data' => 'new_order'],
                        ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        }
        elseif (strpos($data, 'duration_') === 0) {
            $parts = explode('_', $data);
            $type = $parts[1];
            $duration = $parts[2];
            
            $message = "🔑 <b>Pilih Tipe Key untuk " . strtoupper($type) . ":</b>\n\n";
            $message .= "🎲 <b>RANDOM KEY</b>\n";
            $message .= "• Username & password digenerate otomatis\n";
            $message .= "• Format: 2 huruf + 2 angka (Username), 2 angka (Password)\n\n";
            $message .= "✏️ <b>MANUAL KEY</b>\n";
            $message .= "• Input username & password manual\n";
            $message .= "• Format: <code>/username-password</code>\n\n";
            $message .= "Silakan pilih tipe key:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎲 RANDOM KEY', 'callback_data' => "keytype_{$type}_{$duration}_random"],
                        ['text' => '✏️ MANUAL KEY', 'callback_data' => "keytype_{$type}_{$duration}_manual"]
                    ],
                    [
                        ['text' => '↩️ Kembali', 'callback_data' => 'type_' . $type],
                        ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        }
        elseif (strpos($data, 'keytype_') === 0) {
            $parts = explode('_', $data);
            $type = $parts[1];
            $duration = $parts[2];
            $keyType = $parts[3];
            
            if ($keyType == 'random') {
                $amount = $GLOBALS['prices'][$duration];
                $orderId = 'DIMZ' . time() . rand(100, 999);
                
                $payment = createPayment($orderId, $amount);
                
                if ($payment && $payment['status']) {
                    $paymentData = $payment['data'];
                    
                    $message = "💳 <b>PEMBAYARAN " . strtoupper($type) . " (RANDOM)</b>\n\n";
                    $message .= "Jenis: <b>" . strtoupper($type) . "</b>\n";
                    $message .= "Durasi: <b>$duration Hari</b>\n";
                    $message .= "Tipe: <b>KEY RANDOM</b>\n";
                    $message .= "Harga: <b>Rp " . number_format($amount, 0, ',', '.') . "</b>\n";
                    $message .= "Order ID: <code>$orderId</code>\n\n";
                    $message .= "📱 <b>INSTRUKSI PEMBAYARAN:</b>\n";
                    $message .= "1. Scan QR Code di bawah\n";
                    $message .= "2. Bayar sesuai amount\n";
                    $message .= "3. Pembayaran akan terdeteksi otomatis\n\n";
                    $message .= "⏰ <b>Batas Waktu: 10 MENIT</b>\n";
                    $message .= "🔄 <b>Cek Otomatis: Setiap 20 detik</b>\n";
                    $message .= "QR akan otomatis terhapus setelah 10 menit jika tidak bayar\n";
                    $message .= "Expired: " . $paymentData['expired'] . "\n\n";
                    $message .= "🚀 <b>Pembayaran akan diproses otomatis!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Cek Status Manual', 'callback_data' => 'check_payment']
                            ],
                            [
                                ['text' => '❌ Batalkan Pesanan', 'callback_data' => 'cancel_order']
                            ]
                        ]
                    ];
                    
                    savePendingOrder($orderId, $chatId, $type, $duration, $amount, $paymentData['kode_deposit'], 'random');
                    
                    sendPhoto($chatId, $paymentData['link_qr'], $message, json_encode($keyboard));
                } else {
                    $errorMsg = "❌ Gagal membuat pembayaran. Silakan coba lagi.";
                    editMessageSmart($chatId, $messageId, $errorMsg, getBackButton('type_' . $type));
                }
            } elseif ($keyType == 'manual') {
                saveUserState($chatId, 'waiting_manual_input', [
                    'game_type' => $type,
                    'duration' => $duration
                ]);
                
                $instruction = "✏️ <b>MASUKKAN USERNAME & PASSWORD</b>\n\n";
                $instruction .= "📝 <b>Gunakan format:</b>\n";
                $instruction .= "<code>/username-password</code>\n\n";
                $instruction .= "🎯 <b>Contoh:</b>\n";
                $instruction .= "<code>/kambing-1</code>\n";
                $instruction .= "<code>/player-123</code>\n";
                $instruction .= "<code>/gamer-99</code>\n\n";
                $instruction .= "➡️ <b>Username</b> sebelum tanda minus (-)\n";
                $instruction .= "➡️ <b>Password</b> setelah tanda minus (-)";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '↩️ Kembali', 'callback_data' => 'duration_' . $type . '_' . $duration],
                            ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                
                editMessageSmart($chatId, $messageId, $instruction, json_encode($keyboard));
            }
        }
        elseif (strpos($data, 'extend_type_') === 0) {
            $gameType = str_replace('extend_type_', '', $data);
            $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
            
            saveUserState($chatId, 'waiting_extend_credentials', [
                'game_type' => $gameType
            ]);
            
            $message = "✏️ <b>EXTEND $gameName</b>\n\n";
            $message .= "Masukkan <b>USERNAME dan PASSWORD</b> yang ingin di-extend:\n\n";
            $message .= "📝 <b>Format:</b>\n";
            $message .= "<code>/username-password</code>\n\n";
            $message .= "🎯 <b>Contoh:</b>\n";
            $message .= "<code>/kambing-1</code>\n";
            $message .= "<code>/player-123</code>\n\n";
            $message .= "⚠️ <b>Pastikan username dan password terdaftar di $gameName</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '↩️ Kembali', 'callback_data' => 'extend_user'],
                        ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        }
        elseif (strpos($data, 'extend_duration_') === 0) {
            $duration = str_replace('extend_duration_', '', $data);
            $userState = getUserState($chatId);
            
            if ($userState && $userState['state'] == 'waiting_extend_duration') {
                $username = $userState['data']['username'];
                $password = $userState['data']['password'];
                $userData = $userState['data']['user_data'];
                $gameType = $userState['data']['game_type'];
                $amount = $GLOBALS['prices'][$duration];
                
                $orderId = 'EXTEND' . time() . rand(100, 999);
                
                $payment = createPayment($orderId, $amount);
                
                if ($payment && $payment['status']) {
                    $paymentData = $payment['data'];
                    $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                    $currentExp = date('d-m-Y H:i:s', strtotime($userData['expDate']));
                    $newExpDate = date('d-m-Y H:i:s', strtotime($userData['expDate'] . " +$duration days"));
                    
                    $message = "💳 <b>EXTEND $gameName</b>\n\n";
                    $message .= "Username: <code>$username</code>\n";
                    $message .= "Password: <code>$password</code>\n";
                    $message .= "Jenis: <b>$gameName</b>\n";
                    $message .= "Durasi: <b>$duration Hari</b>\n";
                    $message .= "Harga: <b>Rp " . number_format($amount, 0, ',', '.') . "</b>\n";
                    $message .= "Masa Aktif Saat Ini: <b>$currentExp WIB</b>\n";
                    $message .= "Masa Aktif Baru: <b>$newExpDate WIB</b>\n";
                    $message .= "Order ID: <code>$orderId</code>\n\n";
                    $message .= "📱 <b>INSTRUKSI PEMBAYARAN:</b>\n";
                    $message .= "1. Scan QR Code di bawah\n";
                    $message .= "2. Bayar sesuai amount\n";
                    $message .= "3. Pembayaran akan terdeteksi otomatis\n\n";
                    $message .= "⏰ <b>Batas Waktu: 10 MENIT</b>\n";
                    $message .= "🔄 <b>Cek Otomatis: Setiap 20 detik</b>\n";
                    $message .= "QR akan otomatis terhapus setelah 10 menit jika tidak bayar\n";
                    $message .= "Expired: " . $paymentData['expired'] . "\n\n";
                    $message .= "🚀 <b>Pembayaran akan diproses otomatis!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Cek Status Manual', 'callback_data' => 'check_extend']
                            ],
                            [
                                ['text' => '❌ Batalkan Pesanan', 'callback_data' => 'cancel_order']
                            ]
                        ]
                    ];
                    
                    savePendingOrder($orderId, $chatId, $gameType, $duration, $amount, $paymentData['kode_deposit'], 'extend', $username, $password);
                    clearUserState($chatId);
                    
                    sendPhoto($chatId, $paymentData['link_qr'], $message, json_encode($keyboard));
                } else {
                    editMessageSmart($chatId, $messageId, "❌ Gagal membuat pembayaran extend. Silakan coba lagi.", getBackButton('extend_user'));
                    clearUserState($chatId);
                }
            }
        }
        // Point redemption callbacks
        elseif (strpos($data, 'redeem_') === 0 && is_numeric(str_replace('redeem_', '', $data))) {
            $duration = str_replace('redeem_', '', $data);
            processPointRedemption($chatId, $duration, $messageId);
        }
        elseif ($data == 'redeem_ff' || $data == 'redeem_ffmax') {
            $gameType = ($data == 'redeem_ff') ? 'ff' : 'ffmax';
            $userState = getUserState($chatId);
            
            if ($userState && $userState['state'] == 'waiting_redeem_game') {
                $duration = $userState['data']['duration'];
                $pointsNeeded = $userState['data']['points_needed'];
                
                logMessage("DEBUG: Complete redemption - Game: $gameType, Duration: $duration, Chat: $chatId");
                
                completePointRedemption($chatId, $gameType, $duration, $messageId);
                clearUserState($chatId);
            } else {
                editMessageSmart($chatId, $messageId, "❌ <b>Sesi telah berakhir!</b>\n\nSilakan mulai ulang dari menu penukaran point.", getBackButton('redeem_points'));
            }
        }
        elseif ($data == 'check_payment') {
            $order = getPendingOrder($chatId);
            
            if ($order) {
                $orderTime = strtotime($order['created_at']);
                $currentTime = time();
                $timeDiff = $currentTime - $orderTime;
                
                if ($timeDiff > ORDER_TIMEOUT) {
                    updateOrderStatus($order['deposit_code'], 'expired');
                    editMessageSmart($chatId, $messageId, "❌ <b>Pesanan telah expired!</b>\n\nPembayaran tidak dilakukan dalam waktu 10 menit.\n\nSilakan buat pesanan baru.", getBackButton('new_order'));
                    exit;
                }
                
                $paymentStatus = checkPaymentStatus($order['deposit_code']);
                
                if ($paymentStatus) {
                    // Process successful payment
                    if ($order['key_type'] == 'extend') {
                        if (extendUserLicense($order['manual_username'], $order['manual_password'], $order['duration'], $order['game_type'])) {
                            $userData = getUserByUsernameAndPassword($order['manual_username'], $order['manual_password'], $order['game_type']);
                            
                            if ($userData) {
                                $newExpDate = date('d-m-Y H:i:s', strtotime($userData['expDate']));
                                sendExtendSuccess($chatId, $userData, $order['duration'], $newExpDate);
                                updateOrderStatus($order['deposit_code'], 'completed');
                            }
                        }
                    } else {
                        if ($order['key_type'] == 'manual') {
                            $credentials = [
                                'username' => $order['manual_username'],
                                'password' => $order['manual_password']
                            ];
                        } else {
                            $credentials = generateRandomCredentials();
                        }
                        
                        $table = ($order['game_type'] == 'ff') ? 'freefire' : 'ffmax';
                        
                        if (saveLicenseToDatabase($table, $credentials['username'], $credentials['password'], $order['duration'], MERCHANT_CODE)) {
                            sendLicenseToUser($chatId, $order['game_type'], $order['duration'], $credentials, $order['key_type']);
                            updateOrderStatus($order['deposit_code'], 'completed');
                        }
                    }
                } else {
                    $remainingTime = ORDER_TIMEOUT - $timeDiff;
                    $remainingMinutes = floor($remainingTime / 60);
                    $remainingSeconds = $remainingTime % 60;
                    
                    $statusMessage = "⏳ <b>Status Pembayaran: PENDING</b>\n\n";
                    $statusMessage .= "Pembayaran Anda masih dalam proses.\n\n";
                    $statusMessage .= "⏰ <b>Sisa Waktu:</b> {$remainingMinutes}m {$remainingSeconds}s\n";
                    $statusMessage .= "🔄 <b>Cek otomatis setiap 20 detik</b>\n\n";
                    $statusMessage .= "Silakan tunggu beberapa saat dan coba lagi.";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 Cek Lagi', 'callback_data' => 'check_payment']
                            ],
                            [
                                ['text' => '❌ Batalkan', 'callback_data' => 'cancel_order']
                            ]
                        ]
                    ];
                    
                    editMessageSmart($chatId, $messageId, $statusMessage, json_encode($keyboard));
                }
            } else {
                editMessageSmart($chatId, $messageId, "❌ Tidak ada pesanan pending ditemukan.", getBackButton('new_order'));
            }
        }
        elseif ($data == 'check_extend') {
            $order = getPendingOrder($chatId);
            
            if ($order && $order['key_type'] == 'extend') {
                $orderTime = strtotime($order['created_at']);
                $currentTime = time();
                $timeDiff = $currentTime - $orderTime;
                
                if ($timeDiff > ORDER_TIMEOUT) {
                    updateOrderStatus($order['deposit_code'], 'expired');
                    editMessageSmart($chatId, $messageId, "❌ <b>Pesanan extend telah expired!</b>\n\nPembayaran tidak dilakukan dalam waktu 10 menit.", getBackButton('extend_user'));
                    exit;
                }
                
                $paymentStatus = checkPaymentStatus($order['deposit_code']);
                
                if ($paymentStatus) {
                    if (extendUserLicense($order['manual_username'], $order['manual_password'], $order['duration'], $order['game_type'])) {
                        $userData = getUserByUsernameAndPassword($order['manual_username'], $order['manual_password'], $order['game_type']);
                        
                        if ($userData) {
                            $newExpDate = date('d-m-Y H:i:s', strtotime($userData['expDate']));
                            sendExtendSuccess($chatId, $userData, $order['duration'], $newExpDate);
                            updateOrderStatus($order['deposit_code'], 'completed');
                        }
                    }
                } else {
                    $remainingTime = ORDER_TIMEOUT - $timeDiff;
                    $remainingMinutes = floor($remainingTime / 60);
                    $remainingSeconds = $remainingTime % 60;
                    
                    $statusMessage = "⏳ <b>Status Extend: PENDING</b>\n\n";
                    $statusMessage .= "Pembayaran extend masih dalam proses.\n\n";
                    $statusMessage .= "⏰ <b>Sisa Waktu:</b> {$remainingMinutes}m {$remainingSeconds}s\n";
                    $statusMessage .= "🔄 <b>Cek otomatis setiap 20 detik</b>\n\n";
                    $statusMessage .= "Silakan tunggu beberapa saat dan coba lagi.";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔄 Cek Lagi', 'callback_data' => 'check_extend']
                            ],
                            [
                                ['text' => '❌ Batalkan', 'callback_data' => 'cancel_order']
                            ]
                        ]
                    ];
                    
                    editMessageSmart($chatId, $messageId, $statusMessage, json_encode($keyboard));
                }
            }
        }
        elseif ($data == 'cancel_order') {
            $order = getPendingOrder($chatId);
            if ($order) {
                updateOrderStatus($order['deposit_code'], 'cancelled');
            }
            clearUserState($chatId);
            editMessageSmart($chatId, $messageId, "❌ Pesanan dibatalkan.", getBackButton());
        }
        
    } catch (Exception $e) {
        logMessage("Error processing callback: " . $e->getMessage());
        sendMessageWithImage($chatId, "❌ <b>Terjadi kesalahan!</b>\n\nSilakan coba lagi atau gunakan menu /start", getBackButton());
    }
}

logMessage("Update processing completed for $chatId");
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Webhook processed successfully']);
?>
