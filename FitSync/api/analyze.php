<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$query = trim($body['query'] ?? '');
$qty   = max((float)($body['qty'] ?? 100), 1);

if (!$query) {
    jsonError('Informe o alimento ou refeição para analisar.');
}

// ====================== 1. PRIMEIRO: Busca no banco local ======================
$foodName = preg_replace('/\d+\s*(g|gramas|ml|unidade)s?/i', '', $query);
$foodName = trim($foodName);

$local = Database::fetchOne(
    'SELECT name, cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g 
     FROM foods 
     WHERE name LIKE ? 
     LIMIT 1',
    ['%' . $foodName . '%']
);

if ($local) {
    $factor = $qty / 100;
    $response = [
        'name'    => $local['name'],
        'qty'     => round($qty, 1),
        'unit'    => 'g',
        'cal'     => round($local['cal_per_100g'] * $factor, 1),
        'prot'    => round($local['prot_per_100g'] * $factor, 1),
        'carb'    => round($local['carb_per_100g'] * $factor, 1),
        'fat'     => round($local['fat_per_100g'] * $factor, 1),
        'fiber'   => null,
        'sugar'   => null,
        'sodium'  => null,
        'sat_fat' => null,
        'source'  => 'local'
    ];
    jsonResponse(['food' => $response]);
}

// ====================== 2. TENTA IA REAL (com prompt muito melhor) ======================
$aiResult = null;

if (defined('OPENAI_API_KEY') && strpos(OPENAI_API_KEY, 'sk-') === 0) {
    $aiResult = callOpenAI($query, $qty);
}

if (!$aiResult && defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
    $aiResult = callGemini($query, $qty);   // podemos adicionar depois
}

if ($aiResult) {
    jsonResponse(['food' => $aiResult]);
}

// ====================== 3. ÚLTIMO FALLBACK ======================
$response = [
    'name'    => ucfirst(trim($query)),
    'qty'     => round($qty, 1),
    'unit'    => 'g',
    'cal'     => round(135 * ($qty / 100), 1),
    'prot'    => round(7 * ($qty / 100), 1),
    'carb'    => round(16 * ($qty / 100), 1),
    'fat'     => round(5 * ($qty / 100), 1),
    'fiber'   => null,
    'sugar'   => null,
    'sodium'  => null,
    'sat_fat' => null,
    'source'  => 'estimate'
];

jsonResponse(['food' => $response]);

// ====================== FUNÇÃO OPENAI (melhorada) ======================
function callOpenAI(string $query, float $qty): ?array
{
    $prompt = "Você é um nutricionista brasileiro especialista na Tabela TACO, USDA e alimentos brasileiros comuns.

Descreva a seguinte refeição ou alimento: \"{$query}\"
Quantidade informada: {$qty} gramas.

Responda **exclusivamente** com um objeto JSON válido, sem explicações, sem ```json:

{
  \"name\": \"Nome claro e natural do alimento/refeição\",
  \"qty\": {$qty},
  \"unit\": \"g\",
  \"cal\": número inteiro ou decimal,
  \"prot\": número,
  \"carb\": número,
  \"fat\": número,
  \"fiber\": número ou null,
  \"sugar\": número ou null,
  \"sodium\": número ou null,
  \"sat_fat\": número ou null
}

Seja preciso. Para refeições compostas some os componentes de forma realista.";

    $payload = [
        'model'       => OPENAI_MODEL ?? 'gpt-4o-mini',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.1,
        'max_tokens'  => 350,
    ];

    $result = httpPost('https://api.openai.com/v1/chat/completions', $payload, [
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'Content-Type: application/json'
    ]);

    if (empty($result['choices'][0]['message']['content'])) return null;

    $text = trim($result['choices'][0]['message']['content']);
    $text = preg_replace('/```json|```/', '', $text);

    $data = json_decode($text, true);

    return is_array($data) && isset($data['cal']) ? $data : null;
}