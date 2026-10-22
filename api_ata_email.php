<?php
/* ============================================================
   API - Envio da Ata de Visita por e-mail ao cliente (Caminho 1)
   Monta o corpo do e-mail com o conteúdo da ata e envia via SMTP.
   ============================================================ */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_smtp.php';
auth();
header('Content-Type: application/json; charset=utf-8');

function _aj($d){ echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$id      = intval($body['id'] ?? 0);
$destRaw = trim($body['destinatarios'] ?? '');
$pdfB64  = (string)($body['pdf_base64'] ?? '');   // PDF gerado no navegador (opcional)

if (!$id)            _aj(['ok' => false, 'msg' => 'Ata inválida.']);
if ($destRaw === '') _aj(['ok' => false, 'msg' => 'Informe ao menos um e-mail de destino.']);

$dests = array_values(array_filter(array_map('trim', preg_split('/[;,]/', $destRaw)),
          fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL)));
if (!count($dests)) _aj(['ok' => false, 'msg' => 'Nenhum e-mail de destino válido.']);

try {
    $st = db()->prepare("SELECT * FROM atas_visitas WHERE id = ?");
    $st->execute([$id]);
    $ata = $st->fetch(PDO::FETCH_ASSOC);
    if (!$ata) _aj(['ok' => false, 'msg' => 'Ata não encontrada.']);

    $st2 = db()->prepare("SELECT * FROM atas_visitas_encaminhamentos WHERE ata_id = ? ORDER BY id ASC");
    $st2->execute([$id]);
    $encs = $st2->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    _aj(['ok' => false, 'msg' => 'Erro ao carregar a ata: ' . $e->getMessage()]);
}

$esc = fn($s) => htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
$dbr = function($d){ if(!$d) return ''; $p = explode('-', substr((string)$d,0,10)); return count($p)===3 ? "$p[2]/$p[1]/$p[0]" : (string)$d; };
$row = function($rot, $val) use ($esc){
    $val = trim((string)$val);
    if ($val === '') return '';
    return "<tr><td style='padding:7px 12px;background:#eef7f6;font-weight:700;color:#12a19a;width:210px;vertical-align:top;border:1px solid #e3e7f0'>".$esc($rot)."</td>".
           "<td style='padding:7px 12px;color:#2b323e;border:1px solid #e3e7f0'>".nl2br($esc($val))."</td></tr>";
};

$empresaLbl = ($ata['grupo'] && $ata['grupo'] !== $ata['empresa']) ? ($ata['grupo'].' / '.$ata['empresa']) : $ata['empresa'];
$dataLbl    = $dbr($ata['data_visita']) . ($ata['horario'] ? (' às '.$ata['horario']) : '');

$base    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'interweg-beneficios.com.br');
$logoUrl = $base . '/logo_cinza.png';
$html  = "<div style='font-family:Arial,Helvetica,sans-serif;max-width:700px;margin:0 auto;color:#2b323e'>";
$html .= "<div style='text-align:center;padding:16px 20px 8px;background:#fff;border-radius:10px 10px 0 0'>".
         "<img src='$logoUrl' alt='Interweg Seguros' style='height:54px'></div>";
$html .= "<div style='background:#12a19a;color:#fff;padding:12px 20px;text-align:center'>".
         "<div style='font-size:19px;font-weight:700'>Ata de Visita de Relacionamento</div></div>";
$html .= "<table style='width:100%;border-collapse:collapse'>";
$html .= $row('Empresa', $empresaLbl);
$html .= $row('Operadora', $ata['seguradora']);
$html .= $row('Data da visita', $dataLbl);
$html .= $row('Formato', $ata['formato']);
$html .= $row('Participantes (empresa)', $ata['participantes_empresa']);
$html .= $row('Participantes (Interweg)', $ata['participantes_interweg']);
$html .= $row('Participantes (operadora)', $ata['participantes_operadora']);
$html .= $row('Objetivo', $ata['objetivo']);
$html .= $row('Temas abordados', $ata['temas_abordados']);
$html .= $row('Observações', $ata['observacoes']);
$html .= $row('Percepção geral do cliente', $ata['percepcao']);
$html .= $row('Responsável pela visita', $ata['responsavel']);
$html .= "</table>";

if (count($encs)) {
    $html .= "<div style='margin-top:18px;font-weight:700;color:#12a19a;font-size:15px'>Encaminhamentos</div>";
    $html .= "<table style='width:100%;border-collapse:collapse;margin-top:6px'>";
    $html .= "<tr style='background:#f4f6fb'>".
             "<th style='padding:7px 12px;text-align:left;font-size:12px;border:1px solid #e3e7f0'>Ação</th>".
             "<th style='padding:7px 12px;text-align:left;font-size:12px;border:1px solid #e3e7f0'>Responsável</th>".
             "<th style='padding:7px 12px;text-align:left;font-size:12px;border:1px solid #e3e7f0'>Prazo</th>".
             "<th style='padding:7px 12px;text-align:left;font-size:12px;border:1px solid #e3e7f0'>Status</th></tr>";
    foreach ($encs as $en) {
        $html .= "<tr>".
                 "<td style='padding:7px 12px;border:1px solid #e3e7f0'>".$esc($en['descricao'] ?? '')."</td>".
                 "<td style='padding:7px 12px;border:1px solid #e3e7f0'>".$esc($en['responsavel'] ?? '')."</td>".
                 "<td style='padding:7px 12px;border:1px solid #e3e7f0'>".$dbr($en['prazo'] ?? '')."</td>".
                 "<td style='padding:7px 12px;border:1px solid #e3e7f0'>".$esc($en['status'] ?? '')."</td></tr>";
    }
    $html .= "</table>";
}

$html .= "<div style='margin-top:20px;font-size:13px;color:#5b6785;line-height:1.5'>Por favor, confira a ata acima. Se estiver de acordo, pode responder este e-mail com \"De acordo\", ou imprimir, assinar e nos devolver digitalizado.</div>";
$html .= "<div style='margin-top:16px;font-size:13px;color:#8b93a1'>".$esc($ata['analista_nome'])."<br>Interweg Benefícios</div>";
$html .= "</div>";

$assunto = 'Ata de Visita - ' . $ata['empresa'] . ' - ' . $dbr($ata['data_visita']);
$replyTo = trim($_SESSION['user_email'] ?? '');

// Anexo PDF (opcional) — gerado no navegador e enviado em base64/data-uri
$anexos = [];
if ($pdfB64 !== '') {
    $nomeArq = 'Ata_' . preg_replace('/[^A-Za-z0-9]+/', '_', (string)$ata['empresa']) . '.pdf';
    $anexos[] = ['filename' => $nomeArq, 'mime' => 'application/pdf', 'content_base64' => $pdfB64];
}

$erro = null;
$ok = enviarEmailSMTP(implode(',', $dests), $assunto, $html, $erro, $replyTo, $anexos);
if (!$ok) _aj(['ok' => false, 'msg' => 'Falha no envio: ' . ($erro ?: 'erro desconhecido')]);

// Log (cria a tabela se não existir)
try {
    db()->exec("CREATE TABLE IF NOT EXISTS ata_emails_log (
        id INT AUTO_INCREMENT PRIMARY KEY, ata_id INT, destinatarios VARCHAR(500),
        enviado_por VARCHAR(190), criado_em DATETIME) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->prepare("INSERT INTO ata_emails_log (ata_id, destinatarios, enviado_por, criado_em) VALUES (?,?,?,NOW())")
        ->execute([$id, implode(', ', $dests), trim($_SESSION['user_nome'] ?? '')]);
} catch (Throwable $e2) { /* log é opcional */ }

_aj(['ok' => true, 'msg' => 'Ata enviada para: ' . implode(', ', $dests)]);
