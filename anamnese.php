<?php
// Formulário público de Anamnese — acessível por link (?c=slug), sem login.
require_once __DIR__ . '/anamnese_perguntas.php';
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['c'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anamnese — Interweg Saúde</title>
<style>
  :root{ --accent:#0a8a8d; --accent2:#00bfb3; --ink:#26305a; --bg:#eef2f5; --line:#dce1e7; --muted:#6b7280; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:Arial,Helvetica,sans-serif; background:var(--bg); color:#222; }
  .wrap{ max-width:760px; margin:0 auto; padding:18px; }
  .card{ background:#fff; border-radius:14px; box-shadow:0 2px 14px rgba(0,0,0,.06); padding:22px 22px; margin-bottom:16px; }
  .top-img{ margin-bottom:14px; }
  .top-img img{ width:100%; display:block; border-radius:14px; }
  .top-title{ text-align:center; padding:16px 18px; }
  .top-title h1{ margin:0 0 4px; font-size:19px; color:var(--ink); }
  .top-title p{ margin:0; color:var(--muted); font-size:13.5px; }
  .secao{ font-size:15px; font-weight:800; color:var(--accent); text-transform:uppercase; letter-spacing:.5px; margin:8px 0 2px; padding-top:6px; }
  .q{ padding:14px 0; border-bottom:1px solid var(--line); }
  .q:last-child{ border-bottom:none; }
  .q-label{ font-weight:600; color:var(--ink); margin-bottom:10px; font-size:15px; line-height:1.4; }
  .req{ color:#e74c3c; }
  .opt{ display:flex; align-items:center; gap:10px; padding:9px 12px; border:1px solid var(--line); border-radius:10px; margin-bottom:8px; cursor:pointer; font-size:14.5px; }
  .opt:hover{ border-color:var(--accent); background:rgba(10,138,141,.04); }
  .opt input{ width:18px; height:18px; accent-color:var(--accent); flex:0 0 auto; }
  textarea, input[type=text]{ width:100%; padding:11px 13px; border:1px solid var(--line); border-radius:10px; font-family:inherit; font-size:14.5px; }
  textarea{ min-height:70px; resize:vertical; }
  .btn{ background:var(--accent); color:#fff; border:none; border-radius:10px; padding:14px 28px; font-size:16px; font-weight:700; cursor:pointer; width:100%; }
  .btn:disabled{ opacity:.6; cursor:default; }
  .erro{ color:#e74c3c; font-size:13px; margin-top:6px; display:none; }
  .ok-screen{ text-align:center; padding:40px 20px; }
  .ok-screen .ic{ font-size:64px; }
  .foot{ text-align:center; color:var(--muted); font-size:12px; padding:14px; }
</style>
</head>
<body>
<div class="wrap">
  <div id="ana-app">
    <div class="top-img">
      <img src="assinatura_interweg.png" alt="Interweg Seguros — A evolução em gestão de seguros e benefícios"/>
    </div>
    <div class="card top-title">
      <h1>Anamnese de Saúde</h1>
      <p id="ana-sub">Carregando…</p>
    </div>
    <div id="ana-form-area" style="display:none">
      <form id="ana-form"></form>
      <div class="card">
        <button type="button" class="btn" id="ana-enviar" onclick="anaEnviar(this)">Enviar respostas</button>
        <div class="erro" id="ana-erro-geral">Revise as perguntas obrigatórias destacadas acima.</div>
      </div>
    </div>
    <div id="ana-msg" class="card" style="display:none"></div>
  </div>
  <div class="foot">Interweg Seguros e Benefícios · Suas respostas são confidenciais e usadas apenas para a devolutiva de saúde.</div>
</div>

<script>
const ANA_SLUG = <?= json_encode($slug) ?>;
const ANA_PERGUNTAS = <?= json_encode($ANAMNESE_PERGUNTAS, JSON_UNESCAPED_UNICODE) ?>;

function esc(s){ const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; }

async function anaInit(){
  if (!ANA_SLUG){ anaMsg('Link inválido. Solicite o link correto à equipe.'); return; }
  try{
    const r = await fetch('api_anamnese?action=info_campanha&c=' + encodeURIComponent(ANA_SLUG)).then(x=>x.json());
    if (!r.ok){ anaMsg(r.msg || 'Formulário não disponível.'); return; }
    document.getElementById('ana-sub').textContent = 'Empresa: ' + r.cliente + '. Leva poucos minutos e as respostas são confidenciais.';
    anaRender();
    document.getElementById('ana-form-area').style.display = 'block';
  }catch(e){ anaMsg('Erro ao carregar o formulário. Tente novamente.'); }
}

function anaRender(){
  const f = document.getElementById('ana-form');
  let html = ''; let card = '';
  ANA_PERGUNTAS.forEach((p, i) => {
    if (p.tipo === 'secao'){
      if (card) html += `<div class="card">${card}</div>`;
      card = `<div class="secao">${esc(p.titulo)}</div>`;
      return;
    }
    const req = p.obr ? ' <span class="req">*</span>' : '';
    let campo = '';
    const name = 'q' + i;
    if (p.tipo === 'radio'){
      campo = (p.op||[]).map(o => `<label class="opt"><input type="radio" name="${name}" value="${esc(o)}"> <span>${esc(o)}</span></label>`).join('');
    } else if (p.tipo === 'checkbox'){
      campo = (p.op||[]).map(o => `<label class="opt"><input type="checkbox" name="${name}" value="${esc(o)}"> <span>${esc(o)}</span></label>`).join('');
    } else {
      campo = `<textarea name="${name}" placeholder="Escreva aqui (opcional)"></textarea>`;
    }
    card += `<div class="q" data-i="${i}" data-tipo="${p.tipo}" data-obr="${p.obr?1:0}">
        <div class="q-label">${esc(p.q)}${req}</div>
        ${campo}
        <div class="erro">Esta pergunta é obrigatória.</div>
      </div>`;
  });
  if (card) html += `<div class="card">${card}</div>`;
  f.innerHTML = html;
}

function anaColeta(){
  const dados = {}; let faltou = null;
  document.querySelectorAll('#ana-form .q').forEach(q => {
    const i = q.dataset.i, tipo = q.dataset.tipo, obr = q.dataset.obr === '1';
    const p = ANA_PERGUNTAS[i];
    let val = '';
    if (tipo === 'radio'){
      const sel = q.querySelector('input:checked'); val = sel ? sel.value : '';
    } else if (tipo === 'checkbox'){
      const arr = [...q.querySelectorAll('input:checked')].map(x=>x.value); val = arr.join(', ');
    } else {
      const ta = q.querySelector('textarea'); val = ta ? ta.value.trim() : '';
    }
    const erro = q.querySelector('.erro');
    if (obr && !val){ q.style.outline=''; erro.style.display='block'; if(!faltou) faltou=q; }
    else { erro.style.display='none'; }
    if (val) dados[p.q] = val;
  });
  return { dados, faltou };
}

async function anaEnviar(btn){
  const { dados, faltou } = anaColeta();
  if (faltou){
    document.getElementById('ana-erro-geral').style.display='block';
    faltou.scrollIntoView({behavior:'smooth', block:'center'});
    return;
  }
  document.getElementById('ana-erro-geral').style.display='none';
  btn.disabled = true; btn.textContent = 'Enviando…';
  try{
    const r = await fetch('api_anamnese?action=salvar_resposta', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ slug: ANA_SLUG, dados })
    }).then(x=>x.json());
    if (!r.ok){ btn.disabled=false; btn.textContent='Enviar respostas'; alert(r.msg||'Erro ao enviar'); return; }
    document.getElementById('ana-form-area').style.display='none';
    document.querySelector('.top').style.display='none';
    anaMsg('<div class="ok-screen"><div class="ic">✅</div><h2 style="color:var(--accent)">Respostas enviadas!</h2><p>Obrigado por participar. Seus dados ajudarão a equipe de saúde a preparar a devolutiva.</p></div>');
  }catch(e){ btn.disabled=false; btn.textContent='Enviar respostas'; alert('Erro de conexão. Tente novamente.'); }
}

function anaMsg(html){ const m=document.getElementById('ana-msg'); m.innerHTML=html; m.style.display='block'; }
anaInit();
</script>
</body>
</html>
