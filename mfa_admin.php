<?php
$currentPage = 'mfa_admin';
$pageTitle   = 'Verificação em 2 etapas — Interweg Benefícios';

require_once __DIR__ . '/_header.php';
require_once __DIR__ . '/mfa.php';

// Somente admin.
if (!$isAdmin) { header('Location: demandas.php'); exit; }

mfa_migrar();

$msg = '';
$msgTipo = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'reset') {
    $src = $_POST['src'] ?? '';
    $id  = intval($_POST['id'] ?? 0);
    if (in_array($src, ['analistas', 'usuarios'], true) && $id > 0) {
        if (mfa_resetar($src, $id)) {
            $msg = 'MFA resetado. O usuário fará um novo cadastro no próximo acesso.';
            $msgTipo = 'ok';
        } else {
            $msg = 'Não foi possível resetar. Tente novamente.';
            $msgTipo = 'erro';
        }
    }
}

// Monta a lista de perfis administrativos (analistas + usuarios).
$linhas = [];
$tipos = "('admin','head','coordenador','gerente')";
try {
    $qa = db()->query("SELECT id, nome, tipo, login, email, COALESCE(mfa_enabled,0) AS mfa_enabled
                       FROM analistas WHERE ativo = 1 AND LOWER(tipo) IN $tipos ORDER BY nome");
    foreach ($qa as $r) { $r['src'] = 'analistas'; $linhas[] = $r; }
} catch (Throwable $e) {}
try {
    $qu = db()->query("SELECT id, nome, tipo, email AS login, email, COALESCE(mfa_enabled,0) AS mfa_enabled
                       FROM usuarios WHERE ativo = 1 AND LOWER(tipo) IN $tipos ORDER BY nome");
    foreach ($qu as $r) { $r['src'] = 'usuarios'; $linhas[] = $r; }
} catch (Throwable $e) {}

$totAtivos = 0; foreach ($linhas as $l) { if (intval($l['mfa_enabled']) === 1) $totAtivos++; }
?>
<style>
  .mfa-wrap{max-width:920px}
  .mfa-cards{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px}
  .mfa-kpi{flex:1;min-width:180px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:16px 18px}
  .mfa-kpi .n{font-size:26px;font-weight:800;color:var(--accent)}
  .mfa-kpi .l{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}
  .mfa-table{width:100%;border-collapse:collapse;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;overflow:hidden}
  .mfa-table th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);text-align:left;padding:12px 14px;border-bottom:1px solid var(--border)}
  .mfa-table td{padding:12px 14px;border-bottom:1px solid var(--border);font-size:13.5px}
  .mfa-table tr:last-child td{border-bottom:none}
  .pill{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px}
  .pill.on{background:rgba(16,185,129,.14);color:#10b981}
  .pill.off{background:rgba(245,158,11,.14);color:#f59e0b}
  .tchip{font-size:11px;color:var(--muted);text-transform:capitalize}
  .btn-reset{background:transparent;border:1px solid var(--border);color:var(--text);border-radius:8px;padding:6px 12px;font-size:12.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
  .btn-reset:hover{border-color:#f59e0b;color:#f59e0b}
  .banner{border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px}
  .banner.ok{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981}
  .banner.erro{background:rgba(198,40,40,.12);border:1px solid rgba(198,40,40,.3);color:#ff7070}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Verificação em 2 etapas (MFA)</div>
      <div class="page-sub">Perfis administrativos — admin, head, coordenador e gerente. Reset libera novo cadastro do app.</div>
    </div>
  </div>

  <div class="mfa-wrap">
    <?php if ($msg): ?><div class="banner <?= $msgTipo === 'ok' ? 'ok' : ($msgTipo === 'erro' ? 'erro' : 'ok') ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <div class="mfa-cards">
      <div class="mfa-kpi"><div class="n"><?= count($linhas) ?></div><div class="l">Perfis administrativos</div></div>
      <div class="mfa-kpi"><div class="n"><?= $totAtivos ?></div><div class="l">Com MFA ativo</div></div>
      <div class="mfa-kpi"><div class="n"><?= count($linhas) - $totAtivos ?></div><div class="l">Ainda sem cadastrar</div></div>
    </div>

    <table class="mfa-table">
      <thead>
        <tr><th>Nome</th><th>Login</th><th>Perfil</th><th>MFA</th><th style="text-align:right">Ação</th></tr>
      </thead>
      <tbody>
        <?php if (!$linhas): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:26px">Nenhum perfil administrativo encontrado.</td></tr>
        <?php else: foreach ($linhas as $l): $on = intval($l['mfa_enabled']) === 1; ?>
          <tr>
            <td><?= htmlspecialchars($l['nome'], ENT_QUOTES, 'UTF-8') ?></td>
            <td style="color:var(--muted)"><?= htmlspecialchars($l['login'] ?: ($l['email'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><span class="tchip"><?= htmlspecialchars($l['tipo'], ENT_QUOTES, 'UTF-8') ?></span></td>
            <td>
              <?php if ($on): ?><span class="pill on"><span class="material-symbols-rounded" style="font-size:15px">verified_user</span> Ativo</span>
              <?php else: ?><span class="pill off"><span class="material-symbols-rounded" style="font-size:15px">pending</span> Pendente</span><?php endif; ?>
            </td>
            <td style="text-align:right">
              <?php if ($on): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Resetar o MFA de <?= htmlspecialchars(addslashes($l['nome']), ENT_QUOTES, 'UTF-8') ?>? A pessoa terá que cadastrar o app de novo no próximo login.');">
                <input type="hidden" name="acao" value="reset"/>
                <input type="hidden" name="src" value="<?= htmlspecialchars($l['src'], ENT_QUOTES, 'UTF-8') ?>"/>
                <input type="hidden" name="id" value="<?= intval($l['id']) ?>"/>
                <button class="btn-reset" type="submit"><span class="material-symbols-rounded" style="font-size:15px">lock_reset</span> Resetar</button>
              </form>
              <?php else: ?><span style="color:var(--muted);font-size:12px">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <p style="font-size:12px;color:var(--muted);margin-top:14px;line-height:1.6">
      Ao resetar, o segredo do autenticador é apagado e o perfil é obrigado a fazer um novo cadastro no próximo acesso.
      Em caso de perda do celular sem reset, o usuário ainda pode entrar pelo código enviado ao e-mail cadastrado.
    </p>
  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>
