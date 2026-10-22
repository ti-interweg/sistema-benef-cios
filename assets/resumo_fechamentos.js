/* Resumo de Fechamentos no Dashboard (POR ENTRADA / POR TIPO) — somente leitura.
   Réplica do painel de fechamento.php: contagem + Fatura/Agenc./Comis. em R$
   (R$ = Fatura × %, somando só Saúde/Odonto). */
(function(){
  const FC_ENTRADAS = ['Interweg MG','Interweg JF','Interweg SP','Fred','Parceiro - Bernardo','Parceiro - Elmo','Finlândia'];
  const FC_TIPOS    = ['Seguro Novo','Seguro Novo de Cliente Interweg','Renovação','Nomeação'];

  const norm = s => String(s == null ? '' : s).trim().toUpperCase();
  const esc  = s => String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const brl  = v => (parseFloat(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 });
  function isSaudeOdonto(ramo){
    const n = String(ramo == null ? '' : ramo).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
    return n === 'saude' || n === 'odonto' || n === 'saude/odonto' || n === 'saude odonto' || n === 'saude e odonto';
  }

  function agg(base, campo){
    const map = {};
    base.forEach(r => {
      const k = String(r[campo] == null ? '' : r[campo]).trim() || '—';
      if (!map[k]) map[k] = { count:0, fatSO:0, agR:0, coR:0 };
      const g = map[k];
      g.count++;
      const f1 = parseFloat(r.fatura_1);
      const ag = parseFloat(r.pct_agenciamento);
      const co = parseFloat(r.pct_comissao);
      if (isSaudeOdonto(r.ramo)) {
        const fso = isNaN(f1) ? 0 : f1;
        g.fatSO += fso;
        if (!isNaN(ag)) g.agR += fso * ag;   // pct fração (127,42% = 1,2742)
        if (!isNaN(co)) g.coR += fso * co;   // pct fração (4,98% = 0,0498)
      }
    });
    return map;
  }

  function block(titulo, keys, map, totalCount){
    let allKeys = keys.slice();
    Object.keys(map).forEach(k => { if (k !== '—' && !allKeys.some(x => norm(x)===norm(k))) allKeys.push(k); });
    const items = allKeys.map(v => {
      const rk = Object.keys(map).find(k => norm(k)===norm(v));
      const a  = (rk ? map[rk] : null) || { count:0, fatSO:0, agR:0, coR:0 };
      return Object.assign({ v }, a);
    }).sort((x,y) => y.count - x.count || y.fatSO - x.fatSO);
    const maxc = items.reduce((m,i) => Math.max(m, i.count), 0) || 1;
    const rowHtml = it => {
      const share = totalCount ? Math.round(it.count / totalCount * 100) : 0;
      const w = Math.round(it.count / maxc * 100);
      return `<div class="fc-brk-row${it.count === 0 ? ' cc-brk-zero' : ''}">
        <div class="fc-brk-head"><span class="fc-brk-lbl">${esc(it.v)}</span><span class="fc-brk-count"><b>${it.count}</b> · ${share}%</span></div>
        <div class="cc-brk-track"><div class="cc-brk-fill" style="width:${w}%"></div></div>
        <div class="fc-brk-metrics"><span>Fatura&nbsp;<b>R$ ${brl(it.fatSO)}</b></span><span>Agenc.&nbsp;<b>R$ ${brl(it.agR)}</b></span><span>Comis.&nbsp;<b>R$ ${brl(it.coR)}</b></span></div>
      </div>`;
    };
    const first = items.slice(0,3).map(rowHtml).join('');
    const rest  = items.slice(3);
    let restHtml = '';
    if (rest.length){
      const id = 'fcmore'+(++_fcSeq);
      restHtml = `<div id="${id}" style="display:none">${rest.map(rowHtml).join('')}</div>`
        + `<button type="button" class="cc-more-btn" onclick="ccToggleMore('${id}',this)">ver mais (${rest.length})</button>`;
    }
    return `<div class="cc-break-block"><div class="cc-break-title"><span>${esc(titulo)}</span></div><div class="fc-brk-list">${first}${restHtml}</div></div>`;
  }
  let _fcSeq = 0;

  async function render(){
    const wrap = document.getElementById('dash-fech-resumo');
    if (!wrap) return;
    wrap.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:6px 2px">Carregando…</div>';
    let data = [];
    try {
      const r = await fetch('api.php?action=listar_fechamentos').then(x => x.json());
      data = (r && r.data) || [];
    } catch (e) {
      wrap.innerHTML = '<div style="color:var(--muted);font-size:13px">Não foi possível carregar o resumo de fechamentos.</div>';
      return;
    }
    const total = data.length;

    // KPIs (mesma lógica dos cards do Fechamento)
    const vidas = data.reduce((s, r) => s + (parseInt(r.numero_vidas) || 0), 0);
    const valor = data.reduce((s, r) => s + (parseFloat(r.valor_primeira_fatura) || 0), 0);
    const novos = data.filter(r => ['novo','seguro novo'].includes(String(r.tipo || '').toLowerCase())).length;
    const setTxt = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    setTxt('dash-fech-total', total.toLocaleString('pt-BR'));
    setTxt('dash-fech-vidas', vidas.toLocaleString('pt-BR'));
    setTxt('dash-fech-valor', 'R$ ' + brl(valor));
    setTxt('dash-fech-novos', novos.toLocaleString('pt-BR'));

    wrap.innerHTML =
      block('Por entrada', FC_ENTRADAS, agg(data, 'entrada'), total) +
      block('Por tipo',    FC_TIPOS,    agg(data, 'tipo'),    total);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', render);
  else render();
})();
