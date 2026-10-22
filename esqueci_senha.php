<?php
/* ============================================================
   Esqueci minha senha - solicita link de redefinicao por e-mail
   ============================================================ */
require_once __DIR__.'/config.php';
require_once __DIR__.'/mail_smtp.php';

// Garante a tabela de tokens (auto-migracao)
try {
  db()->exec("CREATE TABLE IF NOT EXISTS senha_reset (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analista_id INT NULL,
    login VARCHAR(190) NULL,
    token_hash CHAR(64) NOT NULL,
    criado_em DATETIME NOT NULL,
    expira_em DATETIME NOT NULL,
    usado TINYINT NOT NULL DEFAULT 0,
    INDEX (token_hash),
    INDEX (expira_em)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) { /* silencioso */ }

$msg = ''; $enviado = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $ident = trim($_POST['ident'] ?? '');
  if ($ident === '') {
    $msg = 'Informe o seu login ou e-mail.';
  } else {
    try {
      $st = db()->prepare("SELECT id, login, email, nome FROM analistas WHERE (login = ? OR email = ?) AND ativo = 1 LIMIT 1");
      $st->execute([$ident, $ident]);
      $u = $st->fetch(PDO::FETCH_ASSOC);
      if ($u && !empty($u['email'])) {
        // Invalida tokens anteriores ainda ativos desse usuario
        db()->prepare("UPDATE senha_reset SET usado = 1 WHERE analista_id = ? AND usado = 0")->execute([$u['id']]);

        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        db()->prepare("INSERT INTO senha_reset (analista_id, login, token_hash, criado_em, expira_em, usado)
                       VALUES (?,?,?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)")
            ->execute([$u['id'], $u['login'], $hash]);

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'interweg-beneficios.com.br';
        $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'])), '/');
        $link   = $scheme.'://'.$host.$dir.'/redefinir_senha.php?token='.$raw;

        $nome = htmlspecialchars($u['nome'] ?: 'usuário', ENT_QUOTES, 'UTF-8');
        $linkE = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
        $body =
          "<div style='font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2b323e;line-height:1.5'>".
          "<p>Olá, $nome!</p>".
          "<p>Recebemos um pedido para redefinir a senha do seu acesso ao <b>Sistema Interweg Benefícios</b>.</p>".
          "<p style='margin:22px 0'><a href='$linkE' style='background:#00bfb3;color:#ffffff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold'>Criar nova senha</a></p>".
          "<p style='font-size:13px;color:#5b6785'>Ou copie e cole este endereço no navegador:<br>$linkE</p>".
          "<p style='font-size:13px;color:#8b93a1;margin-top:20px'>Este link vale por <b>1 hora</b> e só pode ser usado uma vez. Se não foi você que solicitou, pode ignorar este e-mail com segurança.</p>".
          "</div>";
        $erroMail = null;
        @enviarEmailSMTP($u['email'], 'Redefinição de senha - Interweg Benefícios', $body, $erroMail);
      }
    } catch (Throwable $e) { /* nao expoe detalhes */ }

    // Resposta SEMPRE generica (nao revela se o login/e-mail existe)
    $enviado = true;
    $msg = 'Se houver uma conta com esse login ou e-mail, enviamos um link para redefinir a senha. Confira sua caixa de entrada (e também o spam).';
  }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Esqueci minha senha · Interweg Benefícios</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Calibri,Arial,sans-serif}
  body{background:#1e2761;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;color:#2b323e}
  .card{background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:100%;max-width:420px;padding:34px 30px}
  .brand{font-size:12px;letter-spacing:2px;color:#00bfb3;font-weight:800;text-align:center;margin-bottom:6px}
  h1{font-size:22px;color:#1e2761;text-align:center;margin-bottom:8px}
  p.sub{font-size:14px;color:#5b6785;text-align:center;margin-bottom:22px;line-height:1.5}
  label{display:block;font-size:13px;font-weight:700;color:#1e2761;margin-bottom:5px}
  input{width:100%;border:1px solid #e3e7f0;border-radius:9px;padding:11px 12px;font-size:15px;color:#2b323e;outline:none}
  input:focus{border-color:#00bfb3;box-shadow:0 0 0 3px rgba(0,191,179,.15)}
  .btn{width:100%;background:#00bfb3;color:#fff;border:none;border-radius:9px;padding:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:16px}
  .btn:hover{background:#00a99e}
  .msg{background:#E9F9EF;border:1px solid #A7E3BD;color:#0d6b32;border-radius:9px;padding:12px 14px;font-size:14px;margin-bottom:18px;line-height:1.5}
  .msg.err{background:#FDECEC;border-color:#F3B4B0;color:#a3271f}
  .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#5b6785;text-decoration:none}
</style>
</head>
<body>
  <div class="card">
    <div class="brand">INTERWEG BENEFÍCIOS</div>
    <h1>Esqueci minha senha</h1>
    <?php if ($enviado): ?>
      <div class="msg"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
      <a class="back" href="login.php">← Voltar para o login</a>
    <?php else: ?>
      <p class="sub">Informe seu login ou e-mail cadastrado. Enviaremos um link para você criar uma nova senha.</p>
      <?php if ($msg): ?><div class="msg err"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
      <form method="POST">
        <label>Login ou e-mail</label>
        <input type="text" name="ident" placeholder="seu login ou e-mail" required autofocus value="<?= htmlspecialchars($_POST['ident'] ?? '', ENT_QUOTES, 'UTF-8') ?>"/>
        <button class="btn" type="submit">Enviar link de redefinição</button>
      </form>
      <a class="back" href="login.php">← Voltar para o login</a>
    <?php endif; ?>
  </div>
</body>
</html>
