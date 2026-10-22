/* =========================================================
   FECHAMENTO — CRUD + cards + filtros + export
   ========================================================= */
(function(){
  'use strict';

  let _todos = [];
  let _cardFilter = null;
  const PAGE_SIZE = 30;
  let _pag = 1;

  // Filtros multi-seleção (aplicados no front): tipo, ramo, operadora, mês, analista
  const _fcFiltros = { tipo:[], ramo:[], operadora:[], mes:[], analista:[], entrada:[] };
  const _FC_MS_DEFAULT = {
    tipo:'Todos os tipos', ramo:'Todos os ramos', operadora:'Todas as operadoras',
    mes:'Todos os meses', analista:'Todos os analistas'
  };

  // ----- helpers -----
  function $(id){ return document.getElementById(id); }
  function brl(v){
    if (v === null || v === undefined || v === '') return '';
    const n = parseFloat(v);
    if (isNaN(n)) return '';
    return n.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
  }
  function pct(v){
    if (v === null || v === undefined || v === '') return '';
    const n = parseFloat(v);
    if (isNaN(n)) return '';
    return (n * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%';
  }
  function dtBR(d){
    if (!d) return '';
    const s = String(d).substring(0,10);
    const p = s.split('-');
    if (p.length !== 3) return s;
    return p[2] + '/' + p[1] + '/' + p[0];
  }
  function tipoBadge(tipo){
    if (!tipo) return '<span class="fct fct-default">—</span>';
    const slug = tipo.toLowerCase().replace(/ç/g,'c').replace(/ã/g,'a').replace(/[^a-z0-9]/g,'');
    return `<span class="fct fct-${slug}">${tipo}</span>`;
  }
  function escapeHtml(s){
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function parseDec(v){
    if (v === null || v === undefined || v === '') return null;
    let s = String(v).trim();
    if (!s) return null;
    s = s.replace(/\./g, '').replace(',', '.');
    const n = parseFloat(s);
    return isNaN(n) ? null : n;
  }

  // ----- API helpers -----
  async function apiGet(action, params){
    const q = new URLSearchParams({action, ...(params||{})}).toString();
    const r = await fetch('api.php?' + q, {credentials:'same-origin'});
    return r.json();
  }
  async function apiPost(action, body){
    const r = await fetch('api.php?action=' + action, {
      method:'POST',
      credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify(body || {})
    });
    return r.json();
  }

  // ----- Carregar analistas para dropdowns -----
  async function carregarAnalistas(){
    try {
      const r = await apiGet('analistas', {});
      if (!r.ok || !r.data) return;
      // Dedup por primeiro nome (capitalizado)
      const vistos = new Set();
      const lista = [];
      r.data.forEach(a => {
        // só analistas de DEMANDAS (tipo 'analista') no Fechamento
        if (String(a.tipo || '').toLowerCase() !== 'analista') return;
        const bruto = (a.nome || '').trim();
        if (!bruto) return;
        const primeiroBruto = bruto.split(' ')[0].trim();
        // Capitaliza: primeira letra maiúscula, resto minúsculo
        const primeiro = primeiroBruto.charAt(0).toUpperCase() + primeiroBruto.slice(1).toLowerCase();
        if (!primeiro) return;
        if (vistos.has(primeiro.toLowerCase())) return;
        vistos.add(primeiro.toLowerCase());
        lista.push(primeiro);
      });
      lista.sort((a,b) => a.localeCompare(b,'pt'));
      const opts = lista.map(n => `<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`).join('');
      const mod = $('mf-analista');
      if (mod) mod.innerHTML = '<option value="">Selecione...</option>' + opts;

      // filtro da tela (multi-seleção por checkbox)
      const apanel = $('msf-analista-panel');
      if (apanel) {
        apanel.innerHTML = lista.map(n =>
          `<label class="ms-opt"><input type="checkbox" value="${escapeHtml(n)}" onchange="fcMsChange('analista')"> ${escapeHtml(n)}</label>`
        ).join('') + `<div class="ms-actions"><button type="button" onclick="fcMsClear('analista')">Limpar</button></div>`;
        (_fcFiltros.analista || []).forEach(v => {
          const c = apanel.querySelector(`input[type=checkbox][value="${(window.CSS && CSS.escape) ? CSS.escape(v) : v}"]`);
          if (c) c.checked = true;
        });
      }
    } catch(e){ console.warn('analistas', e); }
  }

  // ----- Carregar seguradoras (Operadora) do cadastro -----
  async function carregarSeguradoras(){
    try {
      const r = await apiGet('seguradoras', {});
      if (!r.ok || !r.data) return;
      const nomes = r.data.map(s => (s.nome || '').trim()).filter(Boolean);
      // dropdown do modal
      const mod = $('mf-operadora');
      if (mod) {
        const atual = mod.value;
        mod.innerHTML = '<option value="">Selecione...</option>' +
          nomes.map(n => `<option value="${escapeHtml(n)}">${escapeHtml(n)}</option>`).join('');
        if (atual) mod.value = atual;
      }
      // filtro da tela (multi-seleção por checkbox)
      const apanel = $('msf-operadora-panel');
      if (apanel) {
        apanel.innerHTML = nomes.map(n =>
          `<label class="ms-opt"><input type="checkbox" value="${escapeHtml(n)}" onchange="fcMsChange('operadora')"> ${escapeHtml(n)}</label>`
        ).join('') + `<div class="ms-actions"><button type="button" onclick="fcMsClear('operadora')">Limpar</button></div>`;
        (_fcFiltros.operadora || []).forEach(v => {
          const c = apanel.querySelector(`input[type=checkbox][value="${(window.CSS && CSS.escape) ? CSS.escape(v) : v}"]`);
          if (c) c.checked = true;
        });
      }
    } catch(e){ console.warn('seguradoras', e); }
  }

  // ----- Filtros multi-seleção (checkbox) -----
  function _fcNorm(s){ return String(s == null ? '' : s).trim().toUpperCase(); }
  // Considera apenas as tratativas de Saúde, Odonto e Saúde/Odonto.
  function _fcIsSaudeOdonto(ramo){
    const n = String(ramo == null ? '' : ramo).toLowerCase()
      .replace(/[áàâãä]/g,'a').replace(/[éèêë]/g,'e').replace(/[íìîï]/g,'i')
      .replace(/[óòôõö]/g,'o').replace(/[úùûü]/g,'u').replace(/ç/g,'c')
      .replace(/\s+/g, '');
    return n === 'saude' || n === 'odonto' || n === 'saude/odonto' || n === 'odonto/saude';
  }
  function _fcAplicarFiltros(rows){
    const f = _fcFiltros;
    const bate = (arr, v) => arr.length === 0 || arr.map(_fcNorm).includes(_fcNorm(v));
    return (rows || []).filter(r =>
         bate(f.tipo,      r.tipo)
      && bate(f.ramo,      r.ramo)
      && bate(f.operadora, r.operadora)
      && bate(f.mes,       r.mes)
      && bate(f.analista,  r.analista)
      && bate(f.entrada || [], r.entrada)
    );
  }

  // ── Painel de resumo: por Entrada e por Tipo (quantidade + valor + %) ──
  const FC_ENTRADAS = ['Interweg MG','Interweg JF','Interweg SP','Fred','Parceiro - Bernardo','Parceiro - Elmo','Finlândia'];
  const FC_TIPOS    = ['Seguro Novo','Seguro Novo de Cliente Interweg','Renovação','Nomeação'];
  const FC_COLLAPSED = { entrada:true, tipo:true };  // começa fechado
  window.fcBreakToggle = function(btn){
    const blk = btn.closest('.cc-break-block'); if (!blk) return;
    const col = blk.classList.toggle('collapsed');
    if (blk.dataset.block) FC_COLLAPSED[blk.dataset.block] = col;
  };
  const FC_PALETA   = ['#00c9a7','#5b8def','#f5a623','#bd6bff','#ff6b8a','#2fb98a','#f77f3f','#4c6ef5','#e8467c','#12b886'];

  // Base do painel: respeita ramo/operadora/mês/analista + card, ignora entrada/tipo
  function _fcBaseForBreakdown(){
    const f = _fcFiltros;
    const bate = (arr, v) => !arr || arr.length === 0 || arr.map(_fcNorm).includes(_fcNorm(v));
    let base = (_todos || []).filter(r =>
         bate(f.ramo, r.ramo) && bate(f.operadora, r.operadora) && bate(f.mes, r.mes) && bate(f.analista, r.analista));
    base = _fcCardFilter(base);
    return base;
  }
  function _fcAgg(base, campo){
    const map = {};
    base.forEach(r => {
      const k = String(r[campo] == null ? '' : r[campo]).trim() || '—';
      if (!map[k]) map[k] = { count:0, valor:0, fatura:0, agSum:0, agN:0, coSum:0, coN:0, fatSO:0, agR:0, coR:0 };
      const g = map[k];
      g.count++;
      const v  = parseFloat(r.valor_primeira_fatura); if (!isNaN(v))  g.valor  += v;
      const f1 = parseFloat(r.fatura_1);              if (!isNaN(f1)) g.fatura += f1;
      const ag = parseFloat(r.pct_agenciamento);      if (!isNaN(ag)) { g.agSum += ag; g.agN++; }
      const co = parseFloat(r.pct_comissao);          if (!isNaN(co)) { g.coSum += co; g.coN++; }
      // R$ que a Interweg recebe (Fatura × %), somando SÓ Saúde/Odonto/Saúde-Odonto.
      if (_fcIsSaudeOdonto(r.ramo)) {
        const fso = isNaN(f1) ? 0 : f1;
        g.fatSO += fso;
        if (!isNaN(ag)) g.agR += fso * ag;   // pct_agenciamento é fração (127,42% = 1,2742)
        if (!isNaN(co)) g.coR += fso * co;   // pct_comissao é fração (4,98% = 0,0498)
      }
    });
    return map;
  }
  function fcRenderBreakdown(){
    const wrap = $('fc-breakdown');
    if (!wrap) return;
    const base = _fcBaseForBreakdown();
    const totalCount = base.length;
    const aggE = _fcAgg(base, 'entrada');
    const aggT = _fcAgg(base, 'tipo');
    let entKeys = FC_ENTRADAS.slice();
    Object.keys(aggE).forEach(k => { if (k !== '—' && !entKeys.some(x => _fcNorm(x)===_fcNorm(k))) entKeys.push(k); });
    let tipoKeys = FC_TIPOS.slice();
    Object.keys(aggT).forEach(k => { if (k !== '—' && !tipoKeys.some(x => _fcNorm(x)===_fcNorm(k))) tipoKeys.push(k); });
    wrap.innerHTML =
      _fcBreakRow('Por entrada', 'entrada', entKeys, aggE, totalCount) +
      _fcBreakRow('Por tipo',    'tipo',    tipoKeys, aggT, totalCount);
  }
  function _fcBRLc(v){
    v = parseFloat(v) || 0;
    if (v >= 1e6) return 'R$ ' + (v/1e6).toFixed(1).replace('.', ',') + ' mi';
    if (v >= 1e3) return 'R$ ' + (v/1e3).toFixed(1).replace('.', ',') + ' mil';
    return 'R$ ' + Math.round(v);
  }
  function _fcBreakRow(titulo, key, keys, agg, totalCount){
    const items = keys.map(v => {
      const rk = Object.keys(agg).find(k => _fcNorm(k)===_fcNorm(v));
      const a = (rk ? agg[rk] : null) || { count:0, valor:0, fatura:0, agSum:0, agN:0, coSum:0, coN:0, fatSO:0, agR:0, coR:0 };
      return Object.assign({ v }, a);
    }).sort((x,y) => y.count - x.count || y.fatura - x.fatura);
    const maxc = items.reduce((m,i) => Math.max(m, i.count), 0) || 1;
    const rows = items.map(it => {
      const share = totalCount ? Math.round(it.count / totalCount * 100) : 0;
      const w = Math.round(it.count / maxc * 100);
      const ativo = (_fcFiltros[key] || []).map(_fcNorm).includes(_fcNorm(it.v));
      return `<div class="fc-brk-row${ativo ? ' cc-brk-active' : ''}${it.count === 0 ? ' cc-brk-zero' : ''}" data-k="${key}" data-v="${escapeHtml(it.v)}" onclick="fcBreakClick(this)">
        <div class="fc-brk-head"><span class="fc-brk-lbl">${escapeHtml(it.v)}</span><span class="fc-brk-count"><b>${it.count}</b> · ${share}%</span></div>
        <div class="cc-brk-track"><div class="cc-brk-fill" style="width:${w}%"></div></div>
        <div class="fc-brk-metrics"><span>Fatura&nbsp;<b>R$ ${brl(it.fatSO) || '0,00'}</b></span><span>Agenc.&nbsp;<b>R$ ${brl(it.agR) || '0,00'}</b></span><span>Comis.&nbsp;<b>R$ ${brl(it.coR) || '0,00'}</b></span></div>
      </div>`;
    }).join('');
    return `<div class="cc-break-block${FC_COLLAPSED[key] ? ' collapsed' : ''}" data-block="${key}"><button type="button" class="cc-break-title" onclick="fcBreakToggle(this)"><span>${titulo}</span><span class="cc-brk-chev">▾</span></button><div class="fc-brk-list">${rows}</div></div>`;
  }
  window.fcBreakClick = function(btn){
    const key = btn.dataset.k, value = btn.dataset.v;
    const el = $('msf-' + key);
    const cb = el ? [...el.querySelectorAll('input[type=checkbox]')].find(c => _fcNorm(c.value) === _fcNorm(value)) : null;
    if (cb) { cb.checked = !cb.checked; window.fcMsChange(key); }
    else {
      if (!_fcFiltros[key]) _fcFiltros[key] = [];
      const i = _fcFiltros[key].map(_fcNorm).indexOf(_fcNorm(value));
      if (i >= 0) _fcFiltros[key].splice(i, 1); else _fcFiltros[key].push(value);
      _pag = 1; renderTabela();
    }
  };
  window.fcMsToggle = function(key, ev){
    if (ev) ev.stopPropagation();
    const el = $('msf-' + key);
    if (!el) return;
    const abrir = !el.classList.contains('open');
    document.querySelectorAll('.ms.open').forEach(m => m.classList.remove('open'));
    if (abrir) el.classList.add('open');
  };
  window.fcMsChange = function(key){
    const el = $('msf-' + key);
    if (!el) return;
    _fcFiltros[key] = [...el.querySelectorAll('input[type=checkbox]:checked')].map(c => c.value);
    const lbl = $('msf-' + key + '-label');
    const n = _fcFiltros[key].length;
    if (lbl) lbl.textContent = n === 0 ? _FC_MS_DEFAULT[key] : (n === 1 ? _fcFiltros[key][0] : (n + ' selecionados'));
    _pag = 1;
    renderTabela();
  };
  window.fcMsClear = function(key){
    const el = $('msf-' + key);
    if (!el) return;
    el.querySelectorAll('input[type=checkbox]').forEach(c => { c.checked = false; });
    window.fcMsChange(key);
  };
  // fecha painéis ao clicar fora; clique dentro do painel não fecha
  document.addEventListener('click', () => document.querySelectorAll('.ms.open').forEach(m => m.classList.remove('open')));
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ms-panel').forEach(p => p.addEventListener('click', e => e.stopPropagation()));
  });

  // ----- Cards (somatórios) -----
  function atualizarCards(rows){
    const total = rows.length;
    const vidas = rows.reduce((s,r) => s + (parseInt(r.numero_vidas) || 0), 0);
    const valor = rows.reduce((s,r) => s + (parseFloat(r.valor_primeira_fatura) || 0), 0);
    const novos = rows.filter(r => ['novo','seguro novo'].includes((r.tipo||'').toLowerCase())).length;
    const _set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
    _set('fc-total', total.toLocaleString('pt-BR'));
    _set('fc-vidas', vidas.toLocaleString('pt-BR'));
    _set('fc-valor', 'R$ ' + brl(valor));
    _set('fc-novos', novos.toLocaleString('pt-BR'));
  }

  function _fcCardFilter(rows){
    if (!_cardFilter) return rows;
    if (_cardFilter === 'novos') return rows.filter(r => (r.tipo||'').toLowerCase() === 'novo');
    if (_cardFilter === 'vidas') return rows.filter(r => (parseInt(r.numero_vidas)||0) > 0);
    if (_cardFilter === 'valor') return rows.filter(r => (parseFloat(r.valor_primeira_fatura)||0) > 0);
    return rows;
  }

  window.fcFiltrarPorCard = function(tipo, el){
    if (_cardFilter === tipo) {
      _cardFilter = null;
      document.querySelectorAll('.card[data-fc-card]').forEach(c => c.classList.remove('fc-active'));
    } else {
      _cardFilter = tipo;
      document.querySelectorAll('.card[data-fc-card]').forEach(c => c.classList.remove('fc-active'));
      if (el) el.classList.add('fc-active');
    }
    _pag = 1;
    renderTabela();
  };

  // Total Fatura: mostra as parcelas (quebra de linha) + o total somado
  function _fcFaturaCell(r){
    const parts = [];
    for (let k = 1; k <= 5; k++){
      const v = r['fatura_' + k];
      if (v !== null && v !== '' && v !== undefined && parseFloat(v)) parts.push('R$ ' + brl(v));
    }
    const total = r.valor_primeira_fatura;
    const totalStr = (total !== null && total !== '' && total !== undefined) ? 'R$ ' + brl(total) : '';
    if (parts.length <= 1) return totalStr;
    return parts.join('<br>') +
      '<div style="border-top:1px solid var(--border);margin-top:3px;padding-top:3px"><strong>' + totalStr + '</strong></div>';
  }

  // ----- Render -----
  function renderTabela(){
    let rows = _fcCardFilter(_fcAplicarFiltros(_todos));
    const total = rows.length;
    const totalPag = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (_pag > totalPag) _pag = totalPag;
    const ini = (_pag - 1) * PAGE_SIZE;
    const fim = ini + PAGE_SIZE;
    const pageRows = rows.slice(ini, fim);

    const tbody = $('fc-tbody');
    if (pageRows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="16" style="text-align:center;padding:30px;color:#888">Nenhum fechamento encontrado.</td></tr>';
    } else {
      tbody.innerHTML = pageRows.map(r => `
        <tr>
          <td>${r.id}</td>
          <td>${tipoBadge(r.tipo)}</td>
          <td>${escapeHtml(r.entrada||'')}</td>
          <td>${escapeHtml(r.ramo||'')}</td>
          <td>${escapeHtml(r.operadora||'')}</td>
          <td>${escapeHtml(r.mes||'')}</td>
          <td><strong>${escapeHtml(r.empresa||'')}</strong></td>
          <td>${escapeHtml(r.cnpj||'')}</td>
          <td>${escapeHtml(r.analista||'')}</td>
          <td>${dtBR(r.vigencia_operadora)}</td>
          <td style="text-align:right">${r.numero_vidas ? parseInt(r.numero_vidas).toLocaleString('pt-BR') : ''}</td>
          <td style="text-align:right">${pct(r.pct_agenciamento)}</td>
          <td style="text-align:right">${pct(r.pct_comissao)}</td>
          <td style="text-align:right;white-space:normal">${_fcFaturaCell(r)}</td>
          <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${escapeHtml(r.observacao||'')}">${escapeHtml(r.observacao||'')}</td>
          <td style="white-space:nowrap"><button class="btn btn-outline" style="padding:4px 8px;font-size:12px" onclick="fcView(${r.id})" title="Visualizar"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:-3px">visibility</span></button> <button class="btn btn-outline" style="padding:4px 10px;font-size:12px" onclick="fcEdit(${r.id})">Editar</button></td>
        </tr>
      `).join('');
    }

    $('fc-count').textContent = `Lista de Fechamentos (${total})`;
    $('fc-pag-info').textContent = `Página ${_pag} de ${totalPag} — ${total} registro(s)`;
    renderPag(totalPag);
    atualizarCards(_fcAplicarFiltros(_todos));
    fcRenderBreakdown();
  }

  function renderPag(totalPag){
    const div = $('fc-pag-btns');
    if (totalPag <= 1) { div.innerHTML = ''; return; }
    let html = '';
    const max = 7;
    let ini = Math.max(1, _pag - 3);
    let fim = Math.min(totalPag, ini + max - 1);
    ini = Math.max(1, fim - max + 1);
    html += `<button class="pb" onclick="window._fcGoPag(${_pag-1})" ${_pag<=1?'disabled':''}>‹</button>`;
    for (let i = ini; i <= fim; i++) {
      html += `<button class="pb${i===_pag?' active':''}" onclick="window._fcGoPag(${i})">${i}</button>`;
    }
    html += `<button class="pb" onclick="window._fcGoPag(${_pag+1})" ${_pag>=totalPag?'disabled':''}>›</button>`;
    div.innerHTML = html;
  }
  window._fcGoPag = function(p){ _pag = p; renderTabela(); };

  // ----- Load lista -----
  window.fcLoad = async function(){
    try {
      const params = {};
      const s = $('ff-search'); if (s && s.value) params.search = s.value;
      // tipo/ramo/operadora/mes/analista agora são multi-seleção e filtram no front
      const de  = $('ff-de'); if (de && de.value) params.de = de.value;
      const ate = $('ff-ate'); if (ate && ate.value) params.ate = ate.value;

      const r = await apiGet('listar_fechamentos', params);
      if (!r.ok) {
        $('fc-tbody').innerHTML = `<tr><td colspan="16" style="text-align:center;color:#c0392b;padding:20px">Erro: ${escapeHtml(r.msg||'falha')}</td></tr>`;
        return;
      }
      _todos = r.data || [];
      _pag = 1;
      renderTabela();
    } catch(e) {
      console.error(e);
      $('fc-tbody').innerHTML = `<tr><td colspan="16" style="text-align:center;color:#c0392b;padding:20px">Erro ao carregar: ${escapeHtml(e.message||'')}</td></tr>`;
    }
  };

  // ----- Modal -----
  // Soma das 5 parcelas de agenciamento (exibe o total ao vivo no modal)
  window.fcAgencTotal = function(){
    let total = 0;
    for (let k = 1; k <= 5; k++){ const v = parseDec($('mf-agenc-' + k).value); if (v !== null) total += v; }
    const el = $('mf-agenc-total');
    if (el) el.textContent = total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) + '%';
  };

  // Soma das 5 parcelas de fatura (exibe o total ao vivo no modal)
  window.fcFaturaTotal = function(){
    let total = 0;
    for (let k = 1; k <= 5; k++){ const v = parseDec($('mf-fatura-' + k).value); if (v !== null) total += v; }
    const el = $('mf-fatura-total');
    if (el) el.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
  };

  // Permissões no modal: financeiro só edita a COMISSÃO RECEBIDA (resto travado);
  // demais perfis editam tudo MENOS a comissão recebida (lançamento do financeiro).
  function fcAplicarPermissoes(){
    const com = ['mf-comrec-1','mf-comrec-2','mf-comrec-3','mf-comrec-4','mf-comrec-5',
                 'mf-comdata-1','mf-comdata-2','mf-comdata-3','mf-comdata-4','mf-comdata-5'];
    const outros = ['mf-tipo','mf-entrada','mf-ramo','mf-operadora','mf-mes','mf-analista',
                    'mf-empresa','mf-cnpj','mf-vigencia','mf-vidas',
                    'mf-fatura-1','mf-fatura-2','mf-fatura-3','mf-fatura-4','mf-fatura-5',
                    'mf-agenc-1','mf-agenc-2','mf-agenc-3','mf-agenc-4','mf-agenc-5','mf-comis','mf-obs'];
    const fin = !!window.IS_FINANCEIRO;
    com.forEach(id => { const el = $(id); if (el) el.disabled = !fin; });
    outros.forEach(id => { const el = $(id); if (el) el.disabled = fin; });
    if (fin){ const d = $('mf-btn-del'); if (d) d.style.display = 'none'; }
  }

  window.fcOpenModal = function(){
    $('mf-title').innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">task_alt</span> Novo Fechamento';
    $('mf-id').value = '';
    ['mf-tipo','mf-entrada','mf-ramo','mf-operadora','mf-mes','mf-analista',
     'mf-empresa','mf-cnpj','mf-vigencia','mf-vidas',
     'mf-fatura-1','mf-fatura-2','mf-fatura-3','mf-fatura-4','mf-fatura-5',
     'mf-agenc-1','mf-agenc-2','mf-agenc-3','mf-agenc-4','mf-agenc-5','mf-comis',
     'mf-comrec-1','mf-comrec-2','mf-comrec-3','mf-comrec-4','mf-comrec-5',
     'mf-comdata-1','mf-comdata-2','mf-comdata-3','mf-comdata-4','mf-comdata-5','mf-obs'].forEach(id => {
      const el = $(id); if (el) el.value = '';
    });
    fcAgencTotal();
    fcFaturaTotal();
    $('mf-btn-del').style.display = 'none';
    window.fcSetReadOnly(false);
    fcAplicarPermissoes();
    openModal('mod-fc');
  };

  // Modo somente leitura no modal de fechamento
  window.fcSetReadOnly = function(ro){
    const m = document.getElementById('mod-fc');
    if (!m) return;
    m.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = ro; });
    m.querySelectorAll('[data-edit-only]').forEach(el => { el.style.display = ro ? 'none' : ''; });
  };

  // Abre o fechamento apenas para leitura
  window.fcView = function(id){
    window.fcEdit(id);
    $('mf-title').innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">visibility</span> Visualizar Fechamento #' + id;
    window.fcSetReadOnly(true);
    const del = document.getElementById('mf-btn-del'); if (del) del.style.display = 'none';
  };

  window.fcEdit = function(id){
    const r = _todos.find(x => parseInt(x.id) === parseInt(id));
    if (!r) return;
    window.fcSetReadOnly(false);
    $('mf-title').innerHTML = `<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar Fechamento #${id}`;
    $('mf-id').value = r.id;
    $('mf-tipo').value = r.tipo || '';
    $('mf-entrada').value = r.entrada || '';
    $('mf-ramo').value = r.ramo || '';
    $('mf-operadora').value = r.operadora || '';
    $('mf-mes').value = r.mes || '';
    $('mf-analista').value = r.analista || '';
    $('mf-empresa').value = r.empresa || '';
    $('mf-cnpj').value = r.cnpj || '';
    $('mf-vigencia').value = r.vigencia_operadora ? String(r.vigencia_operadora).substring(0,10) : '';
    $('mf-vidas').value = r.numero_vidas || '';
    for (let k = 1; k <= 5; k++){
      const v = r['fatura_' + k];
      $('mf-fatura-' + k).value = (v !== null && v !== '' && v !== undefined) ? brl(v) : '';
    }
    fcFaturaTotal();
    for (let k = 1; k <= 5; k++){
      const v = r['agenciamento_' + k];
      $('mf-agenc-' + k).value = (v !== null && v !== '' && v !== undefined)
        ? (parseFloat(v) * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
    }
    fcAgencTotal();
    $('mf-comis').value = r.pct_comissao ? (parseFloat(r.pct_comissao) * 100).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
    $('mf-obs').value = r.observacao || '';
    // Comissão recebida (5 valores + 5 datas)
    for (let k = 1; k <= 5; k++){
      const cv = r['comissao_rec_' + k];
      $('mf-comrec-' + k).value = (cv !== null && cv !== '' && cv !== undefined) ? brl(cv) : '';
      const cd = r['comissao_data_' + k];
      $('mf-comdata-' + k).value = cd ? String(cd).substring(0,10) : '';
    }
    $('mf-btn-del').style.display = 'inline-flex';
    fcAplicarPermissoes();
    openModal('mod-fc');
  };

  window.fcSave = async function(){
    const id = $('mf-id').value;
    const tipo = $('mf-tipo').value.trim();
    const empresa = $('mf-empresa').value.trim();
    const analista = $('mf-analista').value.trim();
    if (!tipo || !empresa) {
      alert('Preencha os campos obrigatórios: Tipo e Empresa.');
      return;
    }
    // Percentuais vão para o banco como decimal (5,00% → 0.05)
    const comisRaw = parseDec($('mf-comis').value);
    // Agenciamento agora são 5 parcelas; o total (soma) vai em pct_agenciamento.
    const agencFrac = {}; let agencTotal = 0; let temAgenc = false;
    for (let k = 1; k <= 5; k++){
      const raw  = parseDec($('mf-agenc-' + k).value);
      const frac = raw !== null ? raw / 100 : null;
      agencFrac['agenciamento_' + k] = frac;
      if (frac !== null){ agencTotal += frac; temAgenc = true; }
    }
    // 1ª Fatura agora são 5 parcelas; valor_primeira_fatura guarda o TOTAL (soma).
    const fatVal = {}; let fatTotal = 0; let temFat = false;
    for (let k = 1; k <= 5; k++){
      const v = parseDec($('mf-fatura-' + k).value);
      fatVal['fatura_' + k] = v;
      if (v !== null){ fatTotal += v; temFat = true; }
    }
    const body = {
      id: id ? parseInt(id) : null,
      tipo,
      entrada: $('mf-entrada').value.trim() || null,
      ramo: $('mf-ramo').value.trim() || null,
      operadora: $('mf-operadora').value.trim() || null,
      mes: $('mf-mes').value.trim() || null,
      empresa,
      cnpj: $('mf-cnpj').value.trim() || null,
      analista,
      vigencia_operadora: $('mf-vigencia').value || null,
      numero_vidas: $('mf-vidas').value ? parseInt($('mf-vidas').value) : null,
      agenciamento_1: agencFrac.agenciamento_1,
      agenciamento_2: agencFrac.agenciamento_2,
      agenciamento_3: agencFrac.agenciamento_3,
      agenciamento_4: agencFrac.agenciamento_4,
      agenciamento_5: agencFrac.agenciamento_5,
      pct_agenciamento: temAgenc ? agencTotal : null,
      pct_comissao: comisRaw !== null ? comisRaw / 100 : null,
      fatura_1: fatVal.fatura_1,
      fatura_2: fatVal.fatura_2,
      fatura_3: fatVal.fatura_3,
      fatura_4: fatVal.fatura_4,
      fatura_5: fatVal.fatura_5,
      valor_primeira_fatura: temFat ? fatTotal : null,
      comissao_rec_1: parseDec($('mf-comrec-1').value),
      comissao_rec_2: parseDec($('mf-comrec-2').value),
      comissao_rec_3: parseDec($('mf-comrec-3').value),
      comissao_rec_4: parseDec($('mf-comrec-4').value),
      comissao_rec_5: parseDec($('mf-comrec-5').value),
      comissao_data_1: $('mf-comdata-1').value || null,
      comissao_data_2: $('mf-comdata-2').value || null,
      comissao_data_3: $('mf-comdata-3').value || null,
      comissao_data_4: $('mf-comdata-4').value || null,
      comissao_data_5: $('mf-comdata-5').value || null,
      observacao: $('mf-obs').value.trim() || null
    };
    const action = id ? 'editar_fechamento' : 'criar_fechamento';
    const r = await apiPost(action, body);
    if (!r.ok) { alert('Erro ao salvar: ' + (r.msg || 'falha')); return; }
    closeModal('mod-fc');
    fcLoad();
  };

  window.fcDelete = async function(){
    const id = $('mf-id').value;
    if (!id) return;
    if (!confirm('Excluir este fechamento? Esta ação não pode ser desfeita.')) return;
    const r = await apiPost('excluir_fechamento', {id: parseInt(id)});
    if (!r.ok) { alert('Erro: ' + (r.msg || 'falha')); return; }
    closeModal('mod-fc');
    fcLoad();
  };

  // ----- Export Excel -----
  window.fcExportXLSX = function(){
    if (!window.XLSX) {
      alert('Biblioteca XLSX não carregada. Recarregue a página e tente de novo.');
      return;
    }
    const rows = _fcCardFilter(_fcAplicarFiltros(_todos));
    const dados = rows.map(r => ({
      'TIPO': r.tipo || '',
      'ENTRADA': r.entrada || '',
      'RAMO': r.ramo || '',
      'OPERADORA': r.operadora || '',
      'MÊS': r.mes || '',
      'EMPRESA': r.empresa || '',
      'CNPJ': r.cnpj || '',
      'ANALISTA': r.analista || '',
      'VIGÊNCIA OPERADORA': dtBR(r.vigencia_operadora),
      'Nº DE VIDAS': r.numero_vidas || '',
      '% AGENCIAMENTO': r.pct_agenciamento ? parseFloat(r.pct_agenciamento) : '',
      '% COMISSÃO': r.pct_comissao ? parseFloat(r.pct_comissao) : '',
      '1ª FATURA': (r.fatura_1 !== null && r.fatura_1 !== '' && r.fatura_1 !== undefined) ? parseFloat(r.fatura_1) : '',
      'SOMATÓRIO DE FATURAS': r.valor_primeira_fatura ? parseFloat(r.valor_primeira_fatura) : '',
      'OBS.': r.observacao || ''
    }));
    const ws = XLSX.utils.json_to_sheet(dados);
    // Cabeçalho com a cor da Interweg (teal, texto branco em negrito)
    const headerStyle = {
      font:      { bold: true, color: { rgb: 'FFFFFF' }, sz: 11, name: 'Calibri' },
      fill:      { patternType: 'solid', fgColor: { rgb: '00C9A7' } },
      alignment: { horizontal: 'left', vertical: 'center', wrapText: false },
      border: {
        top:    { style: 'thin', color: { rgb: '009880' } },
        bottom: { style: 'thin', color: { rgb: '009880' } },
        left:   { style: 'thin', color: { rgb: '009880' } },
        right:  { style: 'thin', color: { rgb: '009880' } }
      }
    };
    const nCols = dados.length ? Object.keys(dados[0]).length : 0;
    for (let c = 0; c < nCols; c++) {
      const addr = XLSX.utils.encode_cell({ r: 0, c });
      if (ws[addr]) ws[addr].s = headerStyle;
    }
    ws['!rows'] = [{ hpt: 22 }];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Fechamento');
    const hoje = new Date().toISOString().substring(0,10);
    XLSX.writeFile(wb, `fechamento_${hoje}.xlsx`);
  };

  // ----- Boot -----
  document.addEventListener('DOMContentLoaded', function(){
    carregarAnalistas();
    carregarSeguradoras();
    fcLoad();
    // Financeiro não cria fechamento — esconde o botão "Novo Fechamento"
    if (window.IS_FINANCEIRO){
      document.querySelectorAll('[onclick="fcOpenModal()"]').forEach(b => { b.style.display = 'none'; });
    }
  });

})();
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding padding padding
// padding padding padding padding padding padding padding paddin