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
  <title>FitSync — Diário Alimentar + Treinos</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/styles.css" />
  <style>
    /* Estilos adicionais para treinos */
    .workout-add-card {
      background: white;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 28px;
      margin-bottom: 32px;
      box-shadow: var(--shadow);
    }
    .workout-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }
    .workout-title {
      font-family: 'Playfair Display', serif;
      font-size: 20px;
      font-weight: 700;
      color: var(--navy);
    }
    .workout-type-selector {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 20px;
    }
    .workout-type-btn {
      padding: 10px 20px;
      border: 2px solid var(--border);
      background: white;
      border-radius: 14px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }
    .workout-type-btn:hover {
      border-color: var(--accent);
      transform: translateY(-2px);
    }
    .workout-type-btn.active {
      background: linear-gradient(90deg, var(--accent), var(--accent2));
      color: white;
      border-color: transparent;
    }
    .workout-form-row {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }
    .workout-form-group {
      flex: 1;
      min-width: 150px;
    }
    .workout-form-group label {
      display: block;
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--muted);
      margin-bottom: 8px;
    }
    .workout-input, .workout-notes {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid var(--border);
      border-radius: 14px;
      font-size: 15px;
      font-family: 'DM Sans', sans-serif;
      background: #fafcff;
    }
    .workout-input:focus, .workout-notes:focus {
      border-color: var(--accent);
      outline: none;
    }
    .exercises-section {
      margin-top: 24px;
      margin-bottom: 24px;
      padding-top: 20px;
      border-top: 2px solid var(--border);
    }
    .exercises-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .btn-add-exercise {
      padding: 8px 16px;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 12px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
    }
    .exercise-card {
      background: var(--surface2);
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: flex-end;
    }
    .exercise-field {
      flex: 1;
      min-width: 80px;
    }
    .exercise-field input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 10px;
      font-size: 14px;
    }
    .exercise-field label {
      font-size: 10px;
      color: var(--muted);
      display: block;
      margin-bottom: 4px;
    }
    .btn-remove-exercise {
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: var(--muted);
      padding: 8px;
    }
    .btn-remove-exercise:hover {
      color: var(--danger);
    }
    .btn-save-workout {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: white;
      border: none;
      border-radius: 16px;
      font-weight: 700;
      font-size: 16px;
      cursor: pointer;
    }
    .workout-list {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .workout-history-item {
      background: white;
      border: 1.5px solid var(--border);
      border-radius: 20px;
      padding: 20px;
      transition: all 0.3s;
      cursor: pointer;
    }
    .workout-history-item:hover {
      border-color: var(--accent);
      transform: translateX(4px);
    }
    .workout-history-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      flex-wrap: wrap;
      gap: 8px;
    }
    .workout-badge {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
    }
    .workout-badge.strength { background: #e8effd; color: var(--accent); }
    .workout-badge.cardio { background: #e6fff4; color: #059669; }
    .workout-badge.hiit { background: #fff4e6; color: #d97706; }
    .workout-stats {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      font-size: 13px;
      color: var(--muted);
    }
    .analysis-rating {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 20px;
      background: #10b981;
      color: white;
      font-size: 11px;
      font-weight: 700;
      margin-right: 8px;
    }
    .btn-analyze {
      background: none;
      border: 1px solid var(--accent);
      color: var(--accent);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      cursor: pointer;
    }
    .analysis-modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }
    .analysis-modal-content {
      background: white;
      border-radius: 28px;
      max-width: 500px;
      width: 90%;
      max-height: 80vh;
      overflow-y: auto;
      padding: 28px;
    }
    .analysis-modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    .analysis-modal-title {
      font-family: 'Playfair Display', serif;
      font-size: 24px;
      font-weight: 700;
    }
    .analysis-close {
      background: none;
      border: none;
      font-size: 28px;
      cursor: pointer;
    }
    .analysis-section {
      margin-bottom: 20px;
    }
    .analysis-section h4 {
      font-size: 14px;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 10px;
    }
    .analysis-section ul {
      margin-left: 20px;
      color: var(--muted);
    }
    .analysis-motivation {
      background: #f0f5ff;
      padding: 16px;
      border-radius: 16px;
      text-align: center;
      font-style: italic;
      margin-top: 16px;
    }
    .mood-selector {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    .mood-btn {
      padding: 8px 14px;
      border: 1px solid var(--border);
      background: white;
      border-radius: 30px;
      font-size: 13px;
      cursor: pointer;
    }
    .mood-btn.active {
      background: var(--accent);
      color: white;
    }
    #quickExercises {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }
    .quick-ex-btn {
      padding: 4px 12px;
      border: 1px solid var(--border);
      border-radius: 20px;
      background: white;
      font-size: 12px;
      cursor: pointer;
    }
  </style>
</head>
<body>

<!-- HEADER -->
<header>
    <div class="logo">
        <?php if (file_exists(__DIR__ . '/assets/icon.png')): ?>
            <img src="assets/icon.png" alt="FitSync" class="logo-img" />
        <?php else: ?>
            <div class="logo-text">Fit<span>Sync</span></div>
        <?php endif; ?>
    </div>
    <div class="header-right">
        <div class="date-badge" id="dateBadge"></div>
        <div class="user-badge">
            <?php if ($avatar): ?>
                <img class="user-avatar" src="<?= htmlspecialchars($avatar) ?>" alt="<?= $userFirst ?>" />
            <?php else: ?>
                <div class="user-initials"><?= $initials ?></div>
            <?php endif; ?>
            <span class="user-name"><?= $userFirst ?></span>
            <button class="btn-logout" id="btnLogout" title="Sair">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
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
    <button class="nav-tab" data-panel="panelWorkout">
      <span class="tab-icon">💪</span>
      <span class="tab-label">Treinos</span>
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
          <input type="text" class="search-input" id="foodInput" placeholder="Busque um alimento..." autocomplete="off" />
          <div class="search-results" id="searchResults"></div>
        </div>
        <input type="number" class="qty-input" id="qtyInput" placeholder="Qtd (g)" min="1" />
        <button class="btn-add" id="btnAdd">✦ Analisar com IA</button>
      </div>
      <div class="ai-result" id="aiResult" style="display:none;">
        <div class="ai-result-inner">
          <div class="ai-title" id="aiTitle"></div>
          <div class="ai-nutrients" id="aiNutrients"></div>
        </div>
      </div>
    </div>

    <div class="log-header">
      <div class="section-title">Alimentos de Hoje</div>
      <button class="btn-clear" id="btnClear">🗑️ Limpar dia</button>
    </div>
    <div class="food-list" id="foodList"></div>
  </div>

  <!-- ══════════════ PAINEL: TREINOS ══════════════ -->
  <div class="nav-panel" id="panelWorkout" style="display:none;">
    <div class="workout-add-card">
      <div class="workout-header">
        <div class="workout-title">🏋️‍♂️ Registrar Treino</div>
      </div>

      <div class="workout-type-selector">
        <button class="workout-type-btn" data-type="strength">💪 Força</button>
        <button class="workout-type-btn" data-type="cardio">🏃 Cardio</button>
        <button class="workout-type-btn" data-type="hiit">⚡ HIIT</button>
        <button class="workout-type-btn" data-type="yoga">🧘 Yoga</button>
        <button class="workout-type-btn" data-type="functional">🎯 Funcional</button>
        <button class="workout-type-btn" data-type="other">📝 Outro</button>
      </div>

      <div class="workout-form-row">
        <div class="workout-form-group">
          <label>⏱️ Duração (minutos)</label>
          <input type="number" class="workout-input" id="workoutDuration" placeholder="Ex: 60" min="1" max="720">
        </div>
        <div class="workout-form-group">
          <label>⚡ Intensidade</label>
          <select class="workout-input" id="workoutIntensity">
            <option value="low">Baixa</option>
            <option value="medium" selected>Média</option>
            <option value="high">Alta</option>
          </select>
        </div>
        <div class="workout-form-group">
          <label>📊 PSE (0-10)</label>
          <input type="number" class="workout-input" id="workoutEffort" placeholder="Ex: 7" min="0" max="10">
        </div>
      </div>

      <div class="exercises-section">
        <div class="exercises-header">
          <div class="exercises-title">📋 Exercícios Realizados</div>
          <button class="btn-add-exercise" id="btnAddExercise">+ Adicionar exercício</button>
        </div>
        <div id="exercisesList"></div>
        <div id="quickExercises"></div>
      </div>

      <div class="workout-form-row">
        <div class="workout-form-group">
          <label>🔋 Energia antes (1-10)</label>
          <input type="number" class="workout-input" id="workoutEnergy" placeholder="Ex: 7" min="1" max="10">
        </div>
        <div class="workout-form-group">
          <label>😊 Humor</label>
          <div class="mood-selector" id="moodSelector">
            <button class="mood-btn" data-mood="great">Ótimo</button>
            <button class="mood-btn" data-mood="good">Bom</button>
            <button class="mood-btn active" data-mood="neutral">Neutro</button>
            <button class="mood-btn" data-mood="tired">Cansado</button>
            <button class="mood-btn" data-mood="exhausted">Exausto</button>
          </div>
        </div>
      </div>

      <textarea class="workout-notes" id="workoutFeeling" rows="2" placeholder="Como foi o treino? O que sentiu?"></textarea>
      <textarea class="workout-notes" id="workoutNotes" rows="2" placeholder="Notas adicionais"></textarea>

      <button class="btn-save-workout" id="btnSaveWorkout">💾 Salvar Treino</button>
    </div>

    <div class="log-header" style="margin-top: 24px;">
      <div class="section-title">Treinos de Hoje</div>
      <button class="btn-clear" id="btnClearWorkouts">🗑️ Limpar treinos</button>
    </div>
    <div id="workoutList" class="workout-list">
      <div class="empty-state">
        <div class="empty-icon">💪</div>
        <div class="empty-text">Nenhum treino registrado hoje.</div>
      </div>
    </div>
  </div>

  <!-- ══════════════ PAINEL: HISTÓRICO ══════════════ -->
  <div class="nav-panel" id="panelHistory" style="display:none;">
    <div class="section-title" style="margin-bottom:24px;">Histórico dos Últimos 7 Dias</div>
    <div id="historyContent">
      <div class="empty-state">Carregando...</div>
    </div>
  </div>

  <!-- ══════════════ PAINEL: METAS ══════════════ -->
  <div class="nav-panel" id="panelGoals" style="display:none;">
    <div class="section-title" style="margin-bottom:8px;">Minhas Metas Diárias</div>
    <p style="color:var(--muted);margin-bottom:32px;">Defina seus objetivos nutricionais diários</p>
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

</div>

<div class="toast" id="toast"></div>

<script src="assets/app.js"></script>
<script>
// ====================== VARIÁVEIS GLOBAIS ======================
let currentDate = new Date().toISOString().slice(0, 10);
let currentWorkoutType = 'strength';
let selectedMood = 'neutral';

// ====================== FUNÇÕES DE TREINO ======================

function addExerciseCard(exerciseName = '') {
  const container = document.getElementById('exercisesList');
  const card = document.createElement('div');
  card.className = 'exercise-card';
  card.innerHTML = `
    <div class="exercise-field" style="flex:2">
      <label>Exercício</label>
      <input type="text" class="exercise-name" placeholder="Ex: Supino reto" value="${exerciseName.replace(/"/g, '&quot;')}">
    </div>
    <div class="exercise-field">
      <label>Séries</label>
      <input type="number" class="exercise-sets" value="3" min="1">
    </div>
    <div class="exercise-field">
      <label>Repetições</label>
      <input type="number" class="exercise-reps" value="10" min="1">
    </div>
    <div class="exercise-field">
      <label>Carga (kg)</label>
      <input type="number" class="exercise-weight" value="0" min="0" step="2.5">
    </div>
    <div class="exercise-field">
      <label>Descanso (s)</label>
      <input type="number" class="exercise-rest" value="60" min="0">
    </div>
    <button class="btn-remove-exercise" onclick="this.closest('.exercise-card').remove()">✕</button>
  `;
  container.appendChild(card);
}

function collectExercises() {
  const exercises = [];
  document.querySelectorAll('#exercisesList .exercise-card').forEach(card => {
    const name = card.querySelector('.exercise-name')?.value || '';
    if (name.trim()) {
      exercises.push({
        name: name,
        sets: parseInt(card.querySelector('.exercise-sets')?.value) || 0,
        reps: parseInt(card.querySelector('.exercise-reps')?.value) || 0,
        weight: parseFloat(card.querySelector('.exercise-weight')?.value) || 0,
        rest_sec: parseInt(card.querySelector('.exercise-rest')?.value) || 60
      });
    }
  });
  return exercises;
}

async function saveWorkout() {
  const duration = parseInt(document.getElementById('workoutDuration')?.value);
  const intensity = document.getElementById('workoutIntensity')?.value;
  const effort = parseInt(document.getElementById('workoutEffort')?.value);
  const energy = parseInt(document.getElementById('workoutEnergy')?.value);
  const feeling = document.getElementById('workoutFeeling')?.value || '';
  const notes = document.getElementById('workoutNotes')?.value || '';
  const exercises = collectExercises();
  
  if (!duration || duration <= 0) {
    toast('Informe a duração do treino.', true);
    return;
  }
  
  const payload = {
    workout_date: currentDate,
    workout_type: currentWorkoutType,
    duration_min: duration,
    intensity: intensity,
    perceived_effort: effort || null,
    energy_level: energy || null,
    feeling: feeling,
    notes: notes,
    mood: selectedMood,
    exercises: exercises
  };
  
  const btn = document.getElementById('btnSaveWorkout');
  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = '⏳ Salvando...';
  
  try {
    const res = await fetch('api/workouts.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      credentials: 'same-origin'
    });
    
    const data = await res.json();
    
    if (data.success) {
      toast('✅ Treino salvo!');
      document.getElementById('workoutDuration').value = '';
      document.getElementById('workoutEffort').value = '';
      document.getElementById('workoutEnergy').value = '';
      document.getElementById('workoutFeeling').value = '';
      document.getElementById('workoutNotes').value = '';
      document.getElementById('exercisesList').innerHTML = '';
      addExerciseCard();
      await loadWorkoutsForDate(currentDate);
      
      if (data.workout && data.workout.id) {
        setTimeout(() => {
          if (confirm('Deseja analisar este treino com IA?')) {
            analyzeWorkout(data.workout.id);
          }
        }, 500);
      }
    } else {
      toast(data.error || 'Erro ao salvar', true);
    }
  } catch (err) {
    toast('Erro de conexão', true);
  } finally {
    btn.disabled = false;
    btn.textContent = originalText;
  }
}

async function loadWorkoutsForDate(date) {
  try {
    const res = await fetch(`api/workouts.php?date=${date}`, { credentials: 'same-origin' });
    const data = await res.json();
    const container = document.getElementById('workoutList');
    
    if (!data.workouts || data.workouts.length === 0) {
      container.innerHTML = `<div class="empty-state"><div class="empty-icon">💪</div><div class="empty-text">Nenhum treino registrado hoje.</div></div>`;
      return [];
    }
    
    container.innerHTML = data.workouts.map(workout => {
      const analysis = workout.ai_analysis ? JSON.parse(workout.ai_analysis) : null;
      return `
        <div class="workout-history-item" onclick="showWorkoutDetail(${workout.id})">
          <div class="workout-history-header">
            <div>
              <span class="workout-badge ${workout.workout_type}">${getWorkoutTypeName(workout.workout_type)}</span>
              <span style="margin-left:8px;font-weight:600;">${workout.duration_min} min</span>
              <span style="margin-left:8px;font-size:13px;color:var(--muted);">${getIntensityName(workout.intensity)}</span>
            </div>
            <div>
              ${analysis && analysis.rating ? `<span class="analysis-rating">⭐ ${analysis.rating}/10</span>` : `<button class="btn-analyze" onclick="event.stopPropagation(); analyzeWorkout(${workout.id})">🔍 Analisar</button>`}
              <button class="btn-remove-exercise" onclick="event.stopPropagation(); deleteWorkout(${workout.id})" style="margin-left:8px;">✕</button>
            </div>
          </div>
          <div class="workout-stats">
            <span>💪 ${workout.exercises ? JSON.parse(workout.exercises).length : 0} exercícios</span>
            ${workout.perceived_effort ? `<span>📊 PSE: ${workout.perceived_effort}/10</span>` : ''}
            <span>😊 ${getMoodName(workout.mood)}</span>
          </div>
          ${workout.feeling ? `<div style="margin-top:8px;font-size:13px;color:var(--muted);">💬 "${workout.feeling.substring(0, 100)}"</div>` : ''}
        </div>
      `;
    }).join('');
    
    return data.workouts;
  } catch (err) {
    console.error(err);
    return [];
  }
}

async function analyzeWorkout(workoutId) {
  toast('🤖 Analisando treino...');
  try {
    const res = await fetch('api/workouts.php?action=analyze', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ workout_id: workoutId }),
      credentials: 'same-origin'
    });
    const data = await res.json();
    if (data.analysis) {
      showAnalysisModal(data.analysis);
      await loadWorkoutsForDate(currentDate);
    } else {
      toast('Erro na análise', true);
    }
  } catch (err) {
    toast('Erro na análise', true);
  }
}

function showAnalysisModal(analysis) {
  const existing = document.querySelector('.analysis-modal');
  if (existing) existing.remove();
  
  const modal = document.createElement('div');
  modal.className = 'analysis-modal';
  modal.innerHTML = `
    <div class="analysis-modal-content">
      <div class="analysis-modal-header">
        <div class="analysis-modal-title">📊 Análise do Treino</div>
        <button class="analysis-close" onclick="this.closest('.analysis-modal').remove()">✕</button>
      </div>
      <div class="analysis-section">
        <h4>⭐ Avaliação</h4>
        <div style="font-size:48px;font-weight:700;color:var(--accent);">${analysis.rating}/10</div>
      </div>
      <div class="analysis-section">
        <h4>✅ Pontos Positivos</h4>
        <ul>${(analysis.positive_points || []).map(p => `<li>${p}</li>`).join('')}</ul>
      </div>
      <div class="analysis-section">
        <h4>📈 Melhorias</h4>
        <ul>${(analysis.improvements || []).map(p => `<li>${p}</li>`).join('')}</ul>
      </div>
      <div class="analysis-section">
        <h4>🛡️ Dicas de Segurança</h4>
        <ul>${(analysis.safety_tips || []).map(p => `<li>${p}</li>`).join('')}</ul>
      </div>
      <div class="analysis-section">
        <h4>🥗 Dicas Nutricionais</h4>
        <ul>${(analysis.nutrition_tips || []).map(p => `<li>${p}</li>`).join('')}</ul>
      </div>
      <div class="analysis-motivation">
        ✨ "${analysis.motivation_phrase || 'Continue evoluindo!'}"
      </div>
      <button onclick="this.closest('.analysis-modal').remove()" style="width:100%;margin-top:20px;padding:12px;background:var(--accent);color:white;border:none;border-radius:12px;cursor:pointer;">Fechar</button>
    </div>
  `;
  document.body.appendChild(modal);
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
}

async function showWorkoutDetail(workoutId) {
  try {
    const res = await fetch(`api/workouts.php?id=${workoutId}`, { credentials: 'same-origin' });
    const data = await res.json();
    if (data.workout) {
      const workout = data.workout;
      const exercises = JSON.parse(workout.exercises || '[]');
      let exercisesHtml = '';
      if (exercises.length) {
        exercisesHtml = `
          <div class="analysis-section">
            <h4>📋 Exercícios</h4>
            <table style="width:100%;font-size:13px;border-collapse:collapse;">
              <tr style="border-bottom:1px solid var(--border);">
                <th style="text-align:left;padding:8px;">Exercício</th>
                <th>Séries</th>
                <th>Reps</th>
                <th>Carga</th>
              </tr>
              ${exercises.map(ex => `
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:8px;">${ex.name}</td>
                  <td style="text-align:center;">${ex.sets}</td>
                  <td style="text-align:center;">${ex.reps}</td>
                  <td style="text-align:center;">${ex.weight}kg</td>
                 </tr>
              `).join('')}
             </table>
          </div>
        `;
      }
      
      const modal = document.createElement('div');
      modal.className = 'analysis-modal';
      modal.innerHTML = `
        <div class="analysis-modal-content">
          <div class="analysis-modal-header">
            <div class="analysis-modal-title">💪 Detalhes do Treino</div>
            <button class="analysis-close" onclick="this.closest('.analysis-modal').remove()">✕</button>
          </div>
          <div class="analysis-section">
            <h4>📅 Informações</h4>
            <p><strong>Tipo:</strong> ${getWorkoutTypeName(workout.workout_type)}</p>
            <p><strong>Duração:</strong> ${workout.duration_min} min</p>
            <p><strong>Intensidade:</strong> ${getIntensityName(workout.intensity)}</p>
            ${workout.perceived_effort ? `<p><strong>PSE:</strong> ${workout.perceived_effort}/10</p>` : ''}
          </div>
          ${exercisesHtml}
          ${workout.feeling ? `<div class="analysis-section"><h4>💬 Sentimento</h4><p>"${workout.feeling}"</p></div>` : ''}
          <div style="display:flex;gap:12px;margin-top:20px;">
            <button onclick="analyzeWorkout(${workout.id});this.closest('.analysis-modal').remove();" style="flex:1;padding:12px;background:var(--accent);color:white;border:none;border-radius:12px;cursor:pointer;">🤖 Analisar</button>
            <button onclick="this.closest('.analysis-modal').remove()" style="flex:1;padding:12px;background:var(--surface2);border-radius:12px;cursor:pointer;">Fechar</button>
          </div>
        </div>
      `;
      document.body.appendChild(modal);
      modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });
    }
  } catch (err) {
    toast('Erro ao carregar detalhes', true);
  }
}

async function deleteWorkout(workoutId) {
  if (!confirm('Remover este treino?')) return;
  try {
    await fetch(`api/workouts.php?id=${workoutId}`, { method: 'DELETE', credentials: 'same-origin' });
    toast('Treino removido');
    await loadWorkoutsForDate(currentDate);
  } catch (err) {
    toast('Erro ao remover', true);
  }
}

function getWorkoutTypeName(type) {
  const types = { strength: 'Força', cardio: 'Cardio', hiit: 'HIIT', yoga: 'Yoga', functional: 'Funcional', other: 'Outro' };
  return types[type] || type;
}

function getIntensityName(intensity) {
  const intensities = { low: 'Baixa', medium: 'Média', high: 'Alta' };
  return intensities[intensity] || intensity;
}

function getMoodName(mood) {
  const moods = { great: 'Ótimo', good: 'Bom', neutral: 'Neutro', tired: 'Cansado', exhausted: 'Exausto' };
  return moods[mood] || mood;
}

function initWorkoutPanel() {
  // Tipo de treino
  document.querySelectorAll('.workout-type-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.workout-type-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentWorkoutType = btn.dataset.type;
    });
  });
  
  // Humor
  document.querySelectorAll('.mood-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      selectedMood = btn.dataset.mood;
    });
  });
  
  // Adicionar exercício
  document.getElementById('btnAddExercise')?.addEventListener('click', () => addExerciseCard());
  
  // Salvar treino
  document.getElementById('btnSaveWorkout')?.addEventListener('click', saveWorkout);
  
  // Limpar treinos
  document.getElementById('btnClearWorkouts')?.addEventListener('click', async () => {
    if (!confirm('Remover todos os treinos de hoje?')) return;
    const workouts = await loadWorkoutsForDate(currentDate);
    for (const w of workouts) {
      await fetch(`api/workouts.php?id=${w.id}`, { method: 'DELETE', credentials: 'same-origin' });
    }
    toast('Treinos removidos');
    await loadWorkoutsForDate(currentDate);
  });
  
  // Sugestões rápidas
  const quickExercises = ['Supino', 'Agachamento', 'Levantamento Terra', 'Desenvolvimento', 'Rosca Direta', 'Tríceps', 'Puxada', 'Leg Press', 'Prancha', 'Corrida'];
  const quickContainer = document.getElementById('quickExercises');
  if (quickContainer) {
    quickExercises.forEach(ex => {
      const btn = document.createElement('button');
      btn.textContent = ex;
      btn.className = 'quick-ex-btn';
      btn.onclick = () => addExerciseCard(ex);
      quickContainer.appendChild(btn);
    });
  }
  
  // Adicionar primeiro exercício
  if (document.getElementById('exercisesList')?.children.length === 0) {
    addExerciseCard();
  }
}

// ====================== INICIALIZAÇÃO ======================
document.addEventListener('DOMContentLoaded', async () => {
  // Data
  const days = ['Domingo','Segunda','Terça','Quarta','Quinta','Sexta','Sábado'];
  const months = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
  const now = new Date();
  document.getElementById('dateBadge').textContent = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]}`;
  
  // Navegação
  document.querySelectorAll('.nav-tab').forEach(tab => {
    tab.addEventListener('click', async () => {
      const target = tab.dataset.panel;
      document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.nav-panel').forEach(p => p.style.display = 'none');
      tab.classList.add('active');
      const panel = document.getElementById(target);
      if (panel) panel.style.display = 'block';
      
      if (target === 'panelHistory') loadHistory();
      if (target === 'panelGoals') loadGoals();
      if (target === 'panelWorkout') {
        await loadWorkoutsForDate(currentDate);
        initWorkoutPanel();
      }
    });
  });
  
  // Carregar dados iniciais se existir a função do app.js
  if (typeof loadLogs === 'function') await loadLogs();
  
  // Inicializar treinos se o painel estiver visível (não está, mas ok)
  if (document.getElementById('panelWorkout')) {
    // Pré-inicializar
  }
});

// Funções auxiliares para histórico e metas (já existentes no app.js)
async function loadHistory() {
  const res = await fetch('api/foods.php?action=summary&days=7');
  const data = await res.json();
  const container = document.getElementById('historyContent');
  if (!data.history || !data.history.length) {
    container.innerHTML = `<div class="empty-state">Nenhum dado ainda.</div>`;
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
  const days = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
  const [y,m,d] = dateStr.split('-');
  const dt = new Date(y, m-1, d);
  return `${days[dt.getDay()]}, ${d}/${m}`;
}

async function loadGoals() {
  const res = await fetch('api/foods.php?action=goals');
  const data = await res.json();
  if (data.goals) {
    document.getElementById('goalCal').value = data.goals.daily_cal || 2000;
    document.getElementById('goalProt').value = data.goals.daily_prot || 150;
    document.getElementById('goalCarb').value = data.goals.daily_carb || 250;
    document.getElementById('goalFat').value = data.goals.daily_fat || 65;
  }
}

document.getElementById('btnSaveGoals')?.addEventListener('click', async () => {
  const payload = {
    daily_cal: parseInt(document.getElementById('goalCal').value),
    daily_prot: parseInt(document.getElementById('goalProt').value),
    daily_carb: parseInt(document.getElementById('goalCarb').value),
    daily_fat: parseInt(document.getElementById('goalFat').value),
  };
  const res = await fetch('api/foods.php?action=goals', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  const data = await res.json();
  if (data.success) {
    toast('✅ Metas salvas!');
    if (typeof updateSummary === 'function') updateSummary();
  } else {
    toast(data.error || 'Erro', true);
  }
});

function toast(msg, isError = false) {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = msg;
  el.className = `toast show${isError ? ' error' : ''}`;
  setTimeout(() => el.classList.remove('show'), 3000);
}

// Logout
document.getElementById('btnLogout')?.addEventListener('click', async () => {
  if (confirm('Deseja sair?')) {
    await fetch('api/auth.php?action=logout', { method: 'GET' });
    window.location.href = 'login.php';
  }
});
</script>
</body>
</html>