<?php
require_once __DIR__ . '/bootstrap.php';

// Verificação de autenticação
if (empty($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

// Dados do usuário para o header
$userName  = htmlspecialchars($_SESSION['user_name'] ?? 'Usuário');
$userFirst = explode(' ', trim($userName))[0];
$initials  = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', $userName), 0, 2)));

$dbUser = Database::fetchOne('SELECT avatar_url FROM users WHERE id = ?', [$_SESSION['user_id']]);
$avatar = $dbUser['avatar_url'] ?? null;

// Helper para exibir o logo
$logoFile = '';
if (file_exists(__DIR__ . '/assets/logo.png')) {
    $logoFile = 'logo.png';
} elseif (file_exists(__DIR__ . '/assets/icon.png')) {
    $logoFile = 'icon.png';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="FitSync - Diário Alimentar com IA. Registre seus alimentos e acompanhe suas metas nutricionais." />
  <title>FitSync — Diário Alimentar</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/styles.css?v=2" />
  <link rel="icon" type="image/png" href="assets/icon.png" />
</head>
<body>

<!-- HEADER -->
<header>
  <div class="header-inner">
    <div class="logo">
      <?php if ($logoFile): ?>
        <img src="assets/<?= $logoFile ?>" alt="FitSync" class="logo-img" />
      <?php else: ?>
        <div class="logo-text">Fit<span>Sync</span></div>
      <?php endif; ?>
    </div>

    <div class="header-right">
      <div class="date-badge" id="dateBadge"></div>

      <button class="btn-report-header" id="btnOpenReport" aria-label="Relatório do Dia">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
        </svg>
        <span>Relatório do Dia</span>
      </button>

      <div class="user-badge">
        <?php if ($avatar): ?>
          <img class="user-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="<?= $userFirst ?>" referrerpolicy="no-referrer" />
        <?php else: ?>
          <div class="user-initials"><?= $initials ?></div>
        <?php endif; ?>
        <span class="user-name"><?= $userFirst ?></span>
        <button class="btn-logout" id="btnLogout" title="Sair" aria-label="Sair">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </button>
      </div>
    </div>
  </div>
</header>

<div class="container main-content">

  <!-- NAV TABS -->
  <div class="nav-tabs" role="tablist">
    <button class="nav-tab active" data-panel="panelDiary" role="tab" aria-selected="true">
      <span class="tab-icon">📋</span>
      <span class="tab-label">Diário</span>
    </button>
    <button class="nav-tab" data-panel="panelHistory" role="tab" aria-selected="false">
      <span class="tab-icon">📈</span>
      <span class="tab-label">Histórico</span>
    </button>
    <button class="nav-tab" data-panel="panelGoals" role="tab" aria-selected="false">
      <span class="tab-icon">🎯</span>
      <span class="tab-label">Metas</span>
    </button>
  </div>

  <!-- ══════════════ PAINEL: DIÁRIO ══════════════ -->
  <div class="nav-panel active" id="panelDiary" role="tabpanel">

    <div class="summary-label">Resumo de Hoje</div>

    <div class="summary-cards">
      <div class="card card-main">
        <div class="calories-header">
          <span class="calories-icon">🔥</span>
          <div>
            <div class="card-value" id="totalCal">0</div>
            <div class="card-unit">kcal</div>
          </div>
        </div>
        <div class="card-label">Calorias Consumidas</div>
        <div class="card-sub" id="calSub">Restam — kcal da meta diária</div>
        <div class="progress-bar">
          <div class="progress-fill" id="calProgress" style="width:0%"></div>
        </div>
      </div>

      <div class="card macro-card">
        <div class="macro-header">
          <span class="macro-icon" style="background:#e6fff5;">💪</span>
          <span class="macro-name">Proteína</span>
        </div>
        <div class="card-value" id="totalProt">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="protProgress" style="width:0%;background:#10b981"></div>
        </div>
      </div>

      <div class="card macro-card">
        <div class="macro-header">
          <span class="macro-icon" style="background:#fffbeb;">🌾</span>
          <span class="macro-name">Carboidratos</span>
        </div>
        <div class="card-value" id="totalCarb">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="carbProgress" style="width:0%;background:#f59e0b"></div>
        </div>
      </div>

      <div class="card macro-card">
        <div class="macro-header">
          <span class="macro-icon" style="background:#fff1f1;">🧈</span>
          <span class="macro-name">Gordura</span>
        </div>
        <div class="card-value" id="totalFat">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="fatProgress" style="width:0%;background:#ef4444"></div>
        </div>
      </div>
    </div>

    <!-- Water Tracker -->
    <div class="water-tracker">
      <div class="water-label">💧 Hidratação</div>
      <div class="water-cups" id="waterCups">
        <?php for ($i = 0; $i < 8; $i++): ?>
          <button class="water-cup" data-cup="<?= $i ?>" aria-label="Copo de água <?= $i+1 ?>">○</button>
        <?php endfor; ?>
      </div>
      <div class="water-text" id="waterText">0,00L / 2L</div>
    </div>

    <!-- Search & Add -->
    <div class="search-section">
      <div class="search-section-title">✦ Adicionar Alimento</div>

      <div class="meal-selector">
        <button class="meal-btn" data-meal="breakfast">☀️ Café da manhã</button>
        <button class="meal-btn" data-meal="lunch">🍽️ Almoço</button>
        <button class="meal-btn" data-meal="dinner">🌙 Jantar</button>
        <button class="meal-btn active" data-meal="snack">🍎 Lanche</button>
      </div>

      <div class="search-row">
        <div class="search-wrap">
          <span class="search-icon">🔍</span>
          <input type="text" class="search-input" id="foodInput" 
                 placeholder="Busque ou descreva (ex: 150g de arroz com feijão)..."
                 autocomplete="off" aria-label="Buscar alimento" />
          <div class="search-results" id="searchResults"></div>
        </div>
        <input type="number" class="qty-input" id="qtyInput" placeholder="g" min="1" title="Quantidade em gramas" aria-label="Quantidade em gramas" />
        <button class="btn-add" id="btnAdd">✦ Analisar com IA</button>
      </div>

      <div class="ai-result" id="aiResult" style="display:none;">
        <div class="ai-result-inner">
          <div class="ai-title" id="aiTitle">✦ Resultado</div>
          <div id="aiNutrients"></div>
        </div>
      </div>
    </div>

    <!-- Food List -->
    <div class="log-header">
      <div class="section-title">Alimentos de Hoje</div>
      <button class="btn-clear" id="btnClear" aria-label="Limpar todos os alimentos">🗑️ Limpar dia</button>
    </div>
    <div class="food-list" id="foodList"></div>

  </div>

  <!-- ══════════════ PAINEL: HISTÓRICO ══════════════ -->
  <div class="nav-panel" id="panelHistory" style="display:none;" role="tabpanel">
    <div class="section-title" style="margin-bottom:24px;">Histórico dos Últimos 7 Dias</div>
    <div id="historyContent">
      <div class="empty-state">
        <div class="empty-icon">📊</div>
        <div class="empty-text">Carregando histórico...</div>
      </div>
    </div>
  </div>

  <!-- ══════════════ PAINEL: METAS ══════════════ -->
  <div class="nav-panel" id="panelGoals" style="display:none;" role="tabpanel">
    <div class="section-title" style="margin-bottom:8px;">Minhas Metas Diárias</div>
    <p style="color:var(--muted);margin-bottom:28px;font-size:15px;">Defina seus objetivos nutricionais diários.</p>

    <div class="goals-grid">
      <div class="goal-card">
        <div class="goal-icon">🔥</div>
        <label class="goal-label" for="goalCal">Calorias (kcal)</label>
        <input type="number" class="goal-input" id="goalCal" placeholder="2000" min="500" max="10000" />
      </div>
      <div class="goal-card">
        <div class="goal-icon">💪</div>
        <label class="goal-label" for="goalProt">Proteína (g)</label>
        <input type="number" class="goal-input" id="goalProt" placeholder="150" min="10" max="500" />
      </div>
      <div class="goal-card">
        <div class="goal-icon">🌾</div>
        <label class="goal-label" for="goalCarb">Carboidratos (g)</label>
        <input type="number" class="goal-input" id="goalCarb" placeholder="250" min="10" max="1000" />
      </div>
      <div class="goal-card">
        <div class="goal-icon">🧈</div>
        <label class="goal-label" for="goalFat">Gordura (g)</label>
        <input type="number" class="goal-input" id="goalFat" placeholder="65" min="5" max="500" />
      </div>
    </div>

    <button class="btn-save-goals" id="btnSaveGoals">
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
      </svg>
      Salvar Metas
    </button>

    <!-- BMI Calculator -->
    <div class="bmi-card">
      <div class="section-title" style="margin-bottom:6px;">Calculadora de IMC</div>
      <p style="color:var(--muted);font-size:14px;margin-bottom:18px;">Índice de Massa Corporal — referência rápida.</p>
      <div class="bmi-inputs">
        <div class="bmi-input-wrap">
          <div class="bmi-input-label">Altura (cm)</div>
          <input type="number" class="bmi-input" id="bmiHeight" placeholder="170" min="100" max="250" />
        </div>
        <div class="bmi-input-wrap">
          <div class="bmi-input-label">Peso (kg)</div>
          <input type="number" class="bmi-input" id="bmiWeight" placeholder="70" min="30" max="300" />
        </div>
        <div style="display:flex;align-items:flex-end;">
          <button id="btnCalcBMI" class="btn-calc-bmi" aria-label="Calcular IMC">
            Calcular
          </button>
        </div>
      </div>
      <div class="bmi-result" id="bmiResult" style="display:none;"></div>
    </div>
  </div>

</div>

<!-- DAILY REPORT MODAL -->
<div class="modal-overlay" id="reportModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="reportTitle">
    <div class="modal-header">
      <div>
        <div class="modal-title" id="reportTitle">📊 Relatório Nutricional</div>
        <div class="modal-subtitle" id="reportDate"></div>
      </div>
      <button class="modal-close" id="modalClose" aria-label="Fechar">✕</button>
    </div>
    <div class="modal-body" id="reportBody"></div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="assets/app.js?v=2"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const days   = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
  const months = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
  const now    = new Date();
  const rd     = document.getElementById('reportDate');
  if (rd) rd.textContent = `${days[now.getDay()]}, ${now.getDate()} de ${months[now.getMonth()]} de ${now.getFullYear()}`;
});
</script>
</body>
</html>