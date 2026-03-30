<?php
require_once __DIR__ . '/bootstrap.php';
if (empty($_SESSION['user_id'])) { 
    header('Location: login.php'); 
    exit; 
}

$userName  = htmlspecialchars($_SESSION['user_name'] ?? 'Usuário');
$userFirst = explode(' ', trim($userName))[0];
$initials  = implode('', array_map(fn($p) => strtoupper($p[0]), array_slice(explode(' ', $userName), 0, 2)));

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
<div class="container">
  
  <!-- HEADER -->
  <header>
    <div class="logo">
      <span class="logo-name">Fit<span>Sync</span></span>
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
        <button class="btn-logout" id="btnLogout" title="Sair">⏻</button>
      </div>
    </div>
  </header>

  <!-- NAV TABS -->
  <div class="nav-tabs">
    <button class="nav-tab active" data-panel="panelDiary">📋 Diário</button>
    <button class="nav-tab" data-panel="panelHistory">📈 Histórico</button>
    <button class="nav-tab" data-panel="panelGoals">🎯 Metas</button>
  </div>

  <!-- DIÁRIO -->
  <div class="nav-panel active" id="panelDiary">
    <div class="summary-label">Resumo do Dia</div>
    <!-- SUMMARY CARDS - Versão Melhorada -->
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
          <div class="progress-fill" id="calProgress" style="width: 0%"></div>
        </div>
      </div>
            
      <!-- Card de Proteína -->
      <div class="card macro-card">
        <div class="macro-header">
          <span class="macro-icon" style="background: var(--prot-color)">💪</span>
          <span class="macro-name">Proteína</span>
        </div>
        <div class="card-value" id="totalProt">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="protProgress" style="width:0%; background: var(--prot-color)"></div>
        </div>
      </div>
            
      <!-- Card de Carboidratos -->
      <div class="card macro-card">
        <div class="macro-header">
          <span class="macro-icon" style="background: var(--carb-color)">🌾</span>
          <span class="macro-name">Carboidratos</span>
        </div>
        <div class="card-value" id="totalCarb">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="carbProgress" style="width:0%; background: var(--carb-color)"></div>
        </div>
      </div>
            
      <!-- Card de Gordura -->
      <div class="card macro-card">
        <div class="macro-header">
          <span class="macro-icon" style="background: var(--fat-color)">🧈</span>
          <span class="macro-name">Gordura</span>
        </div>
        <div class="card-value" id="totalFat">0</div>
        <div class="card-unit">g</div>
        <div class="progress-bar">
          <div class="progress-fill" id="fatProgress" style="width:0%; background: var(--fat-color)"></div>
        </div>
      </div>
    </div>

    <!-- Busca -->
    <!-- BUSCA MELHORADA -->
<div class="search-section">
  <div class="meal-selector">
    <button class="meal-btn" data-meal="breakfast">☀️ Café da manhã</button>
    <button class="meal-btn" data-meal="lunch">🍽️ Almoço</button>
    <button class="meal-btn" data-meal="dinner">🌙 Jantar</button>
    <button class="meal-btn active" data-meal="snack">🍎 Lanche</button>
  </div>

  <div class="search-row">
    <div class="search-wrap">
      <span class="search-icon">🔍</span>
      <input 
        type="text" 
        class="search-input" 
        id="foodInput" 
        placeholder="Busque um alimento ou descreva para a IA (ex: 150g de arroz com feijão)..." 
        autocomplete="off" 
      />
      <div class="search-results" id="searchResults"></div>
    </div>

    <input 
      type="number" 
      class="qty-input" 
      id="qtyInput" 
      placeholder="Quantidade (g)" 
      min="1" 
    />

    <button class="btn-add" id="btnAdd">
      <span class="btn-text">Analisar com IA</span>
      <span class="btn-loading" style="display:none;">Analisando...</span>
    </button>
  </div>

  <div class="ai-result" id="aiResult">
    <div class="ai-title" id="aiTitle">✦ Analisando alimento...</div>
    <div class="ai-nutrients" id="aiNutrients"></div>
  </div>
</div>

<!-- LISTA DE ALIMENTOS -->
<div class="log-header">
  <div class="section-title">Alimentos de Hoje</div>
  <button class="btn-clear" id="btnClear">🗑️ Limpar dia</button>
</div>

<div class="food-list" id="foodList">
  <!-- Preenchido via JavaScript -->
</div>

    <div class="log-header">
      <div class="section-title">Alimentos de Hoje</div>
      <button class="btn-clear" id="btnClear">Limpar dia</button>
    </div>

    <div class="food-list" id="foodList">
      <div class="empty-state">
        <div class="empty-icon">🥗</div>
        <div class="empty-text">Nenhum alimento registrado ainda.<br>Use a busca acima para começar.</div>
      </div>
    </div>
  </div>

  <!-- HISTÓRICO e METAS (mantidos por enquanto) -->
  <div class="nav-panel" id="panelHistory">...</div>
  <div class="nav-panel" id="panelGoals">...</div>

</div>

<!-- Modal e Toast -->
<div class="overlay" id="modalOverlay">...</div>
<div class="toast" id="toast"></div>

<script src="assets/app.js"></script>
</body>
</html>