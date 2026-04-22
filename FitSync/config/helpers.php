<?php
// ============================================================
//  FitSync — Funções Utilitárias (v2 - Melhorado)
// ============================================================

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function jsonError(string $message, int $status = 400, ?array $details = null): never {
    $response = ['error' => $message];
    if ($details) {
        $response['details'] = $details;
    }
    jsonResponse($response, $status);
}

function requireAuth(): array {
    if (empty($_SESSION['user_id'])) {
        jsonError('Não autenticado.', 401);
    }
    return [
        'id'    => (int)$_SESSION['user_id'],
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
    ];
}

function sanitize(string $str): string {
    return trim(htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validar data no formato YYYY-MM-DD
function isValidDate(string $date): bool {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
}

// Calcular nutrientes para uma quantidade específica
function scaleNutrients(array $food, float $qtyG): array {
    $factor = $qtyG / 100;
    return [
        'cal'     => round($food['cal_per_100g']     * $factor, 1),
        'prot'    => round($food['prot_per_100g']    * $factor, 1),
        'carb'    => round($food['carb_per_100g']    * $factor, 1),
        'fat'     => round($food['fat_per_100g']     * $factor, 1),
        'fiber'   => isset($food['fiber_per_100g']) && $food['fiber_per_100g'] !== null ? round($food['fiber_per_100g']   * $factor, 1) : null,
        'sugar'   => isset($food['sugar_per_100g']) && $food['sugar_per_100g'] !== null ? round($food['sugar_per_100g']   * $factor, 1) : null,
        'sodium'  => isset($food['sodium_per_100g']) && $food['sodium_per_100g'] !== null ? round($food['sodium_per_100g']  * $factor, 1) : null,
        'sat_fat' => isset($food['sat_fat_per_100g']) && $food['sat_fat_per_100g'] !== null ? round($food['sat_fat_per_100g'] * $factor, 1) : null,
    ];
}

// HTTP GET com timeout e tratamento de erro
function httpGet(string $url, array $headers = [], int $timeout = 10): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'FitSync/2.0',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($body === false || $code !== 200) {
        error_log("HTTP GET failed: $url - $error (Code: $code)");
        return null;
    }
    
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $decoded;
}

// HTTP POST com timeout e tratamento de erro
function httpPost(string $url, array $payload, array $headers = [], int $timeout = 30): ?array {
    $ch = curl_init($url);
    $jsonPayload = json_encode($payload);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonPayload,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Content-Length: ' . strlen($jsonPayload)], $headers),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'FitSync/2.0',
    ]);
    
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($body === false) {
        error_log("HTTP POST failed: $url - $error");
        return null;
    }
    
    if ($code !== 200 && $code !== 201) {
        error_log("HTTP POST returned code $code: $url");
        return null;
    }
    
    $decoded = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }
    
    return $decoded;
}

// Gerar CSRF token
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verificar CSRF token
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Log de segurança
function securityLog(string $event, array $details = []): void {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? null,
        'details' => $details
    ];
    error_log(json_encode($logEntry));
}

// Rate limiting simples
function checkRateLimit(string $key, int $limit = 10, int $window = 60): bool {
    $storageKey = "rate_limit_{$key}";
    $now = time();
    
    if (!isset($_SESSION[$storageKey])) {
        $_SESSION[$storageKey] = ['count' => 1, 'first_request' => $now];
        return true;
    }
    
    $data = $_SESSION[$storageKey];
    
    if ($now - $data['first_request'] > $window) {
        $_SESSION[$storageKey] = ['count' => 1, 'first_request' => $now];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$storageKey]['count']++;
    return true;
}