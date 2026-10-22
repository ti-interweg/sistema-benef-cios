<?php
require_once 'config.php';
require_once __DIR__ . '/auditoria.php';

// Acesso por token (sem login). Link enviado à gestão.
$tok  = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
$real = aud_token_publico();
if ($real === '' || $tok === '' || !hash_equals($real, $tok)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><div style="font-family:Arial;max-width:420px;margin:80px auto;text-align:center;color:#334">'
       . '<h2 style="color:#0a8a8d">Link inválido</h2><p>Este link de indicadores não é válido ou expirou.</p></div>';
    exit;
}

// ?gerar=1 (usado pela rotina diária) apenas garante a análise do dia; não força.
$AUD_GERAR = isset($_GET['gerar']) ? 'ifmissing' : 'no';
require __DIR__ . '/_indicadores_dados.php';

$topN = $usados[0] ?? null; $topUser = $porUsuario[0] ?? null; $topMod = $porModulo[0] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Indicadores de Uso · Interweg</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded" />
<style>
  :root{ --accent:#0a8a8d; --accent2:#00bfb3; --bg:#f4f6f8; --card:#fff; --border:#e6eaee; --text:#1f2733; --muted:#6b7280; }
  *{ box-sizing:border-box; }
  body{ margin:0; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; background:var(--bg); color:var(--text); }
  .material-symbols-rounded{ font-family:'Material Symbols Rounded'; font-weight:normal; vertical-align:middle; }
  .wrap{ max-width:900px; margin:0 auto; padding:18px 16px 30px; }
  .top{ background:linear-gradient(120deg,#0a8a8d,#00bfb3); color:#fff; border-radius:18px; padding:26px 24px; margin-bottom:18px; box-shadow:0 10px 30px rgba(10,138,141,.20); }
  .top .kick{ font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; opacity:.85; margin:0 0 6px; }
  .top h1{ margin:0 0 4px; font-size:24px; font-weight:800; }
  .top p{ margin:0; opacity:.92; font-size:13.5px; }
  .cards{ display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:16px; }
  .kpi{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px 16px; }
  .kpi .n{ font-size:22px; font-weight:800; color:var(--accent); }
  .kpi .l{ font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:16px; padding:18px 20px; margin-bottom:16px; box-shadow:0 2px 10px rgba(20,30,50,.04); }
  .card h3{ margin:0 0 3px; font-size:15px; display:flex; align-items:center; gap:8px; }
  .card .sub{ font-size:12px; color:var(--muted); margin-bottom:14px; }
  .insight{ background:linear-gradient(120deg,rgba(0,191,179,.10),transparent); border:1px solid var(--border); border-left:3px solid var(--accent); border-radius:12px; padding:16px 18px; font-size:14px; line-height:1.7; margin-bottom:16px; }
  .insight .h{ display:flex; align-items:center; gap:8px; font-weight:800; color:var(--accent); margin-bottom:6px; }
  .row{ display:flex; align-items:center; gap:10px; margin-bottom:9px; }
  .row .nm{ flex:0 0 190px; font-size:13px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .row .nm small{ color:var(--muted); text-transform:capitalize; }
  .track{ flex:1; height:15px; background:var(--bg); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
  .fill{ height:100%; background:linear-gradient(90deg,var(--accent),var(--accent2)); border-radius:8px 0 0 8px; }
  .zero .fill{ background:#cbd5e1; }
  .row .vl{ flex:0 0 52px; text-align:right; font-size:12.5px; font-weight:700; }
  .two{ display:grid; grid-template-columns:1fr 1fr; gap:16px; }
  .days{ display:flex; align-items:flex-end; gap:5px; height:110px; padding-top:8px; }
  .day{ flex:1; display:flex; flex-direction:column; align-items:center; gap:5px; justify-content:flex-end; }
  .day .bar{ width:62%; background:linear-gradient(180deg,var(--accent),var(--accent2)); border-radius:5px 5px 0 0; min-height:2px; }
  .day .dl{ font-size:9.5px; color:var(--muted); }
  .foot{ text-align:center; color:var(--muted); font-size:11.5px; padding:8px; }
  @media(max-width:720px){
    .cards{ grid-template-columns:repeat(2,1fr); }
    .two{ grid-template-columns:1fr; }
    .row .nm{ flex-basis:120px; }
    .top h1{ font-size:20px; }
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div class="kick">Interweg Benefícios</div>
    <h1>Indicadores de Uso do Sistema</h1>
    <p>Período <?= ai_esc($periodoIA ?: '—') ?> · <?= number_format($totGeral,0,',','.') ?> registros</p>
  </div>

  <div class="cards">
    <div class="kpi"><div class="n"><?= number_format($totGeral,0,',','.') ?></div><div class="l">Registros no total</div></div>
    <div class="kpi"><div class="n"><?= number_format($porEvento['acesso'],0,',','.') ?></div><div class="l">Acessos</div></div>
    <div class="kpi"><div class="n"><?= number_format($porEvento['alteracao']+$porEvento['exclusao'],0,',','.') ?></div><div class="l">Alterações</div></div>
    <div class="kpi"><div class="n"><?= number_format($porEvento['login'],0,',','.') ?></div><div class="l">Logins</div></div>
    <div class="kpi"><div class="n"><?= count($porUsuario) ?></div><div class="l">Usuários ativos</div></div>
  </div>

  <div class="insight">
    <?php if ($analiseIA !== ''): ?>
      <div class="h"><span class="material-symbols-rounded" style="font-size:19px">auto_awesome</span> Análise do dia</div>
      <?= nl2br(ai_esc($analiseIA)) ?>
    <?php else: ?>
      <div class="h"><span class="material-symbols-rounded" style="font-size:19px">insights</span> Leitura rápida</div>
      O período vai de <b><?= ai_esc($periodoIA ?: '—') ?></b>, com <b><?= number_format($totGeral,0,',','.') ?></b> registros.
      <?php if ($topN): ?> A página mais usada é <b><?= ai_esc($topN['lbl']) ?></b> (<?= $topN['c'] ?> acessos)<?php endif; ?><?php if ($topUser): ?>, e quem mais navega é <b><?= ai_esc($topUser['nome']) ?></b><?php endif; ?>.
      <?php if ($topMod): ?> As alterações se concentram em <b><?= ai_esc(ai_lbl($topMod['ent'])) ?></b>.<?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3><span class="material-symbols-rounded" style="color:var(--accent)">leaderboard</span> Páginas mais usadas</h3>
    <div class="sub">Por número de acessos. Barra cinza = menu sem uso.</div>
    <?php foreach ($rankPaginas as $p): $w = round($p['c']/$maxPag*100); ?>
      <div class="row <?= $p['c']===0 ? 'zero' : '' ?>">
        <div class="nm"><?= ai_esc($p['lbl']) ?></div>
        <div class="track"><div class="fill" style="width:<?= max($w, $p['c']>0?3:0) ?>%"></div></div>
        <div class="vl"><?= number_format($p['c'],0,',','.') ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="two">
    <div class="card">
      <h3><span class="material-symbols-rounded" style="color:var(--accent)">group</span> Quem mais usa</h3>
      <div class="sub">Acessos por usuário.</div>
      <?php foreach ($porUsuario as $u): $w = round($u['c']/$maxUser*100); ?>
        <div class="row">
          <div class="nm"><?= ai_esc($u['nome']) ?> <small><?= ai_esc($u['tipo']) ?></small></div>
          <div class="track"><div class="fill" style="width:<?= max($w,3) ?>%"></div></div>
          <div class="vl"><?= number_format($u['c'],0,',','.') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <h3><span class="material-symbols-rounded" style="color:var(--accent)">edit_note</span> Alterações por módulo</h3>
      <div class="sub">Onde há mais criação/edição de dados.</div>
      <?php foreach ($porModulo as $m): $w = round($m['c']/$maxMod*100); ?>
        <div class="row">
          <div class="nm"><?= ai_esc(ai_lbl($m['ent'])) ?></div>
          <div class="track"><div class="fill" style="width:<?= max($w,3) ?>%"></div></div>
          <div class="vl"><?= number_format($m['c'],0,',','.') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <h3><span class="material-symbols-rounded" style="color:var(--accent)">calendar_month</span> Atividade dos últimos 14 dias</h3>
    <div class="sub">Total de registros por dia.</div>
    <div class="days">
      <?php for ($i=13; $i>=0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $c = $porDia[$d] ?? 0; $h = round($c/$maxDia*100); echo '<div class="day" title="'.ai_esc(date('d/m',strtotime($d))).': '.$c.'"><div class="bar" style="height:'.max($h, $c>0?4:1).'%"></div><div class="dl">'.date('d/m',strtotime($d)).'</div></div>'; } ?>
    </div>
  </div>

  <div class="foot">Interweg Seguros e Benefícios · Indicadores gerados a partir do histórico de uso do sistema.</div>
</div>
</body>
</html>
