<?php
/**
 * =====================================================
 * LIB — ENVIO DO BACKUP DO FATURAMENTO ATUAL POR E-MAIL
 * =====================================================
 * Gera o Excel (.xlsx) do Faturamento Atual (faturamento_backup) — as mesmas
 * colunas do botão "Exportar Excel" — e envia por e-mail (SMTP) com o arquivo
 * em anexo. Usada tanto pela automação (cron.php) quanto pelo "Enviar agora".
 *
 * Uso:
 *   require_once __DIR__ . '/lib_faturamento_backup_email.php';
 *   $erro = ''; $info = [];
 *   $ok = fatBackupEnviar('beneficios3@interweg.com.br', $erro, $info);
 */

// IMPORTANTE: o mail_smtp.php precisa ser carregado no ESCOPO GLOBAL (aqui no
// topo), e não dentro de uma função. Ele define $SMTP_CONFIG como variável
// global e enviarEmailSMTP() a lê via `global $SMTP_CONFIG`. Se o require
// fosse feito dentro da função, $SMTP_CONFIG viraria local e a senha "sumiria"
// (erro "SMTP não configurado").
if (is_file(__DIR__ . '/mail_smtp.php')) {
    require_once __DIR__ . '/mail_smtp.php';
}

if (!function_exists('fatBackupGerarXlsx')) {

    /** Gera o conteúdo binário do .xlsx do Faturamento (Atual ou Outubro). Preenche $info. */
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
                ['s', (string)($r['origem'] ?? '')],
                ['s', (string)($r['ramo'] ?? '')],
                ['s', (string)($r['seguradora'] ?? '')],
                ['s', (string)($r['numero_contrato'] ?? '')],
                ['s', (string)($r['cnpj'] ?? '')],
                ['s', (string)($r['grupo'] ?? '')],
                ['s', (string)($r['empresa'] ?? '')],
                ['s', (string)($r['analista_nome'] ?? '')],
                ['s', (string)($r['competencia'] ?? '')],
                ['s', (string)($r['competencia_real'] ?? '')],
                ['s', $fmtData($r['data_emissao'])],
                ['s', $fmtData($r['data_vencimento'])],
                ['s', (string)($r['status_envio'] ?? '')],
                ['s', $fmtData($r['data_envio'])],
                is_numeric($r['numero_vidas']) ? ['n', (int)$r['numero_vidas']] : ['s', ''],
                $premio !== null ? ['n', $premio] : ['s', ''],
                ['s', $pct($r['percentual_comissao'])],
                is_numeric($r['valor_previsto'])    ? ['n', (float)$r['valor_previsto']]    : ['s', ''],
                is_numeric($r['comissao_recebida']) ? ['n', (float)$r['comissao_recebida']] : ['s', ''],
            ];
        }

        $colLetra = function ($n) {
            $s = '';
            while ($n > 0) { $r = ($n - 1) % 26; $s = chr(65 + $r) . $s; $n = intdiv($n - 1, 26); }
            return $s;
        };
        $xmlEsc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $sheetRows = '';
        foreach ($matriz as $i => $linha) {
            $rNum = $i + 1;
            $cells = '';
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
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData></worksheet>';
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
        $relsRoot = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $rotulo = ($tabela === 'faturamento_outubro') ? 'Faturamento Outubro' : 'Faturamento Atual';
        $slug   = ($tabela === 'faturamento_outubro') ? 'faturamento_outubro' : 'faturamento_atual';
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $rotulo . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';

        $tmp = tempnam(sys_get_temp_dir(), 'fat') . '.xlsx';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Não foi possível criar o arquivo XLSX no servidor.');
        }
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $relsRoot);
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);

        $info['linhas']      = count($rows);
        $info['soma_premio'] = $somaPremio;
        $info['arquivo']     = $slug . '_' . date('Y-m-d') . '.xlsx';
        $info['rotulo']      = $rotulo;
        return $bin;
    }

    /** Gera e envia o backup por e-mail. Retorna true/false; preenche $erro e $info. */
    function fatBackupEnviar($destino, &$erro = null, array &$info = [], $tabela = 'faturamento_backup') {
        $destino = trim((string)$destino);
        if ($destino === '' || !filter_var(explode(',', $destino)[0], FILTER_VALIDATE_EMAIL)) {
            $erro = 'Destinatário inválido.'; return false;
        }
        try {
            $xlsx = fatBackupGerarXlsx($info, $tabela);
        } catch (Throwable $e) {
            $erro = 'Erro ao gerar o Excel: ' . $e->getMessage(); return false;
        }

        // mail_smtp.php já foi carregado no topo desta lib (escopo global).
        if (!function_exists('enviarEmailSMTP')) { $erro = 'Função SMTP indisponível (mail_smtp.php não carregado).'; return false; }

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
          . '<p style="color:#656d76;font-size:12px">E-mail automático · Sistema de Benefícios · INTERWEG</p>'
          . '</div>';

        $anexos = [[
            'filename'       => $info['arquivo'] ?? ('faturamento_atual_' . date('Y-m-d') . '.xlsx'),
            'mime'           => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_base64' => base64_encode($xlsx),
        ]];
        $assunto = 'Backup ' . $rotulo . ' — ' . $dataBR;

        $erro = '';
        return enviarEmailSMTP($destino, $assunto, $corpoHtml, $erro, '', $anexos);
    }
}
