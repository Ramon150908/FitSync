<?php
require_once __DIR__ . '/../bootstrap.php';
// ============================================================
//  FitSync — API de Busca de Alimentos
//  GET /api/search.php?q=frango&source=all
//  Sources: local | usda | all
// ============================================================

requireAuth();

$query  = sanitize($_GET['q']      ?? '');
$source = $_GET['source'] ?? 'all';

if (strlen($query) < 2) jsonError('Digite ao menos 2 caracteres.');

$results = [];

// ── 1. Busca no banco local ───────────────────────────────────
if (in_array($source, ['local', 'all'])) {
    $local = Database::fetchAll(
        'SELECT id, fdcId, name, brand, cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g,
                fiber_per_100g, sugar_per_100g, sodium_per_100g, sat_fat_per_100g, source
         FROM foods
         WHERE MATCH(name, brand) AGAINST(? IN BOOLEAN MODE)
            OR name LIKE ?
         LIMIT 10',
        [$query . '*', '%' . $query . '%']
    );

    foreach ($local as $food) {
        $results[] = [
            'id'          => $food['id'],
            'fdcId'       => $food['fdcId'],
            'name'        => $food['name'],
            'brand'       => $food['brand'],
            'source'      => $food['source'],
            'per100g'     => [
                'cal'     => (float)$food['cal_per_100g'],
                'prot'    => (float)$food['prot_per_100g'],
                'carb'    => (float)$food['carb_per_100g'],
                'fat'     => (float)$food['fat_per_100g'],
                'fiber'   => $food['fiber_per_100g']   !== null ? (float)$food['fiber_per_100g']   : null,
                'sugar'   => $food['sugar_per_100g']   !== null ? (float)$food['sugar_per_100g']   : null,
                'sodium'  => $food['sodium_per_100g']  !== null ? (float)$food['sodium_per_100g']  : null,
                'sat_fat' => $food['sat_fat_per_100g'] !== null ? (float)$food['sat_fat_per_100g'] : null,
            ],
        ];
    }
}

// ── 2. Busca na API USDA FoodData Central ─────────────────────
if (in_array($source, ['usda', 'all']) && USDA_API_KEY !== 'SUA_CHAVE_USDA_AQUI') {
    $url = sprintf(
        'https://api.nal.usda.gov/fdc/v1/foods/search?query=%s&dataType=Foundation,SR+Legacy,Survey+%28FNDDS%29&pageSize=15&api_key=%s',
        urlencode($query), USDA_API_KEY
    );

    $usda = httpGet($url);

    if (!empty($usda['foods'])) {
        foreach ($usda['foods'] as $item) {
            // Evitar duplicatas já no banco
            $alreadyIn = array_filter($results, fn($r) => (int)$r['fdcId'] === (int)$item['fdcId']);
            if ($alreadyIn) continue;

            $nutrients = parseUsdaNutrients($item['foodNutrients'] ?? []);

            // Cacheia no banco para buscas futuras
            $existsInDb = Database::fetchOne('SELECT id FROM foods WHERE fdcId = ?', [$item['fdcId']]);
            $dbId = null;
            if (!$existsInDb) {
                $dbId = Database::insert(
                    'INSERT INTO foods (fdcId, name, brand, cal_per_100g, prot_per_100g, carb_per_100g, fat_per_100g,
                                       fiber_per_100g, sugar_per_100g, sodium_per_100g, sat_fat_per_100g, source)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "usda")',
                    [
                        $item['fdcId'],
                        $item['description'],
                        $item['brandOwner'] ?? null,
                        $nutrients['cal'],   $nutrients['prot'],
                        $nutrients['carb'],  $nutrients['fat'],
                        $nutrients['fiber'], $nutrients['sugar'],
                        $nutrients['sodium'],$nutrients['sat_fat'],
                    ]
                );
            } else {
                $dbId = $existsInDb['id'];
            }

            $results[] = [
                'id'      => (int)$dbId,
                'fdcId'   => $item['fdcId'],
                'name'    => $item['description'],
                'brand'   => $item['brandOwner'] ?? null,
                'source'  => 'usda',
                'per100g' => $nutrients,
            ];
        }
    }
}

jsonResponse(['results' => $results, 'query' => $query, 'total' => count($results)]);

// ── Parser de nutrientes da USDA ──────────────────────────────
function parseUsdaNutrients(array $raw): array {
    // Mapa de Nutrient IDs da USDA
    $map = [
        1008 => 'cal',    // Energy (kcal)
        1003 => 'prot',   // Protein
        1005 => 'carb',   // Carbohydrate, by difference
        1004 => 'fat',    // Total lipid (fat)
        1079 => 'fiber',  // Fiber, total dietary
        2000 => 'sugar',  // Sugars, total
        1093 => 'sodium', // Sodium, Na
        1258 => 'sat_fat',// Fatty acids, total saturated
    ];

    $result = array_fill_keys(array_values($map), null);

    foreach ($raw as $n) {
        $nid = $n['nutrientId'] ?? $n['nutrientNumber'] ?? null;
        if ($nid && isset($map[$nid])) {
            $result[$map[$nid]] = round((float)($n['value'] ?? 0), 2);
        }
    }

    return $result;
}
