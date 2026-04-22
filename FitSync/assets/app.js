/* ============================================================
   FitSync — App.js v5.0 (Otimizado)
   ============================================================ */

   window.State = {
    logs:          [],
    goals:         { daily_cal: 2000, daily_prot: 150, daily_carb: 250, daily_fat: 65 },
    date:          new Date().toISOString().slice(0, 10),
    mealType:      'snack',
    pendingFood:   null,
    searchTimeout: null,
    waterCups:     0,
    loading:       false,
};

const State = window.State;

// Cache DOM elements
const DOM = {};

function cacheElements() {
    const ids = ['toast', 'searchResults', 'foodList', 'qtyInput', 'foodInput', 
                 'aiResult', 'aiTitle', 'aiNutrients', 'btnAdd', 'btnClear',
                 'totalCal', 'totalProt', 'totalCarb', 'totalFat', 'calSub',
                 'calProgress', 'protProgress', 'carbProgress', 'fatProgress',
                 'dateBadge', 'btnLogout', 'btnOpenReport', 'reportModal',
                 'modalClose', 'reportBody', 'historyContent'];
    
    ids.forEach(id => {
        DOM[id] = document.getElementById(id);
    });
}

// ── API Helper com timeout e retry ──────────────────────────────
async function api(url, opts = {}) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 30000);
    
    try {
        const response = await fetch(url, {
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
            ...opts,
        });
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `HTTP ${response.status}`);
        }
        
        return await response.json();
    } catch (e) {
        clearTimeout(timeoutId);
        if (e.name === 'AbortError') {
            toast('Tempo limite excedido. Tente novamente.', 'error');
        } else {
            console.error('API error', url, e);
            toast(e.message || 'Erro de conexão.', 'error');
        }
        return { error: e.message || 'Erro de conexão.' };
    }
}

// ── Toast melhorado ────────────────────────────────────────────
function toast(msg, type = 'default') {
    if (!DOM.toast) return;
    DOM.toast.innerHTML = msg;
    DOM.toast.className = `toast show${type !== 'default' ? ' ' + type : ''}`;
    clearTimeout(DOM.toast._timer);
    DOM.toast._timer = setTimeout(() => DOM.toast.classList.remove('show'), 4000);
}

// ── Loading state ─────────────────────────────────────────────
function setLoading(element, isLoading, originalText = null) {
    if (!element) return;
    if (isLoading) {
        element._originalText = element.innerHTML;
        element.disabled = true;
        element.innerHTML = '<span class="spinner"></span> Carregando...';
    } else {
        element.disabled = false;
        element.innerHTML = element._originalText || originalText || element.innerHTML;
    }
}

// ══════════════════════════════════════════════════════════
//  LIVE SEARCH (Debounced)
// ══════════════════════════════════════════════════════════
function showSearchResults(results) {
    if (!DOM.searchResults) return;

    if (!results?.length) {
        DOM.searchResults.innerHTML = `<div style="padding:18px;color:#94a3b8;text-align:center;font-size:14px;font-weight:500;">Nenhum resultado encontrado</div>`;
        DOM.searchResults.classList.add('show');
        return;
    }

    DOM.searchResults.innerHTML = results.map((food, i) => {
        const cal  = food.per100g?.cal  != null ? Math.round(food.per100g.cal)  : '—';
        const prot = food.per100g?.prot != null ? food.per100g.prot : '—';
        const srcColors = { local:'#255ff1', usda:'#10b981', openfoodfacts:'#f59e0b', ai:'#8b5cf6' };
        const badge = food.source ? `<span style="background:${srcColors[food.source]||'#64748b'}22;color:${srcColors[food.source]||'#64748b'};padding:2px 8px;border-radius:99px;font-size:11px;font-weight:700;">${food.source.toUpperCase()}</span>` : '';

        return `<div class="search-result-item" data-idx="${i}" style="display:flex;align-items:center;gap:12px;padding:12px 16px;cursor:pointer;border-bottom:1px solid #e5e7f0;">
            <div style="width:40px;height:40px;border-radius:8px;background:#e8efff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">🍽️</div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <strong style="font-size:14.5px;">${escapeHtml(food.name)}</strong>${badge}
                </div>
                ${food.brand ? `<div style="font-size:12px;color:#94a3b8;margin-top:1px;">${escapeHtml(food.brand)}</div>` : ''}
                <div style="margin-top:4px;font-size:13px;color:#6b7db3;">
                    <strong style="color:#0f1f5c;">${cal}</strong> kcal · <strong style="color:#0f1f5c;">${prot}g</strong> prot · por 100g
                </div>
            </div>
        </div>`;
    }).join('');

    DOM.searchResults.classList.add('show');
    
    DOM.searchResults.querySelectorAll('.search-result-item').forEach(item => {
        item.addEventListener('click', () => {
            selectFood(results[+item.dataset.idx]);
            DOM.searchResults.classList.remove('show');
        });
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ══════════════════════════════════════════════════════════
//  ADICIONAR ALIMENTO
// ══════════════════════════════════════════════════════════
async function confirmAddFromSearch() {
    if (!State.pendingFood) return;
    const qty = parseFloat(DOM.qtyInput?.value) || 100;
    
    if (qty <= 0) {
        toast('Quantidade inválida', 'error');
        return;
    }
    
    const factor = qty / 100;

    const payload = {
        ...State.pendingFood,
        qty_g:     qty,
        cal:       Math.round((State.pendingFood.cal || 0) * factor * 10) / 10,
        prot:      Math.round((State.pendingFood.prot || 0) * factor * 10) / 10,
        carb:      Math.round((State.pendingFood.carb || 0) * factor * 10) / 10,
        fat:       Math.round((State.pendingFood.fat || 0) * factor * 10) / 10,
        fiber:     State.pendingFood.fiber ? Math.round(State.pendingFood.fiber * factor * 10) / 10 : null,
        sugar:     State.pendingFood.sugar ? Math.round(State.pendingFood.sugar * factor * 10) / 10 : null,
        sodium:    State.pendingFood.sodium ? Math.round(State.pendingFood.sodium * factor * 10) / 10 : null,
        sat_fat:   State.pendingFood.sat_fat ? Math.round(State.pendingFood.sat_fat * factor * 10) / 10 : null,
        meal_type: State.mealType,
    };

    setLoading(DOM.btnAdd, true, '✚ Adicionar ao Diário');
    
    const data = await api(`api/foods.php?date=${State.date}`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });

    setLoading(DOM.btnAdd, false);

    if (data.error) {
        toast(data.error, 'error');
        return;
    }
    
    toast(`✅ ${State.pendingFood.food_name} adicionado!`, 'success');
    resetUI();
    await loadLogs();
}

async function analyzeWithAI() {
    const query = DOM.foodInput?.value.trim();
    const qty = parseFloat(DOM.qtyInput?.value) || 100;
    
    if (!query) {
        toast('Digite o alimento ou refeição', 'error');
        return;
    }
    
    if (qty <= 0 || qty > 5000) {
        toast('Quantidade inválida (1-5000g)', 'error');
        return;
    }

    setLoading(DOM.btnAdd, true, '✦ Analisar com IA');

    const data = await api('api/analyze.php', {
        method: 'POST',
        body: JSON.stringify({ query, qty }),
    });

    setLoading(DOM.btnAdd, false);

    if (data.error) {
        toast(data.error, 'error');
        DOM.btnAdd.innerHTML = '✦ Analisar com IA';
        DOM.btnAdd.onclick = analyzeWithAI;
        return;
    }

    State.pendingFood = { ...data.food, meal_type: State.mealType };
    showAIResult(data.food.name, data.food);
    
    if (DOM.aiTitle) DOM.aiTitle.textContent = `✦ ${data.food.name} · ${data.food.qty}g`;
    
    DOM.btnAdd.innerHTML = '✚ Adicionar ao Diário';
    DOM.btnAdd.onclick = confirmAddFromSearch;
}

function resetUI() {
    State.pendingFood = null;
    if (DOM.foodInput) DOM.foodInput.value = '';
    if (DOM.qtyInput) DOM.qtyInput.value = '';
    if (DOM.aiResult) DOM.aiResult.style.display = 'none';
    if (DOM.searchResults) DOM.searchResults.classList.remove('show');
    if (DOM.btnAdd) {
        DOM.btnAdd.innerHTML = '✦ Analisar com IA';
        DOM.btnAdd.onclick = analyzeWithAI;
    }
}

// ══════════════════════════════════════════════════════════
//  CARREGAR DIÁRIO
// ══════════════════════════════════════════════════════════
async function loadLogs() {
    const data = await api(`api/foods.php?date=${State.date}`);
    if (data.error) {
        toast(data.error, 'error');
        return;
    }
    State.logs = data.logs || [];
    if (data.goals) State.goals = data.goals;
    updateSummary();
    renderFoodList();
}

window.updateSummary = function updateSummary() {
    const totals = State.logs.reduce((acc, log) => ({
        cal:  acc.cal  + (parseFloat(log.cal)  || 0),
        prot: acc.prot + (parseFloat(log.prot) || 0),
        carb: acc.carb + (parseFloat(log.carb) || 0),
        fat:  acc.fat  + (parseFloat(log.fat)  || 0),
    }), { cal: 0, prot: 0, carb: 0, fat: 0 });

    const setText = (id, val) => { if (DOM[id]) DOM[id].textContent = val; };
    setText('totalCal',  Math.round(totals.cal));
    setText('totalProt', Math.round(totals.prot));
    setText('totalCarb', Math.round(totals.carb));
    setText('totalFat',  Math.round(totals.fat));

    const g = State.goals;
    
    const updateProgress = (progressId, value, max, fillId) => {
        const el = DOM[progressId];
        const fill = fillId ? DOM[fillId] : null;
        if (!el) return;
        const pct = Math.min(100, (value / (max || 1)) * 100);
        el.style.width = pct + '%';
        if (fill && pct > 100) fill.classList.add('over');
        else if (fill) fill.classList.remove('over');
    };
    
    updateProgress('calProgress', totals.cal, g.daily_cal || 2000, 'calProgress');
    updateProgress('protProgress', totals.prot, g.daily_prot || 150, 'protProgress');
    updateProgress('carbProgress', totals.carb, g.daily_carb || 250, 'carbProgress');
    updateProgress('fatProgress', totals.fat, g.daily_fat || 65, 'fatProgress');

    const remaining = Math.round((g.daily_cal || 2000) - totals.cal);
    if (DOM.calSub) {
        DOM.calSub.textContent = remaining >= 0
            ? `Restam ${remaining} kcal da meta diária`
            : `⚠️ ${Math.abs(remaining)} kcal acima da meta`;
    }
};

// ══════════════════════════════════════════════════════════
//  RENDERIZAR LISTA
// ══════════════════════════════════════════════════════════
function renderFoodList() {
    if (!DOM.foodList) return;

    if (!State.logs.length) {
        DOM.foodList.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">🥗</div>
                <div class="empty-text">Nenhum alimento registrado ainda.<br>Use a busca acima para começar!</div>
            </div>`;
        return;
    }

    const mealInfo = {
        breakfast: { label: 'Café da manhã', icon: '☀️' },
        lunch:     { label: 'Almoço',        icon: '🍽️' },
        dinner:    { label: 'Jantar',        icon: '🌙' },
        snack:     { label: 'Lanche',        icon: '🍎' },
    };

    const groups = {};
    State.logs.forEach(log => {
        if (!groups[log.meal_type]) groups[log.meal_type] = [];
        groups[log.meal_type].push(log);
    });

    const mealOrder = ['breakfast', 'lunch', 'dinner', 'snack'];

    DOM.foodList.innerHTML = mealOrder
        .filter(m => groups[m])
        .map(mealType => {
            const items = groups[mealType];
            const mealCal = Math.round(items.reduce((s, l) => s + (parseFloat(l.cal) || 0), 0));
            const { label, icon } = mealInfo[mealType] || { label: mealType, icon: '🍴' };

            return `
                <div class="meal-group">
                    <div class="meal-group-header">
                        <span class="meal-group-icon">${icon}</span>
                        <span class="meal-group-name">${label}</span>
                        <span class="meal-group-cal">${mealCal} kcal</span>
                    </div>
                    ${items.map(log => `
                        <div class="food-item" data-id="${log.id}">
                            <div class="food-info">
                                <div class="food-name">${escapeHtml(log.food_name)}</div>
                                <div class="food-meta">
                                    <span class="food-macro"><strong>${log.qty_g}g</strong></span>
                                    ${log.prot > 0 ? `<span class="food-macro">💪 <strong>${parseFloat(log.prot).toFixed(1)}g</strong> prot</span>` : ''}
                                    ${log.carb > 0 ? `<span class="food-macro">🌾 <strong>${parseFloat(log.carb).toFixed(1)}g</strong> carb</span>` : ''}
                                    ${log.fat > 0 ? `<span class="food-macro">🧈 <strong>${parseFloat(log.fat).toFixed(1)}g</strong> gord</span>` : ''}
                                </div>
                                <div id="eval-${log.id}"></div>
                            </div>
                            <div class="food-actions">
                                <div class="food-cal">${Math.round(log.cal)} kcal</div>
                                <button class="btn-evaluate" onclick="window.evaluateMeal(${log.id})" title="Avaliar com IA">✦ Avaliar</button>
                                <button class="btn-delete" onclick="window.deleteLog(${log.id})" title="Remover">✕</button>
                            </div>
                        </div>
                    `).join('')}
                </div>`;
        }).join('');
}

window.deleteLog = async function(id) {
    const data = await api(`api/foods.php?id=${id}`, { method: 'DELETE' });
    if (data.error) {
        toast(data.error, 'error');
        return;
    }
    toast('Alimento removido 🗑️');
    await loadLogs();
};

window.evaluateMeal = async function(logId) {
    const log = State.logs.find(l => l.id == logId);
    if (!log) return;

    const evalEl = document.getElementById(`eval-${logId}`);
    if (!evalEl) return;

    evalEl.innerHTML = `<span style="font-size:12px;color:#6b7db3;">⏳ Avaliando...</span>`;

    const data = await api('api/report.php?action=evaluate', {
        method: 'POST',
        body: JSON.stringify({
            food_name: log.food_name,
            meal_type: log.meal_type,
            cal:    log.cal,
            prot:   log.prot,
            carb:   log.carb,
            fat:    log.fat,
            fiber:  log.fiber,
            sodium: log.sodium,
        }),
    });

    if (data.error || !data.evaluation) {
        evalEl.innerHTML = `<span style="font-size:12px;color:#f04343;">Erro ao avaliar</span>`;
        return;
    }

    const ev = data.evaluation;
    const stars = '★'.repeat(ev.rating) + '☆'.repeat(5 - ev.rating);

    evalEl.innerHTML = `
        <div class="eval-badge" style="background:${ev.color}18;color:${ev.color};margin-top:6px;">
            <span>${ev.badge}</span>
            <span>${ev.label}</span>
            <span style="letter-spacing:1px;font-size:14px;">${stars}</span>
        </div>
        ${ev.tip ? `<div style="font-size:12.5px;color:#6b7db3;margin-top:5px;font-style:italic;">${ev.tip}</div>` : ''}
    `;
};

// ══════════════════════════════════════════════════════════
//  WATER TRACKER
// ══════════════════════════════════════════════════════════
function initWaterTracker() {
    const storageKey = `water_${State.date}`;
    State.waterCups = parseInt(localStorage.getItem(storageKey) || '0');
    
    const cups = document.querySelectorAll('.water-cup');
    const text = document.getElementById('waterText');

    function updateWater() {
        cups.forEach((cup, i) => {
            cup.classList.toggle('filled', i < State.waterCups);
            cup.textContent = i < State.waterCups ? '💧' : '○';
        });
        if (text) text.textContent = `${(State.waterCups * 250 / 1000).toFixed(2).replace('.', ',')}L / 2L`;
        localStorage.setItem(storageKey, State.waterCups);
    }

    cups.forEach((cup, i) => {
        cup.addEventListener('click', () => {
            State.waterCups = State.waterCups === i + 1 ? i : i + 1;
            updateWater();
            if (State.waterCups === 8) toast('🎉 Meta de hidratação atingida!', 'success');
        });
    });

    updateWater();
}

// ══════════════════════════════════════════════════════════
//  HISTÓRICO
// ══════════════════════════════════════════════════════════
async function loadHistory() {
    if (!DOM.historyContent) return;
    
    const res = await api('api/foods.php?action=summary&days=7');
    
    if (!res.history?.length) {
        DOM.historyContent.innerHTML = `<div class="empty-state"><div class="empty-icon">📊</div><div class="empty-text">Nenhum dado ainda. Comece a registrar!</div></div>`;
        return;
    }

    const maxCal = Math.max(...res.history.map(r => parseFloat(r.cal || 0)), 1);
    const goal = State.goals.daily_cal || 2000;
    const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    function fmtDate(dateStr) {
        const [y, m, d] = dateStr.split('-');
        const dt = new Date(+y, +m - 1, +d);
        return `${days[dt.getDay()]}, ${d}/${m}`;
    }

    DOM.historyContent.innerHTML = `
        <div class="history-chart-wrap">
            <div class="history-chart-title">Calorias · Últimos ${res.history.length} dias</div>
            <div class="history-bars" style="display:flex;align-items:flex-end;gap:12px;height:140px;padding-bottom:28px;">
                ${res.history.map(row => {
                    const cal = parseFloat(row.cal || 0);
                    const h = Math.max(4, (cal / maxCal) * 120);
                    const col = cal > goal * 1.1 ? '#f97316' : cal < goal * 0.6 ? '#ef4444' : '#255ff1';
                    const [y, mo, d] = row.logged_at.split('-');
                    const dt = new Date(+y, +mo - 1, +d);
                    return `
                        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end;position:relative;">
                            <div style="position:absolute;top:-22px;font-size:11px;font-weight:700;color:#0f1f5c;">${Math.round(cal)}</div>
                            <div style="width:100%;height:${h}px;background:linear-gradient(180deg,${col}99,${col});border-radius:6px 6px 0 0;min-height:4px;" title="${Math.round(cal)} kcal"></div>
                            <div style="font-size:11px;color:#6b7db3;font-weight:600;">${days[dt.getDay()].slice(0,3)}<br>${d}/${mo}</div>
                        </div>`;
                }).join('')}
            </div>
        </div>
        <div style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700;color:#0f1f5c;margin-bottom:12px;">Detalhes por Dia</div>
        ${res.history.map(row => `
            <div class="history-item" style="background:white;border:1.5px solid #dde4f5;border-radius:12px;padding:16px 22px;margin-bottom:8px;display:flex;align-items:center;gap:20px;">
                <div class="history-date" style="font-weight:700;color:#0f1f5c;font-size:15px;min-width:150px;">${fmtDate(row.logged_at)}</div>
                <div class="history-macros" style="display:flex;gap:8px;flex-wrap:wrap;flex:1;">
                    <span class="hm hm-cal" style="background:#fff4e6;color:#c05621;padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:600;">🔥 ${Math.round(row.cal)} kcal</span>
                    <span class="hm hm-prot" style="background:#e6fff5;color:#065f46;padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:600;">💪 ${row.prot}g prot</span>
                    <span class="hm hm-carb" style="background:#fffbeb;color:#92400e;padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:600;">🌾 ${row.carb}g carb</span>
                    <span class="hm hm-fat" style="background:#fff1f1;color:#c53030;padding:5px 12px;border-radius:99px;font-size:12.5px;font-weight:600;">🧈 ${row.fat}g gord</span>
                </div>
            </div>`).join('')}
    `;
}

// ══════════════════════════════════════════════════════════
//  RELATÓRIO DIÁRIO
// ══════════════════════════════════════════════════════════
async function openDailyReport() {
    if (!DOM.reportModal) return;
    
    DOM.reportModal.classList.add('open');
    document.body.style.overflow = 'hidden';

    if (DOM.reportBody) {
        DOM.reportBody.innerHTML = `
            <div style="text-align:center;padding:60px 20px;">
                <div style="font-size:40px;margin-bottom:16px;animation:spin 1s linear infinite;display:inline-block;">⚙️</div>
                <div style="font-size:16px;color:#6b7db3;font-weight:500;">Analisando sua alimentação com IA...</div>
            </div>`;
    }

    const data = await api(`api/report.php?action=daily&date=${State.date}`);

    if (!data.report) {
        if (DOM.reportBody) DOM.reportBody.innerHTML = `
            <div class="empty-state" style="padding:40px 20px;">
                <div class="empty-icon">📊</div>
                <div class="empty-text">${data.message || 'Nenhum dado para hoje.'}</div>
            </div>`;
        return;
    }

    const r = data.report;
    const t = data.totals || {};
    const g = data.goals || State.goals;

    const pct = (val, goal) => goal > 0 ? Math.min(100, Math.round(val / goal * 100)) : 0;
    const macroColor = (p) => p < 60 ? '#ef4444' : p < 85 ? '#f59e0b' : p <= 115 ? '#10b981' : '#f97316';

    const macros = [
        { name: 'Calorias', val: Math.round(t.cal || 0), goal: g.daily_cal || 2000, unit: 'kcal', icon: '🔥' },
        { name: 'Proteínas', val: Math.round(t.prot || 0), goal: g.daily_prot || 150, unit: 'g', icon: '💪' },
        { name: 'Carboidratos', val: Math.round(t.carb || 0), goal: g.daily_carb || 250, unit: 'g', icon: '🌾' },
        { name: 'Gorduras', val: Math.round(t.fat || 0), goal: g.daily_fat || 65, unit: 'g', icon: '🧈' },
    ];

    DOM.reportBody.innerHTML = `
        <div class="report-score-area" style="display:flex;align-items:center;gap:24px;background:#f0f4ff;border-radius:20px;padding:24px;margin-bottom:24px;">
            <div class="score-circle" style="width:100px;height:100px;border-radius:50%;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;background:linear-gradient(145deg,${r.grade_color},${r.grade_color}cc);box-shadow:0 8px 24px rgba(0,0,0,0.12);">
                <div class="score-number" style="font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:white;line-height:1;">${r.score}</div>
                <div class="score-grade" style="font-size:14px;font-weight:700;color:rgba(255,255,255,0.8);margin-top:2px;">Nota ${r.grade}</div>
            </div>
            <div class="score-summary" style="flex:1;">
                <div class="score-summary-text" style="font-size:15px;color:#0f1f5c;line-height:1.6;font-weight:500;">${escapeHtml(r.summary)}</div>
            </div>
        </div>

        <div class="report-macros" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:20px;">
            ${macros.map(m => {
                const p = pct(m.val, m.goal);
                const col = macroColor(p);
                return `
                    <div class="report-macro-item" style="background:#f0f4ff;border-radius:12px;padding:14px;">
                        <div class="report-macro-name" style="font-size:11.5px;font-weight:700;color:#6b7db3;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">${m.icon} ${m.name}</div>
                        <div class="report-macro-value" style="font-family:'Syne',sans-serif;font-size:20px;font-weight:700;color:#0f1f5c;">${m.val}<small style="font-size:14px;font-weight:500;"> ${m.unit}</small></div>
                        <div class="report-macro-pct" style="font-size:12px;color:#6b7db3;margin-top:3px;">${p}% da meta (${m.goal}${m.unit})</div>
                        <div class="report-macro-bar" style="height:6px;background:#dde4f5;border-radius:99px;margin-top:8px;overflow:hidden;">
                            <div class="report-macro-bar-fill" style="width:${p}%;height:100%;background:${col};border-radius:99px;"></div>
                        </div>
                    </div>`;
            }).join('')}
        </div>

        ${r.highlights?.length ? `
        <div class="report-section" style="margin-bottom:22px;">
            <div class="report-section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#a0aec8;margin-bottom:12px;display:flex;align-items:center;gap:8px;">✅ Pontos Positivos</div>
            <div class="report-list">
                ${r.highlights.map(h => `<div class="report-item success" style="background:rgba(13,185,124,0.08);color:#065f46;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;display:flex;align-items:flex-start;gap:10px;"><span class="report-item-icon">✅</span>${escapeHtml(h)}</div>`).join('')}
            </div>
        </div>` : ''}

        ${r.warnings?.length ? `
        <div class="report-section" style="margin-bottom:22px;">
            <div class="report-section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#a0aec8;margin-bottom:12px;display:flex;align-items:center;gap:8px;">⚠️ Atenção</div>
            <div class="report-list">
                ${r.warnings.map(w => `<div class="report-item warning" style="background:rgba(245,158,11,0.08);color:#92400e;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;display:flex;align-items:flex-start;gap:10px;"><span class="report-item-icon">⚠️</span>${escapeHtml(w)}</div>`).join('')}
            </div>
        </div>` : ''}

        ${r.suggestions?.length ? `
        <div class="report-section" style="margin-bottom:22px;">
            <div class="report-section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#a0aec8;margin-bottom:12px;display:flex;align-items:center;gap:8px;">💡 Sugestões</div>
            <div class="report-list">
                ${r.suggestions.map(s => `<div class="report-item suggestion" style="background:#e8efff;color:#0f1f5c;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;display:flex;align-items:flex-start;gap:10px;"><span class="report-item-icon">💡</span>${escapeHtml(s)}</div>`).join('')}
            </div>
        </div>` : ''}

        ${r.hydration_tip ? `
        <div class="report-section" style="margin-bottom:22px;">
            <div class="report-section-title" style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:#a0aec8;margin-bottom:12px;display:flex;align-items:center;gap:8px;">💧 Hidratação</div>
            <div class="report-item info" style="background:rgba(59,130,246,0.08);color:#1e3a8a;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;display:flex;align-items:flex-start;gap:10px;"><span class="report-item-icon">💧</span>${escapeHtml(r.hydration_tip)}</div>
        </div>` : ''}

        ${r.tomorrow_tip ? `<div class="report-item suggestion" style="background:#e8efff;color:#0f1f5c;padding:12px 16px;border-radius:12px;font-size:14px;font-weight:500;display:flex;align-items:flex-start;gap:10px;margin-top:16px;"><span class="report-item-icon">🌅</span><div><strong>Para amanhã:</strong> ${escapeHtml(r.tomorrow_tip)}</div></div>` : ''}
    `;
}

function closeDailyReport() {
    if (DOM.reportModal) DOM.reportModal.classList.remove('open');
    document.body.style.overflow = '';
}

// ══════════════════════════════════════════════════════════
//  METAS
// ══════════════════════════════════════════════════════════
async function loadGoals() {
    const data = await api('api/foods.php?action=goals');
    if (data.goals) {
        const fields = ['Cal', 'Prot', 'Carb', 'Fat'];
        fields.forEach(f => {
            const el = document.getElementById(`goal${f}`);
            if (el) el.value = data.goals[`daily_${f.toLowerCase()}`] || (f === 'Cal' ? 2000 : f === 'Prot' ? 150 : f === 'Carb' ? 250 : 65);
        });
    }
}

function calcBMI() {
    const h = parseFloat(document.getElementById('bmiHeight')?.value || 0) / 100;
    const w = parseFloat(document.getElementById('bmiWeight')?.value || 0);
    const result = document.getElementById('bmiResult');
    
    if (!h || !w || h < 0.5 || h > 2.5 || w < 10 || w > 500) {
        if (result) result.style.display = 'none';
        return;
    }

    const bmi = w / (h * h);
    const categories = [
        { max: 18.5, label: 'Abaixo do peso', color: '#3b82f6' },
        { max: 25,   label: 'Peso normal',    color: '#10b981' },
        { max: 30,   label: 'Sobrepeso',      color: '#f59e0b' },
        { max: 35,   label: 'Obesidade I',    color: '#f97316' },
        { max: Infinity, label: 'Obesidade II+', color: '#ef4444' },
    ];
    const cat = categories.find(c => bmi < c.max);

    result.style.display = 'block';
    result.innerHTML = `
        <div class="bmi-value" style="font-family:'Syne',sans-serif;font-size:36px;font-weight:800;color:${cat.color};">${bmi.toFixed(1)}</div>
        <div class="bmi-category" style="font-size:14px;font-weight:600;color:${cat.color};">${cat.label}</div>
        <div style="font-size:12px;color:#6b7db3;margin-top:6px;">Índice de Massa Corporal</div>
    `;
}

// ══════════════════════════════════════════════════════════
//  INIT
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', async () => {
    cacheElements();
    
    if (!DOM.foodList) return;

    // Date badge
    const days = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
    const months = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const now = new Date();
    if (DOM.dateBadge) DOM.dateBadge.textContent = `${days[now.getDay()]}, ${now.getDate()} de ${months[now.getMonth()]}`;

    // Logout
    DOM.btnLogout?.addEventListener('click', async () => {
        if (!confirm('Deseja sair da sua conta?')) return;
        await api('api/auth.php?action=logout', { method: 'GET' }).catch(() => {});
        window.location.href = 'login.php';
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
    if (DOM.foodInput) {
        DOM.foodInput.addEventListener('input', () => {
            clearTimeout(State.searchTimeout);
            const q = DOM.foodInput.value.trim();
            if (q.length < 2) {
                DOM.searchResults?.classList.remove('show');
                return;
            }
            State.searchTimeout = setTimeout(async () => {
                const res = await api(`api/search.php?q=${encodeURIComponent(q)}&source=all`);
                if (res.results) showSearchResults(res.results);
            }, 300);
        });
        
        DOM.foodInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') analyzeWithAI();
            if (e.key === 'Escape') DOM.searchResults?.classList.remove('show');
        });
    }

    document.addEventListener('click', e => {
        if (!e.target.closest('.search-wrap')) DOM.searchResults?.classList.remove('show');
    });

    // Add button
    DOM.btnAdd?.addEventListener('click', analyzeWithAI);

    // Clear all
    DOM.btnClear?.addEventListener('click', async () => {
        if (!State.logs.length) {
            toast('Nenhum alimento para remover.');
            return;
        }
        if (!confirm('Remover todos os alimentos de hoje?')) return;
        await Promise.all(State.logs.map(l => api(`api/foods.php?id=${l.id}`, { method: 'DELETE' })));
        toast('Dia limpo! 🗑️');
        await loadLogs();
    });

    // Report modal
    DOM.btnOpenReport?.addEventListener('click', openDailyReport);
    DOM.modalClose?.addEventListener('click', closeDailyReport);
    DOM.reportModal?.addEventListener('click', e => {
        if (e.target === DOM.reportModal) closeDailyReport();
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDailyReport();
    });

    // Nav tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.panel;
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.nav-panel').forEach(p => p.style.display = 'none');
            tab.classList.add('active');
            const panel = document.getElementById(target);
            if (panel) panel.style.display = 'block';
            if (target === 'panelHistory') loadHistory();
            if (target === 'panelGoals') loadGoals();
        });
    });

    // Goals save
    document.getElementById('btnSaveGoals')?.addEventListener('click', async () => {
        const payload = {
            daily_cal:  parseInt(document.getElementById('goalCal')?.value) || 2000,
            daily_prot: parseInt(document.getElementById('goalProt')?.value) || 150,
            daily_carb: parseInt(document.getElementById('goalCarb')?.value) || 250,
            daily_fat:  parseInt(document.getElementById('goalFat')?.value) || 65,
        };
        
        const data = await api('api/foods.php?action=goals', {
            method: 'PATCH',
            body: JSON.stringify(payload),
        });
        
        if (data.success) {
            Object.assign(State.goals, payload);
            updateSummary();
            toast('✅ Metas salvas com sucesso!', 'success');
        } else {
            toast(data.error || 'Erro ao salvar metas', 'error');
        }
    });

    // BMI
    document.getElementById('btnCalcBMI')?.addEventListener('click', calcBMI);

    // Water tracker
    initWaterTracker();

    // Initial load
    await loadLogs();
});

// Auth functions
window.handleLogin = async function() {
    const email = document.getElementById('loginEmail')?.value.trim();
    const pass = document.getElementById('loginPassword')?.value;
    const err = document.getElementById('authError');
    if (!err) return;
    err.textContent = '';

    if (!email || !pass) {
        err.textContent = 'Preencha e-mail e senha.';
        return;
    }

    const btn = document.getElementById('btnLogin');
    if (btn) setLoading(btn, true, 'Entrar na conta →');

    try {
        const data = await api('api/auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ email, password: pass }),
        });
        if (data.error) {
            err.textContent = data.error;
            return;
        }
        if (data.success) {
            err.style.color = '#10b981';
            err.textContent = '✅ Login realizado!';
            setTimeout(() => window.location.href = 'index.php', 600);
        }
    } catch (e) {
        err.textContent = 'Erro de conexão.';
    } finally {
        if (btn) setLoading(btn, false);
    }
};

window.handleRegister = async function() {
    const name = document.getElementById('regName')?.value.trim();
    const email = document.getElementById('regEmail')?.value.trim();
    const pass = document.getElementById('regPassword')?.value;
    const err = document.getElementById('authError');
    if (!err) return;
    err.textContent = '';

    if (!name || !email || !pass) {
        err.textContent = 'Preencha todos os campos.';
        return;
    }
    
    if (pass.length < 6) {
        err.textContent = 'Senha deve ter pelo menos 6 caracteres.';
        return;
    }

    const btn = document.getElementById('btnRegister');
    if (btn) setLoading(btn, true, 'Criar minha conta →');

    try {
        const data = await api('api/auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify({ name, email, password: pass }),
        });
        if (data.error) {
            err.textContent = data.error;
            return;
        }
        if (data.success) {
            err.style.color = '#10b981';
            err.textContent = '✅ Conta criada!';
            setTimeout(() => window.location.href = 'index.php', 800);
        }
    } catch (e) {
        err.textContent = 'Erro de conexão.';
    } finally {
        if (btn) setLoading(btn, false);
    }
};