<?php
/**
 * =====================================================
 * LIB AUTOSSUFICIENTE — ENVIO DO BACKUP DO FATURAMENTO POR E-MAIL
 * =====================================================
 * NÃO depende de $SMTP_CONFIG global nem da função enviarEmailSMTP().
 * Lê as credenciais direto do mail_smtp.php (e dos arquivos smtp_senha.txt /
 * smtp_usuario.txt, se existirem) e faz o handshake SMTP por conta própria.
 * Isso evita o problema de escopo/OPcache que dava "SMTP não configurado".
 *
 *   require_once __DIR__ . '/fat_email_lib.php';
 *   $erro=''; $info=[];
 *   $ok = fatBackupEnviar('beneficios3@interweg.com.br', $erro, $info, 'faturamento_outubro');
 */

if (!function_exists('fat_smtp_cfg')) {
    /** Lê as credenciais SMTP do mail_smtp.php + overrides .txt (sem depender de global). */
    function fat_smtp_cfg() {
        $cfg = [
            'host' => 'smtp.hostinger.com', 'port' => 465, 'secure' => 'ssl',
            'user' => 'noreply@interweg-beneficios.com.br', 'pass' => '',
            'from_name' => 'INTERWEG Benefícios',
        ];
        $f = __DIR__ . '/mail_smtp.php';
        if (is_file($f)) {
            $src = (string) @file_get_contents($f);
            // só a linha de CONFIG (tem '=>'); as verificações usam '===' e não casam
            if (preg_match("/'host'\\s*=>\\s*'([^']*)'/", $src, $m))      $cfg['host'] = $m[1];
            if (preg_match("/'port'\\s*=>\\s*(\\d+)/", $src, $m))          $cfg['port'] = (int)$m[1];
            if (preg_match("/'secure'\\s*=>\\s*'([^']*)'/", $src, $m))     $cfg['secure'] = $m[1];
            if (preg_match("/'user'\\s*=>\\s*'([^']*)'/", $src, $m))       $cfg['user'] = $m[1];
            if (preg_match("/'pass'\\s*=>\\s*'([^']*)'/", $src, $m))       $cfg['pass'] = $m[1];
            if (preg_match("/'from_name'\\s*=>\\s*'([^']*)'/", $src, $m))  $cfg['from_name'] = $m[1];
        }
        $limpa = function ($s) {
            $s = (string)$s;
            $s = str_replace(["\xEF\xBB\xBF", "\xFF\xFE", "\xFE\xFF"], '', $s);
            $s = trim($s); $s = trim($s, "\"'"); return trim($s);
        };
        foreach ([dirname(__DIR__) . '/smtp_senha.txt', __DIR__ . '/smtp_senha.txt'] as $pf) {
            if (is_file($pf)) { $p = $limpa(@file_get_contents($pf)); if ($p !== '') { $cfg['pass'] = $p; break; } }
        }
        foreach ([dirname(__DIR__) . '/smtp_usuario.txt', __DIR__ . '/smtp_usuario.txt'] as $uf) {
            if (is_file($uf)) { $u = $limpa(@file_get_contents($uf)); if ($u !== '') { $cfg['user'] = $u; break; } }
        }
        // ignora o texto de exemplo
        if ($cfg['pass'] === 'COLOQUE_A_SENHA_AQUI') $cfg['pass'] = '';
        return $cfg;
    }
}

if (!function_exists('fat_smtp_enviar')) {
    /** Handshake SMTP próprio, com anexos. Retorna true/false; preenche $erro. */
    function fat_smtp_enviar($to, $subject, $htmlBody, &$erro, $attachments = []) {
        $cfg = fat_smtp_cfg();
        if (empty($cfg['pass'])) { $erro = 'SMTP sem senha (verifique mail_smtp.php ou smtp_senha.txt).'; return false; }

        $destinatarios = array_filter(array_map('trim', explode(',', $to)));
        if (!count($destinatarios)) { $erro = 'Sem destinatário válido.'; return false; }

        $host = ($cfg['secure'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
        $fp = @stream_socket_client($host . ':' . $cfg['port'], $errno, $errstr, 20,
                STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
        if (!$fp) { $erro = "Conexão SMTP falhou: $errstr ($errno)"; return false; }
        stream_set_timeout($fp, 20);

        $leia = function () use ($fp) { $d=''; while ($l = fgets($fp, 515)) { $d .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $d; };
        $cmd  = function ($c) use ($fp, $leia) { fputs($fp, $c . "\r\n"); return $leia(); };
        $ok   = function ($r, $code) { return substr(trim($r), 0, 3) === (string)$code; };

        $r = $leia();
        if (!$ok($r, 220)) { $erro = 'SMTP não respondeu: ' . trim($r); fclose($fp); return false; }
        $hl = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $r = $cmd('EHLO ' . $hl);
        if (!$ok($r, 250)) { $cmd('HELO ' . $hl); }
        if ($cfg['secure'] === 'tls') {
            $r = $cmd('STARTTLS');
            if (!$ok($r, 220)) { $erro = 'STARTTLS falhou: ' . trim($r); fclose($fp); return false; }
            @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd('EHLO ' . $hl);
        }
        $r = $cmd('AUTH LOGIN');
        if (!$ok($r, 334)) { $erro = 'AUTH não suportado: ' . trim($r); fclose($fp); return false; }
        $r = $cmd(base64_encode($cfg['user']));
        if (!$ok($r, 334)) { $erro = 'Usuário SMTP recusado: ' . trim($r); fclose($fp); return false; }
        $r = $cmd(base64_encode($cfg['pass']));
        if (!$ok($r, 235)) { $erro = 'Senha SMTP recusada (535): ' . trim($r); fclose($fp); return false; }

        $r = $cmd('MAIL FROM:<' . $cfg['user'] . '>');
        if (!$ok($r, 250)) { $erro = 'MAIL FROM recusado: ' . trim($r); fclose($fp); return false; }
        foreach ($destinatarios as $dest) {
            $r = $cmd('RCPT TO:<' . $dest . '>');
            if (!$ok($r, 250) && !$ok($r, 251)) { $erro = "Destinatário recusado ($dest): " . trim($r); fclose($fp); return false; }
        }
        $r = $cmd('DATA');
        if (!$ok($r, 354)) { $erro = 'DATA recusado: ' . trim($r); fclose($fp); return false; }

        $fromName = '=?UTF-8?B?' . base64_encode($cfg['from_name']) . '?=';
        $headers  = "From: $fromName <" . $cfg['user'] . ">\r\n";
        $headers .= "To: " . implode(', ', $destinatarios) . "\r\n";
        $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        $atoks = [];
        foreach ((array)$attachments as $att) {
            $c = (string)($att['content_base64'] ?? '');
            if (strpos($c, 'base64,') !== false) $c = substr($c, strpos($c, 'base64,') + 7);
            $c = preg_replace('/\s+/', '', $c);
            if ($c === '') continue;
            $atoks[] = [
                'fn' => preg_replace('/[\r\n"]/', '', (string)($att['filename'] ?? 'anexo.xlsx')),
                'mt' => (string)($att['mime'] ?? 'application/octet-stream'),
                'c'  => $c,
            ];
        }

        if (count($atoks)) {
            $b = 'iw_' . md5(uniqid('', true));
            $headers .= "Content-Type: multipart/mixed; boundary=\"$b\"\r\n";
            $body  = "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $body .= rtrim(chunk_split(base64_encode($htmlBody))) . "\r\n";
            foreach ($atoks as $a) {
                $body .= "--$b\r\nContent-Type: " . $a['mt'] . "; name=\"" . $a['fn'] . "\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $a['fn'] . "\"\r\n\r\n";
                $body .= rtrim(chunk_split($a['c'])) . "\r\n";
            }
            $body .= "--$b--";
            $msg = $headers . "\r\n" . $body . "\r\n.";
        } else {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
            $msg = $headers . "\r\n" . rtrim(chunk_split(base64_encode($htmlBody))) . "\r\n.";
        }
        $r = $cmd($msg);
        if (!$ok($r, 250)) { $erro = 'Envio recusado: ' . trim($r); fclose($fp); return false; }
        $cmd('QUIT'); fclose($fp);
        return true;
    }
}

if (!function_exists('fatBackupGerarXlsx')) {
    /** Gera o .xlsx do Faturamento (Atual ou Outubro). Preenche $info. */
    function fatBackupGerarXlsx(array &$info = [], $tabela = 'faturamento_backup') {
        $tabela = in_array($tabela, ['faturamento_backup', 'faturamento_outubro'], true) ? $tabela : 'faturamento_backup';
        $rows = db()->query("
            SELECT id, origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa,
                   analista_nome, competencia, competencia_real, data_emissao, data_vencimento,
                   status_envio, data_envio, numero_vidas, premio_liquido, percentual_comissao,
                   valor_previsto, comissao_recebida
            FROM $tabela
            ORDER BY analista_nome, empresa
        ")->fetchAll(PDO::FETCH_ASSOC);

        $headers = ['ID','Origem','Ramo','Seguradora','Contrato','CNPJ','Grupo','Empresa','Analista',
                    'Mês de envio','Competência','Emissão','Vencimento','Status','Envio','Vidas',
                    'Prêmio Líquido','% Comissão','Prev. Comissão','Com. Recebida'];
        $fmtData = function ($d) {
            if (!$d) return '';
            $s = substr((string)$d, 0, 10);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return $m[3] . '/' . $m[2] . '/' . $m[1];
            return $s;
        };
        $pct = function ($v) {
            if ($v === null || $v === '' || !is_numeric($v)) return '';
            return str_replace('.', ',', (string)round($v * 100, 2)) . '%';
        };
        $matriz = [];
        $matriz[] = array_map(fn($h) => ['s', $h], $headers);
        $somaPremio = 0.0;
        foreach ($rows as $r) {
            $premio = is_numeric($r['premio_liquido']) ? (float)$r['premio_liquido'] : null;
            if ($premio !== null) $somaPremio += $premio;
            $matriz[] = [
                is_numeric($r['id']) ? ['n', (int)$r['id']] : ['s', (string)$r['id']],
                ['s', (string)($r['origem'] ?? '')], ['s', (string)($r['ramo'] ?? '')],
                ['s', (string)($r['seguradora'] ?? '')], ['s', (string)($r['numero_contrato'] ?? '')],
                ['s', (string)($r['cnpj'] ?? '')], ['s', (string)($r['grupo'] ?? '')],
                ['s', (string)($r['empresa'] ?? '')], ['s', (string)($r['analista_nome'] ?? '')],
                ['s', (string)($r['competencia'] ?? '')], ['s', (string)($r['competencia_real'] ?? '')],
                ['s', $fmtData($r['data_emissao'])], ['s', $fmtData($r['data_vencimento'])],
                ['s', (string)($r['status_envio'] ?? '')], ['s', $fmtData($r['data_envio'])],
                is_numeric($r['numero_vidas']) ? ['n', (int)$r['numero_vidas']] : ['s', ''],
                $premio !== null ? ['n', $premio] : ['s', ''],
                ['s', $pct($r['percentual_comissao'])],
                is_numeric($r['valor_previsto'])    ? ['n', (float)$r['valor_previsto']]    : ['s', ''],
                is_numeric($r['comissao_recebida']) ? ['n', (float)$r['comissao_recebida']] : ['s', ''],
            ];
        }
        $colLetra = function ($n) { $s=''; while ($n>0){ $r=($n-1)%26; $s=chr(65+$r).$s; $n=intdiv($n-1,26);} return $s; };
        $xmlEsc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $sheetRows = '';
        foreach ($matriz as $i => $linha) {
            $rNum = $i + 1; $cells = '';
            foreach ($linha as $j => $cell) {
                $ref = $colLetra($j + 1) . $rNum;
                if ($cell[0] === 'n' && $cell[1] !== '' && is_numeric($cell[1])) {
                    $cells .= '<c r="' . $ref . '"><v>' . $cell[1] . '</v></c>';
                } else {
                    $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $xmlEsc($cell[1]) . '</t></is></c>';
                }
            }
            $sheetRows .= '<row r="' . $rNum . '">' . $cells . '</row>';
        }
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>';
        $rotulo = ($tabela === 'faturamento_outubro') ? 'Faturamento Outubro' : 'Faturamento Setembro';
        $slug   = ($tabela === 'faturamento_outubro') ? 'faturamento_outubro' : 'faturamento_atual';
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $rotulo . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'fat') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o XLSX no servidor.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);

        $info['linhas'] = count($rows); $info['soma_premio'] = $somaPremio;
        $info['arquivo'] = $slug . '_' . date('Y-m-d') . '.xlsx';
        $info['rotulo']  = $rotulo;
        return $bin;
    }

    /** Gera e envia o backup por e-mail (handshake SMTP próprio). */
    function fatBackupEnviar($destino, &$erro = null, array &$info = [], $tabela = 'faturamento_backup') {
        $destino = trim((string)$destino);
        if ($destino === '' || !filter_var(explode(',', $destino)[0], FILTER_VALIDATE_EMAIL)) {
            $erro = 'Destinatário inválido.'; return false;
        }
        try { $xlsx = fatBackupGerarXlsx($info, $tabela); }
        catch (Throwable $e) { $erro = 'Erro ao gerar o Excel: ' . $e->getMessage(); return false; }

        $rotulo    = $info['rotulo'] ?? 'Faturamento Atual';
        $dataBR    = date('d/m/Y');
        $premioFmt = 'R$ ' . number_format($info['soma_premio'] ?? 0, 2, ',', '.');
        $corpoHtml =
            '<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2328;font-size:14px;line-height:1.55">'
          . '<p>Olá, Letícia,</p>'
          . '<p>Segue em anexo o backup do <b>' . $rotulo . '</b> de hoje (' . $dataBR . ').</p>'
          . '<table style="border-collapse:collapse;margin:10px 0">'
          . '<tr><td style="padding:4px 10px;color:#656d76">Linhas</td><td style="padding:4px 10px;font-weight:700">' . (int)($info['linhas'] ?? 0) . '</td></tr>'
          . '<tr><td style="padding:4px 10px;color:#656d76">Soma do prêmio líquido</td><td style="padding:4px 10px;font-weight:700">' . $premioFmt . '</td></tr>'
          . '</table>'
          . '<p style="color:#656d76;font-size:12px">E-mail automático · Sistema de Benefícios · INTERWEG</p></div>';

        $anexos = [[
            'filename'       => $info['arquivo'] ?? ('faturamento_' . date('Y-m-d') . '.xlsx'),
            'mime'           => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_base64' => base64_encode($xlsx),
        ]];
        $erro = '';
        return fat_smtp_enviar($destino, 'Backup ' . $rotulo . ' — ' . $dataBR, $corpoHtml, $erro, $anexos);
    }
}
