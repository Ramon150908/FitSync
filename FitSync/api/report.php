<?php
require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$user   = requireAuth();
$action = $_GET['action'] ?? 'daily';

match ($action) {
    'daily'    => handleDailyReport($user),
    'evaluate' => handleMealEvaluation($user),
    default    => jsonError('Ação inválida.', 404),
};

// ====================== RELATÓRIO DIÁRIO ======================
function handleDailyReport(array $user): never
{
    $date = $_GET['date'] ?? date('Y-m-d');

    // Buscar logs do dia
    $logs = Database::fetchAll(
        'SELECT food_name, qty_g, cal, prot, carb, fat, fiber, sugar, sodium, sat_fat, meal_type
         FROM food_logs
         WHERE user_id = ? AND logged_at = ?
         ORDER BY meal_type, created_at',
        [$user['id'], $date]
    );

    // Buscar metas
    $goals = Database::fetchOne(
        'SELECT daily_cal, daily_prot, daily_carb, daily_fat FROM users WHERE id = ?',
        [$user['id']]
    );

    if (empty($logs)) {
        jsonResponse([
            'report' => null,
            'message' => 'Nenhum alimento registrado neste dia.',
        ]);
    }

    // Calcular totais
    $totals = array_reduce($logs, fn($c, $r) => [
        'cal'    => $c['cal']    + (float)$r['cal'],
        'prot'   => $c['prot']   + (float)$r['prot'],
        'carb'   => $c['carb']   + (float)$r['carb'],
        'fat'    => $c['fat']    + (float)$r['fat'],
        'fiber'  => $c['fiber']  + (float)($r['fiber']  ?? 0),
        'sodium' => $c['sodium'] + (float)($r['sodium'] ?? 0),
        'sugar'  => $c['sugar']  + (float)($r['sugar']  ?? 0),
    ], ['cal' => 0, 'prot' => 0, 'carb' => 0, 'fat' => 0, 'fiber' => 0, 'sodium' => 0, 'sugar' => 0]);

    // Agrupar por refeição
    $byMeal = [];
    foreach ($logs as $log) {
        $byMeal[$log['meal_type']][] = $log['food_name'] . ' (' . $log['qty_g'] . 'g)';
    }

    $mealNames = ['breakfast' => 'Café da manhã', 'lunch' => 'Almoço', 'dinner' => 'Jantar', 'snack' => 'Lanche'];
    $mealSummary = '';
    foreach ($byMeal as $type => $items) {
        $mealSummary .= ($mealNames[$type] ?? $type) . ': ' . implode(', ', $items) . "\n";
    }

    // Usar IA para gerar relatório
    $report = callAIReport($user['name'], $totals, $goals, $mealSummary, $date);

    jsonResponse(['report' => $report, 'totals' => $totals, 'goals' => $goals]);
}

// ====================== AVALIAÇÃO DE REFEIÇÃO ======================
function handleMealEvaluation(array $user): never
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Método inválido.', 405);

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $foodName = trim($body['food_name'] ?? '');
    $mealType = $body['meal_type'] ?? 'snack';
    $cal      = (float)($body['cal']  ?? 0);
    $prot     = (float)($body['prot'] ?? 0);
    $carb     = (float)($body['carb'] ?? 0);
    $fat      = (float)($body['fat']  ?? 0);
    $fiber    = $body['fiber']  !== null ? (float)$body['fiber']  : null;
    $sodium   = $body['sodium'] !== null ? (float)$body['sodium'] : null;

    if (!$foodName) jsonError('Nome do alimento obrigatório.');

    $mealNames = ['breakfast' => 'Café da manhã', 'lunch' => 'Almoço', 'dinner' => 'Jantar', 'snack' => 'Lanche'];
    $mealLabel = $mealNames[$mealType] ?? $mealType;

    $evaluation = callAIEvaluation($foodName, $mealLabel, $cal, $prot, $carb, $fat, $fiber, $sodium);

    jsonResponse(['evaluation' => $evaluation]);
}

// ====================== IA: RELATÓRIO DIÁRIO ======================
function callAIReport(string $name, array $totals, array $goals, string $meals, string $date): array
{
    $calPct  = $goals['daily_cal']  > 0 ? round($totals['cal']  / $goals['daily_cal']  * 100) : 0;
    $protPct = $goals['daily_prot'] > 0 ? round($totals['prot'] / $goals['daily_prot'] * 100) : 0;
    $carbPct = $goals['daily_carb'] > 0 ? round($totals['carb'] / $goals['daily_carb'] * 100) : 0;
    $fatPct  = $goals['daily_fat']  > 0 ? round($totals['fat']  / $goals['daily_fat']  * 100) : 0;

    $prompt = "Você é um nutricionista brasileiro especialista e conselheiro de bem-estar. 
Analise a alimentação do dia de {$name} e gere um relatório completo em JSON.

DATA: {$date}
REFEIÇÕES DO DIA:
{$meals}

TOTAIS CONSUMIDOS:
- Calorias: {$totals['cal']} kcal ({$calPct}% da meta de {$goals['daily_cal']} kcal)
- Proteínas: {$totals['prot']}g ({$protPct}% da meta de {$goals['daily_prot']}g)
- Carboidratos: {$totals['carb']}g ({$carbPct}% da meta de {$goals['daily_carb']}g)
- Gorduras: {$totals['fat']}g ({$fatPct}% da meta de {$goals['daily_fat']}g)
- Fibras: {$totals['fiber']}g
- Sódio: {$totals['sodium']}mg
- Açúcares: {$totals['sugar']}g

Responda APENAS com JSON válido, sem markdown:
{
  \"score\": número de 0 a 100 representando a qualidade geral da alimentação,
  \"grade\": letra A, B, C, D ou F,
  \"grade_color\": cor hex correspondente (A=#10b981, B=#22c55e, C=#f59e0b, D=#f97316, F=#ef4444),
  \"summary\": \"Resumo de 2-3 frases sobre o dia alimentar\",
  \"highlights\": [\"ponto positivo 1\", \"ponto positivo 2\"],
  \"warnings\": [\"ponto de atenção 1\", \"ponto de atenção 2\"],
  \"suggestions\": [\"sugestão prática 1\", \"sugestão prática 2\", \"sugestão prática 3\"],
  \"hydration_tip\": \"dica sobre hidratação\",
  \"tomorrow_tip\": \"sugestão para amanhã em uma frase\"
}";

    $result = callOpenAI($prompt);

    if ($result) return $result;

    // Fallback baseado em cálculos simples
    $score = min(100, max(0, round(
        ($calPct  > 80 && $calPct  < 115 ? 25 : ($calPct  > 60 ? 15 : 5)) +
        ($protPct > 80 && $protPct < 120 ? 25 : ($protPct > 60 ? 15 : 5)) +
        ($carbPct > 70 && $carbPct < 115 ? 25 : ($carbPct > 50 ? 15 : 5)) +
        ($fatPct  > 70 && $fatPct  < 115 ? 25 : ($fatPct  > 50 ? 15 : 5))
    ));

    $grade = $score >= 90 ? 'A' : ($score >= 75 ? 'B' : ($score >= 60 ? 'C' : ($score >= 45 ? 'D' : 'F')));
    $colors = ['A' => '#10b981', 'B' => '#22c55e', 'C' => '#f59e0b', 'D' => '#f97316', 'F' => '#ef4444'];

    return [
        'score'         => $score,
        'grade'         => $grade,
        'grade_color'   => $colors[$grade],
        'summary'       => 'Análise baseada nos macronutrientes registrados no dia.',
        'highlights'    => $protPct >= 80 ? ['Bom consumo de proteínas!'] : [],
        'warnings'      => $calPct > 115 ? ['Calorias acima da meta'] : ($calPct < 60 ? ['Calorias muito abaixo da meta'] : []),
        'suggestions'   => ['Mantenha uma alimentação variada', 'Beba bastante água', 'Inclua mais vegetais'],
        'hydration_tip' => 'Lembre-se de beber pelo menos 2L de água por dia.',
        'tomorrow_tip'  => 'Continue focado em atingir suas metas nutricionais!',
    ];
}

// ====================== IA: AVALIAÇÃO DE ALIMENTO ======================
function callAIEvaluation(string $food, string $meal, float $cal, float $prot, float $carb, float $fat, ?float $fiber, ?float $sodium): array
{
    $fiberInfo  = $fiber  !== null ? "{$fiber}g fibras"    : '';
    $sodiumInfo = $sodium !== null ? "{$sodium}mg sódio"   : '';

    $prompt = "Você é um nutricionista brasileiro. Avalie brevemente este alimento/refeição.

Alimento: {$food}
Refeição: {$meal}
Nutrientes: {$cal} kcal | {$prot}g proteína | {$carb}g carboidrato | {$fat}g gordura" .
($fiberInfo  ? " | {$fiberInfo}"  : '') .
($sodiumInfo ? " | {$sodiumInfo}" : '') . "

Responda APENAS com JSON válido, sem markdown:
{
  \"rating\": número de 1 a 5 (estrelas),
  \"badge\": emoji representativo (ex: ✅ 💪 ⚠️ 🔥 🌱),
  \"label\": \"rótulo curto (ex: Excelente escolha!, Boa proteína, Muito calórico)\",
  \"tip\": \"dica curta de 1 frase sobre este alimento nesta refeição\",
  \"color\": cor hex (5 estrelas=#10b981, 4=#22c55e, 3=#f59e0b, 2=#f97316, 1=#ef4444)
}";

    $result = callOpenAI($prompt);

    if ($result) return $result;

    // Fallback
    $rating = 3;
    if ($prot > 20) $rating++;
    if ($cal > 500) $rating--;
    if ($fiber !== null && $fiber > 5) $rating++;
    $rating = max(1, min(5, $rating));

    $colors = [1 => '#ef4444', 2 => '#f97316', 3 => '#f59e0b', 4 => '#22c55e', 5 => '#10b981'];
    $labels = [1 => 'Evite com frequência', 2 => 'Moderação', 3 => 'Razoável', 4 => 'Boa escolha!', 5 => 'Excelente!'];

    return [
        'rating' => $rating,
        'badge'  => $rating >= 4 ? '✅' : ($rating >= 3 ? '⚠️' : '🔴'),
        'label'  => $labels[$rating],
        'tip'    => 'Combine com alimentos variados para uma dieta equilibrada.',
        'color'  => $colors[$rating],
    ];
}

// ====================== HELPER OPENAI ======================
function callOpenAI(string $prompt): ?array
{
    if (!defined('OPENAI_API_KEY') || strpos(OPENAI_API_KEY, 'sk-') !== 0) return null;

    $payload = [
        'model'       => defined('OPENAI_MODEL') ? OPENAI_MODEL : 'gpt-4o-mini',
        'messages'    => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'max_tokens'  => 800,
    ];

    $result = httpPost('https://api.openai.com/v1/chat/completions', $payload, [
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'Content-Type: application/json',
    ]);

    if (empty($result['choices'][0]['message']['content'])) return null;

    $text = trim($result['choices'][0]['message']['content']);
    $text = preg_replace('/```json|```/', '', $text);

    $data = json_decode($text, true);
    return is_array($data) ? $data : null;
}