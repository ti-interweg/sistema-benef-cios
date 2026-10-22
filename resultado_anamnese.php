<?php
// Página PÚBLICA de resultados da devolutiva (somente leitura), por token (?t=...).
require_once __DIR__ . '/anamnese_perguntas.php';
$ordem = [];
foreach ($ANAMNESE_PERGUNTAS as $p) { if (($p['tipo'] ?? '') !== 'secao' && !empty($p['q'])) $ordem[] = $p['q']; }
$token = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['t'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Devolutiva de Saúde · Interweg</title>
<style>
  :root{ --accent:#0a8a8d; --bg:#f4f6f8; --bg-card:#fff; --bg-hover:#f3f5f7; --border:#e6eaee; --text:#1f2733; --muted:#6b7280; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); }
  .wrap{ max-width:1000px; margin:0 auto; padding:22px 18px 8px; }
  .top{ background:linear-gradient(120deg,#0a8a8d 0%,#00bfb3 100%); color:#fff; border-radius:18px; padding:32px 32px; margin-bottom:22px; box-shadow:0 10px 30px rgba(10,138,141,.20); }
  .top .kicker{ font-size:12px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; opacity:.82; margin:0 0 8px; }
  .top h1{ margin:0 0 6px; font-size:30px; font-weight:800; letter-spacing:-.5px; line-height:1.1; }
  .top p{ margin:0; opacity:.92; font-size:14.5px; }
  .dn-q { background:var(--bg-card); border:1px solid var(--border); border-radius:16px; padding:22px 24px; margin-bottom:16px; box-shadow:0 2px 10px rgba(20,30,50,.05); }
  .dn-q-title { font-size:17px; font-weight:700; color:#1f2733; margin-bottom:16px; display:flex; align-items:flex-start; gap:8px; }
  .dn-q-grid { display:grid; grid-template-columns:1.1fr 1fr; gap:20px; align-items:start; }
  .dn-chart-wrap { min-height:220px; position:relative; }
  .dn-analise label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--accent); display:block; margin-bottom:6px; }
  .dn-open-list { border:1px solid var(--border); border-radius:10px; padding:6px; background:var(--bg); }
  .dn-open-item { display:flex; gap:10px; align-items:flex-start; font-size:13px; padding:8px 10px; border-radius:8px; line-height:1.45; }
  .dn-open-item:nth-child(even) { background:rgba(128,128,128,.07); }
  .dn-open-num { flex:0 0 auto; min-width:20px; height:20px; border-radius:50%; background:var(--accent); color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; margin-top:1px; }
  .dn-open-txt { flex:1; overflow-wrap:anywhere; }
  .dn-open-list div:last-child { border-bottom:none; }
  .dn-badge { font-size:11.5px; color:var(--muted); font-weight:600; }
  .material-symbols-rounded{ font-family:'Material Symbols Rounded'; font-weight:normal; font-size:18px; }
  .foot{ text-align:center; color:var(--muted); font-size:12px; padding:14px; }
  @media (max-width:900px){ .dn-q-grid{ grid-template-columns:1fr; } }
  @media print {
    @page { margin: 12mm; }
    html, body { background:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .top .noprint, .noprint { display:none; }
    .wrap { max-width:none !important; padding:0 !important; }
    .dn-q { break-inside: avoid; page-break-inside: avoid; box-shadow:none; }
    .dn-q-grid { grid-template-columns:1fr !important; }
    .dn-chart-wrap, canvas, .dn-analise, .dn-open-list, .card { break-inside: avoid; page-break-inside: avoid; }
  }
</style>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" rel="stylesheet">
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="kicker">Devolutiva de Saúde</div>
    <h1 id="r-titulo">Carregando…</h1>
    <p id="r-sub">Carregando resultados…</p>
  </div>
  <!-- elementos ocultos usados pelo motor -->
  <div id="dn-card-head" style="display:none">
    <input id="dn-empresa" type="text"><input id="dn-titulo" type="text"><input id="dn-n" type="text">
  </div>
  <div id="dn-card"></div>
  <div class="foot">Interweg Seguros e Benefícios · Devolutiva de saúde elaborada pela equipe de Enfermagem.</div>
</div>

<script>
  window.DN_PERGUNTAS_ORDEM = <?= json_encode($ordem, JSON_UNESCAPED_UNICODE) ?>;
  const R_TOKEN = <?= json_encode($token) ?>;
</script>
<script src="assets/devolutiva_nutricional.js?v=<?= @filemtime(__DIR__ . '/assets/devolutiva_nutricional.js') ?: time() ?>"></script>
<script>
(async function(){
  if (!R_TOKEN){ document.getElementById('r-sub').textContent = 'Link inválido.'; return; }
  try{
    const r = await fetch('api_anamnese?action=resultados_token&t=' + encodeURIComponent(R_TOKEN) + '&_=' + Date.now(), { cache:'no-store' }).then(x=>x.json());
    if (!r.ok){ document.getElementById('r-sub').textContent = r.msg || 'Resultados não disponíveis.'; return; }
    document.getElementById('r-titulo').textContent = r.cliente;
    const _n = r.respostas.length;
    document.getElementById('r-sub').textContent = (_n === 1 ? '1 pessoa respondeu' : _n + ' pessoas responderam') + ' o questionário.';
    if (!r.respostas.length){ return; }
    dnFromRespostas(r.respostas, window.DN_PERGUNTAS_ORDEM, { campanhaId:null, analises:r.analises||{}, readOnly:true });
  }catch(e){ document.getElementById('r-sub').textContent = 'Erro ao carregar os resultados.'; }
})();
</script>
</body>
</html>
