<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$query = trim($body['query'] ?? '');
$qty   = (float)($body['qty'] ?? 0);

if (!$query) {
    jsonError('Informe o alimento.');
}

// Versão simples sem IA externa - tenta extrair informações básicas
$foodName = $query;
$quantity = $qty > 0 ? $qty : 100;

// Tenta detectar quantidade no texto (ex: "200g de frango")
if (preg_match('/(\d+)\s*(g|gramas|ml)/i', $query, $matches)) {
    $quantity = (float)$matches[1];
    $foodName = trim(str_replace($matches[0], '', $query));
}

// Busca no banco local primeiro
$results = Database::fetchAll(
    'SELECT name, cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g 
     FROM foods 
     WHERE name LIKE ? 
     LIMIT 1',
    ['%' . $foodName . '%']
);

if (!empty($results)) {
    $food = $results[0];
    $factor = $quantity / 100;

    $response = [
        'name'  => $food['name'],
        'qty'   => round($quantity, 1),
        'unit'  => 'g',
        'cal'   => round($food['cal_per_100g'] * $factor, 1),
        'prot'  => round($food['prot_per_100g'] * $factor, 1),
        'carb'  => round($food['carb_per_100g'] * $factor, 1),
        'fat'   => round($food['fat_per_100g'] * $factor, 1),
        'fiber' => null,
        'sugar' => null,
        'sodium'=> null,
        'sat_fat'=> null,
    ];
} else {
    // Fallback genérico quando não encontra no banco
    $response = [
        'name'  => ucfirst($foodName),
        'qty'   => round($quantity, 1),
        'unit'  => 'g',
        'cal'   => round(150 * ($quantity / 100), 1),   // valor médio estimado
        'prot'  => round(8 * ($quantity / 100), 1),
        'carb'  => round(15 * ($quantity / 100), 1),
        'fat'   => round(6 * ($quantity / 100), 1),
        'fiber' => null,
        'sugar' => null,
        'sodium'=> null,
        'sat_fat'=> null,
    ];
}

jsonResponse(['food' => $response]);