<?php
require_once __DIR__ . '/bootstrap.php';
if (empty($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

$userName  = htmlspecialchars($_SESSION['user_name'] ?? 'Usuário');
$userFirst = explode(' ', trim($userName))[0];
$initials  = implode('', array_map(fn($p) => strtoupper($p[0] ?? ''), array_slice(explode(' ', $userName), 0, 2)));

$dbUser = Database::fetchOne('SELECT avatar_url FROM users WHERE id = ?', [$_SESSION['user_id']]);
$avatar = $dbUser['avatar_url'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>FitSync — Diário Alimentar</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo">
        <?php if (file_exists(__DIR__ . '/assets/icon.png')): ?>
            <img src="assets/icon.png" alt="FitSync" class="logo-img" />
        <?php else: ?>
            <!-- Fallback com texto estilizado caso a imagem não exista -->
            <div class="logo-text">
                Fit<span>Sync</span>
            </div>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <div class="date-badge" id="dateBadge"></div>
        <div class="user-badge">
            <?php if ($avatar): ?>
                <img class="user-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="<?= $userFirst ?>" referrerpolicy="no-referrer" />
            <?php else: ?>
                <div class="user-initials"><?= $initials ?></div>
            <?php endif; ?>
            <span class="user-name"><?= $userFirst ?></span>
            <button class="btn-logout" id="btnLogout" title="Sair">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 19l-7-7 7-7"/>
                  <path d="M16 4v16"/>
                  <path d="M21 12H10"/>
              </svg>
            </button>
        </div>
    </div>
</header>

<div class="container main-content">

  <!-- NAV TABS -->
  <div class="nav-tabs">
    <button class="nav-tab active" data-panel="panelDiary">
      <span class="tab-icon">📋</span>
      <span class="tab-label">Diário</span>
    </button>
    <button class="nav-tab" data-panel="panelHistory">
      <span class="tab-icon">📈</span>
      <span class="tab-label">Histórico</span>
    </button>
    <button class="nav-tab" data-panel="panelGoals">
      <span class="tab-icon">🎯</span>
      <span class="tab-label">Metas</span>
    </button>
  </div>

  <!-- ══════════════ PAINEL: DIÁRIO ══════════════ -->
  <div class="nav-panel active" id="panelDiary">

    <div class="summary-label">Resumo do Dia</div>

    <div class="summary-cards">
      <!-- Card Principal de Calorias -->
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
          <span class="macro-icon" style="background:#10b981">💪</span>
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
          <span class="macro-icon" style="background:#f59e0b">🌾</span>
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
          <span class="macro-icon" style="background:#ef4444">🧈</span>
          <span class="macro-name">Gordura</span>
        </div>
        <div class="card-value" id="totalFat">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="fatProgress" style="width:0%;background:#ef4444"></div>
        </div>
      </div>
    </div>

    <!-- Busca e Adição -->
    <div class="search-section">
      <div class="search-section-title">Adicionar Alimento</div>

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
                 placeholder="Busque um alimento ou descreva (ex: 150g de arroz com feijão)..."
                 autocomplete="off" />
          <div class="search-results" id="searchResults"></div>
        </div>
        <input type="number" class="qty-input" id="qtyInput" placeholder="Qtd (g)" min="1" />
        <button class="btn-add" id="btnAdd">
          <span class="btn-text">✦ Analisar com IA</span>
        </button>
      </div>

      <div class="ai-result" id="aiResult" style="display:none;">
        <div class="ai-result-inner">
          <div class="ai-title" id="aiTitle">✦ Analisando alimento...</div>
          <div class="ai-nutrients" id="aiNutrients"></div>
        </div>
      </div>
    </div>

    <!-- LISTA DE ALIMENTOS -->
    <div class="log-header">
      <div class="section-title">Alimentos de Hoje</div>
      <button class="btn-clear" id="btnClear">🗑️ Limpar dia</button>
    </div>

    <div class="food-list" id="foodList"></div>
  </div>

  <!-- ══════════════ PAINEL: HISTÓRICO ══════════════ -->
  <div class="nav-panel" id="panelHistory" style="display:none;">
    <div class="section-title" style="margin-bottom:24px;">Histórico dos Últimos 7 Dias</div>
    <div id="historyContent">
      <div class="empty-state">
        <div class="empty-icon">📊</div>
        <div class="empty-text">Carregando histórico...</div>
      </div>
    </div>
  </div>

  <!-- ══════════════ PAINEL: METAS ══════════════ -->
  <div class="nav-panel" id="panelGoals" style="display:none;">
    <div class="section-title" style="margin-bottom:8px;">Minhas Metas Diárias</div>
    <p style="color:var(--muted);margin-bottom:32px;font-size:15px;">Defina seus objetivos nutricionais diários</p>

    <div class="goals-grid">
      <div class="goal-card">
        <div class="goal-icon">🔥</div>
        <label class="goal-label">Calorias (kcal)</label>
        <input type="number" class="goal-input" id="goalCal" placeholder="2000" min="500" max="10000" />
      </div>
      <div class="goal-card">
        <div class="goal-icon">💪</div>
        <label class="goal-label">Proteína (g)</label>
        <input type="number" class="goal-input" id="goalProt" placeholder="150" min="10" max="500" />
      </div>
      <div class="goal-card">
        <div class="goal-icon">🌾</div>
        <label class="goal-label">Carboidratos (g)</label>
        <input type="number" class="goal-input" id="goalCarb" placeholder="250" min="10" max="1000" />
      </div>
      <div class="goal-card">
        <div class="goal-icon">🧈</div>
        <label class="goal-label">Gordura (g)</label>
        <input type="number" class="goal-input" id="goalFat" placeholder="65" min="5" max="500" />
      </div>
    </div>

    <button class="btn-save-goals" id="btnSaveGoals">💾 Salvar Metas</button>
  </div>

</div><!-- /container -->

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="assets/app.js"></script>
<script>
// ── NAV TABS ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.nav-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.panel;
      document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.nav-panel').forEach(p => p.style.display = 'none');
      tab.classList.add('active');
      const panel = document.getElementById(target);
      if (panel) panel.style.display = 'block';

      if (target === 'panelHistory') loadHistory();
      if (target === 'panelGoals')   loadGoals();
    });
  });
});

// ── DATE BADGE ────────────────────────────────────────────────
const days = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
const months = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
const now = new Date();
document.getElementById('dateBadge').textContent =
  `${days[now.getDay()]}, ${now.getDate()} de ${months[now.getMonth()]}`;

// ── HISTÓRICO ─────────────────────────────────────────────────
async function loadHistory() {
  const res = await fetch('api/foods.php?action=summary&days=7');
  const data = await res.json();
  const container = document.getElementById('historyContent');

  if (!data.history || !data.history.length) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">📊</div><div class="empty-text">Nenhum dado ainda. Comece a registrar seus alimentos!</div></div>`;
    return;
  }

  container.innerHTML = data.history.map(row => `
    <div class="history-item">
      <div class="history-date">${formatDate(row.logged_at)}</div>
      <div class="history-macros">
        <span class="hm hm-cal">🔥 ${Math.round(row.cal)} kcal</span>
        <span class="hm hm-prot">💪 ${row.prot}g prot</span>
        <span class="hm hm-carb">🌾 ${row.carb}g carb</span>
        <span class="hm hm-fat">🧈 ${row.fat}g gord</span>
      </div>
    </div>
  `).join('');
}

function formatDate(dateStr) {
  const [y,m,d] = dateStr.split('-');
  const dt = new Date(y, m-1, d);
  return `${days[dt.getDay()]}, ${d}/${m}`;
}

// ── METAS ─────────────────────────────────────────────────────
async function loadGoals() {
  const res = await fetch('api/foods.php?action=goals');
  const data = await res.json();
  if (data.goals) {
    document.getElementById('goalCal').value  = data.goals.daily_cal  || 2000;
    document.getElementById('goalProt').value = data.goals.daily_prot || 150;
    document.getElementById('goalCarb').value = data.goals.daily_carb || 250;
    document.getElementById('goalFat').value  = data.goals.daily_fat  || 65;
  }
}

document.getElementById('btnSaveGoals')?.addEventListener('click', async () => {
  const payload = {
    daily_cal:  parseInt(document.getElementById('goalCal').value),
    daily_prot: parseInt(document.getElementById('goalProt').value),
    daily_carb: parseInt(document.getElementById('goalCarb').value),
    daily_fat:  parseInt(document.getElementById('goalFat').value),
  };

  const res = await fetch('api/foods.php?action=goals', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
    credentials: 'same-origin',
  });
  const data = await res.json();
  if (data.success) {
    // update local state
    if (window.State) {
      window.State.goals = { ...window.State.goals, ...payload };
      if (typeof updateSummary === 'function') updateSummary();
    }
    const btn = document.getElementById('btnSaveGoals');
    const orig = btn.textContent;
    btn.textContent = '✅ Metas salvas!';
    btn.style.background = '#10b981';
    setTimeout(() => { btn.textContent = orig; btn.style.background = ''; }, 2500);
  } else {
    alert(data.error || 'Erro ao salvar metas');
  }
});
</script>
</body>
</html>