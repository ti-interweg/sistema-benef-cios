// ============================================================
// PASSAGEM DE BASTÃO — Lista + Nova (form completo) + Editar (modal padrão)
// ============================================================
(function(){
  function $(id){ return document.getElementById(id); }
  async function apiGet(a, p={}){ return (await fetch('api.php?' + new URLSearchParams({action:a, ...p}))).json(); }
  function esc(s){ return String(s||'').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function toastMsg(m){ if (typeof toast === 'function') toast(m); else alert(m); }
  function pbFmtData(s){ if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]}`:s; }
  function pbFmtDataHora(s){ if(!s) return '—'; const m=String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/); return m?`${m[3]}/${m[2]}/${m[1]} ${m[4]}`:pbFmtData(s); }
  let PB_COTACAO_ID = ''; // cotação de origem (ponte Controle de Cotação)
  // Destinatários padrão para notificação por e-mail de passagem de bastão
  const PB_EMAILS_NOTIF_PADRAO = 'beneficios3@interweg.com.br; beneficios1@interweg.com.br; comercialbeneficios@interweg.com.br';
  let pbEnvioAposSalvar = false; // true quando o modal de e-mail foi aberto pelo botão Salvar

  // Form completo (Nova) — ids "pb-"
  const KEYS = ['origem','seguradora','ramo','numero_contrato','cnpj','tipo_estipulacao','tipo_passagem',
    'grupo','nome','porte','comercial_interweg','corretora','planos','numero_vidas',
    'modalidade_contratacao','permanencia_contratual','inicio_vigencia','possui_subfaturas',
    'subfaturas_desc','possui_copart','copart_desc','tipo_custo','valor_fatura','comissionamento',
    'percentual_comissao','data_venc_boleto','data_venc_demais','necessidade_rateio','agenc_parcelas','fatura_parcelas','comp_receb_vitalicio','data_corte_inclusao',
    'data_corte_exclusao','possui_odonto_conjugado','odonto_conjugado_desc','possui_agregados',
    'agregados_desc','idade_limite_filho','exclui_filho_auto','prazo_inclusao_sem_carencia',
    'necessita_doc_mov','doc_mov_desc','copia_contrato_aditivos','possui_relatorio_sinistralidade',
    'caminho_pasta_rede','data_emissao','data_vencimento','contato_dia_a_dia','emails_faturamento',
    'emails_sinistralidade',
    'acessos_portais','resp_nome','resp_cargo','resp_email','resp_telefone','data_nascimento','contatos_diaadia',
    'resp_renov_nome','resp_renov_cargo','resp_renov_email','resp_renov_telefone','data_nascimento_renov','contatos_renov',
    'executivo_operadora','executivo_operadora_email','executivo_operadora_telefone','executivos_extras',
    'politica_beneficios','particularidades','observacao',
    'destinatarios_email_passagem'];

  // Editar (modal) — só os campos de Empresa/Contrato — ids "pe-"
  const STD = ['origem','ramo','seguradora','numero_contrato','cnpj','tipo_estipulacao','tipo_passagem','grupo','nome',
    'porte','data_emissao','data_vencimento','inicio_vigencia','numero_vidas','percentual_comissao',
    'emails_faturamento','resp_nome','resp_email','resp_telefone','observacao'];

  function fillSelect(id, nomes){ const s=$(id); if(s) s.innerHTML='<option value="">—</option>'+nomes.map(n=>`<option>${esc(n)}</option>`).join(''); }

  document.addEventListener('DOMContentLoaded', async () => {
    try { const r = await apiGet('seguradoras'); if (r.ok && r.data){ const ns=r.data.map(s=>s.nome); fillSelect('pb-seguradora',ns); fillSelect('pe-seguradora',ns); } } catch(_){}
    try { const r = await apiGet('analistas');   if (r.ok && r.data){ const ns=r.data.map(a=>a.nome); fillSelect('pb-analista',ns);   fillSelect('pe-analista',ns); } } catch(_){}
    // Autocomplete de Grupo e Razão Social a partir das empresas já cadastradas
    try {
      const r = await apiGet('empresas');
      if (r && r.data){
        const fillDL = (id, vals) => { const dl=$(id); if(!dl) return; dl.innerHTML=[...new Set(vals.filter(Boolean))].sort((a,b)=>String(a).localeCompare(String(b),'pt')).map(v=>`<option value="${esc(v).replace(/"/g,'&quot;')}"></option>`).join(''); };
        fillDL('pb-grupo-list', r.data.map(e=>e.grupo));
        fillDL('pb-nome-list',  r.data.map(e=>e.nome));
      }
    } catch(_){}
    // Ponte Controle de Cotação -> Passagem: pré-preenche o formulário
    try {
      const pf = JSON.parse(sessionStorage.getItem('pb_prefill') || 'null');
      if (pf){
        sessionStorage.removeItem('pb_prefill');
        PB_COTACAO_ID = pf.cotacao_id || '';
        const setSel=(id,v)=>{ const el=$(id); if(!el||v==null||v==='') return; const o=[...el.options].find(x=>pbNorm(x.value||x.textContent)===pbNorm(v)); if(o){el.value=o.value||o.textContent;} else {const n=document.createElement('option'); n.textContent=v; el.appendChild(n); el.value=v;} };
        const setIn=(id,v)=>{ const el=$(id); if(el&&v!=null&&v!=='') el.value=v; };
        setIn('pb-nome',pf.nome); setSel('pb-origem',pf.origem); setSel('pb-ramo',pf.ramo);
        setSel('pb-seguradora',pf.seguradora); setSel('pb-analista',pf.analista);
        setIn('pb-numero_vidas',pf.numero_vidas); setIn('pb-valor_fatura',pf.valor_fatura);
        if(pf.obs_origem){ const ob=$('pb-observacao'); if(ob && !ob.value) ob.value=pf.obs_origem; }
        // Notificação por e-mail (vinda da cotação)
        const cbN = $('pb-notificar_email_passagem');
        if (cbN && (pf.notificar_email_passagem == 1 || pf.notificar_email_passagem === '1' || pf.notificar_email_passagem === true)) {
          cbN.checked = true;
          if (pf.destinatarios_email_passagem) setIn('pb-destinatarios_email_passagem', pf.destinatarios_email_passagem);
          if (typeof window.pbToggleNotifEmail === 'function') window.pbToggleNotifEmail();
        }
        pbShowTab('nova'); window.scrollTo(0,0);
        toastMsg('Dados da cotação importados. Complete os campos e salve.');
      }
    } catch(_){}
    pbCarregarLista();

    // ── Suporte ao parâmetro ?edit=ID: vindo de Cadastros, abre direto a
    // passagem de bastão dessa empresa no modo "Editar na íntegra" ──
    try {
      const urlParams = new URLSearchParams(window.location.search);
      const editId = parseInt(urlParams.get('edit') || '0', 10);
      if (editId > 0) {
        // Carrega o registro e abre o form completo em modo edição
        const r = await (await fetch('api_passagem.php?action=get_passagem&id=' + editId)).json();
        if (r.ok && r.data) {
          const rec = r.data;
          KEYS.forEach(k => {
            const el = $('pb-' + k);
            if (el) {
              let v = rec[k];
              if (v && /^\d{4}-\d{2}-\d{2}/.test(String(v)) && (el.type === 'date')) v = String(v).slice(0,10);
              el.value = (v != null ? String(v) : '');
            }
          });
          pbSubfaturasParse();
          pbPlanosParse();
          if (typeof window.pbContatosRenovParse === 'function') window.pbContatosRenovParse(); if (typeof window.pbContatosDiaParse === 'function') window.pbContatosDiaParse(); if (typeof window.pbExecParse === 'function') window.pbExecParse(); if(typeof window.pbParcelasParse==='function') window.pbParcelasParse();
          const an = $('pb-analista'); if (an) an.value = rec.analista_nome || '';
          const idEl = $('pb-id'); if (idEl) idEl.value = String(editId);
          // Notificação por e-mail
          const cbN = $('pb-notificar_email_passagem');
          if (cbN) cbN.checked = (rec.notificar_email_passagem == 1 || rec.notificar_email_passagem === '1');
          if (typeof window.pbToggleNotifEmail === 'function') window.pbToggleNotifEmail();
          // Mostra botão "Enviar e-mail" quando a notificação está ativa
          const btnEm = $('pb-btn-enviar-email');
          if (btnEm) btnEm.style.display = (cbN && cbN.checked) ? '' : 'none';
          // Marca como já enviado, se houver registro
          if (rec.email_passagem_enviado_em) {
            const infoBox = $('pb-email-enviado-info');
            const qSpan = $('pb-email-enviado-quando');
            if (infoBox) infoBox.style.display = 'block';
            if (qSpan) qSpan.textContent = pbFmtDataHora(rec.email_passagem_enviado_em);
          }
          const banner = $('pb-edit-banner'); if (banner) banner.style.display = 'block';
          const lbl = $('pb-edit-id-label'); if (lbl) lbl.textContent = String(editId);
          const btnS = $('pb-btn-salvar');
          if (btnS) btnS.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar alterações';
          pbShowTab('nova');
          window.scrollTo(0,0);
          toastMsg('Passagem de bastão #' + editId + ' carregada — todos os campos editáveis.');
          // Limpa o parâmetro da URL pra não recarregar se a pessoa der refresh
          if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', window.location.pathname);
          }
        } else {
          toastMsg('Não foi possível carregar a passagem #' + editId);
        }
      }
    } catch(_){}

    // ── Suporte ao parâmetro ?ver=ID: vindo da Base de Clientes, abre o
    // formulário completo em modo SOMENTE LEITURA ──
    try {
      const urlParams2 = new URLSearchParams(window.location.search);
      const verId = parseInt(urlParams2.get('ver') || '0', 10);
      if (verId > 0 && typeof window.pbVisualizar === 'function') {
        await window.pbVisualizar(verId);
        toastMsg('Visualizando #' + verId + ' (somente leitura).');
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', window.location.pathname);
        }
      }
    } catch(_){}

    // Garante os destinatários padrão sempre que o campo estiver vazio
    try {
      const destN = $('pb-destinatarios_email_passagem');
      if (destN && !destN.value.trim()) {
        destN.value = PB_EMAILS_NOTIF_PADRAO;
        const cbN = $('pb-notificar_email_passagem'); if (cbN) cbN.checked = true;
        if (typeof window.pbToggleNotifEmail === 'function') window.pbToggleNotifEmail();
      }
    } catch(_){}
  });

  window.pbShowTab = function(tab){
    const isNova = (tab === 'nova');
    const lista=$('pb-pane-lista'), nova=$('pb-pane-nova'), tl=$('pb-tab-lista'), tn=$('pb-tab-nova');
    if (lista) lista.style.display = isNova ? 'none' : 'block';
    if (nova)  nova.style.display  = isNova ? 'block' : 'none';
    [tl, tn].forEach(b => { if (b) { b.style.borderColor=''; b.style.color=''; } });
    const act = isNova ? tn : tl;
    if (act) { act.style.borderColor='var(--accent)'; act.style.color='var(--accent)'; }
  };

  // ── Máscaras de moeda (R$) e porcentagem (%) — Valores e cobrança ──
  window.pbMaskMoney = function(el){
    let v = (el.value || '').replace(/\D/g, '');   // só dígitos (tratados como centavos)
    if (!v){ el.value = ''; return; }
    v = v.replace(/^0+(?=\d)/, '');                // remove zeros à esquerda
    while (v.length < 3) v = '0' + v;              // garante centavos
    const cents = v.slice(-2);
    let intp = v.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); // milhar com "."
    el.value = intp + ',' + cents;
  };
  window.pbMaskPercent = function(el){
    let v = (el.value || '').replace(/[^\d,]/g, ''); // dígitos e vírgula
    const i = v.indexOf(',');
    if (i >= 0){
      const parts = v.split(',');
      v = parts[0] + ',' + (parts[1] || '').replace(/,/g, '').slice(0, 2); // 1 vírgula, 2 casas
    }
    el.value = v;
  };

  // ── Subfaturas dinâmicas (Razão Social + CNPJ) ──
  function pbSubfatEsc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  window.pbSubfaturaAdd = function(razao, cnpj, contrato){
    const list = $('pb-subfaturas-list'); if (!list) return;
    const div = document.createElement('div');
    div.className = 'pb-subfat-row';
    div.style.cssText = 'display:flex;gap:8px;align-items:center';
    div.innerHTML =
      '<input type="text" class="pb-subfat-razao" placeholder="Razão Social" value="' + pbSubfatEsc(typeof razao==='string'?razao:'') + '" style="flex:2" oninput="pbSubfaturasSerialize()">' +
      '<input type="text" class="pb-subfat-cnpj" placeholder="CNPJ" value="' + pbSubfatEsc(typeof cnpj==='string'?cnpj:'') + '" style="flex:1" oninput="pbSubfaturasSerialize()">' +
      '<input type="text" class="pb-subfat-contrato" placeholder="Nº do Contrato" value="' + pbSubfatEsc(typeof contrato==='string'?contrato:'') + '" style="flex:1" oninput="pbSubfaturasSerialize()">' +
      '<button type="button" class="btn btn-outline btn-sm" data-edit-only title="Remover subfatura" style="padding:4px 10px" onclick="this.closest(\'.pb-subfat-row\').remove(); pbSubfaturasSerialize();">✕</button>';
    list.appendChild(div);
    const inp = div.querySelector('.pb-subfat-razao'); if (inp && razao === undefined) inp.focus();
    pbSubfaturasSerialize();
  };
  window.pbSubfaturasSerialize = function(){
    const list = $('pb-subfaturas-list'), ta = $('pb-subfaturas_desc');
    if (!list || !ta) return;
    const linhas = [];
    list.querySelectorAll('.pb-subfat-row').forEach(r => {
      const razao = ((r.querySelector('.pb-subfat-razao')||{}).value || '').trim();
      const cnpj  = ((r.querySelector('.pb-subfat-cnpj') ||{}).value || '').trim();
      const contrato = ((r.querySelector('.pb-subfat-contrato')||{}).value || '').trim();
      if (razao || cnpj || contrato) linhas.push(razao + '\t' + cnpj + '\t' + contrato);
    });
    ta.value = linhas.join('\n');
  };
  // ── Contatos extras (Renovação): permite adicionar vários contatos ──
  window.pbContatoRenovAdd = function(data){
    const list = $('pb-contatos-renov-list'); if (!list) return;
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'pb-contrenov-row';
    div.style.cssText = 'display:grid;grid-template-columns:1.4fr 1.2fr 1.6fr 1fr 150px 36px;gap:8px;align-items:center';
    div.innerHTML =
      '<input type="text" class="pb-cr-nome" placeholder="Nome" value="' + pbSubfatEsc(d.n) + '" oninput="pbContatosRenovSerialize()">' +
      '<input type="text" class="pb-cr-cargo" placeholder="Cargo" value="' + pbSubfatEsc(d.c) + '" oninput="pbContatosRenovSerialize()">' +
      '<input type="text" class="pb-cr-email" placeholder="E-mail" value="' + pbSubfatEsc(d.e) + '" oninput="pbContatosRenovSerialize()">' +
      '<input type="text" class="pb-cr-tel" placeholder="Telefone" value="' + pbSubfatEsc(d.t) + '" oninput="pbContatosRenovSerialize()">' +
      '<input type="date" class="pb-cr-nasc" value="' + pbSubfatEsc(d.d) + '" oninput="pbContatosRenovSerialize()">' +
      '<button type="button" class="btn btn-outline btn-sm" title="Remover contato" style="padding:4px 10px" onclick="this.closest(\'.pb-contrenov-row\').remove(); pbContatosRenovSerialize();">✕</button>';
    list.appendChild(div);
    const inp = div.querySelector('.pb-cr-nome'); if (inp && data === undefined) inp.focus();
    pbContatosRenovSerialize();
  };
  window.pbContatosRenovSerialize = function(){
    const list = $('pb-contatos-renov-list'), ta = $('pb-contatos_renov'); if (!list || !ta) return;
    const arr = [];
    list.querySelectorAll('.pb-contrenov-row').forEach(r => {
      const g = sel => ((r.querySelector(sel)||{}).value || '').trim();
      const o = { n:g('.pb-cr-nome'), c:g('.pb-cr-cargo'), e:g('.pb-cr-email'), t:g('.pb-cr-tel'), d:g('.pb-cr-nasc') };
      if (o.n || o.c || o.e || o.t || o.d) arr.push(o);
    });
    ta.value = arr.length ? JSON.stringify(arr) : '';
  };
  window.pbContatosRenovParse = function(){
    const list = $('pb-contatos-renov-list'), ta = $('pb-contatos_renov'); if (!list || !ta) return;
    list.innerHTML = '';
    let arr = []; try { arr = JSON.parse((ta.value || '').trim() || '[]'); } catch(e){ arr = []; }
    arr.forEach(o => window.pbContatoRenovAdd(o));
  };

  // ── Contatos adicionais (dia a dia) ──
  window.pbContatoDiaAdd = function(data){
    const list = $('pb-contatos-dia-list'); if (!list) return;
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'pb-contdia-row';
    div.style.cssText = 'display:grid;grid-template-columns:1.4fr 1.2fr 1.6fr 1fr 150px 36px;gap:8px;align-items:center';
    div.innerHTML =
      '<input type="text" class="pb-cd-nome" placeholder="Nome" value="' + pbSubfatEsc(d.n) + '" oninput="pbContatosDiaSerialize()">' +
      '<input type="text" class="pb-cd-cargo" placeholder="Cargo" value="' + pbSubfatEsc(d.c) + '" oninput="pbContatosDiaSerialize()">' +
      '<input type="text" class="pb-cd-email" placeholder="E-mail" value="' + pbSubfatEsc(d.e) + '" oninput="pbContatosDiaSerialize()">' +
      '<input type="text" class="pb-cd-tel" placeholder="Telefone" value="' + pbSubfatEsc(d.t) + '" oninput="pbContatosDiaSerialize()">' +
      '<input type="date" class="pb-cd-nasc" value="' + pbSubfatEsc(d.d) + '" oninput="pbContatosDiaSerialize()">' +
      '<button type="button" class="btn btn-outline btn-sm" title="Remover contato" style="padding:4px 10px" onclick="this.closest(\'.pb-contdia-row\').remove(); pbContatosDiaSerialize();">✕</button>';
    list.appendChild(div);
    const inp = div.querySelector('.pb-cd-nome'); if (inp && data === undefined) inp.focus();
    pbContatosDiaSerialize();
  };
  window.pbContatosDiaSerialize = function(){
    const list = $('pb-contatos-dia-list'), ta = $('pb-contatos_diaadia'); if (!list || !ta) return;
    const arr = [];
    list.querySelectorAll('.pb-contdia-row').forEach(r => {
      const g = sel => ((r.querySelector(sel)||{}).value || '').trim();
      const o = { n:g('.pb-cd-nome'), c:g('.pb-cd-cargo'), e:g('.pb-cd-email'), t:g('.pb-cd-tel'), d:g('.pb-cd-nasc') };
      if (o.n || o.c || o.e || o.t || o.d) arr.push(o);
    });
    ta.value = arr.length ? JSON.stringify(arr) : '';
  };
  window.pbContatosDiaParse = function(){
    const list = $('pb-contatos-dia-list'), ta = $('pb-contatos_diaadia'); if (!list || !ta) return;
    list.innerHTML = '';
    let arr = []; try { arr = JSON.parse((ta.value || '').trim() || '[]'); } catch(e){ arr = []; }
    arr.forEach(o => window.pbContatoDiaAdd(o));
  };

  // ── Executivos extras na operadora (vários contatos) ──
  window.pbExecAdd = function(data){
    const list = $('pb-execs-list'); if (!list) return;
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'pb-exec-row';
    div.style.cssText = 'display:grid;grid-template-columns:1.4fr 1.6fr 1fr 36px;gap:8px;align-items:center';
    div.innerHTML =
      '<input type="text" class="pb-ex-nome" placeholder="Nome do responsável" value="' + pbSubfatEsc(d.n) + '" oninput="pbExecSerialize()">' +
      '<input type="text" class="pb-ex-email" placeholder="E-mail" value="' + pbSubfatEsc(d.e) + '" oninput="pbExecSerialize()">' +
      '<input type="text" class="pb-ex-tel" placeholder="Telefone" value="' + pbSubfatEsc(d.t) + '" oninput="pbExecSerialize()">' +
      '<button type="button" class="btn btn-outline btn-sm" title="Remover contato" style="padding:4px 10px" onclick="this.closest(\'.pb-exec-row\').remove(); pbExecSerialize();">✕</button>';
    list.appendChild(div);
    const inp = div.querySelector('.pb-ex-nome'); if (inp && data === undefined) inp.focus();
    pbExecSerialize();
  };
  window.pbExecSerialize = function(){
    const list = $('pb-execs-list'), ta = $('pb-executivos_extras'); if (!list || !ta) return;
    const arr = [];
    list.querySelectorAll('.pb-exec-row').forEach(r => {
      const g = sel => ((r.querySelector(sel)||{}).value || '').trim();
      const o = { n:g('.pb-ex-nome'), e:g('.pb-ex-email'), t:g('.pb-ex-tel') };
      if (o.n || o.e || o.t) arr.push(o);
    });
    ta.value = arr.length ? JSON.stringify(arr) : '';
  };
  window.pbExecParse = function(){
    const list = $('pb-execs-list'), ta = $('pb-executivos_extras'); if (!list || !ta) return;
    list.innerHTML = '';
    let arr = []; try { arr = JSON.parse((ta.value || '').trim() || '[]'); } catch(e){ arr = []; }
    arr.forEach(o => window.pbExecAdd(o));
  };

  // ── Parcelas de Agenciamento (%) e Valor das Faturas (R$) — soma + guarda ──
  window.pbParcelasTotal = function(){
    const dec = v => { v = (v==null?'':v).toString().trim().replace(/\./g,'').replace(',','.'); const n = parseFloat(v); return isNaN(n) ? null : n; };
    let ta = 0; const af = [];
    for (let k=1;k<=5;k++){ const el=$('pb-agenc-'+k); const raw = el ? el.value : ''; af.push(raw); const v = dec(raw); if (v!==null) ta += v; }
    const ea = $('pb-agenc-total'); if (ea) ea.textContent = ta.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}) + '%';
    let nf = 0; const ff = [];
    for (let k=1;k<=5;k++){ const el=$('pb-fatura-'+k); const raw = el ? el.value : ''; ff.push(raw); if ((raw||'').trim()) nf++; }
    const ef = $('pb-fatura-total'); if (ef) ef.textContent = String(nf);
    const ha = $('pb-agenc_parcelas'); if (ha) ha.value = af.some(x => (x||'').trim()) ? af.join(';') : '';
    const hf = $('pb-fatura_parcelas'); if (hf) hf.value = ff.some(x => (x||'').trim()) ? ff.join(';') : '';
  };
  // Exclui uma parcela de agenciamento: limpa SOMENTE o % daquela posição
  // (a competência de baixo não é mexida).
  // ── Mostrar/ocultar parcelas extras (2ª a 5ª) via botão "+" ──
  function pbAtualizaAddBtn(wrapPfx, btnId){
    let temOculto = false;
    for (let k=2;k<=5;k++){ const w=$(wrapPfx+k); if (w && w.style.display==='none') temOculto = true; }
    const b=$(btnId); if (b) b.style.display = temOculto ? '' : 'none';
  }
  function pbMostraProximo(wrapPfx, btnId){
    for (let k=2;k<=5;k++){ const w=$(wrapPfx+k); if (w && w.style.display==='none'){ w.style.display='flex'; const inp=w.querySelector('input'); if(inp) inp.focus(); break; } }
    pbAtualizaAddBtn(wrapPfx, btnId);
  }
  window.pbAddAgenc       = function(){ pbMostraProximo('pb-agwrap-','pb-agenc-add'); };
  window.pbAddCompetencia = function(){ pbMostraProximo('pb-ftwrap-','pb-fatura-add'); };
  // Reexibe (no load) as parcelas que já têm valor; recolhe as vazias (exceto a 1ª).
  window.pbParcelasReexibir = function(){
    for (let k=2;k<=5;k++){
      const a=$('pb-agenc-'+k),  wa=$('pb-agwrap-'+k); if (wa) wa.style.display = (a && (a.value||'').trim()) ? 'flex' : 'none';
      const f=$('pb-fatura-'+k), wf=$('pb-ftwrap-'+k); if (wf) wf.style.display = (f && (f.value||'').trim()) ? 'flex' : 'none';
    }
    pbAtualizaAddBtn('pb-agwrap-','pb-agenc-add');
    pbAtualizaAddBtn('pb-ftwrap-','pb-fatura-add');
  };

  // Limpa SOMENTE o % daquela posição (e recolhe o campo extra, se for 2ª a 5ª).
  window.pbLimparParcela = function(k){
    const a = $('pb-agenc-'+k); if (a) a.value = '';
    if (k>1){ const w=$('pb-agwrap-'+k); if (w) w.style.display='none'; pbAtualizaAddBtn('pb-agwrap-','pb-agenc-add'); }
    if (typeof window.pbParcelasTotal === 'function') window.pbParcelasTotal();
  };
  // Limpa SOMENTE a competência de AG daquela posição (e recolhe o campo extra).
  window.pbLimparCompetencia = function(k){
    const f = $('pb-fatura-'+k); if (f) f.value = '';
    if (k>1){ const w=$('pb-ftwrap-'+k); if (w) w.style.display='none'; pbAtualizaAddBtn('pb-ftwrap-','pb-fatura-add'); }
    if (typeof window.pbParcelasTotal === 'function') window.pbParcelasTotal();
  };
  window.pbParcelasParse = function(){
    const splitTo = (val, prefix) => { const parts = (val||'').split(';'); for (let k=1;k<=5;k++){ const el=$(prefix+k); if (el) el.value = (parts[k-1]!=null ? parts[k-1] : ''); } };
    splitTo((($('pb-agenc_parcelas')||{}).value), 'pb-agenc-');
    splitTo((($('pb-fatura_parcelas')||{}).value), 'pb-fatura-');
    if (typeof window.pbParcelasReexibir === 'function') window.pbParcelasReexibir();
    window.pbParcelasTotal();
  };

  function pbSubfaturasParse(){
    const list = $('pb-subfaturas-list'), ta = $('pb-subfaturas_desc');
    if (!list || !ta) return;
    list.innerHTML = '';
    const txt = (ta.value || '').trim();
    if (!txt) return;
    txt.split(/\r?\n/).forEach(linha => {
      if (!linha.trim()) return;
      let razao = linha, cnpj = '', contrato = '';
      if (linha.indexOf('\t') >= 0){ const p = linha.split('\t'); razao = (p[0]||'').trim(); cnpj = (p[1]||'').trim(); contrato = (p[2]||'').trim(); }
      else { const m = linha.match(/^(.*?)\s+[-–—]\s+([\d.\/\-\s]+)$/); if (m){ razao = m[1].trim(); cnpj = m[2].trim(); } }
      window.pbSubfaturaAdd(razao, cnpj, contrato);
    });
  }

  // ── Planos dinâmicos (bloco completo: nome, vidas, modalidade, permanência, vigência) ──
  function pbDateOnly(s){ return /^\d{4}-\d{2}-\d{2}/.test(String(s||'')) ? String(s).slice(0,10) : (s||''); }
  function pbPlanosRenumber(){
    const list = $('pb-planos-list'); if (!list) return;
    [...list.querySelectorAll('.pb-plano-row')].forEach((r,i) => {
      const t = r.querySelector('.pb-plano-titulo'); if (t) t.textContent = 'Plano ' + (i+1);
    });
  }
  window.pbPlanoAdd = function(data){
    const list = $('pb-planos-list'); if (!list) return;
    const isNew = (arguments.length === 0);
    const d = data || {};
    const div = document.createElement('div');
    div.className = 'pb-plano-row';
    div.style.cssText = 'border:1px solid var(--border,#30363d);border-radius:8px;padding:12px';
    div.innerHTML =
      '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">' +
        '<strong class="pb-plano-titulo" style="font-size:12px;color:var(--muted,#8b949e)">Plano</strong>' +
        '<button type="button" class="btn btn-outline btn-sm" data-edit-only title="Remover plano" style="padding:2px 9px" onclick="var l=this.closest(\'.pb-plano-row\'); l.parentNode.removeChild(l); pbPlanosSerialize();">✕</button>' +
      '</div>' +
      '<div class="row2">' +
        '<div class="fg"><label>Nome do plano</label><input type="text" class="pb-pl-nome" value="' + pbSubfatEsc(d.n) + '" oninput="pbPlanosSerialize()"></div>' +
        '<div class="fg"><label>Categoria funcional</label><input type="text" class="pb-pl-categoria" value="' + pbSubfatEsc(d.cf) + '" oninput="pbPlanosSerialize()"></div>' +
      '</div>' +
      '<div class="row2" style="margin-top:8px">' +
        '<div class="fg"><label>Acomodação</label><input type="text" class="pb-pl-acomodacao" value="' + pbSubfatEsc(d.ac) + '" oninput="pbPlanosSerialize()"></div>' +
        '<div class="fg"><label>Número de vidas</label><input type="number" min="0" class="pb-pl-vidas" value="' + pbSubfatEsc(d.v) + '" oninput="pbPlanosSerialize()"></div>' +
      '</div>' +
      '<div class="row2" style="margin-top:8px">' +
        '<div class="fg"><label>Modalidade de contratação</label><input type="text" class="pb-pl-modalidade" value="' + pbSubfatEsc(d.m) + '" oninput="pbPlanosSerialize()"></div>' +
        '<div class="fg"><label>Permanência contratual</label><input type="text" class="pb-pl-permanencia" value="' + pbSubfatEsc(d.p) + '" oninput="pbPlanosSerialize()"></div>' +
      '</div>';
    list.appendChild(div);
    pbPlanosRenumber();
    if (isNew){ const inp = div.querySelector('.pb-pl-nome'); if (inp) inp.focus(); }
    pbPlanosSerialize();
  };
  window.pbPlanosSerialize = function(){
    const list = $('pb-planos-list'); if (!list) return;
    const arr = [];
    list.querySelectorAll('.pb-plano-row').forEach(r => {
      const g = sel => ((r.querySelector(sel)||{}).value || '').trim();
      const o = { n:g('.pb-pl-nome'), v:g('.pb-pl-vidas'), cf:g('.pb-pl-categoria'), ac:g('.pb-pl-acomodacao'), m:g('.pb-pl-modalidade'), p:g('.pb-pl-permanencia') };
      if (o.n || o.v || o.cf || o.ac || o.m || o.p) arr.push(o);
    });
    const pEl = $('pb-planos'); if (pEl) pEl.value = arr.length ? JSON.stringify(arr) : '';
    // Espelha o 1º plano nas colunas individuais (compatibilidade com o resto do sistema)
    const f = arr[0] || {};
    const set = (id, val) => { const el = $(id); if (el) el.value = (val != null ? val : ''); };
    set('pb-numero_vidas', f.v);
    set('pb-modalidade_contratacao', f.m);
    set('pb-permanencia_contratual', f.p);
    // NÃO sobrescrever pb-inicio_vigencia aqui: a vigência é do CONTRATO, não do
    // plano (o plano não tem campo de início), então isso apagava o valor digitado.
  };
  function pbPlanosParse(){
    const list = $('pb-planos-list'); if (!list) return;
    list.innerHTML = '';
    const raw = (($('pb-planos')||{}).value || '').trim();
    let arr = [];
    if (raw){
      if (raw.charAt(0) === '['){ try { arr = JSON.parse(raw) || []; } catch(_){ arr = []; } }
      if (!arr.length){
        // Legado: nomes em texto (linhas ou ";")
        const partes = raw.indexOf('\n') >= 0 ? raw.split(/\r?\n/) : raw.split(/\s*;\s*/);
        arr = partes.filter(p => p.trim()).map(p => ({ n:p.trim() }));
      }
    }
    const lv = ($('pb-numero_vidas')||{}).value || '';
    const lm = ($('pb-modalidade_contratacao')||{}).value || '';
    const lp = ($('pb-permanencia_contratual')||{}).value || '';
    const li = ($('pb-inicio_vigencia')||{}).value || '';
    // Legado sem nome de plano, mas com dados nas colunas individuais → cria 1 bloco
    if (!arr.length && (lv || lm || lp || li)) arr = [{ n:'', v:lv, m:lm, p:lp, i:li }];
    // Legado com nome(s) mas sem vidas/modalidade → completa o 1º plano
    else if (arr.length){
      arr[0].v = arr[0].v || lv;
      arr[0].m = arr[0].m || lm;
      arr[0].p = arr[0].p || lp;
      arr[0].i = arr[0].i || li;
    }
    arr.forEach(o => window.pbPlanoAdd(o));
  }

  window.pbNova = function(){
    KEYS.forEach(k => { const el=$('pb-'+k); if(el) el.value=''; });
    pbSubfaturasParse();
    pbPlanosParse(); if(typeof window.pbContatosRenovParse==='function') window.pbContatosRenovParse(); if(typeof window.pbContatosDiaParse==='function') window.pbContatosDiaParse(); if(typeof window.pbExecParse==='function') window.pbExecParse(); if(typeof window.pbParcelasParse==='function') window.pbParcelasParse();
    if (!$('pb-planos-list') || !$('pb-planos-list').querySelector('.pb-plano-row')) window.pbPlanoAdd({});
    const an=$('pb-analista'); if(an) an.value='';
    const cbN=$('pb-notificar_email_passagem'); if(cbN) cbN.checked=true;
    const destN=$('pb-destinatarios_email_passagem'); if(destN) destN.value=PB_EMAILS_NOTIF_PADRAO;
    if (typeof window.pbToggleNotifEmail === 'function') window.pbToggleNotifEmail();
    pbFormReadOnly(false);
    pbShowTab('nova');
    window.scrollTo(0,0);
  };

  window.pbCarregarLista = async function(){
    const tb = $('pb-lista-tbody'); if (!tb) return;
    tb.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:var(--muted)">Carregando...</td></tr>';
    let rows = [];
    try { const r = await (await fetch('api_passagem.php?action=listar_passagem_bastao')).json(); if (r.ok) rows = r.data || []; } catch(_){}
    const cnt = $('pb-count'); if (cnt) cnt.textContent = 'Passagens de Bastão (' + rows.length + ')';
    if (!rows.length){ tb.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:var(--muted)">Nenhuma passagem de bastão cadastrada ainda.</td></tr>'; return; }
    tb.innerHTML = rows.map(r => `<tr>
      <td style="color:var(--muted);font-size:11.5px">#${r.id}</td>
      <td><strong>${esc(r.nome)}</strong>${(r.grupo && r.grupo !== r.nome) ? `<br><span style="color:var(--muted);font-size:11px">${esc(r.grupo)}</span>` : ''}</td>
      <td style="font-size:11.5px">${esc(r.cnpj||'—')}</td>
      <td style="font-size:11.5px">${esc(r.seguradora||'—')}</td>
      <td style="font-size:11.5px">${esc(r.numero_contrato||'—')}</td>
      <td style="font-size:11.5px">${esc(r.ramo||'—')}</td>
      <td style="text-align:center">${r.numero_vidas != null ? r.numero_vidas : '—'}</td>
      <td style="font-size:11.5px;white-space:nowrap">${pbFmtData(r.inicio_vigencia)}</td>
      <td style="font-size:11.5px">${esc(r.comercial_interweg||'—')}</td>
      <td style="font-size:11.5px">${esc(r.analista_nome||'—')}</td>
      <td style="font-size:11.5px;white-space:nowrap">${pbFmtDataHora(r.criado_em)}</td>
      <td style="white-space:nowrap;display:flex;gap:4px">
        <button class="btn btn-outline btn-sm" onclick="pbVisualizar(${r.id})" title="Visualizar (somente leitura)"><span class="material-symbols-rounded" style="font-size:14px">visibility</span></button>
        <button class="btn btn-outline btn-sm" onclick="pbEditar(${r.id})" title="Editar cadastro"><span class="material-symbols-rounded" style="font-size:14px">edit</span></button>
        <button class="btn btn-outline btn-sm" onclick="pbHistorico(${r.id})" title="Ver histórico de edições"><span class="material-symbols-rounded" style="font-size:14px">history</span> Histórico</button>
      </td>
    </tr>`).join('');
  };

  // ── Histórico de edições da passagem de bastão ──
  // Mostra todas as versões (snapshots) registradas na tabela
  // passagem_bastao_historico em ordem cronológica reversa.
  window.pbHistorico = async function(id){
    if (!id) return;
    const cont = $('pb-hist-content');
    const titulo = $('pb-hist-empresa');
    if (titulo) titulo.textContent = 'Carregando...';
    if (cont) cont.innerHTML = '';
    openModal('mod-pb-hist');
    let rec = null;
    try {
      const r = await (await fetch('api_passagem.php?action=get_passagem&id=' + id)).json();
      if (r.ok) rec = r.data;
    } catch(_){}
    if (titulo) titulo.textContent = rec ? (rec.nome + (rec.cnpj ? ' — ' + rec.cnpj : '')) : ('Passagem #' + id);
    try {
      const r = await (await fetch('api_passagem.php?action=historico_passagem_bastao&id=' + id)).json();
      if (!r.ok) { if (cont) cont.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger)">' + esc(r.msg || 'Erro ao carregar histórico') + '</div>'; return; }
      const lista = r.data || [];
      if (!lista.length) { if (cont) cont.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)">Nenhuma edição registrada ainda.<br><small>O histórico começou a ser registrado a partir da última atualização do sistema.</small></div>'; return; }
      const acaoLabel = {criar:'Criação', editar:'Edição', excluir:'Exclusão'};
      const acaoCor   = {criar:'#28a745', editar:'#1f4f99', excluir:'#dc3545'};
      // Campos que NÃO mostramos no diff (ruído técnico)
      const IGNORE_FIELDS = new Set(['id','ativo','criado_em','origem_cadastro']);

      // Função pra normalizar valor pra comparação (null/empty/'' viram '')
      const norm = (v) => (v == null ? '' : String(v).trim());

      // Formata label do campo (snake_case → Título Bonito)
      const fmtLabel = (k) => {
        const map = {
          'tipo_estipulacao': 'Estipulação',
          'tipo_passagem': 'Tipo',
          'numero_contrato': 'Nº Contrato',
          'numero_vidas': 'Nº Vidas',
          'valor_fatura': 'Valor do 1º Faturamento',
          'comercial_interweg': 'Comercial Interweg',
          'modalidade_contratacao': 'Modalidade',
          'permanencia_contratual': 'Permanência',
          'data_emissao': 'Data Emissão',
          'data_vencimento': 'Data Vencimento',
          'inicio_vigencia': 'Início Vigência',
          'mes_renovacao': 'Mês Renovação',
          'percentual_comissao': '% Comissão',
          'comissionamento': '% Agenciamento',
          'data_venc_boleto': 'Venc. 1º Faturamento',
          'data_corte_inclusao': 'Corte Inclusão',
          'data_corte_exclusao': 'Corte Exclusão',
          'necessidade_rateio': 'Necessidade Rateio',
          'contato_dia_a_dia': 'Contato Dia-a-dia',
          'acessos_portais': 'Acessos Portais',
          'politica_beneficios': 'Política Benefícios',
          'possui_subfaturas': 'Possui Subfaturas',
          'subfaturas_desc': 'Subfaturas',
          'possui_copart': 'Possui Coparticipação',
          'copart_desc': 'Coparticipação',
          'possui_odonto_conjugado': 'Possui Odonto Conjugado',
          'odonto_conjugado_desc': 'Odonto Conjugado',
          'possui_agregados': 'Possui Agregados',
          'agregados_desc': 'Agregados (grau)',
          'idade_limite_filho': 'Idade Limite Filho',
          'exclui_filho_auto': 'Exclui Filho Auto',
          'prazo_inclusao_sem_carencia': 'Prazo Inclusão sem Carência',
          'necessita_doc_mov': 'Documentação Movimentação',
          'doc_mov_desc': 'Documentos Necessários',
          'copia_contrato_aditivos': 'Cópia Contrato/Aditivos',
          'possui_relatorio_sinistralidade': 'Relatório Sinistralidade',
          'caminho_pasta_rede': 'Pasta Rede',
          'executivo_operadora': 'Exec. Operadora (Nome)',
          'executivo_operadora_email': 'Exec. Operadora (E-mail)',
          'executivo_operadora_telefone': 'Exec. Operadora (Tel.)',
          'analista_nome': 'Analista',
          'analista_id': 'Analista ID',
          'tipo_estipulacao': 'Estipulação',
          'emails_faturamento': 'E-mails Faturamento',
          'resp_nome': 'Responsável (Nome)',
          'resp_email': 'Responsável (E-mail)',
          'resp_telefone': 'Responsável (Tel.)',
          'observacao': 'Observação',
          'razao_social': 'Razão Social',
          'particularidades': 'Particularidades',
          'corretora': 'Corretora',
          'cnpj': 'CNPJ',
        };
        return map[k] || k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
      };

      const trunc = (v) => { v = String(v); return v.length > 120 ? v.substring(0,120) + '…' : v; };

      cont.innerHTML = lista.map((h, i) => {
        const dt = pbFmtDataHora(h.criado_em);
        const usr = h.usuario_nome || ('usuário #' + (h.usuario_id || '?'));
        const cor = acaoCor[h.acao] || '#666';
        const lbl = acaoLabel[h.acao] || h.acao;
        const snap = h.snapshot_obj || {};
        // O snapshot ANTERIOR (mais antigo) está em lista[i+1] (lista vem em DESC)
        const snapAnterior = (i + 1 < lista.length) ? (lista[i+1].snapshot_obj || {}) : null;

        let dadosHtml = '';
        if (h.acao === 'criar' || !snapAnterior) {
          // CRIAÇÃO ou primeira entrada — mostra TODOS os campos não-vazios
          const keys = Object.keys(snap).filter(k => !IGNORE_FIELDS.has(k) && norm(snap[k]) !== '');
          if (!keys.length) {
            dadosHtml = '<div style="color:var(--muted);font-size:11px;font-style:italic">Sem dados de snapshot</div>';
          } else {
            dadosHtml = '<div style="font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600;text-transform:uppercase">Campos preenchidos na criação:</div>';
            dadosHtml += keys.map(k => `<div style="font-size:11.5px;margin-bottom:2px"><strong style="color:var(--muted)">${esc(fmtLabel(k))}:</strong> ${esc(trunc(snap[k]))}</div>`).join('');
          }
        } else {
          // EDIÇÃO — calcula DIFF com o snapshot anterior
          const allKeys = new Set([...Object.keys(snap), ...Object.keys(snapAnterior)]);
          const mudancas = [];
          allKeys.forEach(k => {
            if (IGNORE_FIELDS.has(k)) return;
            const novo = norm(snap[k]);
            const velho = norm(snapAnterior[k]);
            if (novo !== velho) {
              mudancas.push({ campo: k, de: velho, para: novo });
            }
          });
          if (!mudancas.length) {
            dadosHtml = '<div style="color:var(--muted);font-size:11.5px;font-style:italic">Nenhum campo alterado nessa edição (salvamento sem mudanças).</div>';
          } else {
            dadosHtml = `<div style="font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:600;text-transform:uppercase">${mudancas.length} campo(s) alterado(s):</div>`;
            dadosHtml += mudancas.map(m => `
              <div style="font-size:11.5px;margin-bottom:6px;padding:6px 8px;background:rgba(255,193,7,.08);border-radius:4px;border-left:3px solid #ffc107">
                <strong>${esc(fmtLabel(m.campo))}:</strong><br>
                <span style="color:#dc3545;text-decoration:line-through">${esc(trunc(m.de) || '(vazio)')}</span>
                &nbsp;→&nbsp;
                <span style="color:#28a745;font-weight:600">${esc(trunc(m.para) || '(vazio)')}</span>
              </div>`).join('');
          }
        }

        return `
          <div style="border:1px solid var(--border);border-left:4px solid ${cor};border-radius:7px;padding:12px 14px;margin-bottom:10px;background:var(--bg-card)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:6px">
              <span><strong style="color:${cor};font-size:13px">${esc(lbl)}</strong> por <strong>${esc(usr)}</strong></span>
              <span style="color:var(--muted);font-size:11.5px">${esc(dt)}</span>
            </div>
            ${dadosHtml}
          </div>`;
      }).join('');
    } catch(e){
      if (cont) cont.innerHTML = '<div style="text-align:center;padding:20px;color:var(--danger)">Erro de conexão.</div>';
    }
  };

  window.pbEditar = async function(id){
    let rec = null;
    try { const r = await (await fetch('api_passagem.php?action=get_passagem&id=' + id)).json(); if (r.ok) rec = r.data; } catch(_){}
    if (!rec){ alert('Não foi possível carregar o registro.'); return; }
    $('pe-id').value = rec.id;
    STD.forEach(k => { const el = $('pe-' + k); if (el) el.value = (rec[k] != null ? String(rec[k]) : ''); });
    ['inicio_vigencia','data_emissao','data_vencimento'].forEach(k => { const el = $('pe-' + k); if (el && rec[k]) el.value = String(rec[k]).slice(0,10); });
    const an = $('pe-analista'); if (an) an.value = rec.analista_nome || '';
    const tt = $('pe-title'); if (tt) tt.innerHTML = '<span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar: ' + esc(rec.nome || ('#' + rec.id));
    openModal('mod-pb-edit');
  };

  // ---- Importar a planilha "Modelo - Passagem de Bastão" (.xlsx) ----
  function pbNorm(s){ return String(s==null?'':s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9 ]/g,' ').replace(/\s+/g,' ').trim(); }
  const PB_IMPORT_MAP = [
    ['razao social','nome'],['cnpj','cnpj'],['entrada','origem'],['comercial interweg','comercial_interweg'],
    ['corretora','corretora'],['data de vigencia inicial','inicio_vigencia'],['possui subfaturas','possui_subfaturas'],
    ['se sim para subfatura','subfaturas_desc'],['nome do s plano','planos'],
    ['operadora','seguradora'],['permanencia contratual','permanencia_contratual'],['numero do contrato','numero_contrato'],
    ['numero de vidas','numero_vidas'],['modalidade de contratacao','modalidade_contratacao'],['possui coparticipacao','possui_copart'],
    ['se sim para coparticipacao','copart_desc'],['tipo de custo','tipo_custo'],['valor de fatura','valor_fatura'],
    ['comissionamento','comissionamento'],['data vencimento boleto','data_venc_boleto'],['data corte pra inclusao','data_corte_inclusao'],
    ['data corte para exclusao','data_corte_exclusao'],['necessidade de rateio','necessidade_rateio'],
    ['contato da empresa para o dia a dia','contato_dia_a_dia'],['acessos portais','acessos_portais'],
    ['politica de beneficios','politica_beneficios'],['possui plano odontologico conjugado','possui_odonto_conjugado'],
    ['se sim para plano odontologico conjugado','odonto_conjugado_desc'],['possui agregados','possui_agregados'],
    ['se sim para agregados','agregados_desc'],['idade limite para inclusao de dependente filho','idade_limite_filho'],
    ['os dependentes filhos sao excluidos','exclui_filho_auto'],['prazo para realizacao de inclusoes sem carencia','prazo_inclusao_sem_carencia'],
    ['ha necessidade de envio de documentacao','necessita_doc_mov'],['se sim para necessidade de envio de documentacao','doc_mov_desc'],
    ['copia do contrato e aditivos','copia_contrato_aditivos'],['possui relatorio de sinistralidade','possui_relatorio_sinistralidade'],
    ['particularidades adicionais','particularidades'],['executivo responsavel pelo atendimento na operadora','executivo_operadora'],
    ['caminho da pasta do cliente na pasta de rede','caminho_pasta_rede']
  ];
  const PB_SIMNAO = ['possui_subfaturas','possui_copart','possui_odonto_conjugado','possui_agregados','exclui_filho_auto','necessita_doc_mov','possui_relatorio_sinistralidade'];
  const PB_SELECTS = ['origem','seguradora','ramo','porte','tipo_estipulacao'];

  function pbLoadXLSX(){
    return new Promise((res,rej)=>{
      if (window.XLSX) return res();
      const s=document.createElement('script');
      s.src='https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
      s.onload=res; s.onerror=()=>rej(new Error('Falha ao carregar o leitor de Excel'));
      document.head.appendChild(s);
    });
  }
  function pbFmtDateVal(v){
    if (v instanceof Date && !isNaN(v)){ const p=n=>String(n).padStart(2,'0'); return v.getFullYear()+'-'+p(v.getMonth()+1)+'-'+p(v.getDate()); }
    const m=String(v).match(/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/); if(m){ let y=m[3]; if(y.length===2) y='20'+y; return y+'-'+m[2].padStart(2,'0')+'-'+m[1].padStart(2,'0'); }
    const m2=String(v).match(/^(\d{4})-(\d{2})-(\d{2})/); if(m2) return m2[0];
    return '';
  }
  function pbSetCampo(fieldKey, value){
    const el=$('pb-'+fieldKey); if(!el) return;
    if (fieldKey==='inicio_vigencia'){ el.value = pbFmtDateVal(value); return; }
    if (PB_SIMNAO.includes(fieldKey)){ const n=pbNorm(value); el.value = n.startsWith('sim')?'Sim':(n.startsWith('nao')?'Não':''); return; }
    let val=String(value==null?'':value).trim();
    if (PB_SELECTS.includes(fieldKey)){
      const opts=[...el.options];
      const found=opts.find(o=>pbNorm(o.value||o.textContent)===pbNorm(val));
      if(found){ el.value=found.value||found.textContent; }
      else if(val){ const o=document.createElement('option'); o.textContent=val; el.appendChild(o); el.value=val; }
      return;
    }
    el.value=val;
  }
  window.pbImportarPlanilha = async function(input){
    const file = input && input.files && input.files[0];
    if(!file) return;
    try{
      await pbLoadXLSX();
      const buf = await file.arrayBuffer();
      const wb = XLSX.read(buf, {cellDates:true});
      const ws = wb.Sheets[wb.SheetNames[0]];
      const rows = XLSX.utils.sheet_to_json(ws, {header:1, raw:true, defval:''});
      let n=0;
      rows.forEach(r=>{
        const label = pbNorm(r[0]); const value = r[1];
        if(!label || value==='' || value==null) return;
        const hit = PB_IMPORT_MAP.find(function(m){ return label.startsWith(m[0]); });
        if(hit){ pbSetCampo(hit[1], value); n++; }
      });
      pbShowTab('nova');
      window.scrollTo(0,0);
      toastMsg(n + ' campo(s) preenchido(s) da planilha. Confira e clique em Salvar.');
    }catch(e){ alert('Não consegui ler a planilha: ' + (e.message || e)); }
    finally{ input.value=''; }
  };

  // Reconhece/valida um CNPJ (14 dígitos + dígitos verificadores).
  function pbCnpjValido(v){
    v = String(v||'').replace(/\D/g,'');
    if (v.length !== 14) return false;
    if (/^(\d)\1{13}$/.test(v)) return false;
    const calc = base => {
      let len = base.length, pos = len - 7, sum = 0;
      for (let i = len; i >= 1; i--){ sum += parseInt(base[len-i],10) * pos--; if (pos < 2) pos = 9; }
      const r = sum % 11; return r < 2 ? 0 : 11 - r;
    };
    const d1 = calc(v.slice(0,12));
    const d2 = calc(v.slice(0,12) + d1);
    return v.slice(12) === ('' + d1 + d2);
  }

  window.pbSalvar = async function(btn, etapa){
    const body = {};
    pbSubfaturasSerialize();
    pbPlanosSerialize();
    if (typeof window.pbContatosRenovSerialize === 'function') window.pbContatosRenovSerialize();
    if (typeof window.pbContatosDiaSerialize === 'function') window.pbContatosDiaSerialize();
    if (typeof window.pbExecSerialize === 'function') window.pbExecSerialize();
    if (typeof window.pbParcelasTotal === 'function') window.pbParcelasTotal();
    KEYS.forEach(k => { const el = $('pb-' + k); if (el) body[k] = (el.value || '').trim(); });
    // Garantia: serializa as parcelas de AG (% e competências) lendo direto dos
    // campos — o seletor de mês (type=month) nem sempre dispara "oninput", então
    // não dá pra confiar só no campo escondido.
    (function(){
      const serial = pfx => { const a=[]; for(let k=1;k<=5;k++){ const el=$(pfx+k); a.push(el?(el.value||''):''); } return a.some(x=>(x||'').trim()) ? a.join(';') : ''; };
      body.agenc_parcelas  = serial('pb-agenc-');
      body.fatura_parcelas = serial('pb-fatura-');
    })();
    const an = $('pb-analista'); if (an) body.analista_nome = (an.value || '').trim();
    // Etapa: 'rascunho' (Salvar, não dispara nada) ou 'cliente' (Concluir, oficializa)
    const etapaCad = (etapa === 'rascunho') ? 'rascunho' : 'cliente';
    body.etapa_cadastro = etapaCad;
    // Checkbox de notificação por e-mail
    const cbN = $('pb-notificar_email_passagem');
    body.notificar_email_passagem = (cbN && cbN.checked) ? 1 : 0;
    if (PB_COTACAO_ID) body.cotacao_id = PB_COTACAO_ID;
    // Modo edição: se pb-id estiver preenchido, manda o id pra a API fazer UPDATE
    const editId = ($('pb-id')||{}).value;
    if (editId) body.id = parseInt(editId, 10);
    if (!body.nome){ toastMsg('Preencha a Razão Social / Empresa.'); const n=$('pb-nome'); if(n) n.focus(); return; }
    // Exige os campos obrigatórios tanto no "Salvar rascunho" quanto no "Concluir".
    {
      const obrig = [
        ['origem','Entrada/Origem'],
        ['seguradora','Operadora'], ['ramo','Ramo'], ['numero_contrato','Número do contrato'],
        ['cnpj','CNPJ'], ['tipo_estipulacao','Estipulante / Subestipulante'],
        ['tipo_passagem','Tipo'], ['inicio_vigencia','Início de vigência'],
        ['grupo','Grupo'], ['porte','Porte da empresa'],
        ['comercial_interweg','Comercial Interweg'], ['corretora','Corretora'],
        ['possui_subfaturas','Possui subfaturas?'], ['possui_copart','Possui coparticipação?'],
        ['valor_fatura','Valor do 1º faturamento'],
        ['percentual_comissao','% de comissão'], ['data_venc_boleto','Data vencimento 1º faturamento'],
        ['data_venc_demais','Data vencimento demais faturamentos'],
        ['caminho_pasta_rede','Caminho da pasta do cliente (rede)'],
        ['resp_nome','Contatos (dia a dia): Nome do responsável'],
        ['resp_email','Contatos (dia a dia): E-mail'],
        ['resp_telefone','Contatos (dia a dia): Telefone'],
        ['acessos_portais','Acessos a portais'],
        ['executivo_operadora','Executivo na operadora: Nome do responsável']
      ];
      const faltando = obrig.filter(f => !((body[f[0]]||'').trim())).map(f => f[1]);
      if ((body.cnpj||'').trim() && !pbCnpjValido(body.cnpj)) faltando.push('CNPJ inválido (confira os 14 dígitos)');
      // Plano e contratação: pelo menos 1 plano, com todos os campos
      let planos = [];
      try { planos = JSON.parse((($('pb-planos')||{}).value || '[]')); } catch(e){ planos = []; }
      if (!planos.length) faltando.push('Plano e contratação (adicione ao menos um plano)');
      else {
        const camposPl = { n:'Nome do plano', cf:'Categoria funcional', ac:'Acomodação', v:'Número de vidas', m:'Modalidade de contratação', p:'Permanência contratual' };
        planos.forEach((pl,i) => { Object.keys(camposPl).forEach(k => { if (!((pl[k]!=null?String(pl[k]):'').trim())) faltando.push('Plano '+(i+1)+': '+camposPl[k]); }); });
      }
      // Condicionais (só exige a descrição quando a resposta é "Sim")
      if ((body.possui_subfaturas||'') === 'Sim' && !((body.subfaturas_desc||'').trim())) faltando.push('Subfaturas: descreva razão social e CNPJ');
      if ((body.possui_copart||'') === 'Sim' && !((body.copart_desc||'').trim())) faltando.push('Coparticipação: descreva');
      if (faltando.length){
        alert('Para salvar a passagem, preencha corretamente:\n\n• ' + faltando.join('\n• '));
        const primeiro = obrig.find(f => !((body[f[0]]||'').trim()));
        const alvo = primeiro ? $('pb-'+primeiro[0]) : $('pb-cnpj');
        if (alvo && alvo.focus) alvo.focus();
        return;
      }
    }
    if (btn) btn.disabled = true;
    try {
      const r = await fetch('api_passagem.php?action=criar_passagem_bastao', {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
      }).then(x => x.json());
      if (!r.ok){ alert(r.msg || 'Falha ao salvar.'); return; }
      PB_COTACAO_ID = '';
      const ehEdicao = !!editId;
      const novoId = r.id || editId;
      if (etapaCad === 'rascunho') {
        toastMsg('Rascunho salvo (#' + (r.id || editId || '') + '). Ainda não virou cliente — clique em "Concluir" quando terminar.');
      } else {
        toastMsg(ehEdicao
          ? 'Passagem concluída! Cliente atualizado na base (#' + (r.id || editId) + ').'
          : ('Passagem concluída! Cliente criado na base (#' + (r.id || '') + ')' + (r.cotacao_sincronizada ? ' e cotação atualizada.' : '.')));
      }
      pbCarregarLista();
      // Atualiza o sino/badge de notificações na hora (nova passagem criada)
      if (!editId && typeof window.notifRefresh === 'function') window.notifRefresh();
      // Só abre o e-mail de notificação quando CONCLUÍDO (rascunho não notifica)
      if (etapaCad === 'cliente' && cbN && cbN.checked && novoId && typeof window.pbAbrirEnvioEmail === 'function') {
        const idEl = $('pb-id'); if (idEl) idEl.value = String(novoId);
        pbEnvioAposSalvar = true;
        window.pbAbrirEnvioEmail();
      } else {
        pbLimparEdit();
        pbShowTab('lista');
      }
    } catch(e){ alert('Erro de conexão ao salvar: ' + (e.message || e)); }
    finally { if (btn) btn.disabled = false; }
  };

  // ── Limpa o form completo e sai do modo edição ──
  window.pbLimparEdit = function(){
    KEYS.forEach(k => { const el = $('pb-' + k); if (el) el.value = ''; });
    pbSubfaturasParse();
    pbPlanosParse(); if(typeof window.pbContatosRenovParse==='function') window.pbContatosRenovParse(); if(typeof window.pbContatosDiaParse==='function') window.pbContatosDiaParse(); if(typeof window.pbExecParse==='function') window.pbExecParse(); if(typeof window.pbParcelasParse==='function') window.pbParcelasParse();
    const an = $('pb-analista'); if (an) an.value = '';
    const idEl = $('pb-id'); if (idEl) idEl.value = '';
    const banner = $('pb-edit-banner'); if (banner) banner.style.display = 'none';
    const btnS = $('pb-btn-salvar');
    if (btnS) btnS.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar passagem de bastão';
    const cbN = $('pb-notificar_email_passagem'); if (cbN) cbN.checked = true;
    const destN = $('pb-destinatarios_email_passagem'); if (destN) destN.value = PB_EMAILS_NOTIF_PADRAO;
    const btnEm = $('pb-btn-enviar-email'); if (btnEm) btnEm.style.display = 'none';
    const infoBox = $('pb-email-enviado-info'); if (infoBox) infoBox.style.display = 'none';
    if (typeof window.pbToggleNotifEmail === 'function') window.pbToggleNotifEmail();
  };

  // ── Visualizar/Editar na íntegra: abre o form completo pré-preenchido ──
  window.pbVerNaIntegra = async function(){
    // Pega o id do modal pequeno (pe-id)
    const id = parseInt(($('pe-id')||{}).value || '0', 10);
    if (!id){ alert('Selecione uma passagem primeiro.'); return; }
    let rec = null;
    try {
      const r = await (await fetch('api_passagem.php?action=get_passagem&id=' + id)).json();
      if (r.ok) rec = r.data;
    } catch(_){}
    if (!rec){ alert('Não foi possível carregar o registro completo.'); return; }
    // Preenche TODOS os campos pb-* com o que veio
    KEYS.forEach(k => {
      const el = $('pb-' + k);
      if (el) {
        let v = rec[k];
        // Datas vêm como YYYY-MM-DD HH:MM:SS — só pega os 10 primeiros chars
        if (v && /^\d{4}-\d{2}-\d{2}/.test(String(v)) && (el.type === 'date')) v = String(v).slice(0,10);
        el.value = (v != null ? String(v) : '');
      }
    });
    pbSubfaturasParse();
    pbPlanosParse();
    if(typeof window.pbContatosRenovParse==='function') window.pbContatosRenovParse();
    if(typeof window.pbContatosDiaParse==='function')   window.pbContatosDiaParse();
    if(typeof window.pbExecParse==='function')          window.pbExecParse();
    if(typeof window.pbParcelasParse==='function')      window.pbParcelasParse();
    // Destinatários padrão de notificação quando o registro não tiver e-mails salvos
    const destN = $('pb-destinatarios_email_passagem');
    if (destN && !destN.value.trim()) destN.value = PB_EMAILS_NOTIF_PADRAO;
    // Analista (pode ser select)
    const an = $('pb-analista'); if (an) an.value = rec.analista_nome || '';
    // Marca o id pra a API saber que é edição
    const idEl = $('pb-id'); if (idEl) idEl.value = String(id);
    // Notificação por e-mail: restaura o checkbox e exibe o botão "Enviar e-mail"
    const cbN = $('pb-notificar_email_passagem');
    if (cbN) cbN.checked = (rec.notificar_email_passagem == 1 || rec.notificar_email_passagem === '1');
    if (typeof window.pbToggleNotifEmail === 'function') window.pbToggleNotifEmail();
    const btnEm = $('pb-btn-enviar-email');
    if (btnEm) btnEm.style.display = (cbN && cbN.checked) ? '' : 'none';
    // Marca como já enviado, se houver registro
    const infoBox = $('pb-email-enviado-info');
    const qSpan = $('pb-email-enviado-quando');
    if (rec.email_passagem_enviado_em) {
      if (infoBox) infoBox.style.display = 'block';
      if (qSpan) qSpan.textContent = pbFmtDataHora(rec.email_passagem_enviado_em);
    } else if (infoBox) {
      infoBox.style.display = 'none';
    }
    // Mostra o banner amarelo de "modo edição"
    const banner = $('pb-edit-banner'); if (banner) banner.style.display = 'block';
    const lbl = $('pb-edit-id-label'); if (lbl) lbl.textContent = String(id);
    // Renomeia o botão salvar
    const btnS = $('pb-btn-salvar');
    if (btnS) btnS.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar alterações';
    // Fecha o modal pequeno e abre a aba "Nova passagem"
    closeModal('mod-pb-edit');
    pbFormReadOnly(false); // caminho de edição: form editável
    pbShowTab('nova');
    window.scrollTo(0,0);
  };

  // ── Modo somente leitura do form completo de passagem ──
  function pbFormReadOnly(ro){
    const pane = document.getElementById('pb-pane-nova');
    if (!pane) return;
    pane.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = ro; });
    pane.querySelectorAll('[data-edit-only]').forEach(el => { el.style.display = ro ? 'none' : ''; });
  }

  // ── Visualizar a passagem (form completo, somente leitura) ──
  window.pbVisualizar = async function(id){
    const pe = $('pe-id'); if (pe) pe.value = id;
    await window.pbVerNaIntegra();
    pbFormReadOnly(true);
    const banner = $('pb-edit-banner');
    if (banner) banner.style.display = 'none';
    const btnEm = $('pb-btn-enviar-email'); if (btnEm) btnEm.style.display = 'none';
    window.scrollTo(0,0);
  };

  // ── Exclui a passagem de bastão atual (do modal) ──
  window.pbExcluir = async function(){
    const id = parseInt(($('pe-id')||{}).value || '0', 10);
    if (!id){ alert('Selecione uma passagem primeiro.'); return; }
    const nome = ($('pe-nome')||{}).value || ('#' + id);
    if (!confirm('Tem certeza que deseja EXCLUIR a passagem de bastão de "' + nome + '"?\n\nIsso vai marcar o registro como inativo no sistema. Use com cuidado — só faça isso se for refazer o processo do zero.')) return;
    try {
      const r = await (await fetch('api_passagem.php?action=excluir_passagem_bastao&id=' + id)).json();
      if (!r.ok){ alert(r.msg || 'Falha ao excluir.'); return; }
      toastMsg('Passagem de bastão excluída.');
      closeModal('mod-pb-edit');
      pbCarregarLista();
    } catch(e){ alert('Erro de conexão: ' + (e.message || e)); }
  };

  window.pbSalvarEdit = async function(btn){
    const body = { id: $('pe-id').value };
    STD.forEach(k => { const el = $('pe-' + k); if (el) body[k] = (el.value || '').trim(); });
    const an = $('pe-analista'); if (an) body.analista_nome = (an.value || '').trim();
    if (!body.nome){ toastMsg('Preencha a Empresa.'); const n=$('pe-nome'); if(n) n.focus(); return; }
    if (btn) btn.disabled = true;
    try {
      const r = await fetch('api_passagem.php?action=editar_passagem_bastao', {
        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)
      }).then(x => x.json());
      if (!r.ok){ alert(r.msg || 'Falha ao salvar.'); return; }
      toastMsg('Alterações salvas.');
      closeModal('mod-pb-edit');
      pbCarregarLista();
    } catch(e){ alert('Erro de conexão ao salvar: ' + (e.message || e)); }
    finally { if (btn) btn.disabled = false; }
  };

  // ── Notificação por e-mail: liga/desliga campos extras ──
  window.pbToggleNotifEmail = function(){
    const cb  = $('pb-notificar_email_passagem');
    const box = $('pb-notif-email-box');
    if (!cb || !box) return;
    box.style.display = cb.checked ? '' : 'none';
    // Botão "Enviar e-mail" só aparece se já estamos editando (tem ID) E o checkbox está marcado
    const btn = $('pb-btn-enviar-email');
    const editId = ($('pb-id')||{}).value;
    if (btn) btn.style.display = (cb.checked && editId) ? '' : 'none';
  };

  // ── Abre o modal de revisão do e-mail antes de enviar ──
  window.pbAbrirEnvioEmail = function(){
    const editId = ($('pb-id')||{}).value;
    if (!editId){ alert('Salve a passagem de bastão antes de enviar o e-mail.'); return; }
    const empresa = ($('pb-nome')||{}).value || '';
    const tipo = ($('pb-tipo_passagem')||{}).value || '';
    // Define a palavra dinâmica baseada no tipo de passagem
    let palavra = 'implantação';
    const t = tipo.toLowerCase();
    if (t.indexOf('renov') === 0)      palavra = 'renovação';
    else if (t.indexOf('nomea') === 0) palavra = 'nomeação';
    else if (t.indexOf('seguro') === 0) palavra = 'implantação';

    const destinatarios = ($('pb-destinatarios_email_passagem')||{}).value || '';
    const assunto = 'Conclusão da ' + palavra + ': ' + (empresa || 'Empresa');
    const corpo =
      'Prezados,\n\n' +
      'Finalizamos a ' + palavra + ' da empresa ' + empresa + '. ' +
      'Todos os dados contratuais obtidos pela área de Placement já estão disponíveis ' +
      'na parte de "Passagem de Bastão" do Sistema Interweg.\n\n' +
      'Permanecemos à disposição para quaisquer esclarecimentos.';

    const idEl = $('pb-em-empresa-id'); if (idEl) idEl.value = String(editId);
    const dEl  = $('pb-em-para');       if (dEl)  dEl.value  = destinatarios;
    const aEl  = $('pb-em-assunto');    if (aEl)  aEl.value  = assunto;
    const cEl  = $('pb-em-corpo');      if (cEl)  cEl.value  = corpo;
    openModal('mod-pb-email');
  };

  // ── Passo 1: valida e abre o modal "Tem certeza que deseja enviar?" ──
  window.pbEnviarEmail = function(){
    const id   = parseInt(($('pb-em-empresa-id')||{}).value || '0', 10);
    const para = (($('pb-em-para')||{}).value || '').trim();
    const ass  = (($('pb-em-assunto')||{}).value || '').trim();
    const cor  = (($('pb-em-corpo')||{}).value || '').trim();
    if (!id)   { alert('ID da empresa inválido.'); return; }
    if (!para) { alert('Informe ao menos um destinatário.'); return; }
    if (!ass)  { alert('Informe o assunto.'); return; }
    if (!cor)  { alert('A mensagem está vazia.'); return; }
    const rp = $('pb-confirm-para');    if (rp) rp.textContent = para;
    const ra = $('pb-confirm-assunto'); if (ra) ra.textContent = ass;
    openModal('mod-pb-confirm');
  };

  // ── Passo 2: confirma e envia de fato ──
  window.pbConfirmarEnvio = async function(btn){
    const id   = parseInt(($('pb-em-empresa-id')||{}).value || '0', 10);
    const para = (($('pb-em-para')||{}).value || '').trim();
    const ass  = (($('pb-em-assunto')||{}).value || '').trim();
    const cor  = (($('pb-em-corpo')||{}).value || '').trim();
    const sigNome  = (($('pb-em-sig-nome') ||{}).value || '').trim();
    const sigCargo = (($('pb-em-sig-cargo')||{}).value || '').trim();
    if (btn) btn.disabled = true;
    try {
      const r = await fetch('api_passagem.php?action=enviar_email_passagem', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ empresa_id:id, destinatarios:para, assunto:ass, corpo:cor, sig_nome:sigNome, sig_cargo:sigCargo })
      }).then(x => x.json());
      if (!r.ok){ alert('Não foi possível enviar: ' + (r.msg || 'erro desconhecido')); return; }
      toastMsg('E-mail enviado com sucesso!');
      closeModal('mod-pb-confirm');
      closeModal('mod-pb-email');
      const infoBox = $('pb-email-enviado-info');
      const qSpan = $('pb-email-enviado-quando');
      if (infoBox) infoBox.style.display = 'block';
      if (qSpan)   qSpan.textContent = pbFmtDataHora(r.enviado_em || new Date().toISOString().slice(0,19).replace('T',' '));
      // Se veio do "Salvar", volta pra lista (não fica preso no formulário)
      if (pbEnvioAposSalvar){ pbEnvioAposSalvar = false; pbLimparEdit(); pbShowTab('lista'); pbCarregarLista(); }
    } catch(e){ alert('Erro de conexão: ' + (e.message || e)); }
    finally { if (btn) btn.disabled = false; }
  };

  // ── Cancelar o envio: fecha o modal e, se veio do Salvar, volta pra lista ──
  window.pbCancelarEnvio = function(){
    closeModal('mod-pb-email');
    if (pbEnvioAposSalvar){ pbEnvioAposSalvar = false; pbLimparEdit(); pbShowTab('lista'); pbCarregarLista(); }
  };

  // ── Consulta o CNPJ na Receita e preenche a Razão Social ──
  window.pbConsultarCnpj = async function(btn){
    const info = $('pb-cnpj-info');
    const cnpj = (($('pb-cnpj')||{}).value || '').replace(/\D/g,'');
    if (cnpj.length !== 14){ if(info){ info.style.color='#c0392b'; info.textContent='Digite um CNPJ com 14 dígitos.'; } return; }
    if (btn) btn.disabled = true;
    if (info){ info.style.color='var(--muted)'; info.textContent='Consultando…'; }
    try{
      const r = await apiGet('consultar_cnpj', { cnpj });
      if (r.ok && r.data){
        const d = r.data, nome = $('pb-nome');
        if (nome && !nome.value.trim()) nome.value = d.razao_social || '';
        const partes = [d.razao_social];
        if (d.municipio || d.uf) partes.push((d.municipio||'')+(d.uf?('/'+d.uf):''));
        if (d.situacao) partes.push('situação: '+d.situacao);
        if (info){ info.style.color='var(--success,#28a745)'; info.textContent = '✓ '+partes.filter(Boolean).join(' · '); }
      } else if (info){ info.style.color='#c0392b'; info.textContent = r.msg || 'CNPJ não encontrado.'; }
    }catch(e){ if(info){ info.style.color='#c0392b'; info.textContent='Erro na consulta.'; } }
    if (btn) btn.disabled = false;
  };
})();
