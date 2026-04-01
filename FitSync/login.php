<?php
require_once __DIR__ . '/bootstrap.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$googleEnabled = GOOGLE_CLIENT_ID !== 'SEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FitSync — Entrar</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/styles.css" />
  <style>
    .auth-wrap {
      min-height: 100vh;
      background: linear-gradient(135deg, #1a1054 0%, #255ff1 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    .auth-wrap::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url('data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 1000 1000%27%3E%3Ccircle cx=%27500%27 cy=%27500%27 r=%27180%27 fill=%27none%27 stroke=%27%23ffffff%27 stroke-opacity=%270.08%27 stroke-width=%2720%27/%3E%3C/svg%27') center/cover;
      pointer-events: none;
    }

    .auth-card {
      background: white;
      border-radius: 28px;
      padding: 52px 48px;
      max-width: 440px;
      width: 100%;
      box-shadow: 0 25px 70px rgba(26, 16, 84, 0.25);
      position: relative;
      z-index: 2;
      animation: fadeInScale 0.6s ease forwards;
    }

    @keyframes fadeInScale {
      from { opacity: 0; transform: scale(0.92) translateY(30px); }
      to   { opacity: 1; transform: scale(1) translateY(0); }
    }

    .auth-logo {
      font-family: 'Playfair Display', serif;
      font-size: 52px;
      font-weight: 900;
      letter-spacing: -2px;
      color: #1a1054;
      text-align: center;
      margin-bottom: 8px;
    }

    .auth-logo span { color: #255ff1; }

    .auth-tagline {
      text-align: center;
      font-size: 15px;
      color: #7a80a8;
      margin-bottom: 40px;
      letter-spacing: 3px;
      font-weight: 500;
    }

    .auth-form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .auth-input {
      width: 100%;
      padding: 16px 20px;
      border: 2px solid #e5e7f0;
      border-radius: 16px;
      font-size: 16px;
      transition: all 0.3s ease;
      font-family: 'DM Sans', sans-serif;
      box-sizing: border-box;
    }

    .auth-input:focus {
      outline: none;
      border-color: #255ff1;
      box-shadow: 0 0 0 4px rgba(37, 95, 241, 0.12);
    }

    .btn-primary {
      margin-top: 8px;
      padding: 16px;
      background: linear-gradient(90deg, #255ff1, #4a7fff);
      color: white;
      border: none;
      border-radius: 16px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(37, 95, 241, 0.35);
      font-family: 'DM Sans', sans-serif;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(37, 95, 241, 0.45);
    }

    .btn-primary:active { transform: scale(0.97); }

    .btn-google {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      width: 100%;
      padding: 15px 20px;
      background: white;
      border: 2px solid #e5e7f0;
      border-radius: 16px;
      font-weight: 600;
      color: #333;
      text-decoration: none;
      transition: all 0.3s ease;
      font-family: 'DM Sans', sans-serif;
    }

    .btn-google:hover {
      border-color: #255ff1;
      box-shadow: 0 6px 20px rgba(37, 95, 241, 0.15);
    }

    .divider {
      margin: 32px 0;
      text-align: center;
      position: relative;
      color: #7a80a8;
      font-size: 13px;
    }

    .divider::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: #e5e7f0;
    }

    .divider span {
      background: white;
      padding: 0 20px;
      position: relative;
    }

    .auth-tabs {
      display: flex;
      background: #f8f9ff;
      border-radius: 14px;
      padding: 6px;
      margin-bottom: 30px;
      border: 1px solid #e5e7f0;
    }

    .auth-tab {
      flex: 1;
      padding: 12px;
      border: none;
      background: none;
      font-weight: 600;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      color: #7a80a8;
    }

    .auth-tab.active {
      background: white;
      box-shadow: 0 4px 15px rgba(26, 16, 84, 0.08);
      color: #1a1054;
    }

    .auth-error {
      color: #e03c5a;
      text-align: center;
      font-size: 14px;
      min-height: 24px;
      margin-top: 8px;
    }

    .toast {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      background: #1a1054;
      color: white;
      padding: 14px 28px;
      border-radius: 9999px;
      font-size: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      opacity: 0;
      transition: all 0.4s ease;
      z-index: 1000;
    }

    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(-10px);
    }
  </style>
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-card" id="authCard">
      <div class="auth-logo">Fit<span>Sync</span></div>
      <div class="auth-tagline">IA • Nutrição • Bem-estar</div>

      <?php if ($googleEnabled): ?>
      <a href="auth/google_callback.php?init=1" class="btn-google">
        <svg width="24" height="24" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Continuar com Google
      </a>
      <div class="divider"><span>ou</span></div>
      <?php endif; ?>

      <!-- FIX: abas com troca funcional via JS abaixo -->
      <div class="auth-tabs">
        <button class="auth-tab active" id="tabLogin">Entrar</button>
        <button class="auth-tab" id="tabRegister">Cadastrar</button>
      </div>

      <!-- Formulário de Login -->
      <div class="auth-form" id="formLogin">
        <input type="email"    class="auth-input" id="loginEmail"    placeholder="E-mail"  autocomplete="email" />
        <input type="password" class="auth-input" id="loginPassword" placeholder="Senha"   autocomplete="current-password" />
        <button class="btn-primary" id="btnLogin">Entrar na conta</button>
      </div>

      <!-- Formulário de Cadastro -->
      <div class="auth-form" id="formRegister" style="display: none;">
        <input type="text"     class="auth-input" id="regName"     placeholder="Nome completo"              autocomplete="name" />
        <input type="email"    class="auth-input" id="regEmail"    placeholder="E-mail"                     autocomplete="email" />
        <input type="password" class="auth-input" id="regPassword" placeholder="Senha (mínimo 6 caracteres)" autocomplete="new-password" />
        <button class="btn-primary" id="btnRegister">Criar minha conta</button>
      </div>

      <div class="auth-error" id="authError"></div>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="assets/app.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // ── Botões de ação ──────────────────────────────────────────
    document.getElementById('btnLogin')?.addEventListener('click', handleLogin);
    document.getElementById('btnRegister')?.addEventListener('click', handleRegister);

    // Enter nos campos de senha
    document.getElementById('loginPassword')?.addEventListener('keydown', e => {
      if (e.key === 'Enter') handleLogin();
    });
    document.getElementById('regPassword')?.addEventListener('keydown', e => {
      if (e.key === 'Enter') handleRegister();
    });

    // ── FIX: troca de abas ──────────────────────────────────────
    const tabLogin    = document.getElementById('tabLogin');
    const tabRegister = document.getElementById('tabRegister');
    const formLogin   = document.getElementById('formLogin');
    const formRegister = document.getElementById('formRegister');
    const authError   = document.getElementById('authError');

    tabLogin.addEventListener('click', () => {
      tabLogin.classList.add('active');
      tabRegister.classList.remove('active');
      formLogin.style.display    = 'flex';
      formRegister.style.display = 'none';
      authError.textContent = '';
    });

    tabRegister.addEventListener('click', () => {
      tabRegister.classList.add('active');
      tabLogin.classList.remove('active');
      formRegister.style.display = 'flex';
      formLogin.style.display    = 'none';
      authError.textContent = '';
    });
  });
  </script>
</body>
</html>