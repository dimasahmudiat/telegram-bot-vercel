<?php
// set-webhook.php - Script to set Telegram webhook for Vercel deployment
require_once 'config.php';

header('Content-Type: application/json');

// Get the domain from environment or request
$domain = $_ENV['VERCEL_URL'] ?? $_SERVER['HTTP_HOST'] ?? 'your-vercel-domain.vercel.app';

// Ensure https protocol
if (!str_starts_with($domain, 'http')) {
    $domain = 'https://' . $domain;
}

$webhookUrl = $domain . '/webhook';

// Set webhook
$telegramApiUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook";
$postData = [
    'url' => $webhookUrl,
    'max_connections' => 40,
    'allowed_updates' => ['message', 'callback_query']
];

$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($postData)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($telegramApiUrl, false, $context);
$response = json_decode($result, true);

// Log the result
logMessage("Webhook setup attempt: " . $result);

if ($response && $response['ok']) {
    echo json_encode([
        'success' => true,
        'message' => 'Webhook berhasil diatur',
        'webhook_url' => $webhookUrl,
        'response' => $response
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mengatur webhook',
        'webhook_url' => $webhookUrl,
        'response' => $response,
        'error' => $response['description'] ?? 'Unknown error'
    ]);
}
?>
