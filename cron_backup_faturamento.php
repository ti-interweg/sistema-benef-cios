<?php
/**
 * =====================================================
 * CRON — BACKUP DIÁRIO DO FATURAMENTO ATUAL POR E-MAIL
 * =====================================================
 * Agende este arquivo na Hostinger para rodar DE HORA EM HORA. O envio só
 * acontece de acordo com o que está configurado DENTRO do sistema em
 * "Faturamento Atual > Automação" (ligado/desligado, horário e destinatário).
 * Há trava de "1x por dia", então rodar de hora em hora é seguro.
 *
 * Agendar (modo PHP): public_html/cron_backup_faturamento.php   (Minuto 0, toda hora)
 *
 * Teste manual (envia na hora, ignora config):
 *   https://interweg-beneficios.com.br/cron_backup_faturamento.php?key=intw2026cron&to=voce@exemplo.com
 */

$isCli = (php_sapi_name() === 'cli');
$args  = $_GET;
if ($isCli && !empty($argv)) {
    foreach ($argv as $a) {
        if (strpos($a, '=') !== false) { list($k, $v) = explode('=', $a, 2); $args[$k] = $v; }
    }
}
if (!$isCli) {
    if (($args['key'] ?? '') !== 'intw2026cron') { http_response_code(403); exit('Acesso negado'); }
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/fat_email_lib.php';

$hoje = date('Y-m-d');

// ── MODO TESTE: ?to=email → envia na hora, para esse e-mail, ignorando a config ──
$toTeste = trim($args['to'] ?? '');
if ($toTeste !== '') {
    $erro = ''; $info = [];
    $ok = fatBackupEnviar($toTeste, $erro, $info);
    echo json_encode($ok
        ? ['ok' => true, 'modo' => 'teste', 'enviado_para' => $toTeste, 'linhas' => $info['linhas'] ?? 0]
        : ['ok' => false, 'modo' => 'teste', 'msg' => $erro ?: 'Falha no envio']);
    exit;
}

// ── MODO AUTOMÁTICO: lê a configuração salva no sistema ──
try {
    $cfg = db()->query("SELECT chave, valor FROM configuracoes WHERE chave LIKE 'fat_auto_%' OR chave LIKE 'fatout_auto_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $cfg = []; }

$agora   = date('H:i');
$force   = !empty($args['force']);
$fontes  = [
    'atual'   => ['prefixo' => 'fat_auto_',    'tabela' => 'faturamento_backup'],
    'outubro' => ['prefixo' => 'fatout_auto_', 'tabela' => 'faturamento_outubro'],
];
$resultado = [];

foreach ($fontes as $nome => $f) {
    $p = $f['prefixo'];
    $ativo    = ($cfg[$p.'ativo'] ?? '0') === '1';
    $horaAlvo = $cfg[$p.'hora']   ?? '18:00';
    $dest     = $cfg[$p.'dest']   ?? 'beneficios3@interweg.com.br';
    $ultimo   = $cfg[$p.'ultimo'] ?? '';

    if (!$ativo)            { $resultado[$nome] = 'desligada'; continue; }
    if ($ultimo === $hoje)  { $resultado[$nome] = 'já enviado hoje'; continue; }
    if (!$force && $agora < $horaAlvo) { $resultado[$nome] = "aguardando $horaAlvo (agora $agora)"; continue; }

    $erro = ''; $info = [];
    $ok = fatBackupEnviar($dest, $erro, $info, $f['tabela']);
    if ($ok) {
        try {
            db()->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE valor = VALUES(valor)")->execute([$p.'ultimo', $hoje]);
        } catch (Throwable $e) {}
        $resultado[$nome] = ['ok' => true, 'enviado_para' => $dest, 'linhas' => $info['linhas'] ?? 0];
    } else {
        $resultado[$nome] = ['ok' => false, 'erro' => $erro ?: 'falha no envio'];
    }
}

echo json_encode(['ok' => true, 'resultado' => $resultado, 'data' => date('d/m/Y H:i')]);
