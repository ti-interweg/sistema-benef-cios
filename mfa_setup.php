<?php
require_once 'config.php';
require_once __DIR__ . '/mfa.php';

// Já logado por completo? Vai pro sistema.
if (!empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Precisa ter passado pela senha (estado pendente).
$p = $_SESSION['mfa_pending'] ?? null;
if (!$p) { header('Location: login.php'); exit; }

// Expira o estado pendente após 15 min.
if (time() - intval($p['ts'] ?? 0) > 900) {
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_setup_secret']);
    header('Location: login.php?e=exp'); exit;
}

// Gera o segredo uma única vez por sessão de cadastro.
if (empty($_SESSION['mfa_setup_secret'])) {
    $_SESSION['mfa_setup_secret'] = mfa_gerar_secret();
}
$secret = $_SESSION['mfa_setup_secret'];
$conta  = $p['login'] ?: ($p['email'] ?: 'usuario');
$uri    = mfa_otpauth_uri($secret, $conta);

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'] ?? '';
    if (mfa_totp_verificar($secret, $codigo)) {
        if (mfa_ativar($p['src'], $p['uid'], $secret)) {
            mfa_conceder_sessao($p);
            header('Location: ' . mfa_destino_pos_login($p)); exit;
        }
        $erro = 'Não foi possível salvar o cadastro. Tente novamente.';
    } else {
        $erro = 'Código incorreto. Verifique o app e tente novamente (o código muda a cada 30s).';
    }
}

// Chave em blocos de 4 para digitação manual.
$secretFmt = trim(chunk_split($secret, 4, ' '));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Ativar verificação em 2 etapas — Interweg</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0..1,-50..200"/>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  :root{--bg:#0d1117;--card:#161b22;--border:#30363d;--accent:#00c9a7;--accent2:#00a589;--text:#e6edf3;--muted:#8b949e;--input:#0d1117;}
  .material-symbols-rounded{font-variation-settings:'FILL' 0,'wght' 400,'GRAD' 0,'opsz' 24;vertical-align:middle}
  body{font-family:'Segoe UI',Tahoma,sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:var(--text)}
  .card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:34px 32px;width:440px;max-width:100%;box-shadow:0 24px 64px rgba(0,0,0,.5)}
  .badge{display:inline-flex;align-items:center;gap:8px;background:rgba(0,201,167,.12);color:var(--accent);font-size:12px;font-weight:700;padding:6px 12px;border-radius:999px;margin-bottom:16px}
  h1{font-size:21px;font-weight:700;margin-bottom:6px}
  .sub{font-size:13px;color:var(--muted);line-height:1.55;margin-bottom:20px}
  ol{margin:0 0 18px 18px;font-size:13.5px;color:var(--text);line-height:1.7}
  ol b{color:var(--accent)}
  .qrbox{display:flex;justify-content:center;background:#fff;border-radius:14px;padding:16px;margin:6px auto 14px;width:196px}
  .key{font-family:ui-monospace,Consolas,monospace;font-size:15px;letter-spacing:1px;background:var(--input);border:1px dashed var(--border);border-radius:10px;padding:11px 12px;text-align:center;color:var(--text);word-break:break-all;margin-bottom:18px}
  label{display:block;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
  input[name=codigo]{width:100%;background:var(--input);border:1.5px solid var(--border);border-radius:10px;padding:13px 16px;font-size:22px;letter-spacing:8px;text-align:center;color:var(--text);outline:none;font-family:inherit}
  input[name=codigo]:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(0,201,167,.12)}
  .btn{width:100%;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;margin-top:16px;box-shadow:0 6px 20px rgba(0,201,167,.3)}
  .btn:hover{transform:translateY(-1px)}
  .erro{background:rgba(198,40,40,.12);border:1px solid rgba(198,40,40,.3);color:#ff7070;border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px;text-align:center}
  .foot{margin-top:18px;text-align:center;font-size:12px}
  .foot a{color:var(--muted);text-decoration:none}
  .hint{font-size:12px;color:var(--muted);margin-top:10px;line-height:1.5}
</style>
</head>
<body>
  <div class="card">
    <span class="badge"><span class="material-symbols-rounded" style="font-size:16px">shield_lock</span> Segurança da conta</span>
    <h1>Ative a verificação em 2 etapas</h1>
    <div class="sub">Olá, <b><?= htmlspecialchars($p['nome'], ENT_QUOTES, 'UTF-8') ?></b>. Seu perfil exige um segundo fator de autenticação. Configure uma vez e depois é só digitar o código do app a cada acesso.</div>

    <?php if ($erro): ?><div class="erro">⚠ <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <ol>
      <li>Instale o <b>Google Authenticator</b> (ou Authy / Microsoft Authenticator) no celular.</li>
      <li>No app, toque em <b>+</b> e escolha <b>Ler QR code</b>.</li>
      <li>Aponte para o código abaixo e depois digite os 6 dígitos que aparecerem.</li>
    </ol>

    <div class="qrbox"><div id="qr"></div></div>
    <div class="hint" style="text-align:center;margin:-6px 0 12px">Não consegue ler o QR? Digite esta chave manualmente:</div>
    <div class="key"><?= htmlspecialchars($secretFmt, ENT_QUOTES, 'UTF-8') ?></div>

    <form method="POST" autocomplete="off">
      <label>Código do aplicativo</label>
      <input name="codigo" inputmode="numeric" pattern="[0-9]*" maxlength="6" placeholder="000000" required autofocus/>
      <button class="btn" type="submit">Ativar e entrar →</button>
    </form>

    <div class="foot">
      <a href="logout.php">Cancelar e voltar ao login</a>
      <div style="margin-top:8px;color:var(--muted)">Precisa de ajuda? Fale com a TI: <a href="mailto:<?= MFA_TI_EMAIL ?>" style="color:var(--accent)"><?= MFA_TI_EMAIL ?></a></div>
    </div>
  </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  new QRCode(document.getElementById('qr'), {
    text: <?= json_encode($uri) ?>,
    width: 164, height: 164,
    colorDark: '#0d1117', colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
  });
  var inp = document.querySelector('input[name=codigo]');
  inp.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,6); });
</script>
</body>
</html>
