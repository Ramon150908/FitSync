<?php
require_once __DIR__ . '/bootstrap.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$googleEnabled = GOOGLE_CLIENT_ID !== 'SEU_GOOGLE_CLIENT_ID.apps.googleusercontent.com';
$error = $_GET['error'] ?? '';
$errorMsg = match($error) {
    'oauth_state' => 'Erro de segurança no OAuth. Tente novamente.',
    'oauth_token' => 'Não foi possível autenticar com o Google.',
    'oauth_user'  => 'Não foi possível obter os dados do Google.',
    default       => '',
};
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
    /* ── Auth Page Overrides ── */
    body { background: linear-gradient(135deg, #1a1054 0%, #255ff1 100%); }

    .auth-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow: hidden;
    }

    /* Decorative blobs */
    .auth-blob {
      position: fixed;
      border-radius: 50%;
      filter: blur(80px);
      opacity: 0.18;
      pointer-events: none;
    }
    .blob-1 { width:500px;height:500px;background:#60a5fa;top:-150px;right:-150px; }
    .blob-2 { width:400px;height:400px;background:#a78bfa;bottom:-100px;left:-100px; }
    .blob-3 { width:300px;height:300px;background:#34d399;top:40%;left:50%;transform:translate(-50%,-50%); }

    .auth-card {
      background: rgba(255,255,255,0.98);
      backdrop-filter: blur(20px);
      border-radius: 32px;
      padding: 52px 48px;
      max-width: 460px;
      width: 100%;
      box-shadow: 0 30px 80px rgba(26,16,84,0.3), 0 0 0 1px rgba(255,255,255,0.6);
      position: relative;
      z-index: 2;
      animation: cardIn 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }

    @keyframes cardIn {
      from { opacity:0; transform: translateY(50px) scale(0.9); }
      to   { opacity:1; transform: translateY(0)    scale(1); }
    }

    /* Logo area */
    .auth-logo-area {
      text-align: center;
      margin-bottom: 32px;
    }

    .auth-logo-img {
      height: 60px;
      width: auto;
      object-fit: contain;
      margin-bottom: 12px;
      animation: logoFloat 3s ease-in-out infinite;
    }

    @keyframes logoFloat {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-6px); }
    }

    /* Fallback text logo */
    .auth-logo-text {
      font-family: 'Playfair Display', serif;
      font-size: 48px;
      font-weight: 900;
      letter-spacing: -2px;
      color: var(--navy);
    }
    .auth-logo-text span { color: var(--accent); }

    .auth-tagline {
      font-size: 13px;
      color: var(--muted);
      letter-spacing: 4px;
      font-weight: 600;
      text-transform: uppercase;
      margin-top: 4px;
    }

    /* Google button */
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
      font-size: 15.5px;
      color: #333;
      text-decoration: none;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      margin-bottom: 4px;
    }
    .btn-google:hover {
      border-color: var(--accent);
      box-shadow: 0 8px 25px rgba(37,95,241,0.18);
      transform: translateY(-2px);
    }

    .divider {
      display: flex;
      align-items: center;
      gap: 16px;
      margin: 28px 0;
      color: var(--muted);
      font-size: 13px;
      font-weight: 500;
    }
    .divider::before, .divider::after {
      content:''; flex:1; height:1px; background:#e5e7f0;
    }

    /* Tabs */
    .auth-tabs {
      display: flex;
      background: #f4f6fd;
      border-radius: 16px;
      padding: 5px;
      margin-bottom: 28px;
      border: 1px solid #e5e7f0;
    }
    .auth-tab {
      flex: 1;
      padding: 13px;
      border: none;
      background: none;
      font-weight: 600;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.35s cubic-bezier(0.4,0,0.2,1);
      font-family: 'DM Sans', sans-serif;
      font-size: 15px;
      color: var(--muted);
    }
    .auth-tab.active {
      background: white;
      box-shadow: 0 4px 15px rgba(26,16,84,0.1);
      color: var(--navy);
    }

    /* Inputs */
    .auth-form { display:flex; flex-direction:column; gap:14px; }

    .auth-input {
      width: 100%;
      padding: 16px 20px;
      border: 2px solid #e5e7f0;
      border-radius: 14px;
      font-size: 16px;
      font-family: 'DM Sans', sans-serif;
      transition: all 0.3s ease;
      background: #fafcff;
      box-sizing: border-box;
      color: var(--navy);
    }
    .auth-input:focus {
      outline: none;
      border-color: var(--accent);
      background: white;
      box-shadow: 0 0 0 4px rgba(37,95,241,0.12);
    }
    .auth-input::placeholder { color: #b0bbd4; }

    /* Primary Button */
    .btn-primary {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #255ff1, #3b82f6);
      color: white;
      border: none;
      border-radius: 14px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      margin-top: 6px;
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(37,95,241,0.4);
      font-family: 'DM Sans', sans-serif;
      letter-spacing: 0.3px;
    }
    .btn-primary:hover {
      transform: translateY(-3px);
      box-shadow: 0 14px 35px rgba(37,95,241,0.5);
    }
    .btn-primary:active { transform: scale(0.97); }

    /* Error */
    .auth-error {
      text-align: center;
      margin-top: 14px;
      font-size: 14px;
      min-height: 22px;
      border-radius: 10px;
      padding: 0 8px;
      transition: all 0.3s;
    }
    .auth-error:not(:empty) {
      padding: 10px 16px;
      background: #fff1f1;
      color: #e03c5a;
    }

    /* Toast */
    .toast {
      position: fixed;
      bottom: 30px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--navy);
      color: white;
      padding: 14px 28px;
      border-radius: 9999px;
      font-size: 14px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      opacity: 0;
      transition: all 0.4s ease;
      z-index: 1000;
      white-space: nowrap;
    }
    .toast.show {
      opacity: 1;
      transform: translateX(-50%) translateY(-10px);
    }

    @media (max-width: 520px) {
      .auth-card { padding: 36px 28px; }
    }
  </style>
</head>
<body>
  <div class="blob-1 auth-blob"></div>
  <div class="blob-2 auth-blob"></div>
  <div class="blob-3 auth-blob"></div>

  <div class="auth-wrap">
    <div class="auth-card">

      <!-- Logo -->
      <div class="auth-logo-area">
        <img src="assets/logo-horizontal.png" 
             alt="FitSync" 
             class="auth-logo-img"
             onerror="this.style.display='none';document.querySelector('.auth-logo-text').style.display='block';" />
        <div class="auth-logo-text" style="display:none;">Fit<span>Sync</span></div>
        <div class="auth-tagline">IA · Nutrição · Bem-estar</div>
      </div>

      <?php if ($googleEnabled): ?>
      <a href="auth/google_callback.php?init=1" class="btn-google">
        <svg width="22" height="22" viewBox="0 0 24 24">
          <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
          <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
          <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
          <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Continuar com Google
      </a>
      <div class="divider">ou entre com e-mail</div>
      <?php endif; ?>

      <div class="auth-tabs">
        <button class="auth-tab active" id="tabLogin">Entrar</button>
        <button class="auth-tab" id="tabRegister">Cadastrar</button>
      </div>

      <!-- Login Form -->
      <div class="auth-form" id="formLogin">
        <input type="email"    class="auth-input" id="loginEmail"    placeholder="Seu e-mail"  autocomplete="email" />
        <input type="password" class="auth-input" id="loginPassword" placeholder="Sua senha"   autocomplete="current-password" />
        <button class="btn-primary" id="btnLogin">Entrar na conta →</button>
      </div>

      <!-- Register Form -->
      <div class="auth-form" id="formRegister" style="display:none;">
        <input type="text"     class="auth-input" id="regName"     placeholder="Seu nome completo"               autocomplete="name" />
        <input type="email"    class="auth-input" id="regEmail"    placeholder="Seu e-mail"                      autocomplete="email" />
        <input type="password" class="auth-input" id="regPassword" placeholder="Crie uma senha (mín. 6 dígitos)" autocomplete="new-password" />
        <button class="btn-primary" id="btnRegister">Criar minha conta →</button>
      </div>

      <?php if ($errorMsg): ?>
      <div class="auth-error"><?= htmlspecialchars($errorMsg) ?></div>
      <?php else: ?>
      <div class="auth-error" id="authError"></div>
      <?php endif; ?>

    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="assets/app.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    // Botões
    document.getElementById('btnLogin')?.addEventListener('click', handleLogin);
    document.getElementById('btnRegister')?.addEventListener('click', handleRegister);
    document.getElementById('loginPassword')?.addEventListener('keydown', e => { if (e.key === 'Enter') handleLogin(); });
    document.getElementById('regPassword')?.addEventListener('keydown',  e => { if (e.key === 'Enter') handleRegister(); });

    // Troca de abas
    const tabLogin     = document.getElementById('tabLogin');
    const tabRegister  = document.getElementById('tabRegister');
    const formLogin    = document.getElementById('formLogin');
    const formRegister = document.getElementById('formRegister');
    const authError    = document.getElementById('authError');

    tabLogin.addEventListener('click', () => {
      tabLogin.classList.add('active');    tabRegister.classList.remove('active');
      formLogin.style.display    = 'flex'; formRegister.style.display = 'none';
      if (authError) authError.textContent = '';
    });

    tabRegister.addEventListener('click', () => {
      tabRegister.classList.add('active'); tabLogin.classList.remove('active');
      formRegister.style.display = 'flex'; formLogin.style.display    = 'none';
      if (authError) authError.textContent = '';
    });
  });
  </script>
</body>
</html>