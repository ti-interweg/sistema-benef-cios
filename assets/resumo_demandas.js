/* Resumo de Demandas no Dashboard (POR SITUAÇÃO / POR ANALISTA) — só leitura.
   Mesmo estilo dos resumos de Cotações/Fechamentos. */
(function(){
  const esc = s => String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const norm = s => String(s==null?'':s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim();

  function rowHtml(label, count, maxc){
    const w = maxc ? Math.round(count / maxc * 100) : 0;
    return `<div class="cc-brk-row${count===0?' cc-brk-zero':''}">
      <div class="cc-brk-lbl">${esc(label)}</div>
      <div class="cc-brk-track"><div class="cc-brk-fill" style="width:${w}%"></div></div>
      <div class="cc-brk-val"><b>${count}</b></div>
    </div>`;
  }
  let _seq = 0;
  function blockHtml(titulo, items){
    const maxc = items.reduce((m,i)=>Math.max(m,i.count),0) || 1;
    const first = items.slice(0,3).map(i=>rowHtml(i.label,i.count,maxc)).join('');
    const rest  = items.slice(3);
    let restHtml = '';
    if (rest.length){
      const id = 'rdmore'+(++_seq);
      restHtml = `<div id="${id}" style="display:none">${rest.map(i=>rowHtml(i.label,i.count,maxc)).join('')}</div>`
        + `<button type="button" class="cc-more-btn" onclick="ccToggleMore('${id}',this)">ver mais (${rest.length})</button>`;
    }
    return `<div class="cc-break-block"><div class="cc-break-title"><span>${esc(titulo)}</span></div><div class="cc-brk-list">${first}${restHtml}</div></div>`;
  }
  function aggCount(base, field){
    const map = {};
    base.forEach(d => { const raw = String(d[field]==null?'':d[field]).trim() || '—'; const k = norm(raw); if(!map[k]) map[k]={label:raw,count:0}; map[k].count++; });
    return Object.values(map).filter(x => x.label !== '—').sort((a,b)=>b.count-a.count);
  }

  async function render(){
    const wrap = document.getElementById('dash-dem-resumo');
    if (!wrap) return;
    wrap.innerHTML = '<div style="color:var(--muted);font-size:13px;padding:6px 2px">Carregando…</div>';
    let data = [];
    try { const r = await fetch('api.php?action=listar').then(x=>x.json()); data = (r && r.data) || []; }
    catch(e){ wrap.innerHTML = '<div style="color:var(--muted);font-size:13px">Não foi possível carregar o resumo de demandas.</div>'; return; }

    const nrm = s => String(s==null?'':s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
    const emAnalise = data.filter(d => nrm(d.situacao) === 'em analise').length;
    const concluido = data.filter(d => nrm(d.situacao) === 'concluido').length;
    const pendente  = data.filter(d => { const n=nrm(d.situacao); return n !== 'em analise' && n !== 'concluido'; }).length;
    const situItems = [
      { label:'Em andamento', count:emAnalise },
      { label:'Concluído',    count:concluido },
      { label:'Pendente',     count:pendente }
    ].sort((a,b)=>b.count-a.count);

    wrap.innerHTML = blockHtml('Por situação', situItems) + blockHtml('Por analista', aggCount(data,'analista_nome'));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', render);
  else render();
})();
