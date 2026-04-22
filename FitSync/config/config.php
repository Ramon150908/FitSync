<?php
// ============================================================
//  FitSync — Configurações Gerais (v2.1 - USDA Ativado)
// ============================================================

// ── Banco de Dados ────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'fitsync');
define('DB_USER',     'root');
define('DB_PASS',     '');           
define('DB_CHARSET',  'utf8mb4');

// ── Google OAuth (opcional) ───────────────────────────────────
define('GOOGLE_CLIENT_ID',     'SEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'SEU_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost/FitSync/auth/google_callback.php');

// ── USDA FoodData Central (AGORA ATIVADO) ─────────────────────
define('USDA_API_KEY', 'DEMO_KEY');   // ← Funciona para testes
// Para uso real (sem limite), crie grátis em: https://fdc.nal.usda.gov/api-key-signup.html
define('OPENFOODFACTS_USER_AGENT', 'FitSync-App/1.0 (https://seusite.com; contato@seusite.com)'); // ← Obrigatório!
// ── Aplicação ─────────────────────────────────────────────────
define('APP_NAME', 'FitSync');
define('APP_URL',  'http://localhost/FitSync');

// ── Sessão ────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', 86400 * 30);
    session_start();
}

// ── IA para análise de alimentos (OpenAI) ─────────────────────
define('OPENAI_API_KEY', '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');   // ← SUBSTITUA PELA SUA CHAVE
define('OPENAI_MODEL',   'gpt-4o-mini');              // barato e excelente
?>