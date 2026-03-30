<?php
require_once __DIR__ . '/../bootstrap.php';
// ... resto do código permanece igual
// ============================================================
//  FitSync — Google OAuth Callback
//  URI configurado no Google Console:
//  http://localhost/fitsync-php/auth/google_callback.php
// ============================================================

// ── Passo 1: Gerar URL de autorização ─────────────────────────
// Chamado pelo botão "Entrar com Google" no frontend
if (isset($_GET['init'])) {
    $state  = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// ── Passo 2: Processar callback do Google ─────────────────────
$code  = $_GET['code']  ?? '';
$state = $_GET['state'] ?? '';

// Validar state (CSRF)
if (!$code || $state !== ($_SESSION['oauth_state'] ?? '')) {
    header('Location: ' . APP_URL . '/login.php?error=oauth_state');
    exit;
}

unset($_SESSION['oauth_state']);

// Trocar code por access_token
$tokenData = httpPost('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (empty($tokenData['access_token'])) {
    header('Location: ' . APP_URL . '/login.php?error=oauth_token');
    exit;
}

// Buscar dados do usuário
$googleUser = httpGet(
    'https://www.googleapis.com/oauth2/v3/userinfo',
    ['Authorization: Bearer ' . $tokenData['access_token']]
);

if (empty($googleUser['sub'])) {
    header('Location: ' . APP_URL . '/login.php?error=oauth_user');
    exit;
}

// ── Passo 3: Upsert do usuário no banco ───────────────────────
$existing = Database::fetchOne(
    'SELECT id, name, email, avatar_url, daily_cal, daily_prot, daily_carb, daily_fat FROM users WHERE google_id = ? OR email = ?',
    [$googleUser['sub'], $googleUser['email']]
);

if ($existing) {
    // Atualiza dados do Google
    Database::query(
        'UPDATE users SET google_id = ?, avatar_url = ?, provider = "google", name = ? WHERE id = ?',
        [$googleUser['sub'], $googleUser['picture'] ?? null, $googleUser['name'], $existing['id']]
    );
    $userId = $existing['id'];
} else {
    // Novo usuário via Google
    $userId = Database::insert(
        'INSERT INTO users (google_id, name, email, avatar_url, provider) VALUES (?, ?, ?, ?, "google")',
        [$googleUser['sub'], $googleUser['name'], $googleUser['email'], $googleUser['picture'] ?? null]
    );
}

$user = Database::fetchOne(
    'SELECT id, name, email, avatar_url, daily_cal, daily_prot, daily_carb, daily_fat FROM users WHERE id = ?',
    [$userId]
);

// Salva sessão
session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['name'];
$_SESSION['user_email'] = $user['email'];

header('Location: ' . APP_URL . '/index.php');
exit;

