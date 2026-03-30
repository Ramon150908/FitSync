<?php
// ============================================================
//  FitSync — Configurações Gerais (v2.0)
// ============================================================

// ── Banco de Dados ────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'fitsync');
define('DB_USER',     'root');
define('DB_PASS',     '');           // Coloque sua senha se tiver
define('DB_CHARSET',  'utf8mb4');

// ── Google OAuth (opcional) ───────────────────────────────────
define('GOOGLE_CLIENT_ID',     'SEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'SEU_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI',  'http://localhost/FitSync/auth/google_callback.php');

// ── APIs Externas ─────────────────────────────────────────────
// define('USDA_API_KEY',     'DEMO_KEY');                    // Pode deixar assim por enquanto
// define('ANTHROPIC_API_KEY', 'sk-ant-................................');   // ← SUA CHAVE REAL AQUI
// define('ANTHROPIC_MODEL',   'claude-3-5-sonnet-20240620');
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
