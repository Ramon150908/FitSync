<?php
require_once __DIR__ . '/../bootstrap.php';
// ============================================================
//  FitSync — API do Diário Alimentar (CRUD)
//  GET    /api/foods.php?date=2025-03-09   → listar
//  POST   /api/foods.php                  → adicionar
//  DELETE /api/foods.php?id=123           → remover
//  GET    /api/foods.php?action=summary&days=7  → histórico
//  PATCH  /api/foods.php?action=goals     → atualizar metas
// ============================================================

$user   = requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ── Roteamento ─────────────────────────────────────────────────
if ($method === 'GET' && $action === 'summary')  { handleSummary($user); }
if ($method === 'GET' && $action === 'goals')    { handleGetGoals($user); }
if ($method === 'PATCH' && $action === 'goals')  { handleUpdateGoals($user); }
if ($method === 'GET')                           { handleList($user); }
if ($method === 'POST')                          { handleAdd($user); }
if ($method === 'DELETE')                        { handleDelete($user); }

jsonError('Rota não encontrada.', 404);

// ── Listar alimentos do dia ────────────────────────────────────
function handleList(array $user): never {
    $date = $_GET['date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) jsonError('Data inválida.');

    $rows = Database::fetchAll(
        'SELECT id, food_name, qty_g, unit, cal, prot, carb, fat, fiber, sugar, sodium, sat_fat,
                meal_type, logged_at, created_at
         FROM food_logs
         WHERE user_id = ? AND logged_at = ?
         ORDER BY created_at DESC',
        [$user['id'], $date]
    );

    // Totais do dia
    $totals = array_reduce($rows, fn($c, $r) => [
        'cal'  => $c['cal']  + $r['cal'],
        'prot' => $c['prot'] + $r['prot'],
        'carb' => $c['carb'] + $r['carb'],
        'fat'  => $c['fat']  + $r['fat'],
    ], ['cal' => 0, 'prot' => 0, 'carb' => 0, 'fat' => 0]);

    // Metas do usuário
    $goals = Database::fetchOne(
        'SELECT daily_cal, daily_prot, daily_carb, daily_fat FROM users WHERE id = ?',
        [$user['id']]
    );

    jsonResponse(['logs' => $rows, 'totals' => $totals, 'goals' => $goals, 'date' => $date]);
}

// ── Adicionar alimento ─────────────────────────────────────────
function handleAdd(array $user): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $foodName = sanitize($body['food_name'] ?? '');
    $foodId   = !empty($body['food_id'])   ? (int)$body['food_id']  : null;
    $qty      = (float)($body['qty_g']     ?? 0);
    $unit     = in_array($body['unit'] ?? '', ['g', 'ml']) ? $body['unit'] : 'g';
    $mealType = in_array($body['meal_type'] ?? '', ['breakfast','lunch','dinner','snack'])
                ? $body['meal_type'] : 'snack';
    $date     = $_GET['date'] ?? date('Y-m-d');

    if (!$foodName)  jsonError('Nome do alimento obrigatório.');
    if ($qty <= 0)   jsonError('Quantidade inválida.');

    // Nutrientes podem vir direto (da IA) ou serão calculados do banco
    if (!empty($body['cal'])) {
        $n = [
            'cal'     => (float)$body['cal'],
            'prot'    => (float)($body['prot']    ?? 0),
            'carb'    => (float)($body['carb']    ?? 0),
            'fat'     => (float)($body['fat']     ?? 0),
            'fiber'   => isset($body['fiber'])   ? (float)$body['fiber']   : null,
            'sugar'   => isset($body['sugar'])   ? (float)$body['sugar']   : null,
            'sodium'  => isset($body['sodium'])  ? (float)$body['sodium']  : null,
            'sat_fat' => isset($body['sat_fat']) ? (float)$body['sat_fat'] : null,
        ];
    } elseif ($foodId) {
        $food = Database::fetchOne(
            'SELECT cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g,
                    fiber_per_100g, sugar_per_100g, sodium_per_100g, sat_fat_per_100g
             FROM foods WHERE id = ?', [$foodId]
        );
        if (!$food) jsonError('Alimento não encontrado no banco.', 404);
        $n = scaleNutrients($food, $qty);
    } else {
        jsonError('Informe os nutrientes ou um food_id válido.');
    }

    $id = Database::insert(
        'INSERT INTO food_logs (user_id, food_id, food_name, qty_g, unit, cal, prot, carb, fat,
                                fiber, sugar, sodium, sat_fat, meal_type, logged_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $user['id'], $foodId, $foodName, $qty, $unit,
            $n['cal'], $n['prot'], $n['carb'], $n['fat'],
            $n['fiber'], $n['sugar'], $n['sodium'], $n['sat_fat'],
            $mealType, $date,
        ]
    );

    jsonResponse(['success' => true, 'id' => (int)$id, 'nutrients' => $n]);
}

// ── Remover entrada ────────────────────────────────────────────
function handleDelete(array $user): never {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) jsonError('ID inválido.');

    $row = Database::fetchOne(
        'SELECT id FROM food_logs WHERE id = ? AND user_id = ?',
        [$id, $user['id']]
    );
    if (!$row) jsonError('Registro não encontrado.', 404);

    Database::query('DELETE FROM food_logs WHERE id = ?', [$id]);
    jsonResponse(['success' => true]);
}

// ── Histórico (últimos N dias) ────────────────────────────────
function handleSummary(array $user): never {
    $days = min((int)($_GET['days'] ?? 7), 30);

    $rows = Database::fetchAll(
        'SELECT logged_at,
                ROUND(SUM(cal), 1)  AS cal,
                ROUND(SUM(prot), 1) AS prot,
                ROUND(SUM(carb), 1) AS carb,
                ROUND(SUM(fat), 1)  AS fat
         FROM food_logs
         WHERE user_id = ? AND logged_at >= CURDATE() - INTERVAL ? DAY
         GROUP BY logged_at
         ORDER BY logged_at ASC',
        [$user['id'], $days]
    );

    jsonResponse(['history' => $rows]);
}

// ── Metas ─────────────────────────────────────────────────────
function handleGetGoals(array $user): never {
    $goals = Database::fetchOne(
        'SELECT daily_cal, daily_prot, daily_carb, daily_fat FROM users WHERE id = ?',
        [$user['id']]
    );
    jsonResponse(['goals' => $goals]);
}

function handleUpdateGoals(array $user): never {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    $cal  = (int)($body['daily_cal']  ?? 0);
    $prot = (int)($body['daily_prot'] ?? 0);
    $carb = (int)($body['daily_carb'] ?? 0);
    $fat  = (int)($body['daily_fat']  ?? 0);

    if ($cal < 500 || $cal > 10000)   jsonError('Meta de calorias inválida (500–10000).');
    if ($prot < 10 || $prot > 500)    jsonError('Meta de proteína inválida.');
    if ($carb < 10 || $carb > 1000)   jsonError('Meta de carboidratos inválida.');
    if ($fat  < 5  || $fat  > 500)    jsonError('Meta de gordura inválida.');

    Database::query(
        'UPDATE users SET daily_cal = ?, daily_prot = ?, daily_carb = ?, daily_fat = ? WHERE id = ?',
        [$cal, $prot, $carb, $fat, $user['id']]
    );

    jsonResponse(['success' => true]);
}
