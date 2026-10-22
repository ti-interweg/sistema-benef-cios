<?php
require_once 'config.php';
require_once __DIR__ . '/senha_politica.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Migração idempotente (caso a coluna ainda não exista).
foreach (['analistas','usuarios'] as $_t) {
    try { db()->exec("ALTER TABLE $_t ADD COLUMN must_change_pw TINYINT NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
}
senha_migrar();

$erro = '';
$obrigatorio = !empty($_SESSION['must_change_pw']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n1 = $_POST['nova'] ?? '';
    $n2 = $_POST['conf'] ?? '';
    $erroForca = null;
    if (!senha_forte($n1, $erroForca)) {
        $erro = $erroForca;
    } elseif ($n1 !== $n2) {
        $erro = 'As senhas não conferem.';
    } else {
        $src  = (($_SESSION['user_src'] ?? 'analistas') === 'usuarios') ? 'usuarios' : 'analistas';
        $hash = password_hash($n1, PASSWORD_DEFAULT);
        try {
            db()->prepare("UPDATE $src SET senha = ?, must_change_pw = 0, senha_alterada_em = NOW() WHERE id = ?")
                ->execute([$hash, $_SESSION['user_id']]);
            $_SESSION['must_change_pw'] = 0;
            header('Location: index.php'); exit;
        } catch (Throwable $e) {
            $erro = 'Não foi possível salvar a nova senha. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Definir senha — Interweg Benefícios</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Tahoma,sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#161b22;border:1px solid #30363d;border-radius:18px;padding:36px 32px;width:100%;max-width:420px;box-shadow:0 24px 64px rgba(0,0,0,.5)}
  .title{font-size:20px;font-weight:700;margin-bottom:6px}
  .sub{font-size:13px;color:#8b949e;margin-bottom:22px;line-height:1.5}
  label{display:block;font-size:12px;font-weight:700;color:#8b949e;text-transform:uppercase;letter-spacing:.5px;margin:0 0 7px}
  .fg{margin-bottom:16px}
  input{width:100%;background:#0d1117;border:1.5px solid #30363d;border-radius:10px;padding:12px 14px;font-size:14px;color:#e6edf3;outline:none;font-family:inherit}
  input:focus{border-color:#00c9a7;box-shadow:0 0 0 3px rgba(0,201,167,.12)}
  .btn{width:100%;background:linear-gradient(135deg,#00c9a7,#00a589);color:#fff;border:none;border-radius:10px;padding:14px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px}
  .erro{background:rgba(198,40,40,.12);border:1px solid rgba(198,40,40,.3);color:#ff7070;border-radius:10px;padding:11px 14px;font-size:13px;margin-bottom:16px}
  .dica{font-size:12px;color:#8b949e;margin-top:10px}
  .out{display:block;text-align:center;margin-top:16px;font-size:12px;color:#8b949e;text-decoration:none}
</style>
</head>
<body>
  <div class="card">
    <div class="title">Defina sua senha</div>
    <div class="sub">
      <?= $obrigatorio
          ? 'Este é seu primeiro acesso (ou sua senha foi redefinida). Crie uma senha pessoal para continuar — ninguém mais terá acesso a ela.'
          : 'Crie uma nova senha pessoal para sua conta.' ?>
    </div>

    <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="fg">
        <label>Nova senha</label>
        <input type="password" name="nova" placeholder="mín. 8, com maiúscula, minúscula e especial" required autofocus/>
      </div>
      <div class="fg">
        <label>Confirmar nova senha</label>
        <input type="password" name="conf" placeholder="repita a senha" required/>
      </div>
      <button class="btn" type="submit">Salvar e entrar →</button>
      <div class="dica">Regra: mínimo 8 caracteres, com letra maiúscula, minúscula e um caractere especial (ex.: ! @ # $ %). A senha expira a cada 30 dias. Evite reutilizar senhas de outros sistemas.</div>
    </form>

    <a class="out" href="logout.php">Sair</a>
  </div>
</body>
</html>
