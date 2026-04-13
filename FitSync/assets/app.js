/* ============================================================
   FitSync — App.js (v3.0)
   ============================================================ */

// Expose State globally so inline scripts can access it
window.State = {
  logs:          [],
  goals:         { daily_cal: 2000, daily_prot: 150, daily_carb: 250, daily_fat: 65 },
  date:          new Date().toISOString().slice(0, 10),
  mealType:      'snack',
  pendingFood:   null,
  searchTimeout: null,
};
const State = window.State;

// ── API helper ─────────────────────────────────────────────────
async function api(url, opts = {}) {
  const r = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...opts,
  });
  if (!r.ok && r.status !== 400) {
    console.error('API error', r.status, url);
  }
  return await r.json();
}

// ── Toast ──────────────────────────────────────────────────────
function toast(msg, isError = false) {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = msg;
  el.className = `toast show${isError ? ' error' : ''}`;
  setTimeout(() => el.classList.remove('show'), 4000);
}

// ====================== LIVE SEARCH ======================
function showSearchResults(results) {
  const container = document.getElementById('searchResults');
  if (!container) return;

  if (!results || !results.length) {
    container.innerHTML = `<div style="padding:16px;color:#888;text-align:center;font-size:14px;">Nenhum alimento encontrado</div>`;
    container.classList.add('show');
    return;
  }

  let html = '';
  results.forEach(food => {
    const cal  = food.per100g?.cal  != null ? Math.round(food.per100g.cal)  : '—';
    const prot = food.per100g?.prot != null ? food.per100g.prot : '—';
    const img  = food.image
      ? `<img src="${food.image}" style="width:38px;height:38px;object-fit:contain;border-radius:8px;margin-right:12px;flex-shrink:0;" loading="lazy">`
      : '';
    const badge = food.source ? `<span style="background:#f0f5ff;color:#255ff1;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;">${food.source.toUpperCase()}</span>` : '';

    html += `
      <div class="search-result-item" data-id="${food.id || ''}" style="display:flex;align-items:center;">
        ${img}
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <strong>${food.name}</strong>
            ${badge}
          </div>
          ${food.brand ? `<div style="font-size:12px;color:#94a3b8;margin-top:1px;">${food.brand}</div>` : ''}
          <div style="margin-top:4px;font-size:13px;color:#64748b;">
            ${cal} kcal · ${prot}g prot · 100g
          </div>
        </div>
      </div>`;
  });

  container.innerHTML = html;
  container.classList.add('show');

  container.querySelectorAll('.search-result-item').forEach((item, i) => {
    item.addEventListener('click', () => {
      selectFood(results[i]);
      container.classList.remove('show');
    });
  });
}

function selectFood(food) {
  const qty = 100;
  State.pendingFood = {
    food_id:   food.id   || null,
    food_name: food.name,
    qty_g:     qty,
    unit:      'g',
    cal:       food.per100g?.cal     ?? 0,
    prot:      food.per100g?.prot    ?? 0,
    carb:      food.per100g?.carb    ?? 0,
    fat:       food.per100g?.fat     ?? 0,
    fiber:     food.per100g?.fiber   ?? null,
    sugar:     food.per100g?.sugar   ?? null,
    sodium:    food.per100g?.sodium  ?? null,
    sat_fat:   food.per100g?.sat_fat ?? null,
  };

  const qtyInput = document.getElementById('qtyInput');
  if (qtyInput) qtyInput.value = qty;

  const aiResult = document.getElementById('aiResult');
  if (aiResult) aiResult.style.display = 'block';

  const aiTitle = document.getElementById('aiTitle');
  if (aiTitle) aiTitle.innerHTML = `✦ ${food.name}${food.brand ? ` <small style="opacity:.7">${food.brand}</small>` : ''}`;

  const aiNutrients = document.getElementById('aiNutrients');
  if (aiNutrients) aiNutrients.innerHTML = `
    <div><strong>${food.per100g?.cal ?? '—'}</strong> kcal &nbsp;·&nbsp;
         <strong>${food.per100g?.prot ?? '—'}g</strong> prot &nbsp;·&nbsp;
         <strong>${food.per100g?.carb ?? '—'}g</strong> carb &nbsp;·&nbsp;
         <strong>${food.per100g?.fat ?? '—'}g</strong> gord</div>
    <div style="font-size:12px;color:#94a3b8;margin-top:4px;">por 100g · ajuste a quantidade acima</div>
  `;

  const btn = document.getElementById('btnAdd');
  if (btn) {
    btn.innerHTML = '+ Adicionar ao Diário';
    btn.onclick   = confirmAddFromSearch;
  }
}

// ====================== ADICIONAR ======================
async function confirmAddFromSearch() {
  if (!State.pendingFood) return;

  const qty    = parseFloat(document.getElementById('qtyInput')?.value) || 100;
  const factor = qty / 100;

  const payload = {
    ...State.pendingFood,
    qty_g:     qty,
    cal:       Math.round(State.pendingFood.cal  * factor),
    prot:      Math.round((State.pendingFood.prot || 0) * factor * 10) / 10,
    carb:      Math.round((State.pendingFood.carb || 0) * factor * 10) / 10,
    fat:       Math.round((State.pendingFood.fat  || 0) * factor * 10) / 10,
    meal_type: State.mealType,
  };

  const data = await api(`api/foods.php?date=${State.date}`, {
    method: 'POST',
    body:   JSON.stringify(payload),
  });

  if (data.error) return toast(data.error, true);

  toast(`✅ ${State.pendingFood.food_name} adicionado!`);
  resetUI();
  await loadLogs();
}

async function analyzeWithAI() {
  const query    = document.getElementById('foodInput')?.value.trim();
  const qtyInput = parseFloat(document.getElementById('qtyInput')?.value) || 100;

  if (!query) return toast('Digite o alimento', true);

  const btn = document.getElementById('btnAdd');
  if (btn) { btn.disabled = true; btn.innerHTML = '⏳ Analisando...'; }

  try {
    const data = await api('api/analyze.php', {
      method: 'POST',
      body:   JSON.stringify({ query, qty: qtyInput }),
    });

    if (data.error) throw new Error(data.error);

    State.pendingFood = { ...data.food, meal_type: State.mealType };

    const aiResult = document.getElementById('aiResult');
    if (aiResult) aiResult.style.display = 'block';

    const aiTitle = document.getElementById('aiTitle');
    if (aiTitle) aiTitle.textContent = `✦ ${data.food.name} · ${data.food.qty}g`;

    const aiNutrients = document.getElementById('aiNutrients');
    if (aiNutrients) aiNutrients.innerHTML = `
      <div><strong>${Math.round(data.food.cal)}</strong> kcal &nbsp;·&nbsp;
           <strong>${data.food.prot}g</strong> prot &nbsp;·&nbsp;
           <strong>${data.food.carb}g</strong> carb &nbsp;·&nbsp;
           <strong>${data.food.fat}g</strong> gord</div>
    `;

    if (btn) { btn.innerHTML = '+ Adicionar ao Diário'; btn.onclick = confirmAddFromSearch; }

  } catch (e) {
    toast(e.message || 'Erro ao analisar', true);
    if (btn) btn.innerHTML = '✦ Analisar com IA';
  } finally {
    if (btn) btn.disabled = false;
  }
}

function resetUI() {
  State.pendingFood = null;
  const fi = document.getElementById('foodInput');
  const qi = document.getElementById('qtyInput');
  const ai = document.getElementById('aiResult');
  const sr = document.getElementById('searchResults');
  const btn = document.getElementById('btnAdd');

  if (fi)  fi.value = '';
  if (qi)  qi.value = '';
  if (ai)  ai.style.display = 'none';
  if (sr)  sr.classList.remove('show');
  if (btn) { btn.innerHTML = '✦ Analisar com IA'; btn.onclick = analyzeWithAI; }
}

// ====================== CARREGAR DIÁRIO ======================
async function loadLogs() {
  const data = await api(`api/foods.php?date=${State.date}`);
  if (data.error) return toast(data.error, true);

  State.logs  = data.logs  || [];
  if (data.goals) State.goals = data.goals;
  updateSummary();
  renderFoodList();
}

// Expose for inline scripts
window.updateSummary = function updateSummary() {
  const totals = State.logs.reduce((acc, log) => ({
    cal:  acc.cal  + parseFloat(log.cal  || 0),
    prot: acc.prot + parseFloat(log.prot || 0),
    carb: acc.carb + parseFloat(log.carb || 0),
    fat:  acc.fat  + parseFloat(log.fat  || 0),
  }), { cal: 0, prot: 0, carb: 0, fat: 0 });

  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('totalCal',  Math.round(totals.cal));
  set('totalProt', Math.round(totals.prot));
  set('totalCarb', Math.round(totals.carb));
  set('totalFat',  Math.round(totals.fat));

  const g = State.goals;
  const setW = (id, v, m) => {
    const el = document.getElementById(id);
    if (el) el.style.width = Math.min(100, (v / (m || 1)) * 100) + '%';
  };
  setW('calProgress',  totals.cal,  g.daily_cal  || 2000);
  setW('protProgress', totals.prot, g.daily_prot || 150);
  setW('carbProgress', totals.carb, g.daily_carb || 250);
  setW('fatProgress',  totals.fat,  g.daily_fat  || 65);

  const rem = Math.round((g.daily_cal || 2000) - totals.cal);
  const calSub = document.getElementById('calSub');
  if (calSub) calSub.textContent =
    rem >= 0 ? `Restam ${rem} kcal da meta diária` : `${Math.abs(rem)} kcal acima da meta`;
}

// Also assign to local for internal use
const updateSummary = window.updateSummary;

function renderFoodList() {
  const container = document.getElementById('foodList');
  if (!container) return;

  if (!State.logs.length) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🥗</div>
        <div class="empty-text">Nenhum alimento registrado ainda.<br>Use a busca acima para começar.</div>
      </div>`;
    return;
  }

  const mealLabels = {
    breakfast: 'Café da manhã',
    lunch:     'Almoço',
    dinner:    'Jantar',
    snack:     'Lanche',
  };

  container.innerHTML = State.logs.map(log => `
    <div class="food-item" data-id="${log.id}">
      <div class="food-info">
        <div class="food-name">${log.food_name}</div>
        <div class="food-qty">${log.qty_g}g · ${mealLabels[log.meal_type] || log.meal_type}</div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="food-cal">${Math.round(log.cal)} kcal</div>
        <button onclick="deleteLog(${log.id})" title="Remover"
          style="background:none;border:none;cursor:pointer;font-size:18px;color:#cbd5e1;transition:color 0.2s;padding:4px;"
          onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#cbd5e1'">✕</button>
      </div>
    </div>
  `).join('');
}

async function deleteLog(id) {
  const data = await api(`api/foods.php?id=${id}`, { method: 'DELETE' });
  if (data.error) return toast(data.error, true);
  toast('Alimento removido');
  await loadLogs();
}

// ====================== INIT ======================
document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('foodList')) return;   // Not on main page

  // Logout
  document.getElementById('btnLogout')?.addEventListener('click', async () => {
    if (confirm('Deseja sair da sua conta?')) {
      await api('api/auth.php?action=logout', { method: 'GET' }).catch(() => {});
      window.location.href = 'login.php';
    }
  });

  // Meal selector
  document.querySelectorAll('.meal-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.meal-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      State.mealType = btn.dataset.meal;
    });
  });

  // Live search
  const foodInput = document.getElementById('foodInput');
  if (foodInput) {
    foodInput.addEventListener('input', () => {
      clearTimeout(State.searchTimeout);
      const q = foodInput.value.trim();

      if (q.length < 2) {
        document.getElementById('searchResults')?.classList.remove('show');
        // Reset add button if it was in "add" mode
        const btn = document.getElementById('btnAdd');
        if (btn && btn.textContent.includes('Adicionar')) {
          btn.innerHTML = '✦ Analisar com IA';
          btn.onclick = analyzeWithAI;
          State.pendingFood = null;
        }
        return;
      }

      State.searchTimeout = setTimeout(async () => {
        const res = await api(`api/search.php?q=${encodeURIComponent(q)}&source=all`);
        if (res.results) showSearchResults(res.results);
      }, 280);
    });

    foodInput.addEventListener('keydown', e => {
      if (e.key === 'Enter') analyzeWithAI();
      if (e.key === 'Escape') {
        document.getElementById('searchResults')?.classList.remove('show');
      }
    });
  }

  // Close dropdown on outside click
  document.addEventListener('click', e => {
    if (!e.target.closest('.search-wrap')) {
      document.getElementById('searchResults')?.classList.remove('show');
    }
  });

  // Add button default
  const btnAdd = document.getElementById('btnAdd');
  if (btnAdd) btnAdd.addEventListener('click', analyzeWithAI);

  // Clear all
  document.getElementById('btnClear')?.addEventListener('click', async () => {
    if (!State.logs.length) return toast('Nenhum alimento para remover.');
    if (!confirm('Remover todos os alimentos de hoje?')) return;
    await Promise.all(State.logs.map(log =>
      api(`api/foods.php?id=${log.id}`, { method: 'DELETE' })
    ));
    toast('Dia limpo! 🗑️');
    await loadLogs();
  });

  // Initial load
  await loadLogs();
});

// ====================== AUTH ======================
async function handleLogin() {
  const email   = document.getElementById('loginEmail')?.value.trim();
  const pass    = document.getElementById('loginPassword')?.value;
  const errorEl = document.getElementById('authError');
  if (!errorEl) return;

  errorEl.textContent = '';
  errorEl.style.color = '#e03c5a';

  if (!email || !pass) { errorEl.textContent = 'Preencha e-mail e senha.'; return; }

  const btn = document.getElementById('btnLogin');
  if (btn) { btn.disabled = true; btn.textContent = 'Entrando...'; }

  try {
    const data = await api('api/auth.php?action=login', {
      method: 'POST',
      body:   JSON.stringify({ email, password: pass }),
    });

    if (data.error) { errorEl.textContent = data.error; return; }

    if (data.success) {
      errorEl.style.color = '#10b981';
      errorEl.textContent = '✅ ' + (data.message || 'Login realizado!');
      setTimeout(() => { window.location.href = 'index.php'; }, 700);
    }
  } catch (err) {
    errorEl.textContent = 'Erro de conexão com o servidor.';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Entrar na conta →'; }
  }
}

async function handleRegister() {
  const name    = document.getElementById('regName')?.value.trim();
  const email   = document.getElementById('regEmail')?.value.trim();
  const pass    = document.getElementById('regPassword')?.value;
  const errorEl = document.getElementById('authError');
  if (!errorEl) return;

  errorEl.textContent = '';
  errorEl.style.color = '#e03c5a';

  if (!name || !email || !pass) { errorEl.textContent = 'Preencha todos os campos.'; return; }

  const btn = document.getElementById('btnRegister');
  if (btn) { btn.disabled = true; btn.textContent = 'Criando conta...'; }

  try {
    const data = await api('api/auth.php?action=register', {
      method: 'POST',
      body:   JSON.stringify({ name, email, password: pass }),
    });

    if (data.error) { errorEl.textContent = data.error; return; }

    if (data.success) {
      errorEl.style.color = '#10b981';
      errorEl.textContent = '✅ ' + (data.message || 'Conta criada!');
      setTimeout(() => { window.location.href = 'index.php'; }, 1000);
    }
  } catch (err) {
    errorEl.textContent = 'Erro de conexão com o servidor.';
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Criar minha conta →'; }
  }
}