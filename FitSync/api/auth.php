<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    match ($action) {
        'register' => handleRegister(),
        'login'    => handleLogin(),
        'logout'   => handleLogout(),
        'me'       => handleMe(),
        default    => jsonError('Ação inválida.', 404),
    };
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno no servidor.']);
    exit;
}

// ====================== CADASTRO ======================
function handleRegister(): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name     = sanitize($body['name'] ?? '');
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    if (strlen($name) < 2)    jsonError('Nome deve ter pelo menos 2 caracteres.');
    if (!isValidEmail($email)) jsonError('E-mail inválido.');
    if (strlen($password) < 6) jsonError('Senha deve ter pelo menos 6 caracteres.');

    // Verifica se conta já existe
    $existing = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        jsonError('Este e-mail já está cadastrado. Use outro ou faça login.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $id = Database::insert(
        'INSERT INTO users (name, email, password_hash, provider, daily_cal, daily_prot, daily_carb, daily_fat)
         VALUES (?, ?, ?, "email", 2000, 150, 250, 65)',
        [$name, $email, $hash]
    );

    $user = Database::fetchOne(
        'SELECT id, name, email, avatar_url, daily_cal, daily_prot, daily_carb, daily_fat 
         FROM users WHERE id = ?',
        [$id]
    );

    setUserSession($user);
    jsonResponse(['success' => true, 'message' => 'Conta criada com sucesso!']);
}

// ====================== LOGIN ======================
function handleLogin(): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    if (!$email || !$password) {
        jsonError('Preencha e-mail e senha.');
    }

    $user = Database::fetchOne(
        'SELECT id, name, email, password_hash, avatar_url, provider, daily_cal, daily_prot, daily_carb, daily_fat 
         FROM users WHERE email = ?',
        [$email]
    );

    if (!$user || $user['provider'] !== 'email') {
        jsonError('E-mail ou senha incorretos.');
    }

    if (!password_verify($password, $user['password_hash'] ?? '')) {
        jsonError('E-mail ou senha incorretos.');   // Mensagem genérica por segurança
    }

    unset($user['password_hash']);
    setUserSession($user);

    jsonResponse(['success' => true, 'message' => 'Login realizado com sucesso!']);
}

// ====================== OUTRAS FUNÇÕES ======================
function handleLogout(): never {
    session_unset();
    session_destroy();
    jsonResponse(['success' => true]);
}

function handleMe(): never {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['user' => null]);
    }
    $user = Database::fetchOne(
        'SELECT id, name, email, avatar_url, daily_cal, daily_prot, daily_carb, daily_fat 
         FROM users WHERE id = ?',
        [$_SESSION['user_id']]
    );
    jsonResponse(['user' => $user]);
}

function setUserSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
}