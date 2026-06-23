<?php
/**
 * Google OAuth Callback
 * Handles Google login/registration
 */
require_once __DIR__ . '/../includes/db.php';

$code = $_GET['code'] ?? '';

if (!$code) {
    header('Location: login.php?error=no_code');
    exit;
}

$clientId = getSetting('google_client_id');
$clientSecret = getSetting('google_client_secret');

if (!$clientId || !$clientSecret) {
    header('Location: login.php?error=google_not_configured');
    exit;
}

// Exchange code for token
$redirectUri = SITE_URL . '/auth/google-callback.php';
$tokenUrl = 'https://oauth2.googleapis.com/token';

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    file_put_contents('/tmp/google_oauth_debug.log', date('Y-m-d H:i:s') . " cURL error: $curlError\n", FILE_APPEND);
    header('Location: login.php?error=curl_error');
    exit;
}

if ($httpCode !== 200) {
    file_put_contents('/tmp/google_oauth_debug.log', date('Y-m-d H:i:s') . " Token exchange failed: HTTP $httpCode, Response: $response\n", FILE_APPEND);
    header('Location: login.php?error=token_exchange_failed');
    exit;
}

$data = json_decode($response, true);
$accessToken = $data['access_token'] ?? '';

if (!$accessToken) {
    file_put_contents('/tmp/google_oauth_debug.log', date('Y-m-d H:i:s') . " No access token in response: $response\n", FILE_APPEND);
    header('Location: login.php?error=no_access_token');
    exit;
}

// Get user info from Google
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($response, true);
$googleId = $googleUser['id'] ?? '';
$email = $googleUser['email'] ?? '';
$name = $googleUser['name'] ?? '';
$avatar = $googleUser['picture'] ?? '';

if (!$googleId || !$email) {
    file_put_contents('/tmp/google_oauth_debug.log', date('Y-m-d H:i:s') . " Userinfo failed: $response\n", FILE_APPEND);
    header('Location: login.php?error=userinfo_failed');
    exit;
}

try {
    $db = getDB();

    // Check if user exists by Google ID
    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if ($user) {
        // Existing Google user - login
        $userId = $user['id'];
    } else {
        // Check if email already registered (without Google)
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existingUser = $stmt->fetch();

        if ($existingUser) {
            // Link Google account to existing user
            $stmt = $db->prepare("UPDATE users SET google_id = ?, avatar = COALESCE(NULLIF(?, ''), avatar) WHERE id = ?");
            $stmt->execute([$googleId, $avatar, $existingUser['id']]);
            $userId = $existingUser['id'];
        } else {
            // Create new user
            $stmt = $db->prepare("INSERT INTO users (email, google_id, store_name, avatar) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $googleId, $name, $avatar]);
            $userId = $db->lastInsertId();
        }
    }

    // Create session
    $token = generateToken();
    $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);

    $stmt = $db->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expires]);

    setcookie('auth_token', $token, time() + SESSION_LIFETIME, '/', '', true, true);

    // Update last login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$userId]);
    logActivity($userId, 'google_login', 'Google login');

    header('Location: /');
    exit;

} catch (Exception $e) {
    file_put_contents('/tmp/google_oauth_debug.log', date('Y-m-d H:i:s') . " DB Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    header('Location: login.php?error=system_error');
    exit;
}
