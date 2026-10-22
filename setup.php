<?php
// =========================================================
//  setup.php — Cria tabelas e usuários no banco de dados
//  APAGUE ESTE ARQUIVO após executar!
// =========================================================
require_once 'config.php';

$erros = [];
$ok = [];

$sqls = [
"CREATE TABLE IF NOT EXISTS usuarios (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100)  NOT NULL,
    email      VARCHAR(150)  NOT NULL UNIQUE,
    senha      VARCHAR(255)  NOT NULL,
    tipo       ENUM('admin','analista') DEFAULT 'analista',
    ativo      TINYINT(1) DEFAULT 1,
    criado_em  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS demandas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    grupo               VARCHAR(150),
    empresa             VARCHAR(200),
    seguradora          VARCHAR(150),
    ramo                ENUM('Saúde','Odonto'),
    tipo_demanda        VARCHAR(100),
    situacao            VARCHAR(100) DEFAULT 'Em Análise',
    recebimento         DATE,
    inicio_tratativa    DATE,
    proxima_acao        DATE,
    data_conclusao      DATE,
    dados_beneficiario  TEXT,
    assunto_email       VARCHAR(255),
    protocolo           VARCHAR(150),
    detalhamento        TEXT,
    analista_id         INT,
    analista_nome       VARCHAR(100),
    criado_em           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (analista_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"CREATE TABLE IF NOT EXISTS log_atividades (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT,
    usuario_nome VARCHAR(100),
    acao         VARCHAR(50),
    demanda_id   INT,
    detalhes     TEXT,
    criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

"INSERT IGNORE INTO usuarios (nome, email, senha, tipo) VALUES
('Administrador', 'admin@interweg-beneficios.com.br', '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Anna Luiza',    'anna@interweg-beneficios.com.br',     '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Bruna',         'bruna@interweg-beneficios.com.br',    '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Pâmela',        'pamela@interweg-beneficios.com.br',   '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Enf. Carolina', 'carolina@interweg-beneficios.com.br', '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Enf. Talita',   'talita@interweg-beneficios.com.br',   '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Ângela',        'angela@interweg-beneficios.com.br',   '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Enf. Isabella', 'isabella@interweg-beneficios.com.br', '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista'),
('Nayara',        'nayara@interweg-beneficios.com.br',   '\$2y\$12\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'analista')"
];

foreach ($sqls as $sql) {
    try {
        db()->exec($sql);
        $ok[] = 'OK: ' . substr(trim($sql), 0, 60) . '...';
    } catch (Exception $e) {
        $erros[] = 'ERRO: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Setup</title>
<style>body{font-family:sans-serif;padding:2rem;background:#0d1117;color:#e6edf3} .ok{color:#00c9a7} .err{color:#ff6b6b} h2{color:#fff}</style>
</head>
<body>
<h2>Setup — Controle de Demandas</h2>
<?php foreach($ok as $m): ?><p class="ok">✅ <?= htmlspecialchars($m) ?></p><?php endforeach; ?>
<?php foreach($erros as $m): ?><p class="err">❌ <?= htmlspecialchars($m) ?></p><?php endforeach; ?>
<?php if(empty($erros)): ?>
<p class="ok"><strong>✅ Tudo criado com sucesso! Acesse <a href="login.php" style="color:#00c9a7">login.php</a></strong></p>
<p style="color:#aaa">⚠️ Apague este arquivo (setup.php) do File Manager após usar!</p>
<?php endif; ?>
</body>
</html>

