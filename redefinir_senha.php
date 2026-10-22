<?php
/* ============================================================
   Redefinir senha - valida o token do e-mail e grava a nova senha
   ============================================================ */
require_once __DIR__.'/config.php';
require_once __DIR__.'/senha_politica.php';
if (function_exists('senha_migrar')) { @senha_migrar(); }

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$hash  = $token !== '' ? hash('sha256', $token) : '';

$reg = null;
if ($hash !== '') {
  try {
    $st = db()->prepare("SELECT * FROM senha_reset WHERE token_hash = ? AND usado = 0 AND expira_em >= NOW() LIMIT 1");
    $st->execute([$hash]);
    $reg = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $reg = null; }
}
$valido = (bool)$reg;

$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valido) {
  $s1 = (string)($_POST['senha']  ?? '');
  $s2 = (string)($_POST['senha2'] ?? '');
  $erro = null;
  if ($s1 !== $s2) {
    $msg = 'As senhas não conferem.';
  } elseif (function_exists('senha_forte') && !senha_forte($s1, $erro)) {
    $msg = $erro ?: 'Senha fora da política.';
  } else {
    try {
      $hashSenha = password_hash($s1, PASSWORD_DEFAULT);
      db()->prepare("UPDATE analistas SET senha = ?, senha_alterada_em = NOW() WHERE id = ?")
          ->execute([$hashSenha, $reg['analista_id']]);
      db()->prepare("UPDATE senha_reset SET usado = 1 WHERE id = ?")->execute([$reg['id']]);
      $ok = true;
    } catch (Throwable $e) {
      $msg = 'Não foi possível salvar a nova senha. Tente novamente.';
    }
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Redefinir senha · Interweg Benefícios</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Calibri,Arial,sans-serif}
  body{background:#1e2761;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#2b323e}
  .card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:100%;max-width:420px;padding:34px 30px}
  .brand{font-size:12px;letter-spacing:2px;color:#00bfb3;font-weight:800;text-align:center;margin-bottom:6px}
  h1{font-size:22px;color:#1e2761;text-align:center;margin-bottom:8px}
  p.sub{font-size:14px;color:#5b6785;text-align:center;margin-bottom:22px;line-height:1.5}
  label{display:block;font-size:13px;font-weight:700;color:#1e2761;margin-bottom:5px;margin-top:14px}
  input{width:100%;border:1px solid #e3e7f0;border-radius:9px;padding:11px 12px;font-size:15px;color:#2b323e;outline:none}
  input:focus{border-color:#00bfb3;box-shadow:0 0 0 3px rgba(0,191,179,.15)}
  .btn{width:100%;background:#00bfb3;color:#fff;border:none;border-radius:9px;padding:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:20px}
  .btn:hover{background:#00a99e}
  .msg{border-radius:9px;padding:12px 14px;font-size:14px;margin-bottom:6px;line-height:1.5}
  .msg.err{background:#FDECEC;border:1px solid #F3B4B0;color:#a3271f}
  .msg.ok{background:#E9F9EF;border:1px solid #A7E3BD;color:#0d6b32}
  .dica{font-size:12px;color:#8b93a1;margin-top:10px;line-height:1.5}
  .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#5b6785;text-decoration:none}
  .btn-link{display:block;text-align:center;background:#00bfb3;color:#fff;border-radius:9px;padding:12px;font-weight:700;text-decoration:none;margin-top:18px}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">INTERWEG BENEFÍCIOS</div>
    <h1>Redefinir senha</h1>

    <?php if ($ok): ?>
      <div class="msg ok">Senha alterada com sucesso! Já pode entrar com a nova senha.</div>
      <a class="btn-link" href="login.php">Ir para o login</a>

    <?php elseif (!$valido): ?>
      <div class="msg err">Este link é inválido ou expirou. Peça um novo em "Esqueci minha senha".</div>
      <a class="back" href="esqueci_senha.php">← Solicitar novo link</a>

    <?php else: ?>
      <p class="sub">Crie uma nova senha para o seu acesso.</p>
      <?php if ($msg): ?><div class="msg err"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"/>
        <label>Nova senha</label>
        <input type="password" name="senha" placeholder="••••••••" required autofocus/>
        <label>Confirmar nova senha</label>
        <input type="password" name="senha2" placeholder="••••••••" required/>
        <div class="dica">Mínimo de 8 caracteres, com letra maiúscula, minúscula e um caractere especial (ex.: @ ! #).</div>
        <button class="btn" type="submit">Salvar nova senha</button>
      </form>
      <a class="back" href="login.php">← Voltar para o login</a>
    <?php endif; ?>
  </div>
</body>
</html>
