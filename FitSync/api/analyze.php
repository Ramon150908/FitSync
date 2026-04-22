<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Rate limiting
if (!checkRateLimit('analyze', 20, 60)) {
    jsonError('Muitas requisições. Aguarde um momento.', 429);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$query = trim($body['query'] ?? '');
$qty   = max((float)($body['qty'] ?? 100), 1);

// Limitar quantidade máxima
if ($qty > 5000) {
    jsonError('Quantidade máxima permitida é 5000g.');
}

if (strlen($query) < 2) {
    jsonError('Informe pelo menos 2 caracteres para analisar.');
}

if (strlen($query) > 200) {
    jsonError('Descrição muito longa. Use no máximo 200 caracteres.');
}

// ====================== 1. BUSCA NO BANCO LOCAL (otimizada) ======================
$foodName = preg_replace('/\d+\s*(g|gramas|ml|unidade|unidades)s?/i', '', $query);
$foodName = trim(preg_replace('/\s+/', ' ', $foodName));

$local = Database::fetchOne(
    'SELECT name, cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g,
            fiber_per_100g, sugar_per_100g, sodium_per_100g, sat_fat_per_100g
     FROM foods 
     WHERE MATCH(name) AGAINST(? IN BOOLEAN MODE)
        OR name LIKE ?
     LIMIT 1',
    [$foodName . '*', '%' . $foodName . '%']
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
        'fiber'   => $local['fiber_per_100g'] ? round($local['fiber_per_100g'] * $factor, 1) : null,
        'sugar'   => $local['sugar_per_100g'] ? round($local['sugar_per_100g'] * $factor, 1) : null,
        'sodium'  => $local['sodium_per_100g'] ? round($local['sodium_per_100g'] * $factor, 1) : null,
        'sat_fat' => $local['sat_fat_per_100g'] ? round($local['sat_fat_per_100g'] * $factor, 1) : null,
        'source'  => 'local'
    ];
    jsonResponse(['food' => $response]);
}

// ====================== 2. IA (apenas se chave configurada) ======================
$aiResult = null;

if (defined('OPENAI_API_KEY') && !empty(OPENAI_API_KEY) && strpos(OPENAI_API_KEY, 'sk-') === 0) {
    $aiResult = callOpenAIForAnalysis($query, $qty);
}

if ($aiResult) {
    jsonResponse(['food' => $aiResult]);
}

// ====================== 3. FALLBACK INTELIGENTE ======================
// Tenta extrair informações do texto
$response = estimateNutrients($query, $qty);
jsonResponse(['food' => $response]);

// ====================== FUNÇÃO OPENAI ======================
function callOpenAIForAnalysis(string $query, float $qty): ?array
{
    $prompt = "Você é um nutricionista brasileiro especialista na Tabela TACO, USDA e alimentos brasileiros comuns.

Descreva a seguinte refeição ou alimento: \"{$query}\"
Quantidade informada: {$qty} gramas.

Responda EXCLUSIVAMENTE com um objeto JSON válido, sem explicações, sem markdown, apenas o JSON:

{
  \"name\": \"Nome claro e natural do alimento/refeição (máx 50 caracteres)\",
  \"qty\": {$qty},
  \"unit\": \"g\",
  \"cal\": numero,
  \"prot\": numero,
  \"carb\": numero,
  \"fat\": numero,
  \"fiber\": numero ou null,
  \"sugar\": numero ou null,
  \"sodium\": numero ou null,
  \"sat_fat\": numero ou null
}

Seja preciso. Para refeições compostas some os componentes de forma realista.";

    $payload = [
        'model'       => OPENAI_MODEL,
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.2,
        'max_tokens'  => 400,
        'response_format' => ['type' => 'json_object']
    ];

    $result = httpPost('https://api.openai.com/v1/chat/completions', $payload, [
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'Content-Type: application/json'
    ]);

    if (empty($result['choices'][0]['message']['content'])) {
        error_log('OpenAI: Resposta vazia');
        return null;
    }

    $text = trim($result['choices'][0]['message']['content']);
    $text = preg_replace('/```json\s*|\s*```/', '', $text);
    
    $data = json_decode($text, true);
    
    if (!is_array($data) || !isset($data['cal'])) {
        error_log('OpenAI: JSON inválido - ' . substr($text, 0, 200));
        return null;
    }
    
    // Validar e sanitizar
    return [
        'name'    => substr($data['name'] ?? ucfirst($query), 0, 100),
        'qty'     => $qty,
        'unit'    => 'g',
        'cal'     => max(0, min(5000, round($data['cal'] ?? 0, 1))),
        'prot'    => max(0, min(500, round($data['prot'] ?? 0, 1))),
        'carb'    => max(0, min(500, round($data['carb'] ?? 0, 1))),
        'fat'     => max(0, min(200, round($data['fat'] ?? 0, 1))),
        'fiber'   => isset($data['fiber']) ? max(0, min(100, round($data['fiber'], 1))) : null,
        'sugar'   => isset($data['sugar']) ? max(0, min(100, round($data['sugar'], 1))) : null,
        'sodium'  => isset($data['sodium']) ? max(0, min(5000, round($data['sodium'], 1))) : null,
        'sat_fat' => isset($data['sat_fat']) ? max(0, min(100, round($data['sat_fat'], 1))) : null,
        'source'  => 'ai'
    ];
}

// ====================== ESTIMATIVA INTELIGENTE ======================
function estimateNutrients(string $query, float $qty): array
{
    $queryLower = strtolower($query);
    $factor = $qty / 100;
    
    // Mapeamento de alimentos comuns brasileiros
    $foodMap = [
        'arroz' => ['cal' => 130, 'prot' => 2.7, 'carb' => 28, 'fat' => 0.3],
        'feijão' => ['cal' => 77, 'prot' => 4.8, 'carb' => 13.6, 'fat' => 0.5],
        'feijao' => ['cal' => 77, 'prot' => 4.8, 'carb' => 13.6, 'fat' => 0.5],
        'frango' => ['cal' => 165, 'prot' => 31, 'carb' => 0, 'fat' => 3.6],
        'carne' => ['cal' => 250, 'prot' => 26, 'carb' => 0, 'fat' => 15],
        'peixe' => ['cal' => 150, 'prot' => 25, 'carb' => 0, 'fat' => 5],
        'ovo' => ['cal' => 155, 'prot' => 13, 'carb' => 1.1, 'fat' => 11],
        'batata' => ['cal' => 86, 'prot' => 1.6, 'carb' => 20, 'fat' => 0.1],
        'macarrao' => ['cal' => 158, 'prot' => 5.8, 'carb' => 30.9, 'fat' => 0.9],
        'pao' => ['cal' => 300, 'prot' => 8, 'carb' => 57, 'fat' => 3.5],
        'leite' => ['cal' => 61, 'prot' => 3.2, 'carb' => 4.7, 'fat' => 3.3],
        'queijo' => ['cal' => 264, 'prot' => 17.4, 'carb' => 2.4, 'fat' => 20.2],
        'salada' => ['cal' => 20, 'prot' => 1, 'carb' => 4, 'fat' => 0.2],
        'fruta' => ['cal' => 60, 'prot' => 0.5, 'carb' => 15, 'fat' => 0.2],
        'banana' => ['cal' => 89, 'prot' => 1.1, 'carb' => 23, 'fat' => 0.3],
        'maca' => ['cal' => 52, 'prot' => 0.3, 'carb' => 14, 'fat' => 0.2],
        'laranja' => ['cal' => 47, 'prot' => 0.9, 'carb' => 11.8, 'fat' => 0.1],
    ];
    
    $matched = null;
    foreach ($foodMap as $key => $values) {
        if (strpos($queryLower, $key) !== false) {
            $matched = $values;
            break;
        }
    }
    
    if ($matched) {
        return [
            'name'    => ucfirst(trim($query)),
            'qty'     => round($qty, 1),
            'unit'    => 'g',
            'cal'     => round($matched['cal'] * $factor, 1),
            'prot'    => round($matched['prot'] * $factor, 1),
            'carb'    => round($matched['carb'] * $factor, 1),
            'fat'     => round($matched['fat'] * $factor, 1),
            'fiber'   => null,
            'sugar'   => null,
            'sodium'  => null,
            'sat_fat' => null,
            'source'  => 'estimate'
        ];
    }
    
    // Fallback genérico
    return [
        'name'    => ucfirst(trim($query)),
        'qty'     => round($qty, 1),
        'unit'    => 'g',
        'cal'     => round(135 * $factor, 1),
        'prot'    => round(7 * $factor, 1),
        'carb'    => round(16 * $factor, 1),
        'fat'     => round(5 * $factor, 1),
        'fiber'   => null,
        'sugar'   => null,
        'sodium'  => null,
        'sat_fat' => null,
        'source'  => 'estimate'
    ];
}