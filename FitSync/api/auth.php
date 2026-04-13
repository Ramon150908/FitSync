<?php
require_once __DIR__ . '/../bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'register':
            handleRegister();
            break;
        case 'login':
            handleLogin();
            break;
        case 'logout':
            handleLogout();
            break;
        default:
            jsonError('Ação inválida.', 404);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
    exit;
}

// ====================== CADASTRO ======================
function handleRegister(): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $name     = trim($body['name'] ?? '');
    $email    = strtolower(trim($body['email'] ?? ''));
    $password = $body['password'] ?? '';

    if (strlen($name) < 2)    jsonError('Nome deve ter pelo menos 2 caracteres.');
    if (!isValidEmail($email)) jsonError('E-mail inválido.');
    if (strlen($password) < 6) jsonError('Senha deve ter pelo menos 6 caracteres.');

    // Verifica se e-mail já existe
    $existing = Database::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        jsonError('Este e-mail já está cadastrado.');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $id = Database::insert(
            'INSERT INTO users (name, email, password_hash, provider, daily_cal, daily_prot, daily_carb, daily_fat)
             VALUES (?, ?, ?, "email", 2000, 150, 250, 65)',
            [$name, $email, $hash]
        );

        $user = Database::fetchOne(
            'SELECT id, name, email, avatar_url FROM users WHERE id = ?',
            [$id]
        );

        setUserSession($user);

        jsonResponse(['success' => true, 'message' => 'Conta criada com sucesso!']);

    } catch (PDOException $e) {
        jsonError('Erro ao salvar no banco: ' . $e->getMessage());
    }
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
        'SELECT id, name, email, password_hash, avatar_url, provider 
         FROM users WHERE email = ?',
        [$email]
    );

    if (!$user || $user['provider'] !== 'email' || empty($user['password_hash'])) {
        jsonError('E-mail ou senha incorretos.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        jsonError('E-mail ou senha incorretos.');
    }

    unset($user['password_hash']);
    setUserSession($user);

    jsonResponse(['success' => true, 'message' => 'Login realizado com sucesso!']);
}

// ====================== LOGOUT ======================
function handleLogout(): never {
    session_unset();
    session_destroy();
    jsonResponse(['success' => true]);
}

function setUserSession(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_email'] = $user['email'];
}