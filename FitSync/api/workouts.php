<?php
require_once __DIR__ . '/../bootstrap.php';

// ============================================================
//  FitSync — API de Treinos
// ============================================================

header('Content-Type: application/json; charset=utf-8');

$user = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Roteamento
try {
    if ($method === 'GET' && $action === 'history') {
        handleHistory($user);
    } elseif ($method === 'GET' && $action === 'templates') {
        handleGetTemplates($user);
    } elseif ($method === 'POST' && $action === 'analyze') {
        handleAnalyze($user);
    } elseif ($method === 'GET' && $id > 0) {
        handleGetOne($user, $id);
    } elseif ($method === 'GET') {
        handleList($user);
    } elseif ($method === 'POST') {
        handleCreate($user);
    } elseif ($method === 'PUT') {
        handleUpdate($user, $id);
    } elseif ($method === 'DELETE') {
        handleDelete($user, $id);
    } else {
        jsonError('Rota não encontrada.', 404);
    }
} catch (Exception $e) {
    jsonError('Erro interno: ' . $e->getMessage(), 500);
}

// ====================== LISTAR TREINOS DO DIA ======================
function handleList(array $user): never {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonError('Data inválida.');
    }

    $workouts = Database::fetchAll(
        'SELECT id, workout_type, duration_min, intensity, perceived_effort, 
                exercises, notes, feeling, energy_level, mood, ai_analysis,
                workout_date, created_at
         FROM workout_logs
         WHERE user_id = ? AND workout_date = ?
         ORDER BY created_at DESC',
        [$user['id'], $date]
    );

    // Decodificar JSON
    foreach ($workouts as &$w) {
        $w['exercises'] = $w['exercises'] ?? '[]';
        $w['ai_analysis'] = $w['ai_analysis'] ?? '{}';
    }

    jsonResponse(['workouts' => $workouts, 'date' => $date]);
}

// ====================== BUSCAR UM TREINO ESPECÍFICO ======================
function handleGetOne(array $user, int $id): never {
    $workout = Database::fetchOne(
        'SELECT * FROM workout_logs WHERE id = ? AND user_id = ?',
        [$id, $user['id']]
    );

    if (!$workout) {
        jsonError('Treino não encontrado.', 404);
    }

    jsonResponse(['workout' => $workout]);
}

// ====================== CRIAR TREINO ======================
function handleCreate(array $user): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $workout_date = $body['workout_date'] ?? date('Y-m-d');
    $workout_type = $body['workout_type'] ?? 'strength';
    $duration_min = (int)($body['duration_min'] ?? 0);
    $intensity = $body['intensity'] ?? 'medium';
    $perceived_effort = isset($body['perceived_effort']) ? (int)$body['perceived_effort'] : null;
    $exercises = json_encode($body['exercises'] ?? []);
    $notes = sanitize($body['notes'] ?? '');
    $feeling = sanitize($body['feeling'] ?? '');
    $energy_level = isset($body['energy_level']) ? (int)$body['energy_level'] : null;
    $mood = $body['mood'] ?? 'good';

    // Validações
    if ($duration_min <= 0) {
        jsonError('Duração inválida.');
    }
    if ($duration_min > 720) {
        jsonError('Duração máxima de 12 horas.');
    }

    $validTypes = ['strength', 'cardio', 'hiit', 'yoga', 'functional', 'other'];
    if (!in_array($workout_type, $validTypes)) {
        jsonError('Tipo de treino inválido.');
    }

    $validIntensity = ['low', 'medium', 'high'];
    if (!in_array($intensity, $validIntensity)) {
        jsonError('Intensidade inválida.');
    }

    $validMoods = ['great', 'good', 'neutral', 'tired', 'exhausted'];
    if (!in_array($mood, $validMoods)) {
        $mood = 'good';
    }

    if ($perceived_effort !== null && ($perceived_effort < 0 || $perceived_effort > 10)) {
        $perceived_effort = null;
    }

    if ($energy_level !== null && ($energy_level < 1 || $energy_level > 10)) {
        $energy_level = null;
    }

    $id = Database::insert(
        'INSERT INTO workout_logs 
         (user_id, workout_date, workout_type, duration_min, intensity, 
          perceived_effort, exercises, notes, feeling, energy_level, mood)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$user['id'], $workout_date, $workout_type, $duration_min, $intensity,
         $perceived_effort, $exercises, $notes, $feeling, $energy_level, $mood]
    );

    // Buscar o treino criado para retornar
    $workout = Database::fetchOne('SELECT * FROM workout_logs WHERE id = ?', [$id]);

    jsonResponse(['success' => true, 'id' => $id, 'workout' => $workout]);
}

// ====================== ATUALIZAR TREINO ======================
function handleUpdate(array $user, int $id): never {
    $existing = Database::fetchOne(
        'SELECT id FROM workout_logs WHERE id = ? AND user_id = ?',
        [$id, $user['id']]
    );
    if (!$existing) {
        jsonError('Treino não encontrado.', 404);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $updates = [];
    $params = [];

    if (isset($body['workout_type'])) {
        $updates[] = 'workout_type = ?';
        $params[] = $body['workout_type'];
    }
    if (isset($body['duration_min'])) {
        $updates[] = 'duration_min = ?';
        $params[] = (int)$body['duration_min'];
    }
    if (isset($body['intensity'])) {
        $updates[] = 'intensity = ?';
        $params[] = $body['intensity'];
    }
    if (isset($body['perceived_effort'])) {
        $updates[] = 'perceived_effort = ?';
        $params[] = $body['perceived_effort'] ? (int)$body['perceived_effort'] : null;
    }
    if (isset($body['exercises'])) {
        $updates[] = 'exercises = ?';
        $params[] = json_encode($body['exercises']);
    }
    if (isset($body['notes'])) {
        $updates[] = 'notes = ?';
        $params[] = sanitize($body['notes']);
    }
    if (isset($body['feeling'])) {
        $updates[] = 'feeling = ?';
        $params[] = sanitize($body['feeling']);
    }
    if (isset($body['energy_level'])) {
        $updates[] = 'energy_level = ?';
        $params[] = $body['energy_level'] ? (int)$body['energy_level'] : null;
    }
    if (isset($body['mood'])) {
        $updates[] = 'mood = ?';
        $params[] = $body['mood'];
    }

    if (empty($updates)) {
        jsonError('Nenhum campo para atualizar.');
    }

    $params[] = $id;
    $sql = 'UPDATE workout_logs SET ' . implode(', ', $updates) . ' WHERE id = ?';
    Database::query($sql, $params);

    jsonResponse(['success' => true]);
}

// ====================== DELETAR TREINO ======================
function handleDelete(array $user, int $id): never {
    $existing = Database::fetchOne(
        'SELECT id FROM workout_logs WHERE id = ? AND user_id = ?',
        [$id, $user['id']]
    );
    if (!$existing) {
        jsonError('Treino não encontrado.', 404);
    }

    Database::query('DELETE FROM workout_logs WHERE id = ?', [$id]);
    jsonResponse(['success' => true]);
}

// ====================== HISTÓRICO ======================
function handleHistory(array $user): never {
    $days = min((int)($_GET['days'] ?? 7), 30);
    
    $history = Database::fetchAll(
        'SELECT workout_date, 
                COUNT(*) as total_workouts,
                SUM(duration_min) as total_duration,
                ROUND(AVG(perceived_effort), 1) as avg_effort
         FROM workout_logs
         WHERE user_id = ? AND workout_date >= CURDATE() - INTERVAL ? DAY
         GROUP BY workout_date
         ORDER BY workout_date ASC',
        [$user['id'], $days]
    );

    $summary = Database::fetchOne(
        'SELECT 
            COUNT(*) as workouts_this_week,
            SUM(duration_min) as total_minutes_this_week,
            ROUND(AVG(duration_min), 1) as avg_duration
         FROM workout_logs
         WHERE user_id = ? AND workout_date >= CURDATE() - INTERVAL 7 DAY',
        [$user['id']]
    );

    jsonResponse(['history' => $history, 'summary' => $summary]);
}

// ====================== TEMPLATES DE EXERCÍCIOS ======================
function handleGetTemplates(array $user): never {
    $templates = Database::fetchAll(
        'SELECT id, name, muscle_group, notes FROM exercise_templates 
         WHERE user_id = ? OR user_id = 0
         ORDER BY user_id DESC, name ASC',
        [$user['id']]
    );
    
    jsonResponse(['templates' => $templates]);
}

// ====================== ANALISAR TREINO COM IA ======================
function handleAnalyze(array $user): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $workout_id = (int)($body['workout_id'] ?? 0);
    
    if (!$workout_id) {
        jsonError('ID do treino não informado.');
    }
    
    // Buscar treino
    $workout = Database::fetchOne(
        'SELECT * FROM workout_logs WHERE id = ? AND user_id = ?',
        [$workout_id, $user['id']]
    );
    
    if (!$workout) {
        jsonError('Treino não encontrado.', 404);
    }
    
    // Chamar IA para análise
    $analysis = callWorkoutAI($workout);
    
    if ($analysis) {
        Database::query(
            'UPDATE workout_logs SET ai_analysis = ? WHERE id = ?',
            [json_encode($analysis), $workout_id]
        );
        jsonResponse(['analysis' => $analysis]);
    } else {
        jsonError('Não foi possível analisar o treino no momento.', 500);
    }
}

// ====================== FUNÇÃO IA PARA TREINOS ======================
function callWorkoutAI(array $workout): ?array {
    // Se não tem API key, retorna análise padrão
    if (!defined('OPENAI_API_KEY') || strpos(OPENAI_API_KEY, 'sk-') !== 0) {
        return generateFallbackAnalysis($workout);
    }
    
    $exercises = json_decode($workout['exercises'] ?? '[]', true);
    $exercisesText = '';
    foreach ($exercises as $ex) {
        $exercisesText .= sprintf(
            "- %s: %d séries x %d repetições, %.1fkg, descanso %ds\n",
            $ex['name'] ?? 'Exercício',
            $ex['sets'] ?? 0,
            $ex['reps'] ?? 0,
            $ex['weight'] ?? 0,
            $ex['rest_sec'] ?? 60
        );
    }
    
    if (empty($exercisesText)) {
        $exercisesText = "Nenhum exercício registrado em detalhe.";
    }
    
    $prompt = "Você é um personal trainer especialista. Analise o seguinte treino e forneça feedback.

TIPO: {$workout['workout_type']}
DURAÇÃO: {$workout['duration_min']} min
INTENSIDADE: {$workout['intensity']}
PSE (0-10): {$workout['perceived_effort']}
ENERGIA (1-10): {$workout['energy_level']}
HUMOR: {$workout['mood']}
SENTIMENTO: {$workout['feeling']}

EXERCÍCIOS:
{$exercisesText}

NOTAS: {$workout['notes']}

Responda APENAS com JSON válido:
{
  \"rating\": 7.5,
  \"positive_points\": [\"ponto1\", \"ponto2\"],
  \"improvements\": [\"melhoria1\", \"melhoria2\"],
  \"safety_tips\": [\"dica1\"],
  \"nutrition_tips\": [\"dica1\"],
  \"next_workout_suggestion\": \"sugestão\",
  \"motivation_phrase\": \"frase motivacional\"
}";

    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.3,
        'max_tokens' => 600,
    ];
    
    $result = httpPost('https://api.openai.com/v1/chat/completions', $payload, [
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'Content-Type: application/json'
    ]);
    
    if (empty($result['choices'][0]['message']['content'])) {
        return generateFallbackAnalysis($workout);
    }
    
    $text = trim($result['choices'][0]['message']['content']);
    $text = preg_replace('/```json|```/', '', $text);
    
    $data = json_decode($text, true);
    
    return is_array($data) ? $data : generateFallbackAnalysis($workout);
}

function generateFallbackAnalysis(array $workout): array {
    $rating = 7.0;
    $positive = [];
    $improvements = [];
    
    // Análise baseada nos dados
    if ($workout['duration_min'] >= 45 && $workout['duration_min'] <= 90) {
        $positive[] = "Duração ideal para um treino produtivo.";
        $rating += 0.5;
    } elseif ($workout['duration_min'] > 120) {
        $improvements[] = "Treino muito longo. Tente reduzir para 60-90 minutos.";
        $rating -= 1;
    } elseif ($workout['duration_min'] < 30 && $workout['duration_min'] > 0) {
        $improvements[] = "Treino curto. Tente aumentar para pelo menos 30 minutos.";
        $rating -= 0.5;
    }
    
    if ($workout['perceived_effort'] >= 7) {
        $positive[] = "Boa intensidade! Você está se desafiando.";
        $rating += 0.5;
    } elseif ($workout['perceived_effort'] <= 4 && $workout['perceived_effort'] > 0) {
        $improvements[] = "Intensidade baixa. Tente aumentar a carga.";
        $rating -= 0.5;
    }
    
    if ($workout['energy_level'] >= 7) {
        $positive[] = "Ótimo nível de energia antes do treino.";
    } elseif ($workout['energy_level'] <= 4 && $workout['energy_level'] > 0) {
        $improvements[] = "Considere melhorar alimentação e descanso pré-treino.";
    }
    
    if (empty($positive)) {
        $positive = ["Parabéns por registrar seu treino! Continue assim."];
    }
    if (empty($improvements)) {
        $improvements = ["Continue evoluindo gradualmente."];
    }
    
    return [
        'rating' => max(1, min(10, round($rating, 1))),
        'positive_points' => array_slice($positive, 0, 3),
        'improvements' => array_slice($improvements, 0, 3),
        'safety_tips' => ["Mantenha a postura correta", "Aqueça antes do treino", "Respeite seus limites"],
        'nutrition_tips' => ["Hidrate-se bem", "Consuma proteína após o treino"],
        'next_workout_suggestion' => "Continue progredindo gradualmente",
        'motivation_phrase' => "Cada treino é um passo mais perto do seu objetivo! 💪"
    ];
}