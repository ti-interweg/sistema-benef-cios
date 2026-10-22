<?php
require_once 'config.php';
require_once __DIR__ . '/auditoria.php';
auth();
if (!aud_pode_ver()) { header('Location: demandas.php'); exit; }
aud_migrar_once();

// Gera a análise se ainda não houver a de hoje; ?regerar=1 força atualizar.
$AUD_GERAR = isset($_GET['regerar']) ? 'force' : 'ifmissing';
require __DIR__ . '/_indicadores_dados.php';

$tokenPublico = aud_token_publico();
$base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$linkPublico = $base . '/indicadores_publico?token=' . $tokenPublico;

$currentPage = 'auditoria_indicadores';
$pageTitle   = 'Indicadores de Uso — Interweg Benefícios';
require_once __DIR__ . '/_header.php';
?>
<style>
  .ai-wrap{max-width:1080px}
  .ai-cards{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px}
  .ai-kpi{flex:1;min-width:150px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:15px 18px}
  .ai-kpi .n{font-size:24px;font-weight:800;color:var(--accent)}
  .ai-kpi .l{font-size:11.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
  .ai-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-bottom:16px}
  .ai-card h3{margin:0 0 4px;font-size:15px;color:var(--text);display:flex;align-items:center;gap:8px}
  .ai-card .sub{font-size:12px;color:var(--muted);margin-bottom:14px}
  .ai-row{display:flex;align-items:center;gap:10px;margin-bottom:9px}
  .ai-row .nm{flex:0 0 210px;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ai-row .nm small{color:var(--muted);text-transform:capitalize}
  .ai-track{flex:1;height:16px;background:var(--bg,#0d1117);border:1px solid var(--border);border-radius:8px;overflow:hidden}
  .ai-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--accent2,#00a589));border-radius:8px 0 0 8px}
  .ai-row .vl{flex:0 0 56px;text-align:right;font-size:12.5px;font-weight:700}
  .ai-zero .ai-fill{background:#9ca3af55}
  .ai-tags{display:flex;flex-wrap:wrap;gap:8px}
  .ai-tag{font-size:12px;background:var(--bg);border:1px solid var(--border);border-radius:999px;padding:5px 11px;color:var(--muted)}
  .ai-days{display:flex;align-items:flex-end;gap:6px;height:120px;padding-top:8px}
  .ai-day{flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;justify-content:flex-end}
  .ai-day .bar{width:60%;background:linear-gradient(180deg,var(--accent),var(--accent2,#00a589));border-radius:5px 5px 0 0;min-height:2px}
  .ai-day .dl{font-size:10px;color:var(--muted);white-space:nowrap}
  .ai-insight{background:linear-gradient(120deg,rgba(0,201,167,.08),transparent);border:1px solid var(--border);border-left:3px solid var(--accent);border-radius:10px;padding:14px 16px;font-size:13.5px;line-height:1.65;color:var(--text);margin-bottom:16px}
  .ai-two{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .ai-pub{background:var(--bg-card);border:1px dashed var(--accent);border-radius:12px;padding:14px 16px;margin-bottom:16px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .ai-pub input{flex:1;min-width:200px;padding:9px 11px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--text);font-size:12.5px}
  @media(max-width:820px){ .ai-two{grid-template-columns:1fr} .ai-row .nm{flex-basis:130px} }
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Indicadores de Uso</div>
      <div class="page-sub">Análise do uso do sistema a partir do histórico. Páginas mais e menos usadas, quem usa, o que é alterado.</div>
    </div>
    <a class="btn btn-outline" href="auditoria_admin.php"><span class="material-symbols-rounded" style="font-size:18px">history</span> Ver histórico</a>
  </div>

  <div class="ai-wrap">
    <div class="ai-pub">
      <span class="material-symbols-rounded" style="color:var(--accent)">share</span>
      <div style="font-size:13px;font-weight:600">Link público (para enviar ao chefe)</div>
      <input id="ai-pub-url" type="text" readonly value="<?= ai_esc($linkPublico) ?>" onclick="this.select()"/>
      <button class="btn btn-primary" style="padding:8px 14px" onclick="navigator.clipboard.writeText(document.getElementById('ai-pub-url').value);this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar',1500)">Copiar</button>
    </div>

    <div class="ai-cards">
      <div class="ai-kpi"><div class="n"><?= number_format($totGeral,0,',','.') ?></div><div class="l">Registros no total</div></div>
      <div class="ai-kpi"><div class="n"><?= number_format($porEvento['acesso'],0,',','.') ?></div><div class="l">Acessos a páginas</div></div>
      <div class="ai-kpi"><div class="n"><?= number_format($porEvento['alteracao']+$porEvento['exclusao'],0,',','.') ?></div><div class="l">Alterações</div></div>
      <div class="ai-kpi"><div class="n"><?= number_format($porEvento['login'],0,',','.') ?></div><div class="l">Logins</div></div>
    </div>

    <?php $topN = $usados[0] ?? null; $topUser = $porUsuario[0] ?? null; $topMod = $porModulo[0] ?? null; ?>
    <div class="ai-insight">
      <?php if ($analiseIA !== ''): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px"><span class="material-symbols-rounded" style="font-size:18px;color:var(--accent)">auto_awesome</span><b>Análise do dia (IA)</b></div>
        <?= nl2br(ai_esc($analiseIA)) ?>
        <div style="margin-top:10px;font-size:11.5px;color:var(--muted)">Gerada por IA<?= $analiseEm ? ' · ' . ai_esc(date('d/m/Y H:i', strtotime($analiseEm))) : '' ?> · <a href="?regerar=1" style="color:var(--accent);text-decoration:none">Atualizar agora</a></div>
      <?php else: ?>
        <b>Leitura rápida.</b>
        O histórico cobre o período de <b><?= ai_esc($periodoIA) ?></b>, com <b><?= number_format($totGeral,0,',','.') ?></b> registros.
        <?php if ($topN): ?> A página mais usada é <b><?= ai_esc($topN['lbl']) ?></b> (<?= $topN['c'] ?> acessos)<?php endif; ?><?php if ($topUser): ?>, e quem mais navega é <b><?= ai_esc($topUser['nome']) ?></b><?php endif; ?>.
        <?php if ($topMod): ?> As alterações se concentram em <b><?= ai_esc(ai_lbl($topMod['ent'])) ?></b>.<?php endif; ?>
        <div style="margin-top:10px;font-size:11.5px;color:var(--muted)"><a href="?regerar=1" style="color:var(--accent);text-decoration:none">Gerar análise por IA</a></div>
      <?php endif; ?>
    </div>

    <div class="ai-card">
      <h3><span class="material-symbols-rounded" style="color:var(--accent)">leaderboard</span> Páginas / menus mais usados</h3>
      <div class="sub">Por número de acessos registrados. Inclui os menus sem uso (barra cinza).</div>
      <?php foreach ($rankPaginas as $p): $w = round($p['c']/$maxPag*100); ?>
        <div class="ai-row <?= $p['c']===0 ? 'ai-zero' : '' ?>">
          <div class="nm"><?= ai_esc($p['lbl']) ?></div>
          <div class="ai-track"><div class="ai-fill" style="width:<?= max($w, $p['c']>0?3:0) ?>%"></div></div>
          <div class="vl"><?= number_format($p['c'],0,',','.') ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ai-two">
      <div class="ai-card">
        <h3><span class="material-symbols-rounded" style="color:var(--accent)">group</span> Quem mais usa</h3>
        <div class="sub">Acessos por usuário.</div>
        <?php if (!$porUsuario): ?><div style="color:var(--muted);font-size:13px">Sem dados.</div><?php endif; ?>
        <?php foreach ($porUsuario as $u): $w = round($u['c']/$maxUser*100); ?>
          <div class="ai-row">
            <div class="nm"><?= ai_esc($u['nome']) ?> <small><?= ai_esc($u['tipo']) ?></small></div>
            <div class="ai-track"><div class="ai-fill" style="width:<?= max($w,3) ?>%"></div></div>
            <div class="vl"><?= number_format($u['c'],0,',','.') ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="ai-card">
        <h3><span class="material-symbols-rounded" style="color:var(--accent)">edit_note</span> Alterações por módulo</h3>
        <div class="sub">Onde há mais criação/edição/exclusão de dados.</div>
        <?php if (!$porModulo): ?><div style="color:var(--muted);font-size:13px">Sem dados.</div><?php endif; ?>
        <?php foreach ($porModulo as $m): $w = round($m['c']/$maxMod*100); ?>
          <div class="ai-row">
            <div class="nm"><?= ai_esc(ai_lbl($m['ent'])) ?></div>
            <div class="ai-track"><div class="ai-fill" style="width:<?= max($w,3) ?>%"></div></div>
            <div class="vl"><?= number_format($m['c'],0,',','.') ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="ai-card">
      <h3><span class="material-symbols-rounded" style="color:var(--accent)">calendar_month</span> Atividade dos últimos 14 dias</h3>
      <div class="sub">Total de registros por dia.</div>
      <div class="ai-days">
        <?php for ($i=13; $i>=0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $c = $porDia[$d] ?? 0; $h = round($c/$maxDia*100); echo '<div class="ai-day" title="'.ai_esc(date('d/m',strtotime($d))).': '.$c.'"><div class="bar" style="height:'.max($h, $c>0?4:1).'%"></div><div class="dl">'.date('d/m',strtotime($d)).'</div></div>'; } ?>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
