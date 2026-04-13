<?php
require_once __DIR__ . '/../bootstrap.php';
// ============================================================
//  FitSync — API de Busca de Alimentos (Local + USDA + Open Food Facts)
//  GET /api/search.php?q=frango&source=all
//  Sources: local | usda | openfoodfacts | all
// ============================================================

requireAuth();

$query  = sanitize($_GET['q'] ?? '');
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
         LIMIT 8',
        [$query . '*', '%' . $query . '%']
    );

    foreach ($local as $food) {
        $results[] = [
            'id'     => $food['id'],
            'fdcId'  => $food['fdcId'],
            'name'   => $food['name'],
            'brand'  => $food['brand'],
            'source' => $food['source'] ?? 'local',
            'per100g'=> [
                'cal'     => (float)$food['cal_per_100g'],
                'prot'    => (float)$food['prot_per_100g'],
                'carb'    => (float)$food['carb_per_100g'],
                'fat'     => (float)$food['fat_per_100g'],
                'fiber'   => $food['fiber_per_100g'] !== null ? (float)$food['fiber_per_100g'] : null,
                'sugar'   => $food['sugar_per_100g'] !== null ? (float)$food['sugar_per_100g'] : null,
                'sodium'  => $food['sodium_per_100g'] !== null ? (float)$food['sodium_per_100g'] : null,
                'sat_fat' => $food['sat_fat_per_100g'] !== null ? (float)$food['sat_fat_per_100g'] : null,
            ],
        ];
    }
}

// ── 2. Busca na USDA ──────────────────────────────────────────
if (in_array($source, ['usda', 'all']) && USDA_API_KEY !== 'SUA_CHAVE_USDA_AQUI') {
    $url = sprintf(
        'https://api.nal.usda.gov/fdc/v1/foods/search?query=%s&dataType=Foundation,SR+Legacy,Survey+(FNDDS)&pageSize=8&api_key=%s',
        urlencode($query), USDA_API_KEY
    );

    $usda = httpGet($url);
    if (!empty($usda['foods'])) {
        foreach ($usda['foods'] as $item) {
            if (array_filter($results, fn($r) => ($r['fdcId'] ?? 0) == ($item['fdcId'] ?? 0))) continue;

            $nutrients = parseUsdaNutrients($item['foodNutrients'] ?? []);

            // Cache no banco
            $exists = Database::fetchOne('SELECT id FROM foods WHERE fdcId = ?', [$item['fdcId']]);
            $dbId = $exists['id'] ?? Database::insert(/* mesmo INSERT do código original */);

            $results[] = [
                'id'      => (int)($dbId ?? 0),
                'fdcId'   => $item['fdcId'],
                'name'    => $item['description'],
                'brand'   => $item['brandOwner'] ?? null,
                'source'  => 'usda',
                'per100g' => $nutrients,
            ];
        }
    }
}

// ── 3. Busca na Open Food Facts ───────────────────────────────
if (in_array($source, ['openfoodfacts', 'all'])) {
    $url = "https://world.openfoodfacts.org/api/v2/search?search_terms=" . urlencode($query) .
           "&search_simple=1&fields=code,product_name,brands,energy-kcal_100g,proteins_100g,carbohydrates_100g,fat_100g,fiber_100g,sugars_100g,salt_100g,saturated-fat_100g,image_url&json=1&page_size=8";

    $off = httpGet($url, ['User-Agent: ' . OPENFOODFACTS_USER_AGENT]);

    if (!empty($off['products'])) {
        foreach ($off['products'] as $p) {
            // Evitar duplicatas grosseiras
            if (array_filter($results, fn($r) => stripos($r['name'] ?? '', $p['product_name'] ?? '') !== false)) continue;

            $results[] = [
                'id'      => null,                    // não existe no banco ainda
                'code'    => $p['code'] ?? null,      // código de barras OFF
                'name'    => $p['product_name'] ?? 'Produto sem nome',
                'brand'   => $p['brands'] ?? null,
                'source'  => 'openfoodfacts',
                'image'   => $p['image_url'] ?? null,
                'per100g' => [
                    'cal'     => isset($p['energy-kcal_100g']) ? round((float)$p['energy-kcal_100g'], 1) : null,
                    'prot'    => isset($p['proteins_100g']) ? round((float)$p['proteins_100g'], 1) : null,
                    'carb'    => isset($p['carbohydrates_100g']) ? round((float)$p['carbohydrates_100g'], 1) : null,
                    'fat'     => isset($p['fat_100g']) ? round((float)$p['fat_100g'], 1) : null,
                    'fiber'   => isset($p['fiber_100g']) ? round((float)$p['fiber_100g'], 1) : null,
                    'sugar'   => isset($p['sugars_100g']) ? round((float)$p['sugars_100g'], 1) : null,
                    'sodium'  => isset($p['salt_100g']) ? round((float)$p['salt_100g'] * 400, 1) : null, // salt → sodium (aprox)
                    'sat_fat' => isset($p['saturated-fat_100g']) ? round((float)$p['saturated-fat_100g'], 1) : null,
                ],
            ];
        }
    }
}

// Limita total de resultados
$results = array_slice($results, 0, 12);

jsonResponse(['results' => $results, 'query' => $query, 'total' => count($results)]);