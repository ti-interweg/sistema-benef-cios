// ═══════════════════════════════════════════════════════════════
// INFORMATIVO DE SINISTRALIDADE — Enfermagem
// 3 variações · grade editável (colunas + linhas) · PPT · PDF
// ═══════════════════════════════════════════════════════════════
if (typeof window.toast !== 'function') window.toast = m => { try { alert(m); } catch(e){} };
if (typeof window.esc !== 'function')   window.esc   = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
let SIN_VAR = 'positivo';
let SIN_BI  = false;
let SIN_TABIMG = '';
let SIN_INFOIMG = '';
let SIN_EMAIL_IMG = ''; // informativo capturado para anexar no e-mail
let SIN_OPERADORA = 'auto';
// Regras por operadora: orientação conhecida de cada relatório de sinistralidade.
// 'transposto' = meses no topo (colunas) · 'padrao' = meses nas linhas · 'auto' = detectar.
const SIN_OPERADORAS = {
  auto:                  { orient:'auto'       },
  // ── orientação confirmada (já vi o layout) ──
  amil_odonto:           { orient:'padrao'     },
  amil_saude:            { orient:'padrao'     },
  aurora_saude:          { orient:'transposto', metrics:[
                            {re:/quantidade de vidas|^vidas|beneficiari/, col:'Qtde de Vidas'},
                            {re:/custo total|^custo$|custo odont/,        col:'Custo'},
                            {re:/copart/,                                  col:'Coparticipação'},
                            {re:/receita total|receita bruta|receita|faturamento/, col:'Receita'},
                            {re:/sinistralidade/,                          col:'Sinistralidade'} ] },
  bb_dental:             { orient:'transposto', metrics:[
                            {re:/beneficiari/,                             col:'Qtde de Vidas'},
                            {re:/fat.*l[ií]?quid|faturamento|receita/,     col:'Receita'},
                            {re:/custo rede|^custo/,                       col:'Custo'},
                            {re:/sinistralidade/,                          col:'Sinistralidade'} ] },
  bradesco_odonto:       { orient:'padrao', parser:'pct', spec:'odonto',
                            cols:['Data de Competência','Faturamento','Faturamento Líquido','Repasse','Materiais Dentários','Sinistro Apurado','Sinistralidade','Qtde de Vidas','Custo PC','Sin PC'] },
  bradesco_saude:        { orient:'padrao', parser:'pct', spec:'saude',
                            cols:['Data de Competência','Faturamento','Evento','Sinistralidade','Nº de Vidas','Faturamento Per Capita','Evento Per Capita'] },
  metlife_odonto:        { orient:'transposto', metrics:[
                            {re:/faturamento|receita/,                     col:'Receita'},
                            {re:/despesa total|desp.*rede|despesa|custo/,  col:'Custo'},
                            {re:/sinistralidade/,                          col:'Sinistralidade'} ] },
  unimed_bh:             { orient:'transposto', metrics:[
                            {re:/contingente|vidas|beneficiari/,           col:'Qtde de Vidas'},
                            {re:/receita total|receita bruta|receita/,     col:'Receita'},
                            {re:/custo assistencial total|custo assistencial|custo/, col:'Custo'},
                            {re:/^iu$|sinistralidade|utilizacao/,          col:'Sinistralidade'} ] },
  // ── ainda sem amostra → detecta automático (ajusto quando enviar exemplo) ──
  caberj_saude:          { orient:'auto'       },
  hapvida:               { orient:'auto'       },
  odontoprev:            { orient:'auto'       },
  unimed_saude:          { orient:'auto'       },
  sulamerica_odonto:     { orient:'auto'       },
  sulamerica_saude:      { orient:'auto'       },
  unimed_gov_valadares:  { orient:'auto'       },
  unimed_ma:             { orient:'auto'       }
};
function sinSetOperadora(v){
  SIN_OPERADORA = v || 'auto';
  try{ localStorage.setItem('sinOperadora', SIN_OPERADORA); }catch(e){}
  // Ajusta o checkbox "Meses nas colunas" conforme a operadora escolhida
  const cb=document.getElementById('sin-transpor'); const o=SIN_OPERADORAS[SIN_OPERADORA];
  if(cb && o && o.orient!=='auto') cb.checked = (o.orient==='transposto');
}

// ─── Imagem FIXA do informativo (gravada no SERVIDOR via upload_info_img.php) ───
// Funciona em qualquer navegador/computador. Só sai quando o usuário trocar/remover.
const SIN_INFOIMG_ENDPOINT = 'upload_info_img.php';

function _sinRenderInfoThumb(){
  const t = document.getElementById('sin-info-thumb');
  if (!t) return;
  if (SIN_INFOIMG){
    t.innerHTML =
      '<div style="display:flex;align-items:center;gap:12px"><img src="'+SIN_INFOIMG+'" style="max-width:300px;max-height:140px;border:1px solid var(--border);border-radius:6px"/>'+
      '<div style="display:flex;flex-direction:column;gap:6px">'+
        '<span style="font-size:11.5px;color:#0a8a8d;display:inline-flex;align-items:center;gap:5px"><span class="material-symbols-rounded" style="font-size:14px">push_pin</span> Imagem fixa, usada em todos os informativos até você trocar</span>'+
        '<button class="btn btn-outline btn-sm" type="button" onclick="sinInfoImgClear()"><span class="material-symbols-rounded" style="font-size:14px">close</span> Trocar / Remover</button>'+
      '</div></div>';
  } else {
    t.innerHTML = '';
  }
}

async function sinInfoImgUp(input){
  const file=input.files&&input.files[0]; if(!file) return;
  if(!/^image\//.test(file.type)){ toast('Selecione um arquivo de imagem.'); input.value=''; return; }
  if (file.size > 8*1024*1024){ toast('Imagem grande demais (máx. 8 MB).'); input.value=''; return; }

  // Preview imediato (data URL local)
  try {
    const reader = new FileReader();
    reader.onload = e => { SIN_INFOIMG = e.target.result; _sinRenderInfoThumb(); };
    reader.readAsDataURL(file);
  } catch(_){}

  // Upload pro servidor → fica fixa pra todos os usuários
  try {
    const fd = new FormData();
    fd.append('imagem', file);
    const r = await fetch(SIN_INFOIMG_ENDPOINT, { method:'POST', body: fd });
    const data = await r.json();
    if (!data.ok){ toast('Erro ao salvar no servidor: ' + (data.msg || '')); return; }
    SIN_INFOIMG = data.url + '&t=' + Date.now();
    _sinRenderInfoThumb();
    toast('Imagem fixa atualizada — vai aparecer em todos os próximos informativos.');
  } catch(err){
    toast('Erro ao subir a imagem: ' + err.message);
  } finally {
    if (input) input.value = '';
  }
}

async function sinInfoImgClear(){
  if (!confirm('Remover a imagem fixa? (Você pode subir outra depois para trocar)')) return;
  try {
    const r = await fetch(SIN_INFOIMG_ENDPOINT + '?action=remove', { method:'POST', body: new FormData() });
    const data = await r.json();
    if (!data.ok){ toast('Erro: ' + (data.msg || '')); return; }
    SIN_INFOIMG = '';
    const i=document.getElementById('sin-info-img'); if(i) i.value='';
    _sinRenderInfoThumb();
    toast(data.msg || 'Imagem fixa removida.');
  } catch(err){
    toast('Erro: ' + err.message);
  }
}

// Ao abrir a página, busca se já existe imagem fixa no servidor e exibe
async function sinInfoImgRestore(){
  try {
    const r = await fetch(SIN_INFOIMG_ENDPOINT + '?action=check');
    const data = await r.json();
    if (data && data.exists && data.url){ SIN_INFOIMG = data.url; _sinRenderInfoThumb(); }
  } catch(_){}
}
document.addEventListener('DOMContentLoaded', sinInfoImgRestore);

function sinSetInfoMes(m){ if(!m) return; const el=document.getElementById('sin-info-titulo'); if(el) el.value='Informativo '+m; }
function sinAtualizarPrompt(){
  const tEl=document.getElementById('sin-info-tema');
  const pEl=document.getElementById('sin-info-prompt');
  if(!pEl) return;
  const tema=((tEl&&tEl.value)||'').trim() || 'saúde';
  pEl.value =
'Crie uma fotografia realista e profissional (fotorrealista, alta qualidade, elementos reais — sem ilustração, desenho ou cartoon), em formato de banner horizontal (proporção aproximada 16:6), estilo moderno e clean, para um informativo de saúde corporativo com o tema: '+tema.toUpperCase()+'.\n\n'+
'Paleta de cores da marca Interweg: verde/teal como cor predominante e detalhes em vermelho.\n\n'+
'Mostre objetos e elementos REAIS diretamente ligados ao tema "'+tema+'", dispostos de forma elegante sobre uma superfície clean. Iluminação suave e natural, profundidade de campo com fundo desfocado de um ambiente clínico/hospitalar moderno.\n\n'+
'Deixe uma área limpa e livre no canto superior esquerdo para inserir o título depois. NÃO inclua nenhum texto, letra ou número na imagem. Resolução alta e nitidez profissional.';
}
// Persiste tema/texto/título do informativo no navegador (não some ao recarregar)
function sinSalvarInfo(){
  try {
    localStorage.setItem('sinInfo', JSON.stringify({
      tema:   (document.getElementById('sin-info-tema')   || {}).value || '',
      texto:  (document.getElementById('sin-info-texto')  || {}).value || '',
      titulo: (document.getElementById('sin-info-titulo') || {}).value || ''
    }));
  } catch(_){}
}
document.addEventListener('DOMContentLoaded', () => {
  try {
    const s = JSON.parse(localStorage.getItem('sinInfo') || '{}');
    const setIf = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
    setIf('sin-info-tema',   s.tema);
    setIf('sin-info-texto',  s.texto);
    setIf('sin-info-titulo', s.titulo);
  } catch(_){}
  ['sin-info-tema','sin-info-texto','sin-info-titulo'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', sinSalvarInfo);
  });
  if (typeof sinAtualizarPrompt === 'function') sinAtualizarPrompt();
});
function sinCopiarPrompt(){
  const el=document.getElementById('sin-info-prompt'); if(!el) return;
  const txt=el.value;
  const done=()=>toast('Prompt copiado! Cole no Gemini para gerar a imagem.');
  if(navigator.clipboard && navigator.clipboard.writeText){ navigator.clipboard.writeText(txt).then(done).catch(()=>{ el.select(); document.execCommand('copy'); done(); }); }
  else { el.select(); document.execCommand('copy'); done(); }
}
const SIN_PPTX = 'https://cdn.jsdelivr.net/npm/pptxgenjs@3.12.0/dist/pptxgen.bundle.min.js';
const SIN_H2C  = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
const SIN_JSPDF= 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
const SIN_TESS = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
function sinLoadScript(src){ return new Promise((res,rej)=>{ const s=document.createElement('script'); s.src=src; s.onload=res; s.onerror=()=>rej(new Error('Falha ao carregar '+src)); document.head.appendChild(s); }); }
function sinImgToDataURL(src){ return new Promise(res=>{ const im=new Image(); im.onload=()=>{ try{ const c=document.createElement('canvas'); c.width=im.naturalWidth; c.height=im.naturalHeight; c.getContext('2d').drawImage(im,0,0); res(c.toDataURL('image/png')); }catch(e){ res(''); } }; im.onerror=()=>res(''); im.src=src; }); }
// Colunas + linhas padrão (extraídas da tabela enviada)
const SIN_DEF_COLS = ['Data de Competência','Receita','Custo Odontológico','Sinistralidade','Qtde de Usuários','Custo per Capita','Qtde de Vidas'];
// Grade começa vazia — os valores são extraídos da imagem (OCR) ou digitados/importados.
const SIN_DEF_ROWS = Array.from({length:12}, ()=> SIN_DEF_COLS.map(()=> ''));
document.addEventListener('DOMContentLoaded', () => {
  sinBuildGrid(SIN_DEF_COLS, SIN_DEF_ROWS);
  // Restaura a última operadora escolhida
  try{
    const sav=localStorage.getItem('sinOperadora');
    const sel=document.getElementById('sin-operadora');
    if(sav && sel && SIN_OPERADORAS[sav]){ sel.value=sav; sinSetOperadora(sav); }
  }catch(e){}
});
// ─── Variação ────────────────────────────────────────────────
function sinSetVar(v){
  SIN_VAR = v;
  document.querySelectorAll('.sin-var-btn').forEach(b => b.classList.toggle('active', b.dataset.var===v));
}
function sinSetBi(checked){
  SIN_BI = !!checked;
  document.body.classList.toggle('sin-bi', SIN_BI);
}

// ── ENVIO POR E-MAIL (só com BI marcado) ──
// Captura o informativo já renderizado (#sin-doc) como PNG base64
async function _sinCapturarInformativoBase64(){
  const node = document.getElementById('sin-doc');
  if (!node) return null;
  try {
    if (!window.html2canvas) await sinLoadScript(SIN_H2C);
    const canvas = await html2canvas(node, {
      scale: 2,
      backgroundColor:'#ffffff',
      useCORS: true,
      letterRendering: true,
      imageTimeout: 0
    });
    // JPEG com qualidade 0.85 pra reduzir tamanho do e-mail
    return canvas.toDataURL('image/jpeg', 0.85);
  } catch(e){
    console.error('Erro ao capturar informativo:', e);
    return null;
  }
}

function sinAbrirEnvioEmail(){
  // Envio liberado com ou sem análise de BI marcada.
  // Exige que o informativo já tenha sido gerado
  if (!document.getElementById('sin-doc')){
    toast('Antes de enviar por e-mail, clique em "Gerar Informativo Editável" para criar o informativo.');
    return;
  }
  const para    = (document.getElementById('sin-em-para')||{}).value || '';
  const ass     = (document.getElementById('sin-em-assunto')||{}).value || '';
  const link    = (document.getElementById('sin-em-link')||{}).value || '';
  const login   = (document.getElementById('sin-em-login')||{}).value || '';
  const senha   = (document.getElementById('sin-em-senha')||{}).value || '';
  const empresa = (document.getElementById('sin-empresa')||{}).value || '';
  const compet  = (document.getElementById('sin-competencia')||{}).value || '';
  const titulo  = (document.getElementById('sin-info-titulo')||{}).value || '';

  if (!para.trim()){ toast('Informe ao menos um destinatário.'); return; }
  // Link do BI é opcional — nem todo cliente tem BI.

  // Coleta os textos de análise para colocar no corpo (perfil dos beneficiários, etc.)
  const demografico = ((document.getElementById('sin-bi-demografico')||{}).value || '').trim();
  const high        = ((document.getElementById('sin-bi-high')||{}).value || '').trim();
  const consultas   = ((document.getElementById('sin-bi-consultas')||{}).value || '').trim();
  const exames      = ((document.getElementById('sin-bi-exames')||{}).value || '').trim();
  const internacoes = ((document.getElementById('sin-bi-internacoes')||{}).value || '').trim();
  const terapias    = ((document.getElementById('sin-bi-terapias')||{}).value || '').trim();
  const prestadores = ((document.getElementById('sin-bi-prestadores')||{}).value || '').trim();
  const produtos    = ((document.getElementById('sin-bi-produtos')||{}).value || '').trim();
  const ranking     = ((document.getElementById('sin-bi-ranking')||{}).value || '').trim();

  // Assunto padrão se vazio
  let assunto = ass.trim();
  if (!assunto){
    const partes = [];
    if (titulo) partes.push(titulo);
    else partes.push('Informativo de Sinistralidade');
    if (compet)  partes.push(compet);
    if (empresa) partes.push(empresa);
    assunto = partes.join(' — ');
  }

  // O informativo (imagem) é o corpo do e-mail — sem texto de mensagem.
  // Link/Login/Senha do WeeBi já vão dentro da própria imagem do informativo.
  const corpo = '';

  // Preenche o modal e abre
  const $ = id => document.getElementById(id);
  if ($('sin-em-modal-para'))    $('sin-em-modal-para').value    = para;
  if ($('sin-em-modal-assunto')) $('sin-em-modal-assunto').value = assunto;
  if ($('sin-em-modal-corpo'))   $('sin-em-modal-corpo').value   = corpo;
  if (typeof window.openModal === 'function') window.openModal('mod-sin-email');
  else { const m=$('mod-sin-email'); if (m) m.classList.add('show'); }

  // Captura o informativo agora e mostra a miniatura no modal (garante que vai anexado)
  const wrap  = $('sin-em-modal-img-wrap');
  const aviso = $('sin-em-modal-img-aviso');
  const imgEl = $('sin-em-modal-img');
  SIN_EMAIL_IMG = '';
  if (wrap)  wrap.style.display  = 'none';
  if (aviso) aviso.style.display = 'none';
  _sinCapturarInformativoBase64().then(img => {
    if (img) {
      SIN_EMAIL_IMG = img;
      if (imgEl) imgEl.src = img;
      if (wrap)  wrap.style.display = 'block';
    } else {
      if (aviso) aviso.style.display = 'block';
    }
  }).catch(() => { if (aviso) aviso.style.display = 'block'; });
}

async function sinEnviarEmail(btn){
  const para = ((document.getElementById('sin-em-modal-para')||{}).value || '').trim();
  let   ass  = ((document.getElementById('sin-em-modal-assunto')||{}).value || '').trim();
  const cor  = ((document.getElementById('sin-em-modal-corpo')||{}).value || '').trim();
  const empresa = (document.getElementById('sin-empresa')||{}).value || '';
  // Só o destinatário é obrigatório. Assunto e mensagem são opcionais.
  if (!para){ alert('Informe ao menos um destinatário.'); return; }
  if (!ass){ ass = 'Informativo de Sinistralidade' + (empresa ? ' — ' + empresa : ''); }
  if (!confirm('Confirmar o envio do informativo para:\n\n' + para + '\n\nAssunto: ' + ass)) return;
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">hourglass_top</span> Capturando informativo...';
  }
  try {
    // 1) Usa o informativo já capturado ao abrir o modal; se faltar, captura agora
    const imagemBase64 = SIN_EMAIL_IMG || await _sinCapturarInformativoBase64();
    if (!imagemBase64){
      alert('Não foi possível capturar o informativo. Gere-o novamente e tente outra vez.');
      return;
    }
    if (btn) btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">hourglass_top</span> Enviando...';
    // Acesso ao WeeBi (link clicável abaixo da imagem)
    const weebiLink  = ((document.getElementById('sin-em-link')||{}).value  || '').trim();
    const weebiLogin = ((document.getElementById('sin-em-login')||{}).value || '').trim();
    const weebiSenha = ((document.getElementById('sin-em-senha')||{}).value || '').trim();
    // 2) Envia tudo pro backend (corpo + imagem)
    const r = await fetch('api_sinistralidade_email.php?action=enviar_email_sinistralidade', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ empresa, destinatarios:para, assunto:ass, corpo:cor, imagem_informativo: imagemBase64,
        weebi_link: weebiLink, weebi_login: weebiLogin, weebi_senha: weebiSenha })
    }).then(x => x.json());
    if (!r.ok){ alert('Não foi possível enviar: ' + (r.msg || 'erro desconhecido')); return; }
    toast('Informativo enviado com sucesso!');
    if (typeof window.closeModal === 'function') window.closeModal('mod-sin-email');
    else { const m=document.getElementById('mod-sin-email'); if (m) m.classList.remove('show'); }
  } catch(e){ alert('Erro de conexão: ' + (e.message || e)); }
  finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<span class="material-symbols-rounded" style="font-size:17px">send</span> Enviar';
    }
  }
}

window.sinAbrirEnvioEmail = sinAbrirEnvioEmail;
window.sinEnviarEmail     = sinEnviarEmail;
// Amplia/normaliza a imagem antes do OCR (Tesseract lê muito melhor em alta resolução)
function sinPrepImg(file){
  return new Promise(res=>{
    const u=URL.createObjectURL(file); const im=new Image();
    im.onload=()=>{
      const alvo=3200; // largura mínima desejada (alta resolução melhora a leitura de linhas finas)
      const scale=Math.max(1, alvo/(im.naturalWidth||alvo));
      const c=document.createElement('canvas');
      c.width=Math.round((im.naturalWidth||alvo)*scale);
      c.height=Math.round((im.naturalHeight||1)*scale);
      const ctx=c.getContext('2d');
      ctx.imageSmoothingEnabled=true; ctx.imageSmoothingQuality='high';
      ctx.fillStyle='#fff'; ctx.fillRect(0,0,c.width,c.height);
      ctx.drawImage(im,0,0,c.width,c.height);
      // tons de cinza + contraste: tira cores/gridlines e deixa o texto mais nítido pro OCR
      try{
        const id=ctx.getImageData(0,0,c.width,c.height), d=id.data;
        for(let k=0;k<d.length;k+=4){
          let g=0.299*d[k]+0.587*d[k+1]+0.114*d[k+2];
          g=(g-128)*1.25+128;                // realce de contraste suave
          d[k]=d[k+1]=d[k+2]=(g<0?0:g>255?255:g);
        }
        ctx.putImageData(id,0,0);
      }catch(e){}
      URL.revokeObjectURL(u); res(c);
    };
    im.onerror=()=>{ URL.revokeObjectURL(u); res(null); };
    im.src=u;
  });
}
// (sinAgregarBI, sinImportarBI e sinLerIA foram movidas para assets/sinistralidade_ia.js)
// ─── Extrair valores da imagem → preenche a grade (IA de visão; Tesseract de reserva) ──────
async function sinTabImgUp(input){
  const file=input.files&&input.files[0]; if(!file) return;
  if(!/^image\//.test(file.type)){ toast('Selecione um arquivo de imagem.'); input.value=''; return; }
  const url=URL.createObjectURL(file);
  const thumb=document.getElementById('sin-tab-thumb');
  const mesIni=(document.getElementById('sin-mes-ini')||{}).value||'';
  const _op=SIN_OPERADORAS[SIN_OPERADORA]||{orient:'auto'};
  const _force=(_op.orient==='auto')?undefined:_op.orient;
  const okThumb=()=>{ thumb.innerHTML='<div style="display:flex;align-items:center;gap:12px"><img src="'+url+'" style="max-width:300px;max-height:140px;border:1px solid var(--border);border-radius:6px"/>'+
      '<span style="font-size:12px;color:var(--muted)">Valores na grade acima — <strong>revise</strong> antes de gerar.</span></div>'; };
  thumb.innerHTML='<div style="color:#0a8a8d;font-size:12px"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">hourglass_top</span> Lendo a imagem com IA de visão... pode levar alguns segundos.</div>';
  try{
    // 1) IA de visão (lê como um humano)
    const ia = await sinLerIA(file);
    if(ia && ia.cols && Array.isArray(ia.rows) && ia.rows.length){
      let p={cols:ia.cols, rows:ia.rows};
      if(_op.parser==='pct') p=sinPad12(p, mesIni);
      sinBuildGrid(p.cols, p.rows);
      toast(p.rows.length+' linha(s) lidas com IA de visão. Revise antes de gerar.');
      okThumb(); input.value=''; return;
    }
    // IA falhou: mostra o motivo REAL (antes era engolido e caía direto no OCR local).
    const _iaErro = (ia && ia.erro) ? (ia.erro + (ia.detalhe ? ' — ' + String(ia.detalhe).slice(0,300) : '')) : 'motivo desconhecido';
    console.warn('OCR IA indisponível:', _iaErro);
    // 2) Reserva: Tesseract no navegador (bem menos preciso — é o que vem "desconfigurado")
    thumb.innerHTML='<div style="color:#c0392b;font-size:12px;line-height:1.4"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">error</span> A leitura por <b>IA</b> falhou: '+ _iaErro.replace(/</g,'&lt;') +'.<br>Usando OCR local (menos preciso)...</div>';
    if(!window.Tesseract) await sinLoadScript(SIN_TESS);
    const big = await sinPrepImg(file);                 // amplia/realça antes de ler
    let imgForOcr = url;
    if(big){ try{ imgForOcr = big.toDataURL('image/png'); }catch(e){ imgForOcr = url; } }
    // PSM 6 (bloco único) lê tabelas com linhas de grade muito melhor que o automático.
    // Em operadoras numéricas (ex.: Bradesco), restringe o OCR a dígitos/moeda → menos erro.
    let res=null;
    try{
      const worker = await Tesseract.createWorker('por');
      const params={ tessedit_pageseg_mode:'6', preserve_interword_spaces:'1' };
      if(_op.numerico) params.tessedit_char_whitelist='0123456789.,%R$/ -';
      await worker.setParameters(params);
      res = await worker.recognize(imgForOcr);
      try{ await worker.terminate(); }catch(_){}
    }catch(e){ res=null; }
    // Fallback: se o PSM 6 leu quase nada, usa o modo automático
    if(!res || !res.data || String(res.data.text||'').replace(/\s/g,'').length < 30){
      res = await Tesseract.recognize(imgForOcr, 'por');
    }
    // Parser dedicado (ex.: Bradesco) ou parser geral
    let parsed = (_op.parser==='pct') ? sinParseDirectPct(res.data.text, _op.spec)
                                      : sinParseOcr(res.data.text, _force, _op.metrics, _op.direct);
    if(_op.parser==='pct' && parsed.rows.length){
      parsed = sinPad12(parsed, (document.getElementById('sin-mes-ini')||{}).value||'');
    }
    const rows=parsed.rows;
    const existing=[...document.querySelectorAll('#sin-grid-head .col-h')].map(i=>i.value.trim());
    const cols = parsed.cols || (existing.length?existing:SIN_DEF_COLS);
    if(rows.length){
      sinBuildGrid(cols, rows);
      toast(rows.length+' linha(s) extraída(s)'+(parsed.cols?' — formato transposto detectado e ajustado automaticamente':'')+'. Revise os valores na grade'+(parsed.cols?' e use "Preencher meses" se as datas não vieram.':'.'));
    } else {
      // Imagem ilegível → monta a grade na tela (colunas da operadora + meses) pra digitar direto
      sinPrepararGrade(SIN_OPERADORA, mesIni);
      toast('Não consegui ler a imagem. Montei a grade'+(SIN_OPERADORA&&SIN_OPERADORA!=='auto'?' da operadora':'')+' com os meses — é só digitar os valores na tabela acima.');
    }
    okThumb();
  }catch(e){ console.error(e); thumb.innerHTML=''; toast('Erro ao ler a imagem: '+e.message); }
  finally{ input.value=''; }
}
// ─── Sinônimos de colunas (cada operadora usa nomes diferentes) ──
// Mapeia o rótulo lido na imagem para a coluna padrão do informativo.
function sinMapCol(label){
  const n=sinNorm(label).replace(/^[-•·\s]+/,'');
  if(!n) return (label||'').trim();
  if(/per capita|custo carteira|custo pc|custo medio|capita/.test(n)) return 'Custo per Capita';
  if(/sinistralidade|sin pago|sin\. pago|% de eventos|% de utilizacao|utilizacao|^iu$/.test(n)) return 'Sinistralidade';
  if(/copart/.test(n)) return 'Coparticipação';
  if(/receita|faturamento|premio|mensalidade|valor faturado|fat liquido|fat\.? liquido/.test(n)) return 'Receita';
  if(/custo|sinistro|despesa|evento|sinist/.test(n)) return 'Custo';
  if(/vidas|beneficiari|contingente|contingencia/.test(n)) return 'Qtde de Vidas';
  if(/usuario|atendido|atendimento|inclus/.test(n)) return 'Qtde de Usuários';
  if(/%$/.test(String(label).trim())) return 'Sinistralidade'; // rótulo terminado em % ≈ sinistralidade
  return (label||'').replace(/^[-•·\s]+/,'').trim() || String(label);
}
// Tokens reconhecidos: R$ x.xxx,xx · x.xxx,xx% · 1.234.567 · inteiros · traço
const SIN_VAL_SRC   = "-?R\\$\\s?-?[\\d.]+,\\d{2}%?|-?[\\d.]+,\\d{2}%?|-?\\d{1,3}(?:\\.\\d{3})+|-?\\d{1,4}%?|—|-";
const SIN_MONTH_SRC = '(?:janeiro|fevereiro|marco|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)';
// Separa uma linha em {rótulo, valores}
function sinOcrSplit(line){
  const s=String(line).replace(/^[\s\-•·▪◦*|]+/,'').trim();
  const re=new RegExp(SIN_VAL_SRC,'g'); const m=re.exec(s);
  let label, valsStr;
  if(m){ label=s.slice(0,m.index).trim(); valsStr=s.slice(m.index); }
  else { label=s; valsStr=''; }
  const vals=valsStr?(valsStr.match(new RegExp(SIN_VAL_SRC,'g'))||[]):[];
  return {label, vals};
}
function sinIsMonth(label){
  const s=String(label).replace(/\s+/g,'');
  return new RegExp('^'+SIN_MONTH_SRC+'[a-zç]*/?\\d{2,4}$','i').test(s)
      || /^(0?[1-9]|1[0-2])\/\d{2,4}$/.test(String(label).trim());
}
// Converte um rótulo de mês (mai/25, 05/25, Maio/2025) em {m:0-11, y:ano}
function sinParseMesAny(s){
  s=String(s||'').trim();
  const num=s.match(/^(0?[1-9]|1[0-2])[\/\-](\d{2,4})$/);
  if(num){ let y=parseInt(num[2],10); if(y<100) y+=2000; return {m:parseInt(num[1],10)-1, y}; }
  return sinParseCompetencia(s); // trata nomes (mai, maio, Maio/2025)
}
// Conserta separadores bagunçados pelo OCR: "7.26218" / "6778.52" / "4,699.03" → "7.262,18"
function sinFixMoney(t){
  const digits=String(t).replace(/\D/g,'');
  if(digits.length<3) return String(t).replace(/[R$\s]/g,'');
  const cents=digits.slice(-2), intp=digits.slice(0,-2);
  return intp.replace(/\B(?=(\d{3})+(?!\d))/g,'.')+','+cents;
}
// Conserta % sem vírgula: "3609%" → "36,09%" (mantém os que já têm vírgula)
function sinFixPct(t){
  t=String(t).replace('%','');
  if(/,/.test(t)) return t.replace('.','')+'%';
  const d=t.replace(/\D/g,'');
  if(d.length<=2) return d+'%';
  return d.slice(0,-2)+','+d.slice(-2)+'%';
}
// Layouts "meses nas linhas" com % no meio. Cada spec define as colunas antes/depois do %.
const SIN_PCT_SPECS = {
  odonto: { before:['Faturamento','Faturamento Líquido','Repasse','Materiais Dentários','Sinistro Apurado'], pct:'Sinistralidade', after:['Qtde de Vidas','Custo PC','Sin PC'] },
  saude:  { before:['Faturamento','Evento'], pct:'Sinistralidade', after:['Nº de Vidas','Faturamento Per Capita','Evento Per Capita'] }
};
// Escolhe o layout: pela operadora (hint) ou auto pela qtde de valores antes do %
function sinPctSpec(text, hint){
  if(hint && SIN_PCT_SPECS[hint]) return SIN_PCT_SPECS[hint];
  const freq={};
  String(text||'').split('\n').forEach(line=>{
    const toks=(line.match(/R\$|[\d][\d.,]*%?/g)||[]).filter(t=>t!=='R$'&&t!=='R'&&/\d/.test(t));
    const pct=toks.findIndex(t=>/%$/.test(t)); if(pct<1) return;
    let i0=0; while(i0<pct){ const d=String(toks[i0]).replace(/\D/g,''); if(d.length<=2||/^(19|20)\d{2}$/.test(d)){i0++;}else break; }
    const n=pct-i0; freq[n]=(freq[n]||0)+1;
  });
  let best=0,bc=0; Object.keys(freq).forEach(k=>{ if(freq[k]>bc){ bc=freq[k]; best=+k; } });
  return best>=4 ? SIN_PCT_SPECS.odonto : SIN_PCT_SPECS.saude;
}
// Parser por operadora (Bradesco Odonto/Saúde): % como âncora; robusto a OCR bagunçado.
function sinParseDirectPct(text, hint){
  const spec=sinPctSpec(text, hint);
  const before=spec.before, after=spec.after, nB=before.length;
  const cols=['Data de Competência', ...before, spec.pct, ...after];
  const fmt=(c,v)=>{ if(!v) return ''; return /vidas/i.test(c)? String(v).replace(/[.,]/g,'') : sinFixMoney(v); };
  const raw=String(text||'').split('\n').map(l=>l.trim()).filter(Boolean);
  const rows=[];
  raw.forEach(line=>{
    const toks=(line.match(/R\$|[\d][\d.,]*%?/g)||[]).filter(t=>t!=='R$'&&t!=='R'&&/\d/.test(t));
    if(toks.length < nB+1) return;
    // pula a data inicial (mês ≤2 díg + ano) — exceto na linha de Total
    const isTotal=/\b(total|meses)\b/i.test(line);
    let i0=0;
    while(i0<toks.length){ const d=String(toks[i0]).replace(/\D/g,''); if(d.length<=2 || /^(19|20)\d{2}$/.test(d)){ i0++; } else break; }
    const rest=toks.slice(i0);
    const pctRel=rest.findIndex(t=>/%$/.test(t));
    let bef = (pctRel>=1) ? rest.slice(0,pctRel) : rest.slice(0,nB);
    if(bef.length>nB) bef=bef.slice(bef.length-nB);   // ancora à direita (valores junto ao %)
    if(bef.length<1) return;
    const sin = (pctRel>=0) ? sinFixPct(rest[pctRel]) : '';
    const aft = (pctRel>=0) ? rest.slice(pctRel+1) : rest.slice(nB);
    // mês
    let mes=''; const md=String(toks[0]).replace(/\D/g,''), yd=String(toks[1]||'').replace(/\D/g,'');
    if(isTotal) mes='Total';
    else if(/^(0?[1-9]|1[0-2])$/.test(md) && /^(19|20)?\d{2}$/.test(yd)){ let y=yd.length>=4?yd.slice(2):yd; mes=SIN_MES_ABR[parseInt(md,10)-1]+'/'+y; }
    const row=[mes];
    before.forEach((c,idx)=> row.push(fmt(c, bef[idx])));
    row.push(sin);
    after.forEach((c,idx)=> row.push(fmt(c, aft[idx])));
    rows.push(row);
  });
  return {cols, rows};
}
// Garante 12 competências + Total: encaixa as linhas lidas no mês certo e cria as que faltam (em branco)
function sinPad12(parsed, mesIni){
  const totalRow = parsed.rows.find(r=>/total/i.test(r[0]));
  const monthRows = parsed.rows.filter(r=>r!==totalRow);
  let start = mesIni ? sinParseCompetencia(mesIni) : null;
  if(!start){ for(const r of monthRows){ const mm=sinParseMesAny(r[0]); if(mm){ start=mm; break; } } }
  if(!start) return parsed;                       // sem referência de mês, não mexe
  const ncols=parsed.cols.length, out=[], used=new Set();
  for(let i=0;i<12;i++){
    let m=start.m+i, y=start.y; while(m>11){ m-=12; y++; }
    const lbl=sinAbrevMes(SIN_MESES[m]+'/'+y);
    const fi=monthRows.findIndex((r,idx)=>{ if(used.has(idx)) return false; const mm=sinParseMesAny(r[0]); return mm && mm.m===m; });
    if(fi>=0){ used.add(fi); monthRows[fi][0]=lbl; out.push(monthRows[fi]); }
    else { const blank=Array(ncols).fill(''); blank[0]=lbl; out.push(blank); }
  }
  // sobras (ex.: linha de total sem o rótulo "TOTAL" lido) → preserva; rotula como Total se não tiver mês
  monthRows.forEach((r,idx)=>{ if(!used.has(idx)){ if(!totalRow && !sinParseMesAny(r[0])) r[0]='Total'; out.push(r); } });
  if(totalRow) out.push(totalRow);
  return {cols:parsed.cols, rows:out};
}
// Heurística: detecta orientação (meses nas linhas vs. transposta) e
// devolve {cols, rows}. cols=null usa as colunas atuais da grade.
// force: 'transposto' | 'padrao' | undefined (auto)
// metrics: lista [{re, col}] da operadora (transposta) — opcional
// direct:  lista [{col, idx}] da operadora (meses nas linhas) — posição do valor em cada linha
function sinParseOcr(text, force, metrics, direct){
  const raw=String(text||'').split('\n').map(l=>l.trim()).filter(Boolean);
  // 1) procura uma linha de cabeçalho com vários meses (sinal de tabela transposta)
  const monthTokRe=new RegExp('(?:'+SIN_MONTH_SRC+')[a-zç]*\\s*/?\\s*\\d{2,4}|\\b(?:0?[1-9]|1[0-2])\\s*/\\s*\\d{2,4}\\b','ig');
  let headerMonths=null, headerLine='';
  raw.forEach(l=>{ const toks=l.match(monthTokRe); if(toks && toks.length>=3 && (!headerMonths || toks.length>headerMonths.length)){ headerMonths=toks; headerLine=l; } });
  // 2) parse genérico de todas as linhas (guarda o texto cru p/ excluir cabeçalhos)
  const monthCount=l=>(String(l).match(new RegExp(monthTokRe.source,'ig'))||[]).length;
  const parsed=raw.map(l=>({...sinOcrSplit(l), raw:l})).filter(p=>p.vals.length>=2 || /total/i.test(p.label));
  const monthRows=parsed.filter(p=>sinIsMonth(p.label)).length;
  // indicadores = linhas com vários valores, sem ser mês, total, ou cabeçalho de meses.
  // Quando a operadora força "transposto", afrouxa o limiar (3 valores) p/ aproveitar leituras ruins.
  const minVals = (force==='transposto') ? 3 : 6;
  const metricLines=parsed.filter(p=> p.vals.length>=minVals && !sinIsMonth(p.label) && !/^total/i.test(p.label) && /[a-zà-ÿ]/i.test(p.label) && monthCount(p.raw)<3);
  // comprimento mais comum entre as linhas de indicador = nº de meses
  const lens={}; metricLines.forEach(p=>{ lens[p.vals.length]=(lens[p.vals.length]||0)+1; });
  let nMonths=0, best=0; Object.keys(lens).forEach(k=>{ if(lens[k]>best){ best=lens[k]; nMonths=+k; } });
  let transposed;
  if(force==='transposto') transposed = metricLines.length>=1 || (metrics && metrics.length>0);
  else if(force==='padrao') transposed = false;
  else transposed = metricLines.length>=2 && nMonths>=8 && metricLines.length>monthRows;
  // 3a) TRANSPOSTA → pivota: indicadores viram colunas, posições viram linhas (meses)
  if(transposed){
    // Define as linhas de indicador: pela whitelist da operadora (extrai só as certas,
    // na ordem definida, ignorando título/período/lixo) OU pela detecção genérica.
    let mlines;
    if(metrics && metrics.length){
      mlines = metrics.map(mt=>{
        const p = parsed.find(pp => !sinIsMonth(pp.label) && monthCount(pp.raw)<3 && pp.vals.length>=3 && mt.re.test(sinNorm(pp.label)));
        return { col: mt.col, vals: p ? p.vals : [] };
      });
      // Se a regra da operadora não casou com nada, devolve vazio (mensagem clara) — sem jogar lixo do título.
      if(!mlines.some(c=>c.vals.length)) return {cols:null, rows:[]};
    } else {
      mlines = metricLines.map(p => ({ col: sinMapCol(p.label), vals: p.vals }));
    }
    // nº de meses = comprimento mais comum entre as linhas escolhidas
    const lens2={}; mlines.forEach(c=>{ if(c.vals.length) lens2[c.vals.length]=(lens2[c.vals.length]||0)+1; });
    let nM=0,b2=0; Object.keys(lens2).forEach(k=>{ if(lens2[k]>b2){ b2=lens2[k]; nM=+k; } });
    if(!nM) nM = headerMonths ? headerMonths.length : 0;
    const cols=['Data de Competência', ...mlines.map(c=>c.col)];
    const rows=[];
    // GERA os meses em sequência a partir do primeiro lido (robusto a meses que o OCR perdeu).
    const start = (headerMonths && headerMonths[0]) ? sinParseMesAny(headerMonths[0].replace(/\s+/g,'')) : null;
    const hasTotal = /\btotal\b/i.test(headerLine);        // cabeçalho tem coluna Total?
    const realN = hasTotal ? Math.max(1,nM-1) : nM;
    for(let i=0;i<nM;i++){
      let mlbl='';
      if(i>=realN){ mlbl='Total'; }
      else if(start){ let m=start.m+i, y=start.y; while(m>11){ m-=12; y++; } mlbl=sinAbrevMes(SIN_MESES[m]+'/'+y); }
      else if(headerMonths && headerMonths[i]){ mlbl=sinAbrevMes(headerMonths[i].replace(/\s+/g,'')); }
      rows.push([mlbl, ...mlines.map(c=>c.vals[i]||'')]);
    }
    return {cols, rows};
  }
  // 3a-bis) AUTO: tabela "meses nas linhas" larga com % no meio (ex.: Bradesco) → parser ancorado no %
  if(force!=='transposto' && !(metrics && metrics.length)){
    const linhasPct = raw.filter(l=>{
      const tk=(l.match(/R\$|[\d][\d.,]*%?/g)||[]).filter(t=>/\d/.test(t)&&t!=='R$'&&t!=='R');
      const p=tk.findIndex(t=>/%$/.test(t));
      return p>=4 && tk.length>=p+1;     // % depois de ≥4 valores = formato Bradesco-like
    }).length;
    if(linhasPct>=3) return sinParseDirectPct(text);
  }
  // 3b) PADRÃO (meses nas linhas)
  // Reconhece mês por nome (Abril/2025) OU numérico (04/2025 → abr/25).
  const reMonth=new RegExp('((?:'+SIN_MONTH_SRC+')[a-zç]*\\s*/?\\s*\\d{2,4}|\\b(?:0?[1-9]|1[0-2])\\s*/\\s*\\d{2,4})','i');
  const reTotal=/\btotal\b/i;
  const out=[];
  raw.forEach(line=>{
    const probe=line.match(new RegExp(SIN_VAL_SRC,'g'))||[];
    if(probe.length<3 && !reTotal.test(line)) return;
    let label='', isData=false;
    const mm=line.match(reMonth);
    if(mm){ const tok=mm[1].replace(/\s+/g,''); label = /^\d/.test(tok) ? sinAbrevMes(tok) : tok; isData=true; }
    else if(reTotal.test(line)){ label='Total'; isData=true; }
    let rest=line; if(mm) rest=line.replace(mm[1],' ');
    const vals=rest.match(new RegExp(SIN_VAL_SRC,'g'))||[];
    if(direct && direct.length){
      if(!isData) return;                       // só linhas de mês/total
      out.push([label, ...direct.map(d=>vals[d.idx]||'')]);
    } else {
      out.push([label, ...vals]);
    }
  });
  if(direct && direct.length){
    return {cols:['Data de Competência', ...direct.map(d=>d.col)], rows:out};
  }
  return {cols:null, rows:out};
}
// ─── Grade (colunas editáveis + linhas) ──────────────────────
function sinInput(val, cls){ const i=document.createElement('input'); i.value=(val==null?'':val); if(cls) i.className=cls; return i; }
function sinDelBtn(sel){ return `<button class="btn btn-danger btn-sm" type="button" onclick="this.closest('${sel}').remove()"><span class="material-symbols-rounded" style="font-size:13px">close</span></button>`; }
function sinBuildGrid(cols, rows){
  const head=document.getElementById('sin-grid-head'); const body=document.getElementById('sin-grid-body');
  head.innerHTML=''; body.innerHTML='';
  const mkBtn=(txt,title,color,handler)=>{ const b=document.createElement('button'); b.type='button'; b.textContent=txt; b.title=title; b.style.cssText='border:1px solid var(--border);background:#fff;color:'+color+';cursor:pointer;border-radius:4px;width:20px;height:22px;flex:0 0 auto;font-size:12px;line-height:1;padding:0'; b.onclick=function(){ handler(this); }; return b; };
  cols.forEach(c=>{
    const th=document.createElement('th');
    const wrap=document.createElement('div'); wrap.style.cssText='display:flex;align-items:center;gap:3px';
    wrap.appendChild(mkBtn('◀','Mover coluna pra esquerda','#26305a',b=>sinMoveCol(b,-1)));
    const ci=sinInput(c,'col-h'); ci.style.textAlign='center'; ci.style.fontWeight='600'; wrap.appendChild(ci);
    wrap.appendChild(mkBtn('▶','Mover coluna pra direita','#26305a',b=>sinMoveCol(b,1)));
    wrap.appendChild(mkBtn('×','Remover esta coluna','#c0392b',b=>sinDelCol(b)));
    th.appendChild(wrap); head.appendChild(th);
  });
  const thx=document.createElement('th'); thx.style.width='30px'; head.appendChild(thx);
  rows.forEach(r=> body.appendChild(sinRowTr(r, cols.length)) );
}
// Remove uma coluna inteira (cabeçalho + a célula correspondente em todas as linhas)
function sinDelCol(btn){
  const th=btn.closest('th'); const head=document.getElementById('sin-grid-head');
  const idx=[...head.children].indexOf(th); if(idx<0) return;
  th.remove();
  [...document.querySelectorAll('#sin-grid-body tr')].forEach(tr=>{ const td=tr.children[idx]; if(td) td.remove(); });
}
// Move uma coluna pra esquerda (dir=-1) ou direita (dir=1)
function sinMoveCol(btn,dir){
  const th=btn.closest('th'); const head=document.getElementById('sin-grid-head');
  const ths=[...head.children]; const idx=ths.indexOf(th); if(idx<0) return;
  const maxIdx=ths.length-2;  // última coluna real (a última th é espaçador)
  const tgt=idx+dir; if(tgt<0||tgt>maxIdx) return;
  head.insertBefore(th, dir<0 ? ths[tgt] : ths[tgt].nextSibling);
  [...document.querySelectorAll('#sin-grid-body tr')].forEach(tr=>{
    const tds=[...tr.children]; const td=tds[idx]; if(!td) return;
    tr.insertBefore(td, dir<0 ? tds[tgt] : (tds[tgt].nextSibling||null));
  });
}
// Converte texto BR (1.366.199,08 / R$ 7.262,18 / 69,32%) em número
function sinBR(s){ s=String(s||'').replace(/[R$%\s]/g,''); if(!s) return null; const n=parseFloat(s.replace(/\./g,'').replace(',','.')); return isNaN(n)?null:n; }
function sinFmtBR(n){ return n.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
// Calcula a linha Total a partir dos meses (soma valores; recalcula % e per capita)
function sinCalcTotal(){
  const cols=[...document.querySelectorAll('#sin-grid-head .col-h')].map(i=>i.value.trim());
  const n=cols.length; if(n<2) return;
  const cn=i=>sinNorm(cols[i]||'');
  const trs=[...document.querySelectorAll('#sin-grid-body tr')];
  const dataTrs=trs.filter(tr=>{ const f=tr.querySelector('td input'); return f && !/total/i.test(f.value); });
  if(!dataTrs.length){ toast('Sem meses pra somar.'); return; }
  const vidasIdx=cols.findIndex((c,i)=>/vidas|qtde|benefic/.test(cn(i)));
  const recIdx=cols.findIndex((c,i)=>/(faturamento|receita|premio|mensalidade)/.test(cn(i)) && !/capita|pc$/.test(cn(i)));
  const cusIdx=cols.findIndex((c,i)=>/(evento|custo|sinistro|despesa)/.test(cn(i)) && !/capita|pc$|sinistralidade/.test(cn(i)));
  const sums=Array(n).fill(0), has=Array(n).fill(false);
  dataTrs.forEach(tr=>{ const inp=[...tr.querySelectorAll('td input')]; for(let i=1;i<n;i++){ const v=sinBR(inp[i]?inp[i].value:''); if(v!=null){ sums[i]+=v; has[i]=true; } } });
  const recSum=recIdx>=0?sums[recIdx]:null, cusSum=cusIdx>=0?sums[cusIdx]:null, vidSum=vidasIdx>=0?sums[vidasIdx]:null;
  const total=cols.map((c,i)=>{
    if(i===0) return 'Total';
    const k=cn(i);
    if(/sinistral|eventos|^iu$/.test(k) || /%$/.test(String(c))){ return (recSum&&cusSum)?(cusSum/recSum*100).toFixed(2).replace('.',',')+'%':''; }
    if(/capita|pc$/.test(k)){ if(vidSum){ const base=/(fat|receita|premio)/.test(k)?recSum:cusSum; if(base!=null) return sinFmtBR(base/vidSum); } return ''; }
    if(i===vidasIdx) return has[i]?String(Math.round(sums[i])):'';
    return has[i]?sinFmtBR(sums[i]):'';
  });
  const existing=trs.find(tr=>{ const f=tr.querySelector('td input'); return f && /total/i.test(f.value); });
  if(existing){ const inp=[...existing.querySelectorAll('td input')]; total.forEach((v,i)=>{ if(inp[i]) inp[i].value=v; }); }
  else { document.getElementById('sin-grid-body').appendChild(sinRowTr(total, n)); }
  toast('Total calculado a partir dos meses — confira.');
}
function sinRowTr(values, ncols){
  const tr=document.createElement('tr');
  for(let i=0;i<ncols;i++){ const td=document.createElement('td'); td.appendChild(sinInput((values&&values[i])||'')); tr.appendChild(td); }
  const tdx=document.createElement('td'); tdx.style.textAlign='center'; tdx.innerHTML=sinDelBtn('tr'); tr.appendChild(tdx);
  return tr;
}
function sinAddRow(){
  const ncols=document.querySelectorAll('#sin-grid-head .col-h').length || SIN_DEF_COLS.length;
  document.getElementById('sin-grid-body').appendChild(sinRowTr([], ncols));
}
function sinAddCol(){
  const head=document.getElementById('sin-grid-head');
  const th=document.createElement('th'); th.appendChild(sinInput('Coluna','col-h'));
  head.insertBefore(th, head.lastElementChild);
  [...document.querySelectorAll('#sin-grid-body tr')].forEach(tr=>{
    const td=document.createElement('td'); td.appendChild(sinInput(''));
    tr.insertBefore(td, tr.lastElementChild);
  });
}
// Preencher a coluna de competência em sequência (Abril/2025, Maio/2025, ...)
const SIN_MESES=['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
function sinParseCompetencia(s){
  s=String(s||'').trim();
  const mm=s.match(/^([A-Za-zçãéíóúâêôÁ-úÀ-ÿ]+)\s*[\/\- ]?\s*(\d{2,4})$/);
  if(!mm) return null;
  const norm=x=>x.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'');
  const nome=norm(mm[1]).slice(0,3);
  const idx=SIN_MESES.findIndex(x=>norm(x).startsWith(nome));
  if(idx<0) return null;
  let y=mm[2]; if(y.length===2) y='20'+y;
  return {m:idx, y:parseInt(y,10)};
}
function sinPreencherMeses(){
  const el=document.getElementById('sin-mes-ini');
  let start = (el && el.value.trim()) ? el.value.trim()
            : prompt('A partir de qual competência preencher? (ex.: Abril/2025)','Abril/2025');
  if(!start){ toast('Digite o mês inicial (ex.: Abril/2025) no campo ao lado do botão.'); return; }
  const p=sinParseCompetencia(start);
  if(!p){ toast('Formato inválido. Use Mês/Ano — ex.: Abril/2025'); return; }
  let m=p.m, y=p.y;
  [...document.querySelectorAll('#sin-grid-body tr')].forEach(tr=>{
    const first=tr.querySelector('td input'); if(!first) return;
    if(/total/i.test(first.value)) return; // não mexe na linha Total
    first.value = sinAbrevMes(SIN_MESES[m] + '/' + y); // abreviado: abr/26
    m++; if(m>11){ m=0; y++; }
  });
  toast('Meses preenchidos — confira.');
}
// Abrevia os meses já preenchidos na grade (Abril/2026 → abr/26)
function sinAbreviarMeses(){
  [...document.querySelectorAll('#sin-grid-body tr')].forEach(tr=>{
    const first=tr.querySelector('td input'); if(!first) return;
    if(/total/i.test(first.value)) return;
    if(first.value.trim()) first.value=sinAbrevMes(first.value);
  });
  toast('Meses abreviados.');
}
function sinColetaTab(){
  const cols=[...document.querySelectorAll('#sin-grid-head .col-h')].map(i=>i.value.trim());
  const rows=[...document.querySelectorAll('#sin-grid-body tr')].map(tr=>[...tr.querySelectorAll('td input')].map(i=>i.value.trim()))
              .filter(r=>r.some(v=>v));
  return {cols, rows};
}
// ─── Excel modelo / import ───────────────────────────────────
function sinNorm(s){ return String(s||'').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').replace(/\s+/g,' '); }
function sinTemplateXlsx(){
  if (typeof XLSX==='undefined') return toast('Biblioteca de planilhas não carregou.');
  const {cols,rows}=sinColetaTab();
  // Grade vazia + operadora escolhida → modelo no formato da operadora (com meses)
  if(!rows.length && SIN_OPERADORA && SIN_OPERADORA!=='auto'){
    const mesIni=(document.getElementById('sin-mes-ini')||{}).value||'';
    return sinModeloOperadora(SIN_OPERADORA, mesIni);
  }
  const ws=XLSX.utils.aoa_to_sheet([cols.length?cols:SIN_DEF_COLS, ...(rows.length?rows:SIN_DEF_ROWS)]);
  const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Sinistralidade');
  XLSX.writeFile(wb,'modelo_sinistralidade.xlsx');
}
// Colunas esperadas de uma operadora (a partir da sua whitelist), sem repetir
function sinColsOperadora(opKey){
  const op=SIN_OPERADORAS[opKey]||{};
  if(op.cols && op.cols.length) return op.cols.slice();
  const lista = (op.metrics && op.metrics.length) ? op.metrics : (op.direct && op.direct.length ? op.direct : null);
  if(lista){
    const cols=['Data de Competência']; lista.forEach(m=>{ if(cols.indexOf(m.col)<0) cols.push(m.col); });
    return cols;
  }
  return SIN_DEF_COLS.slice();
}
// Monta a grade na tela já no formato da operadora (colunas certas + 12 meses + Total) pra digitar
function sinPrepararGrade(opKey, mesIni){
  const cols=sinColsOperadora(opKey);
  const p=mesIni?sinParseCompetencia(mesIni):null;
  const rows=[];
  for(let i=0;i<12;i++){
    const r=cols.map(()=> '');
    if(p){ let m=p.m+i, y=p.y; while(m>11){ m-=12; y++; } r[0]=sinAbrevMes(SIN_MESES[m]+'/'+y); }
    rows.push(r);
  }
  rows.push(cols.map((c,i)=> i===0?'Total':''));
  sinBuildGrid(cols, rows);
}
// Gera e baixa um modelo Excel já no formato da operadora (12 meses + Total) pra preencher
function sinModeloOperadora(opKey, mesIni){
  if (typeof XLSX==='undefined') return toast('Biblioteca de planilhas não carregou.');
  const cols=sinColsOperadora(opKey);
  // tenta preencher os meses se houver um mês inicial (campo "Mês inicial")
  const p=mesIni?sinParseCompetencia(mesIni):null;
  const linhas=[];
  for(let i=0;i<12;i++){
    const row=cols.map(()=> '');
    if(p){ let m=p.m+i, y=p.y; while(m>11){ m-=12; y++; } row[0]=sinAbrevMes(SIN_MESES[m]+'/'+y); }
    linhas.push(row);
  }
  const totalRow=cols.map((c,i)=> i===0?'Total':''); linhas.push(totalRow);
  const ws=XLSX.utils.aoa_to_sheet([cols, ...linhas]);
  const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Sinistralidade');
  const nome=(opKey&&opKey!=='auto')?opKey:'sinistralidade';
  XLSX.writeFile(wb,'modelo_'+nome+'.xlsx');
}
function sinImportXlsx(input){
  const file=input.files&&input.files[0]; if(!file) return;
  if (typeof XLSX==='undefined'){ toast('Biblioteca de planilhas não carregou.'); input.value=''; return; }
  const reader=new FileReader();
  reader.onload=e=>{
    try{
      const wb=XLSX.read(new Uint8Array(e.target.result),{type:'array'});
      const ws=wb.Sheets[wb.SheetNames[0]];
      const aoa=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:false});
      if(!aoa.length){ toast('Planilha vazia.'); input.value=''; return; }
      // Passa a planilha pelo mesmo motor da imagem: pivota se transposta e aplica a regra da operadora.
      const _op=SIN_OPERADORAS[SIN_OPERADORA]||{orient:'auto'};
      const _force=(_op.orient==='auto')?undefined:_op.orient;
      const txt=aoa.map(r=>(r||[]).map(c=>String(c==null?'':c).trim()).join(' ')).join('\n');
      let parsed = (_op.parser==='pct') ? sinParseDirectPct(txt, _op.spec)
                                        : sinParseOcr(txt, _force, _op.metrics, _op.direct);
      if(_op.parser==='pct' && parsed.rows.length){
        parsed = sinPad12(parsed, (document.getElementById('sin-mes-ini')||{}).value||'');
      }
      if(parsed.cols && parsed.rows.length){
        sinBuildGrid(parsed.cols, parsed.rows);
        toast(parsed.rows.length+' linha(s) importada(s) — formato ajustado pela operadora. Revise.');
      } else {
        // formato direto (meses nas linhas): usa a planilha como está
        const cols=(aoa[0]||[]).map(c=>String(c||'').trim());
        const rows=aoa.slice(1).map(r=>cols.map((_,i)=>String((r&&r[i]!=null)?r[i]:'').trim())).filter(r=>r.some(v=>v));
        sinBuildGrid(cols, rows);
        toast(rows.length+' linha(s) importada(s).');
      }
    }catch(err){ console.error(err); toast('Erro ao ler planilha: '+err.message); }
    finally{ input.value=''; }
  };
  reader.readAsArrayBuffer(file);
}
// ─── Estado ──────────────────────────────────────────────────
function sinEstado(){
  const v=id=>{ const el=document.getElementById(id); return el?el.value.trim():''; };
  return {
    variacao:SIN_VAR, withBi:SIN_BI,
    empresa:v('sin-empresa'), competencia:v('sin-competencia'),
    analise:v('sin-analise'), sinMes:v('sin-mes'), acum:v('sin-acum'), brk:v('sin-break'),
    tab:sinColetaTab(),
    transpor:(document.getElementById('sin-transpor')||{checked:true}).checked,
    infoTitulo:v('sin-info-titulo'), infoTexto:v('sin-info-texto'), infoImg:SIN_INFOIMG,
    bi:{ high:v('sin-bi-high'), consultas:v('sin-bi-consultas'), exames:v('sin-bi-exames'),
         internacoes:v('sin-bi-internacoes'), terapias:v('sin-bi-terapias'), prestadores:v('sin-bi-prestadores'),
         produtos:v('sin-bi-produtos'), ranking:v('sin-bi-ranking'), demografico:v('sin-bi-demografico') },
    weebi:{ link:v('sin-em-link'), login:v('sin-em-login'), senha:v('sin-em-senha') }
  };
}
function sinPct(s){ const n=parseFloat(String(s||'').replace('%','').replace(/\./g,'').replace(',','.')); return isNaN(n)?null:n; }
// Separa o texto do informativo em: intro + itens (✓) + frase de fechamento
function sinFmtInfo(texto){
  const parts=String(texto||'').split('✓');
  const intro=(parts[0]||'').trim();
  let items=parts.slice(1).map(s=>s.trim()).filter(Boolean);
  let closing='';
  if(items.length){
    const last=items[items.length-1];
    const m=last.match(/^(.*?[a-zà-ú0-9])\.\s+([A-ZÀ-Ú].*)$/);
    if(m){ items[items.length-1]=m[1]; closing=m[2]; }
  }
  return {intro, items, closing};
}
// Abrevia "Abril/2026" → "abr/26" (deixa "Total" e textos sem mês como estão)
const SIN_MES_ABR=['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez'];
function sinAbrevMes(s){
  s=String(s||'').trim();
  // formato numérico: 03/25, 3/2025, 03-2025 → mar/25
  const num=s.match(/^(0?[1-9]|1[0-2])\s*[\/\-]\s*(\d{2,4})$/);
  if(num){ let y=num[2]; if(y.length===4) y=y.slice(2); return SIN_MES_ABR[parseInt(num[1],10)-1]+'/'+y; }
  const m=s.match(/^([A-Za-zà-úÀ-Ú]+)\s*[\/\- ]?\s*(\d{2,4})$/);
  if(!m) return s;
  const ab={janeiro:'jan',fevereiro:'fev',marco:'mar',abril:'abr',maio:'mai',junho:'jun',julho:'jul',agosto:'ago',setembro:'set',outubro:'out',novembro:'nov',dezembro:'dez'};
  const key=m[1].toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').slice(0,3);
  let abr=null; for(const k in ab){ if(k.startsWith(key)){ abr=ab[k]; break; } }
  if(!abr) abr=m[1].slice(0,3).toLowerCase();
  let y=m[2]; if(y.length===4) y=y.slice(2);
  return abr+'/'+y;
}
// Marca/desmarca o vermelho manualmente (duplo-clique numa célula de Sinistralidade)
function sinToggleRed(td){
  const on = td.getAttribute('data-red')==='1';
  if(on){ td.setAttribute('data-red','0'); td.style.background=td.getAttribute('data-bg')||''; td.style.color=''; td.style.fontWeight='600'; }
  else { td.setAttribute('data-red','1'); td.style.background='#e74c3c'; td.style.color='#fff'; td.style.fontWeight='700'; }
}
// ─── Render do preview ───────────────────────────────────────
let SIN_STATE=null;
function sinGerar(){
  const st=sinEstado(); SIN_STATE=st;
  if(!st.empresa){ if(!confirm('Empresa em branco. Gerar mesmo assim?')) return; }
  document.getElementById('sin-render').innerHTML=sinHTML(st);
  document.getElementById('sin-form').style.display='none';
  document.getElementById('sin-preview').style.display='block';
  window.scrollTo(0,0);
}
function sinVoltar(){ document.getElementById('sin-preview').style.display='none'; document.getElementById('sin-form').style.display='block'; }
function sinTema(v){
  if(v==='atencao') return {
    band:'#0a8a8d', bandFg:'#ffffff', accent:'#ffffff',
    titulo:'ATENÇÃO', icon:'warning',
    cardBorder:'#c0392b', sepColor:'rgba(255,255,255,.35)', edgeColor:'#0a8a8d'
  };
  return {
    band:'#0a8a8d', bandFg:'#ffffff', accent:'#ffffff',
    titulo:'PARABÉNS', icon:'check_circle',
    cardBorder:'#0a8a8d', sepColor:'rgba(255,255,255,.35)', edgeColor:'#0a8a8d'
  };
}
function sinHTML(st){
  const t=sinTema(st.variacao);
  const isAtencao = st.variacao==='atencao';
  const cols=st.tab.cols, rows=st.tab.rows;
  const brk=sinPct(st.brk);
  const limite=(brk!=null)?brk:Infinity; // breakeven vazio → nenhum vermelho automático
  // Transposto: meses nas colunas, métricas nas linhas (igual ao informativo)
  const monthIdx=0;
  const monthLabels=rows.map(r=>r[monthIdx]||'');
  const metricIdxs=cols.map((_,i)=>i).filter(i=>i!==monthIdx);
  const isSinMetric=i=>sinNorm(cols[i]||'').includes('sinistral');
  // cor do indicador: vermelho se acima do breakeven, senão teal
  const stCor=val=>{ const p=sinPct(val); return (p!=null&&brk!=null&&p>brk)?'#c0392b':'#0a8a8d'; };
  // Card de estatística com borda colorida + ícone interno + número grande em vermelho
  const stat=(num,lbl,iconName)=>{
    const _p = sinPct(num);
    const _alerta = (_p!=null && brk!=null && _p > brk);
    const _corNum = _alerta ? '#c0392b' : '#555';
    return `<div style="flex:1;border:1.5px solid ${t.cardBorder};border-radius:16px;padding:28px 22px;display:flex;flex-direction:column;align-items:center;gap:10px;background:#fff;min-height:175px;justify-content:center">
      <span class="material-symbols-rounded" style="font-size:38px;color:#3a3a3a;font-variation-settings:'FILL' 0, 'wght' 400">${iconName}</span>
      <div style="font-size:44px;font-weight:800;color:${_corNum};line-height:1;letter-spacing:-1px">${esc(num||'—')}</div>
      <div style="font-size:16.5px;color:#555;font-weight:600;text-align:center;line-height:1.35">${lbl}</div>
    </div>`;
  };
  // Ícone Material direto (warning vermelho preenchido / check_circle branco preenchido)
  const iconBlock = isAtencao
    ? `<span class="material-symbols-rounded" style="font-size:60px;color:#c0392b;font-variation-settings:'FILL' 1">warning</span>`
    : `<span class="material-symbols-rounded" style="font-size:60px;color:#fff;font-variation-settings:'FILL' 1">check_circle</span>`;
  let thead, tbody;
  const HBG='#eceff3', HFG='#26305a', BORDER='#dce1e7', ALT='#f4f6f8';
  if(st.transpor!==false){
    // Meses nas COLUNAS, métricas nas linhas (transposto)
    thead='<th contenteditable="true" style="padding:9px 12px;background:'+HBG+';color:'+HFG+';font-size:15.5px;border:1px solid '+BORDER+';text-align:center;font-weight:700;letter-spacing:.2px">'+esc(cols[monthIdx]||'')+'</th>'
      + monthLabels.map(m=>`<th style="padding:9px 6px;background:${HBG};color:${HFG};font-size:14.5px;border:1px solid ${BORDER};text-align:center;font-weight:700">${esc(sinAbrevMes(m))}</th>`).join('');
    tbody=metricIdxs.map((i,idx)=>{
      const sinRow=isSinMetric(i);
      const bg = (idx%2) ? ALT : '#ffffff';
      const lbl=`<td contenteditable="true" style="padding:9px 10px;text-align:center;border:1px solid ${BORDER};font-weight:700;font-size:14.5px;background:${bg};color:#26305a">${esc(cols[i]||'')}</td>`;
      const cells=rows.map(r=>{
        let style='padding:9px 6px;border:1px solid '+BORDER+';font-size:14px;background:'+bg+';text-align:center';
        let extra='';
        if(sinRow){ const p=sinPct(r[i]); const autoRed=(p!=null && p>=limite); if(autoRed){ style+=';background:#e74c3c;color:#fff;font-weight:700'; } else { style+=';font-weight:600'; } extra=` data-bg="${bg}" data-red="${autoRed?1:0}" ondblclick="sinToggleRed(this)" title="Duplo-clique para marcar/desmarcar vermelho"`; }
        return `<td contenteditable="true"${extra} style="${style}">${esc(r[i]||'')}</td>`;
      }).join('');
      return `<tr>${lbl}${cells}</tr>`;
    }).join('');
  } else {
    // Meses nas LINHAS, métricas nas colunas (direto, igual à grade)
    const sinColIdx=cols.findIndex(c=>sinNorm(c||'').includes('sinistral'));
    thead=cols.map((c,ci)=>`<th contenteditable="true" style="padding:10px 12px;background:${HBG};color:${HFG};font-size:16px;border:1px solid ${BORDER};text-align:center;font-weight:700;letter-spacing:.2px">${esc(c)}</th>`).join('');
    tbody=rows.map((r,idx)=>{
      const isTotal=sinNorm(r[0]||'')==='total';
      const bg = isTotal ? HBG : ((idx%2) ? ALT : '#ffffff');
      const cells=r.map((v,ci)=>{
        let style='padding:9px 10px;border:1px solid '+BORDER+';font-size:14px;background:'+bg+';text-align:center';
        let extra='';
        if(ci===0) style+=';font-weight:700;color:#26305a';
        else if(isTotal) style+=';font-weight:700';
        if(ci===sinColIdx){ style+=';text-align:center'; const p=sinPct(v); const autoRed=(p!=null && p>=limite); if(autoRed){ style+=';background:#e74c3c;color:#fff;font-weight:700'; } extra=` data-bg="${bg}" data-red="${autoRed?1:0}" ondblclick="sinToggleRed(this)" title="Duplo-clique para marcar/desmarcar vermelho"`; }
        return `<td contenteditable="true"${extra} style="${style}">${esc(v||'')}</td>`;
      }).join('');
      return `<tr>${cells}</tr>`;
    }).join('');
  }
  let biHTML='';
  if(st.withBi){
    const b=st.bi;
    const ICN={'High Users':'groups','Consultas':'stethoscope','Exames':'biotech','Internações':'king_bed','Terapias':'healing','Prestadores':'local_hospital','Utilização por produto':'category','Ranking de utilização':'leaderboard','Perfil demográfico':'diversity_3'};
    const blk=(t2,v2)=> v2?`<div style="display:flex;gap:14px;align-items:flex-start;background:#f4f6f8;border-radius:12px;padding:15px 18px;margin-bottom:12px">
        <div style="flex:0 0 44px;width:44px;height:44px;border-radius:50%;background:#fff;border:2px solid #0a8a8d;display:flex;align-items:center;justify-content:center">
          <span class="material-symbols-rounded" style="font-size:23px;color:#0a8a8d">${ICN[t2]||'insights'}</span>
        </div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:800;color:#26305a;font-size:20px;margin-bottom:7px">${t2}</div>
          <div class="sin-ed" contenteditable="true" style="font-size:17px;color:#333;line-height:1.65">${esc(v2)}</div>
        </div>
      </div>`:'';
    biHTML=`<div style="padding:20px 18px;border-top:3px solid #0a8a8d">
      <div style="font-size:25px;font-weight:800;color:#26305a;margin-bottom:18px">Análise de uso e tendências</div>
      ${blk('High Users',b.high)}${blk('Consultas',b.consultas)}${blk('Exames',b.exames)}
      ${blk('Internações',b.internacoes)}${blk('Terapias',b.terapias)}${blk('Prestadores',b.prestadores)}
      ${blk('Utilização por produto',b.produtos)}${blk('Ranking de utilização',b.ranking)}${blk('Perfil demográfico',b.demografico)}
    </div>`;
  }
  // Bloco WeeBi (acesso ao BI) — aparece com ou sem análise de BI, se houver link/login/senha
  const w = st.weebi || {};
  const weebiHTML = (w.link || w.login || w.senha) ? `<div style="margin:16px 18px 0;background:#0a8a8d;border-radius:12px;padding:20px 24px;color:#fff">
      <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
        <span class="material-symbols-rounded" style="font-size:26px">insights</span>
        <div style="font-weight:800;font-size:21px;line-height:1.3">Acesse o WeeBi da sua empresa para acompanhar a sinistralidade e outras informações.</div>
      </div>
      ${w.link  ? `<div style="font-size:17px;line-height:1.75;word-break:break-all"><strong>Link:</strong> <a href="${esc(w.link)}" style="color:#d8f5f4">${esc(w.link)}</a></div>` : ''}
      ${w.login ? `<div style="font-size:17px;line-height:1.75"><strong>Login:</strong> ${esc(w.login)}</div>` : ''}
      ${w.senha ? `<div style="font-size:17px;line-height:1.75"><strong>Senha:</strong> ${esc(w.senha)}</div>` : ''}
    </div>` : '';
  let infoHTML='';
  if(st.infoTitulo || st.infoTexto || st.infoImg){
    // Flag "Informativo Maio" (ribbon azul), sem fundo azul; espaço p/ imagem; texto vermelho com fundo
    const flag = st.infoTitulo
      ? `<div style="padding:0 0 4px"><span style="display:inline-block;background:#0a8a8d;color:#fff;font-weight:800;font-size:17px;letter-spacing:.3px;padding:9px 38px 9px 20px;clip-path:polygon(0 0,calc(100% - 16px) 0,100% 50%,calc(100% - 16px) 100%,0 100%)">${esc(st.infoTitulo)}</span></div>`
      : '';
    const imgHtml = st.infoImg
      ? `<div style="padding:6px 0 0"><img src="${st.infoImg}" alt="Informativo" style="width:100%;display:block;border-radius:6px"/></div>`
      : `<div style="padding:6px 0 0"><img src="informativo_saude.png" alt="Informativo" style="width:100%;display:block;border-radius:6px" onerror="this.style.display='none'"/></div>`;
    let txt='';
    if(st.infoTexto){
      const f=sinFmtInfo(st.infoTexto);
      const chips = f.items.length
        ? `<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">${f.items.map(it=>`<span style="display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #d0d0d0;color:#555;border-radius:20px;padding:8px 16px;font-size:16px;font-weight:600"><span class="material-symbols-rounded" style="font-size:19px;color:#0a8a8d">check_circle</span>${esc(it)}</span>`).join('')}</div>`
        : '';
      const closing = f.closing ? `<div style="margin-top:14px;font-weight:800;color:#555;font-size:17.5px">${esc(f.closing)}</div>` : '';
      txt = `<div style="margin:10px 0 16px;background:#f0f0f0;border-radius:8px;padding:18px 20px">
        <div style="font-size:16.5px;line-height:1.65;color:#555">${esc(f.intro)}</div>${chips}${closing}
      </div>`;
    }
    infoHTML = `<div style="min-width:1247px;max-width:1247px;margin:16px auto 0;box-sizing:border-box">${flag}${imgHtml}${txt}</div>`;
  }
  return `
  <div id="sin-doc" style="font-family:Arial,Helvetica,sans-serif;color:#222;max-width:1280px;margin:0 auto;background:#fff">
    <div style="text-align:center;padding:24px 0 14px">
      <img src="logo_cinza.png" alt="INTERWEG" style="height:125px" onerror="this.onerror=null;this.src='logo.png'"/>
      <div style="display:none;font-size:38px;font-weight:800;color:#0a8a8d">INTER<span style="color:#0e2a36">WEG</span> <span style="font-size:20px;font-weight:600;color:#555">Seguros</span></div>
    </div>
    <div style="padding:0 10px;display:flex;justify-content:center">
      <div style="background:${t.band};color:${t.bandFg};padding:22px 26px;display:flex;align-items:center;gap:18px;font-size:17.5px;line-height:1.55;border-left:6px solid ${t.edgeColor};border-radius:6px;min-width:1247px;max-width:100%;margin:0 auto;box-sizing:border-box">
        <div style="flex:0 0 auto;display:flex;align-items:center;justify-content:center;gap:12px;width:160px">
          ${iconBlock}
          <div style="font-family:'Segoe UI',Tahoma,Arial,sans-serif;font-size:21px;font-weight:700;color:${t.accent}">${t.titulo}</div>
        </div>
        <div style="width:1px;align-self:stretch;background:${t.sepColor};min-height:80px"></div>
        <div style="flex:1;min-width:0;text-align:left">
          <span class="sin-ed" contenteditable="true">${esc(st.analise)}</span>
        </div>
      </div>
    </div>
    <div style="padding:18px 10px 20px;display:flex;justify-content:center">
      <div style="display:flex;gap:16px;align-items:stretch;min-width:1247px;max-width:100%;margin:0 auto;box-sizing:border-box;width:100%">
        ${stat(st.sinMes,'Sinistralidade do mês','monitoring')}
        ${stat(st.acum,'Acumulado 12 meses','calendar_month')}
        ${stat(st.brk,'Breakeven Operadora','balance')}
      </div>
    </div>
    <div style="padding:0 10px 14px;display:flex;justify-content:center">
      <table style="border-collapse:collapse;margin:0 auto;min-width:1247px;max-width:100%">
        <thead><tr>${thead}</tr></thead>
        <tbody>${tbody}</tbody>
      </table>
    </div>
    ${biHTML}
    ${weebiHTML}
    ${infoHTML}
    <div style="text-align:center;font-size:12px;color:#999;padding:12px">${esc(st.empresa)} · ${esc(st.competencia)} · Interweg Seguros e Benefícios</div>
  </div>`;
}
// ─── Exportar PDF ────────────────────────────────────────────
async function sinBaixarPDF(){
  try{
    if(!(window.html2canvas)) await sinLoadScript(SIN_H2C);
    if(!(window.jspdf&&window.jspdf.jsPDF)) await sinLoadScript(SIN_JSPDF);
    const node=document.getElementById('sin-doc');
    const canvas=await html2canvas(node,{
      scale: Math.max(3, window.devicePixelRatio || 2),
      backgroundColor:'#ffffff',
      useCORS:true,
      letterRendering:true,
      imageTimeout:0
    });
    const img=canvas.toDataURL('image/jpeg',0.98);
    const {jsPDF}=window.jspdf;
    const pdf=new jsPDF('p','mm','a4');
    const pw=210, ph=297, w=pw, h=canvas.height*pw/canvas.width;
    let pos=0, rest=h;
    pdf.addImage(img,'JPEG',0,pos,w,h); rest-=ph;
    while(rest>0){ pos-=ph; pdf.addPage(); pdf.addImage(img,'JPEG',0,pos,w,h); rest-=ph; }
    pdf.save('informativo_sinistralidade_'+((SIN_STATE&&SIN_STATE.empresa)||'').replace(/\s+/g,'_')+'.pdf');
  }catch(e){ console.error(e); toast('Erro ao gerar PDF: '+e.message); }
}
// ─── Exportar como imagem (PNG) ──────────────────────────────
async function sinBaixarImagem(){
  try{
    if(!(window.html2canvas)) await sinLoadScript(SIN_H2C);
    const node=document.getElementById('sin-doc');
    const canvas=await html2canvas(node,{
      scale: Math.max(3, window.devicePixelRatio || 2),
      backgroundColor:'#ffffff',
      useCORS:true,
      letterRendering:true,
      imageTimeout:0
    });
    const a=document.createElement('a');
    a.href=canvas.toDataURL('image/png');
    a.download='informativo_sinistralidade_'+((SIN_STATE&&SIN_STATE.empresa)||'').replace(/\s+/g,'_')+'.png';
    document.body.appendChild(a); a.click(); a.remove();
  }catch(e){ console.error(e); toast('Erro ao gerar imagem: '+e.message); }
}
// ─── Exportar PPT ────────────────────────────────────────────
async function sinBaixarPPT(){
  try{
    if(!window.PptxGenJS) await sinLoadScript(SIN_PPTX);
    const st=SIN_STATE||sinEstado();
    const t=sinTema(st.variacao);
    const pptx=new PptxGenJS(); pptx.defineLayout({name:'IW',width:10,height:5.63}); pptx.layout='IW';
    const TEAL='0A8A8D', RED='C0392B', NAVY='26305A', accent=(st.variacao==='atencao'?RED:TEAL);
    // Logo (logo_cinza com fallback para logo)
    let logoData = await sinImgToDataURL('logo_cinza.png');
    if(!logoData) logoData = await sinImgToDataURL('logo.png');
    let s=pptx.addSlide(); s.background={color:'FFFFFF'};
    if(logoData){
      // mantém proporção do logo numa altura ~0.55"
      const logoH=0.55, logoW=logoH*3.4;
      s.addImage({data:logoData,x:(10-logoW)/2,y:0.12,w:logoW,h:logoH,sizing:{type:'contain',w:logoW,h:logoH}});
    } else {
      s.addText('INTERWEG  Seguros', {x:0,y:0.15,w:10,align:'center',fontFace:'Arial',bold:true,fontSize:20,color:TEAL});
    }
    // Banda ATENÇÃO/PARABÉNS no PPT
    const bandFill = (st.variacao==='atencao')?'FFFFFF':TEAL;
    const bandFg   = (st.variacao==='atencao')?'444444':'FFFFFF';
    const titleCol = (st.variacao==='atencao')?RED:'FFFFFF';
    s.addText([
      {text:(t.titulo||'')+'  ',options:{bold:true,fontSize:14,color:titleCol}},
      {text:st.analise||'',options:{fontSize:11,color:bandFg}}
    ], {x:0,y:0.78,w:10,h:0.7,align:'center',fill:{color:bandFill},valign:'middle'});
    // Cards de estatística com borda colorida e número grande em vermelho
    const cardBorderCol = (st.variacao==='atencao')?RED:TEAL;
    const stat=(x,num,lbl)=>{
      s.addShape(pptx.ShapeType.rect,{x,y:1.55,w:2.6,h:1.0,fill:{color:'FFFFFF'},line:{color:cardBorderCol,width:1.5}});
      s.addText(num||'—',{x,y:1.65,w:2.6,h:0.5,align:'center',bold:true,fontSize:26,color:RED});
      s.addText(lbl,{x,y:2.18,w:2.6,h:0.3,align:'center',fontSize:10,color:'555555'});
    };
    stat(1.0,st.sinMes,'Sinistralidade do mês');
    stat(3.7,st.acum,'Acumulado 12 meses');
    stat(6.4,st.brk,'Breakeven Operadora');
    const cols=st.tab.cols, rows=st.tab.rows;
    const brkP=sinPct(st.brk);
    const limiteP=(brkP!=null)?brkP:Infinity;
    let head, body;
    const HEADBG='ECEFF3', HEADFG='26305A';
    if(st.transpor!==false){
      const monthIdx=0;
      const monthLabels=rows.map(r=>r[monthIdx]||'');
      const metricIdxs=cols.map((_,i)=>i).filter(i=>i!==monthIdx);
      const isSin=i=>sinNorm(cols[i]||'').includes('sinistral');
      head=[{text:cols[monthIdx]||'',options:{fill:HEADBG,color:HEADFG,bold:true,fontSize:9,align:'center'}}]
        .concat(monthLabels.map(m=>({text:sinAbrevMes(m)||'',options:{fill:HEADBG,color:HEADFG,bold:true,fontSize:9,align:'center'}})));
      body=metricIdxs.map((i,idx)=>{
        const sinRow=isSin(i);
        const bg=(idx%2)?'F4F6F8':'FFFFFF';
        const lbl={text:cols[i]||'',options:{bold:true,fontSize:9,color:HEADFG,fill:bg,align:'center'}};
        const cells=rows.map(r=>{ const o={text:r[i]||'',options:{fontSize:9,fill:bg,align:'center'}}; if(sinRow){ const p=sinPct(r[i]); if(p!=null&&p>=limiteP){ o.options.fill='E74C3C'; o.options.color='FFFFFF'; o.options.bold=true; } } return o; });
        return [lbl,...cells];
      });
    } else {
      const sinColIdx=cols.findIndex(c=>sinNorm(c||'').includes('sinistral'));
      head=cols.map((c,ci)=>({text:(ci===0?c:sinAbrevMes(c))||'',options:{fill:HEADBG,color:HEADFG,bold:true,fontSize:9,align:'center'}}));
      body=rows.map((r,idx)=>{
        const isTotal=sinNorm(r[0]||'')==='total';
        const bg= isTotal?HEADBG:((idx%2)?'F4F6F8':'FFFFFF');
        return r.map((v,ci)=>{
          const o={text:(ci===0?sinAbrevMes(v):v)||'',options:{fontSize:9,fill:bg,align:'center'}};
          if(ci===0){ o.options.bold=true; o.options.color=HEADFG; }
          else if(isTotal){ o.options.bold=true; }
          if(ci===sinColIdx){ o.options.align='center'; const p=sinPct(v); if(p!=null&&p>=limiteP){ o.options.fill='E74C3C'; o.options.color='FFFFFF'; o.options.bold=true; } }
          return o;
        });
      });
    }
    const _nc=head.length, _lw=1.2, _rw=(9.4-_lw)/Math.max(1,_nc-1);
    const _colW=[_lw].concat(Array(Math.max(0,_nc-1)).fill(_rw));
    s.addTable([head,...body],{x:0.3,y:2.65,w:9.4,colW:_colW,fontSize:9,valign:'middle',autoPage:false,border:{type:'solid',pt:0.5,color:'E3EAEA'}});
    if(st.withBi){
      let sb=pptx.addSlide(); sb.background={color:'FFFFFF'};
      sb.addText('Análise de uso e tendências',{x:0.4,y:0.3,w:9,bold:true,fontSize:18,color:TEAL});
      const b=st.bi; const parts=[]; const add=(t2,v2)=>{ if(v2){ parts.push({text:t2+': ',options:{bold:true,color:TEAL,fontSize:11,breakLine:false}}); parts.push({text:v2+'\n',options:{fontSize:11,color:'333333'}}); } };
      add('High Users',b.high);add('Consultas',b.consultas);add('Exames',b.exames);add('Internações',b.internacoes);add('Terapias',b.terapias);add('Prestadores',b.prestadores);add('Utilização por produto',b.produtos);add('Ranking',b.ranking);add('Perfil demográfico',b.demografico);
      sb.addText(parts.length?parts:[{text:'(sem dados de BI)'}],{x:0.4,y:1.0,w:9.2,h:4.2,valign:'top'});
    }
    const infoData = st.infoImg || await sinImgToDataURL('informativo_saude.png');
    if(infoData || st.infoTitulo || st.infoTexto){
      const si=pptx.addSlide(); si.background={color:'FFFFFF'};
      if(st.infoTitulo) si.addText(st.infoTitulo,{x:0.3,y:0.35,w:4,h:0.55,fontSize:18,bold:true,color:'FFFFFF',fill:{color:'0A8A8D'},align:'left',valign:'middle'});
      if(infoData) si.addImage({data:infoData,x:0.3,y:1.1,w:9.4,h:2.9,sizing:{type:'contain',w:9.4,h:2.9}});
      if(st.infoTexto) si.addText(st.infoTexto,{x:0.3,y:4.2,w:9.4,h:1.2,fontSize:12,color:
'C0392B',fill:{color:'FBE3E0'},align:'left',valign:'top'});
    }
    await pptx.writeFile({fileName:'informativo_sinistralidade_'+((st.empresa)||'').replace(/\s+/g,'_')+'.pptx'});
  }catch(e){ console.error(e); toast('Erro ao gerar PPT: '+e.message); }
}
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// padding padding padding padding padding
// FIM REAL
