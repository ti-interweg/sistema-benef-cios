<?php
require_once 'config.php';
require_once __DIR__ . '/mfa.php';

// Já logado por completo? Vai pro sistema.
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$p = $_SESSION['mfa_pending'] ?? null;
if (!$p) { header('Location: login.php'); exit; }

// Expira o estado pendente após 15 min.
if (time() - intval($p['ts'] ?? 0) > 900) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_email']);
    header('Location: login.php?e=exp'); exit;
}

// Carrega o segredo do usuário. Se não houver, manda cadastrar.
$mfa = mfa_status_usuario($p['src'], $p['uid']);
if ($mfa['enabled'] !== 1 || $mfa['secret'] === '') {
    header('Location: mfa_setup.php'); exit;
}

$erro = '';
$info = '';
$acao = $_POST['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'email') {
    // Envia código por e-mail (fallback).
    [$ok, $msg] = mfa_email_enviar($p['email'], $p['nome']);
    if ($ok) $info = $msg; else $erro = $msg;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica: aceita o código do app OU o enviado por e-mail.
    $codigo = $_POST['codigo'] ?? '';
    $ok = mfa_totp_verificar($mfa['secret'], $codigo);
    if (!$ok && !empty($_SESSION['mfa_email'])) {
        [$ok, $msg] = mfa_email_verificar($codigo);
        if (!$ok && $msg) $erro = $msg;
    }
    if ($ok) {
        // "Confiar neste dispositivo por 14 dias" (marcado por padrão).
        if (!empty($_POST['lembrar'])) mfa_confianca_registrar($p['src'], $p['uid']);
        mfa_conceder_sessao($p);
        header('Location: ' . mfa_destino_pos_login($p)); exit;
    }
    if ($erro === '') $erro = 'Código incorreto. Tente novamente (o código do app muda a cada 30s).';
}

$temEmail = filter_var(trim((string)($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false;
$emailMasc = $temEmail ? mfa_mascarar_email($p['email']) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Verificação em 2 etapas — Interweg</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,-50..200"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#0d1117;--card:#161b22;--border:#30363d;--accent:#00c9a7;--accent2:#00a589;--text:#e6edf3;--muted:#8b949e;--input:#0d1117;}
  .material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle}
  body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:var(--text)}
  .card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:36px 34px;width:400px;max-width:100%;box-shadow:0 24px 64px rgba(0,0,0,.5)}
  .icon{width:52px;height:52px;border-radius:14px;background:rgba(0,201,167,.12);color:var(--accent);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
  h1{font-size:21px;font-weight:700;margin-bottom:6px}
  .sub{font-size:13px;color:var(--muted);line-height:1.55;margin-bottom:22px}
  label{display:block;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
  input[name=codigo]{width:100%;background:var(--input);border:1.5px solid var(--border);border-radius:10px;padding:14px 16px;font-size:24px;letter-spacing:10px;text-align:center;color:var(--text);outline:none;font-family:inherit}
  input[name=codigo]:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,201,167,.12)}
  .btn{width:100%;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;margin-top:16px;box-shadow:0 6px 20px rgba(0,201,167,.3)}
  .btn:hover{transform:translateY(-1px)}
  .linkbtn{width:100%;background:transparent;border:1px solid var(--border);color:var(--text);border-radius:10px;padding:12px;font-size:13.5px;font-weight:600;cursor:pointer;margin-top:12px;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit}
  .linkbtn:hover{border-color:var(--accent);color:var(--accent)}
  .erro{background:rgba(198,40,40,.12);border:1px solid rgba(198,40,40,.3);color:#ff7070;border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;text-align:center}
  .info{background:rgba(0,201,167,.1);border:1px solid rgba(0,201,167,.3);color:var(--accent);border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;text-align:center}
  .foot{margin-top:20px;text-align:center;font-size:12px}
  .foot a{color:var(--muted);text-decoration:none}
  .sep{display:flex;align-items:center;gap:10px;margin:18px 0 4px;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:1px}
  .sep::before,.sep::after{content:'';flex:1;height:1px;background:var(--border)}
</style>
</head>
<body>
  <div class="card">
    <div class="icon"><span class="material-symbols-rounded" style="font-size:26px">encrypted</span></div>
    <h1>Verificação em 2 etapas</h1>
    <div class="sub">Olá, <b><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></b>. Abra o app autenticador e digite o código de 6 dígitos para concluir o acesso.</div>

    <?php if ($erro): ?><div class="erro">⚠ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($info): ?><div class="info"><?= htmlspecialchars($info, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
      <label>Código de verificação</label>
      <input name="codigo" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" required autofocus/>
      <label style="display:flex;gap:8px;align-items:center;font-size:12.5px;color:var(--muted);text-transform:none;letter-spacing:0;font-weight:500;margin-top:14px;cursor:pointer">
        <input type="checkbox" name="lembrar" value="1" checked style="width:auto;accent-color:var(--accent)"/>
        Confiar neste dispositivo por 14 dias (não pedir o código de novo)
      </label>
      <button class="btn" type="submit">Entrar →</button>
    </form>

    <?php if ($temEmail): ?>
    <div class="sep">ou</div>
    <form method="POST">
      <input type="hidden" name="acao" value="email"/>
      <button class="linkbtn" type="submit"><span class="material-symbols-rounded" style="font-size:18px">mail</span> Receber código por e-mail (<?= htmlspecialchars($emailMasc, ENT_QUOTES, 'UTF-8') ?> + cópia TI)</button>
    </form>
    <?php endif; ?>

    <div class="foot">
      <a href="logout.php">Cancelar e voltar ao login</a>
      <div style="margin-top:8px;color:var(--muted)">Sem acesso ao app nem ao e-mail? Fale com a TI: <a href="mailto:<?= MFA_TI_EMAIL ?>" style="color:var(--accent)"><?= MFA_TI_EMAIL ?></a></div>
    </div>
  </div>
<script>
  var inp = document.querySelector('input[name=codigo]');
  inp.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,6); });
</script>
</body>
</html>
