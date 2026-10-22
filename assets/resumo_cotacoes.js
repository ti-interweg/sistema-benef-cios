/* ============================================================
   Resumo de Cotações (Dashboard) — por Situação, Entrada e Tipo
   Considera apenas ramos de saúde/odonto. Mostra só a contagem.
   ============================================================ */
(function(){
  const ENTRADAS = [
    ['Interweg MG','Belo Horizonte'], ['Interweg SP','São Paulo'], ['Interweg JF','Juiz de Fora'],
    ['Finlândia','Finlândia'], ['Fred','Fred'],
    ['Parceiro - Bernardo','Parceiro - Bernardo'], ['Parceiro - Elmo','Parceiro - Elmo']
  ];
  const TIPOS = ['Seguro Novo','Seguro Novo de Cliente Interweg','Renovação','Nomeação'];
  const SITUACOES = [
    ['Fechadas',  ['EM IMPLANTAÇÃO','EM NOMEAÇÃO','IMPLANTAÇÃO FINALIZADA','INICIAR IMPLANTAÇÃO','NOMEAÇÃO CONCLUÍDA','PASSAGEM DE BASTÃO']],
    ['Em aberto', ['ESTUDO APRESENTADO','AGUARDANDO DADOS','AGUARDANDO DOCUMENTAÇÃO','EM COTAÇÃO','EM NEGOCIAÇÃO COM A OPERADORA','INICIAR COTAÇÃO']],
    ['Perdidas',  ['DECLINADA']]
  ];
  const RAMOS_OK = ['saude','odonto','saude odonto'];

  const norm = s => String(s==null?'':s).toLowerCase().normalize('NFD')
    .replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();
  const esc = s => String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

  function rowHtml(label, count, maxc){
    const w = maxc ? Math.round(count / maxc * 100) : 0;
    return `<div class="cc-brk-row${count===0?' cc-brk-zero':''}">
      <div class="cc-brk-lbl">${esc(label)}</div>
      <div class="cc-brk-track"><div class="cc-brk-fill" style="width:${w}%"></div></div>
      <div class="cc-brk-val"><b>${count}</b></div>
    </div>`;
  }
  let _ccSeq = 0;
  function blockHtml(titulo, items){
    const maxc = items.reduce((m,i)=>Math.max(m,i.count),0) || 1;
    const first = items.slice(0,3).map(i=>rowHtml(i.label,i.count,maxc)).join('');
    const rest  = items.slice(3);
    let restHtml = '';
    if (rest.length){
      const id = 'ccmore'+(++_ccSeq);
      restHtml = `<div id="${id}" style="display:none">${rest.map(i=>rowHtml(i.label,i.count,maxc)).join('')}</div>`
        + `<button type="button" class="cc-more-btn" onclick="ccToggleMore('${id}',this)">ver mais (${rest.length})</button>`;
    }
    return `<div class="cc-break-block"><div class="cc-break-title"><span>${esc(titulo)}</span></div><div class="cc-brk-list">${first}${restHtml}</div></div>`;
  }
  window.ccToggleMore = function(id, btn){
    const el = document.getElementById(id); if(!el) return;
    const aberto = el.style.display !== 'none';
    el.style.display = aberto ? 'none' : '';
    btn.textContent = aberto ? ('ver mais ('+el.children.length+')') : 'ver menos';
  };

  function aggBy(base, campo){
    const agg = {};
    base.forEach(d => {
      const raw = String(d[campo]==null?'':d[campo]).trim() || '—';
      const nk = norm(raw);
      if (!agg[nk]) agg[nk] = { label: raw, count: 0 };
      agg[nk].count++;
    });
    return agg;
  }
  function itemsFromPreset(agg, preset, isPair){
    // preset: para entrada = [[valor,label]], para tipo = [valor]
    const items = preset.map(p => {
      const v = isPair ? p[0] : p;
      const l = isPair ? p[1] : p;
      return { label: l, count: (agg[norm(v)] ? agg[norm(v)].count : 0) };
    });
    const presetKeys = preset.map(p => norm(isPair ? p[0] : p));
    Object.keys(agg).forEach(nk => {
      if (nk !== '—' && !presetKeys.includes(nk)) items.push({ label: agg[nk].label, count: agg[nk].count });
    });
    items.sort((a,b)=>b.count-a.count);
    return items;
  }

  async function render(){
    const wrap = document.getElementById('dash-cot-resumo');
    if (!wrap) return;
    wrap.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:6px 2px">Carregando resumo…</div>';
    let data = [];
    try {
      const r = await fetch('api.php?action=listar_cotacoes').then(x=>x.json());
      data = (r && r.data) || [];
    } catch(e){
      wrap.innerHTML = '<div style="color:var(--muted);font-size:13px">Não foi possível carregar o resumo de cotações.</div>';
      return;
    }
    const base = data.filter(d => RAMOS_OK.includes(norm(d.ramo)));

    const situItems = SITUACOES.map(([nome, sts]) => {
      const setN = sts.map(norm);
      return { label: nome, count: base.filter(d => setN.includes(norm(d.status))).length };
    }).sort((a,b)=>b.count-a.count);

    const entItems  = itemsFromPreset(aggBy(base,'entrada'), ENTRADAS, true);
    const tipoItems = itemsFromPreset(aggBy(base,'tipo'),    TIPOS,    false);

    wrap.innerHTML =
      blockHtml('Por situação', situItems) +
      blockHtml('Por entrada',  entItems) +
      blockHtml('Por tipo',     tipoItems);
  }

  document.addEventListener('DOMContentLoaded', render);
})();
