<?php
$currentPage = 'devolutiva_nutricional';
$pageTitle   = 'Devolutiva de Saúde — Interweg Benefícios';
$pageScripts = ['assets/devolutiva_nutricional.js'];

require_once __DIR__ . '/_header.php';
if (!$isAdmin && !$isEnfermeira && !$isRenovacao) { header('Location: demandas.php'); exit; }
require_once __DIR__ . '/anamnese_perguntas.php';
// Ordem das perguntas (só as que viram coluna/gráfico) para montar os resultados
$ordem = [];
foreach ($ANAMNESE_PERGUNTAS as $p) { if (($p['tipo'] ?? '') !== 'secao' && !empty($p['q'])) $ordem[] = $p['q']; }
?>

<style>
  #dn-card { font-family: Arial, Helvetica, sans-serif; }
  .dn-q { background:var(--bg-card); border:1px solid var(--border); border-radius:14px; padding:18px 20px; margin-bottom:18px; }
  .dn-q-title { font-size:17px; font-weight:800; color:#26305a; margin-bottom:14px; display:flex; align-items:flex-start; gap:8px; }
  .dn-q-grid { display:grid; grid-template-columns:1.1fr 1fr; gap:20px; align-items:start; }
  .dn-chart-wrap { min-height:220px; position:relative; }
  .dn-analise label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--accent); display:block; margin-bottom:6px; }
  .dn-analise textarea { width:100%; min-height:180px; padding:12px 14px; border:1px solid var(--border); border-radius:10px; font-family:inherit; font-size:14px; line-height:1.55; resize:vertical; background:var(--bg); color:var(--text); }
  .dn-open-list { border:1px solid var(--border); border-radius:10px; padding:6px; background:var(--bg); }
  .dn-open-item { display:flex; gap:10px; align-items:flex-start; font-size:13px; padding:8px 10px; border-radius:8px; color:var(--text); line-height:1.45; }
  .dn-open-item:nth-child(even) { background:rgba(128,128,128,.07); }
  .dn-open-num { flex:0 0 auto; min-width:20px; height:20px; border-radius:50%; background:var(--accent); color:#fff; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; margin-top:1px; }
  .dn-open-txt { flex:1; overflow-wrap:anywhere; }
  .dn-open-list div:last-child { border-bottom:none; }
  .dn-badge { font-size:11.5px; color:var(--muted); font-weight:600; }
  .dn-camp { border:1px solid var(--border); border-radius:10px; padding:12px 14px; margin-bottom:10px; display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; background:var(--bg-card); }
  .dn-link { font-size:12px; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:6px 8px; max-width:340px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  @media (max-width: 900px){ .dn-q-grid { grid-template-columns:1fr; } }
  @media print {
    @page { margin: 12mm; }
    html, body { background:#fff !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; width:auto !important; margin:0 !important; }
    .nav, .sidebar, .page-header .btn, #dn-camp-box, #dn-upload-box, #dn-card-head, .dn-no-print { display:none !important; }
    /* Tudo cabe na largura da folha */
    .main, main.main { margin:0 !important; padding:0 !important; width:auto !important; max-width:100% !important; }
    #dn-card, .dn-q, .dn-q-grid, .dn-analise { width:auto !important; max-width:100% !important; overflow:visible !important; box-sizing:border-box !important; }
    .dn-q { break-inside: avoid; page-break-inside: avoid; box-shadow:none; }
    .dn-q-grid { grid-template-columns:1fr !important; }
    .dn-chart-wrap, canvas, .dn-analise, .dn-open-list { break-inside: avoid; page-break-inside: avoid; }
    /* Esconde o campo editável e imprime o texto completo em um bloco que quebra linha */
    .dn-analise textarea, .dn-ia-btn { display:none !important; }
    .dn-analise-print {
      display:block !important;
      white-space:pre-wrap !important; overflow-wrap:anywhere !important; word-break:break-word !important;
      width:100% !important; max-width:100% !important; box-sizing:border-box !important;
      font-size:13px; line-height:1.5; color:#000; border:1px solid #ccc; border-radius:8px; padding:10px 12px; min-height:36px;
    }
  }
  .dn-analise-print { display:none; }
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Devolutiva de Saúde</div>
      <div class="page-sub">Crie o formulário, compartilhe com o cliente e veja os resultados em gráficos + análise</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center" class="dn-no-print">
      <span id="dn-save-status" style="font-size:12px;font-weight:600;color:var(--muted);display:none"></span>
      <button class="btn btn-primary" id="dn-btn-salvar" onclick="dnSalvarTudo()" style="display:none"><span class="material-symbols-rounded" style="font-size:18px">save</span> Salvar análises</button>
      <button class="btn btn-outline" id="dn-btn-limpar" onclick="dnLimparAnalises()" style="display:none"><span class="material-symbols-rounded" style="font-size:18px">restart_alt</span> Limpar análises</button>
      <button class="btn btn-outline" id="dn-btn-print" onclick="dnImprimirLimpo()" style="display:none"><span class="material-symbols-rounded" style="font-size:18px">print</span> Imprimir / PDF</button>
    </div>
  </div>

  <!-- Campanhas / formulários -->
  <div id="dn-camp-box" style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:18px">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px">
      <h3 style="margin:0;color:var(--accent);display:flex;align-items:center;gap:8px"><span class="material-symbols-rounded">assignment</span> Formulários por cliente</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <input id="dn-novo-cliente" type="text" placeholder="Nome do cliente / turma" style="padding:9px 12px;min-width:220px"/>
        <button class="btn btn-primary" onclick="dnCriarCampanha(this)"><span class="material-symbols-rounded" style="font-size:18px">add</span> Criar formulário</button>
      </div>
    </div>
    <div id="dn-camp-lista"><div style="color:var(--muted);font-size:13px">Carregando…</div></div>
  </div>

  <!-- Upload manual (alternativa) -->
  <details id="dn-upload-box" style="margin-bottom:18px">
    <summary style="cursor:pointer;color:var(--muted);font-size:13px">Ou: enviar um arquivo .csv/.xlsx manualmente (ex.: exportado do Google Forms)</summary>
    <div style="border:1px dashed var(--accent);border-radius:12px;padding:18px;text-align:center;margin-top:10px;background:rgba(0,191,179,.04)">
      <input type="file" id="dn-file" accept=".csv,.xlsx,.xls" style="display:none" onchange="dnImportar(this)"/>
      <button class="btn btn-outline" onclick="document.getElementById('dn-file').click()"><span class="material-symbols-rounded" style="font-size:18px">file_upload</span> Escolher arquivo</button>
      <div id="dn-file-info" style="font-size:12px;color:var(--muted);margin-top:8px"></div>
    </div>
  </details>

  <!-- Cabeçalho da devolutiva -->
  <div id="dn-card-head" style="display:none;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:18px">
    <div style="display:grid;grid-template-columns:1fr 1fr 160px;gap:14px;align-items:end">
      <div class="fg"><label>Empresa / Turma</label><input id="dn-empresa" type="text" placeholder="Ex: MinuTrade" oninput="dnSalvarMeta()"/></div>
      <div class="fg"><label>Título da devolutiva</label><input id="dn-titulo" type="text" placeholder="Ex: Devolutiva da Anamnese" oninput="dnSalvarMeta()"/></div>
      <div class="fg"><label>Respondentes</label><input id="dn-n" type="text" readonly style="background:var(--bg-hover);color:var(--muted)"/></div>
    </div>
  </div>

  <!-- Resultados -->
  <div id="dn-card"></div>
</main>

<script>
  // Ordem das perguntas (do questionário) para montar os resultados vindos do banco
  window.DN_PERGUNTAS_ORDEM = <?= json_encode($ordem, JSON_UNESCAPED_UNICODE) ?>;
  window.DN_BASE_URL = <?= json_encode((isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].rtrim(dirname($_SERVER['PHP_SELF']),'/\\')) ?>;
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
