<?php
// ============================================================
//  FitSync — Funções Utilitárias
// ============================================================

function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonError(string $message, int $status = 400): never {
    jsonResponse(['error' => $message], $status);
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
    return trim(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Calcular nutrientes para uma quantidade específica
function scaleNutrients(array $food, float $qtyG): array {
    $factor = $qtyG / 100;
    return [
        'cal'     => round($food['cal_per_100g']     * $factor, 1),
        'prot'    => round($food['prot_per_100g']    * $factor, 1),
        'carb'    => round($food['carb_per_100g']    * $factor, 1),
        'fat'     => round($food['fat_per_100g']     * $factor, 1),
        'fiber'   => $food['fiber_per_100g']   !== null ? round($food['fiber_per_100g']   * $factor, 1) : null,
        'sugar'   => $food['sugar_per_100g']   !== null ? round($food['sugar_per_100g']   * $factor, 1) : null,
        'sodium'  => $food['sodium_per_100g']  !== null ? round($food['sodium_per_100g']  * $factor, 1) : null,
        'sat_fat' => $food['sat_fat_per_100g'] !== null ? round($food['sat_fat_per_100g'] * $factor, 1) : null,
    ];
}

// HTTP GET simples com cURL
function httpGet(string $url, array $headers = []): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'FitSync/1.0',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) return null;
    return json_decode($body, true);
}

// HTTP POST com cURL
function httpPost(string $url, array $payload, array $headers = []): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) return null;
    return json_decode($body, true);
}
