/* ============================================================
   FitSync — App.js COMPLETO com USDA (Live Search + IA)
   ============================================================ */

const State = {
  logs: [],
  goals: { daily_cal: 2000, daily_prot: 150, daily_carb: 250, daily_fat: 65 },
  date: new Date().toISOString().slice(0, 10),
  mealType: 'snack',
  pendingFood: null,
  searchTimeout: null,
};

async function api(url, opts = {}) {
  const r = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...opts,
  });
  return await r.json();
}

function toast(msg, isError = false) {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent = msg;
  el.className = `toast show ${isError ? 'error' : ''}`;
  setTimeout(() => el.classList.remove('show'), 4000);
}

// ====================== LIVE SEARCH ======================
function showSearchResults(results) {
  const container = document.getElementById('searchResults');
  if (!container) return;

  if (!results.length) {
    container.innerHTML = `<div style="padding:16px;color:#888;text-align:center;">Nenhum alimento encontrado</div>`;
    container.classList.add('show');
    return;
  }

  // FIX: bloco unico — suporte a imagem incluido (era duplicado e o segundo estava FORA da funcao, causando ReferenceError)
  let html = '';
  results.forEach(food => {
    const cal  = food.per100g?.cal  ? Math.round(food.per100g.cal)  : 0;
    const prot = food.per100g?.prot ? food.per100g.prot : 0;
    const img  = food.image
      ? `<img src="${food.image}" style="width:40px;height:40px;object-fit:contain;border-radius:8px;margin-right:12px;" loading="lazy">`
      : '';
    html += `
      <div class="search-result-item" data-id="${food.id || ''}" style="display:flex;align-items:center;">
        ${img}
        <div style="flex:1;">
          <strong>${food.name}</strong>
          ${food.brand ? `<small> — ${food.brand}</small>` : ''}
          <div style="margin-top:4px;font-size:13px;color:#666;">
            ${cal} kcal/100g &bull; ${prot}g prot &bull; ${food.source?.toUpperCase() || ''}
          </div>
        </div>
      </div>`;
  });

  container.innerHTML = html;
  container.classList.add('show');

  container.querySelectorAll('.search-result-item').forEach(item => {
    item.addEventListener('click', () => {
      const selected = results.find(r => String(r.id) === String(item.dataset.id));
      if (selected) selectFood(selected);
      container.classList.remove('show');
    });
  });
}

function selectFood(food) {
  const qty = 100;

  State.pendingFood = {
    food_id:   food.id,
    food_name: food.name,
    qty_g:     qty,
    unit:      'g',
    cal:       food.per100g.cal,
    prot:      food.per100g.prot,
    carb:      food.per100g.carb,
    fat:       food.per100g.fat,
    fiber:     food.per100g.fiber   || null,
    sugar:     food.per100g.sugar   || null,
    sodium:    food.per100g.sodium  || null,
    sat_fat:   food.per100g.sat_fat || null,
  };

  document.getElementById('qtyInput').value = qty;
  document.getElementById('aiResult').style.display = 'block';
  document.getElementById('aiTitle').innerHTML =
    `✦ ${food.name} <small>${food.brand || food.source?.toUpperCase() || ''}</small>`;

  document.getElementById('aiNutrients').innerHTML = `
    <div><strong>${food.per100g.cal}</strong> kcal/100g</div>
    <div><strong>${food.per100g.prot}g</strong> prot &bull;
         <strong>${food.per100g.carb}g</strong> carb &bull;
         <strong>${food.per100g.fat}g</strong> gord</div>
  `;

  const btn = document.getElementById('btnAdd');
  btn.innerHTML = '+ Adicionar ao Diario';
  btn.onclick   = confirmAddFromSearch;
}

// ====================== ADICIONAR (USDA ou IA) ======================
async function confirmAddFromSearch() {
  if (!State.pendingFood) return;

  const qty    = parseFloat(document.getElementById('qtyInput').value) || 100;
  const factor = qty / 100;

  const payload = {
    ...State.pendingFood,
    qty_g:     qty,
    cal:       Math.round(State.pendingFood.cal  * factor),
    prot:      Math.round(State.pendingFood.prot * factor),
    carb:      Math.round(State.pendingFood.carb * factor),
    fat:       Math.round(State.pendingFood.fat  * factor),
    meal_type: State.mealType,
  };

  const data = await api(`api/foods.php?date=${State.date}`, {
    method: 'POST',
    body:   JSON.stringify(payload),
  });

  if (data.error) return toast(data.error, true);

  toast(`${State.pendingFood.food_name} adicionado!`);
  resetUI();
  await loadLogs();
}

async function analyzeWithAI() {
  const query    = document.getElementById('foodInput').value.trim();
  const qtyInput = parseFloat(document.getElementById('qtyInput').value) || 100;

  if (!query) return toast('Digite o alimento', true);

  const btn = document.getElementById('btnAdd');
  btn.disabled = true;
  btn.innerHTML = '&#8987; Analisando...';

  try {
    const data = await api('api/analyze.php', {
      method: 'POST',
      body:   JSON.stringify({ query, qty: qtyInput }),
    });

    if (data.error) throw new Error(data.error);

    State.pendingFood = { ...data.food, meal_type: State.mealType };
    document.getElementById('aiResult').style.display = 'block';
    document.getElementById('aiTitle').textContent = `✦ ${data.food.name} • ${data.food.qty}g`;
    document.getElementById('aiNutrients').innerHTML = `
      <div><strong>${Math.round(data.food.cal)}</strong> kcal</div>
      <div><strong>${data.food.prot}g</strong> prot &bull;
           <strong>${data.food.carb}g</strong> carb &bull;
           <strong>${data.food.fat}g</strong> gord</div>
    `;

    btn.innerHTML = '+ Adicionar ao Diario';
    btn.onclick   = confirmAddFromSearch;

  } catch (e) {
    toast(e.message, true);
  } finally {
    btn.disabled = false;
  }
}

function resetUI() {
  State.pendingFood = null;
  document.getElementById('foodInput').value = '';
  document.getElementById('qtyInput').value  = '';
  document.getElementById('aiResult').style.display = 'none';
  document.getElementById('searchResults').classList.remove('show');
  const btn = document.getElementById('btnAdd');
  btn.innerHTML = 'Analisar com IA';
  btn.onclick   = analyzeWithAI;
}

// ====================== CARREGAR DIARIO ======================
async function loadLogs() {
  const data = await api(`api/foods.php?date=${State.date}`);
  if (data.error) return toast(data.error, true);

  State.logs  = data.logs  || [];
  State.goals = data.goals || State.goals;
  updateSummary();
  renderFoodList();
}

function updateSummary() {
  const totals = State.logs.reduce((acc, log) => ({
    cal:  acc.cal  + parseFloat(log.cal  || 0),
    prot: acc.prot + parseFloat(log.prot || 0),
    carb: acc.carb + parseFloat(log.carb || 0),
    fat:  acc.fat  + parseFloat(log.fat  || 0),
  }), { cal: 0, prot: 0, carb: 0, fat: 0 });

  document.getElementById('totalCal').textContent  = Math.round(totals.cal);
  document.getElementById('totalProt').textContent = Math.round(totals.prot);
  document.getElementById('totalCarb').textContent = Math.round(totals.carb);
  document.getElementById('totalFat').textContent  = Math.round(totals.fat);

  const g = State.goals;
  document.getElementById('calProgress').style.width  = Math.min(100, (totals.cal  / g.daily_cal)  * 100) + '%';
  document.getElementById('protProgress').style.width = Math.min(100, (totals.prot / g.daily_prot) * 100) + '%';
  document.getElementById('carbProgress').style.width = Math.min(100, (totals.carb / g.daily_carb) * 100) + '%';
  document.getElementById('fatProgress').style.width  = Math.min(100, (totals.fat  / g.daily_fat)  * 100) + '%';

  const rem = Math.round(g.daily_cal - totals.cal);
  document.getElementById('calSub').textContent =
    rem >= 0 ? `Restam ${rem} kcal` : `${Math.abs(rem)} kcal acima`;
}

function renderFoodList() {
  const container = document.getElementById('foodList');
  if (!State.logs.length) {
    container.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">&#x1F957;</div>
        <div class="empty-text">Nenhum alimento registrado ainda.<br>Use a busca acima.</div>
      </div>`;
    return;
  }
  container.innerHTML = State.logs.map(log => `
    <div class="food-item">
      <div class="food-info">
        <div class="food-name">${log.food_name}</div>
        <div class="food-qty">${log.qty_g}g &bull; ${log.meal_type}</div>
      </div>
      <div class="food-cal">${Math.round(log.cal)} kcal</div>
    </div>
  `).join('');
}

// ====================== INICIALIZACAO (pagina principal) ======================
document.addEventListener('DOMContentLoaded', async () => {
  if (!document.getElementById('foodList')) return;

  // FIX: logout chama o endpoint antes de redirecionar
  document.getElementById('btnLogout')?.addEventListener('click', async () => {
    if (confirm('Deseja sair?')) {
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

  // Live Search
  const foodInput = document.getElementById('foodInput');
  foodInput.addEventListener('input', () => {
    clearTimeout(State.searchTimeout);
    State.searchTimeout = setTimeout(async () => {
      const q = foodInput.value.trim();
      if (q.length < 2) {
        document.getElementById('searchResults').classList.remove('show');
        return;
      }
      const res = await api(`api/search.php?q=${encodeURIComponent(q)}&source=all`);
      if (res.results) showSearchResults(res.results);
    }, 280);
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('.search-wrap')) {
      document.getElementById('searchResults')?.classList.remove('show');
    }
  });

  document.getElementById('btnAdd').addEventListener('click', analyzeWithAI);
  foodInput.addEventListener('keydown', e => { if (e.key === 'Enter') analyzeWithAI(); });

  // Limpar dia
  document.getElementById('btnClear')?.addEventListener('click', async () => {
    if (!State.logs.length) return toast('Nenhum alimento para remover.');
    if (!confirm('Remover todos os alimentos de hoje?')) return;
    await Promise.all(State.logs.map(log =>
      api(`api/foods.php?id=${log.id}`, { method: 'DELETE' })
    ));
    toast('Dia limpo!');
    await loadLogs();
  });

  await loadLogs();
});

// ====================== AUTH ======================
async function handleLogin() {
  const email   = document.getElementById('loginEmail')?.value.trim();
  const pass    = document.getElementById('loginPassword')?.value;
  const errorEl = document.getElementById('authError');

  errorEl.textContent = '';
  errorEl.style.color = '#e03c5a';

  if (!email || !pass) {
    errorEl.textContent = 'Preencha e-mail e senha.';
    return;
  }

  try {
    const data = await api('api/auth.php?action=login', {
      method: 'POST',
      body:   JSON.stringify({ email, password: pass }),
    });

    if (data.error) { errorEl.textContent = data.error; return; }

    if (data.success) {
      errorEl.style.color = '#10b981';
      errorEl.textContent = data.message || 'Login realizado! Redirecionando...';
      setTimeout(() => { window.location.href = 'index.php'; }, 800);
    }
  } catch (err) {
    errorEl.textContent = 'Erro de conexao com o servidor.';
  }
}

async function handleRegister() {
  const name    = document.getElementById('regName')?.value.trim();
  const email   = document.getElementById('regEmail')?.value.trim();
  const pass    = document.getElementById('regPassword')?.value;
  const errorEl = document.getElementById('authError');

  errorEl.textContent = '';
  errorEl.style.color = '#e03c5a';

  if (!name || !email || !pass) {
    errorEl.textContent = 'Preencha todos os campos.';
    return;
  }

  try {
    const data = await api('api/auth.php?action=register', {
      method: 'POST',
      body:   JSON.stringify({ name, email, password: pass }),
    });

    if (data.error) { errorEl.textContent = data.error; return; }

    if (data.success) {
      errorEl.style.color = '#10b981';
      errorEl.textContent = data.message || 'Conta criada! Redirecionando...';
      setTimeout(() => { window.location.href = 'index.php'; }, 1200);
    }
  } catch (err) {
    errorEl.textContent = 'Erro de conexao com o servidor.';
  }
}