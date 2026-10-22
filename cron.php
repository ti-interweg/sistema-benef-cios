<?php
// cron.php - Executar via Cron Job da Hostinger
// URL: https://interweg-beneficios.com.br/cron.php?key=CHAVE_SECRETA
$key = $_GET['key'] ?? '';
if($key !== 'intw2026cron'){http_response_code(403);exit('Acesso negado');}

require 'config.php';
header('Content-Type: application/json');

// Buscar configs
$cfgs = db()->query("SELECT chave,valor FROM configuracoes")->fetchAll(PDO::FETCH_KEY_PAIR);
$emailTo = $cfgs['email_notificacao'] ?? 'ti@interweg.com.br';
$dias = (int)($cfgs['alerta_dias'] ?? 1);
$resumo = $cfgs['resumo_dias'] ?? 'uteis';

$hoje = date('Y-m-d');
$diaSemana = date('N'); // 1=seg, 7=dom
$results = [];

// 1. ALERTAS DE PROXIMA ACAO
if($dias >= 0 && ($cfgs['alerta_dias'] ?? '') !== 'off'){
  $limite = date('Y-m-d', strtotime("+{$dias} days"));
  $stmt = db()->prepare("SELECT * FROM demandas WHERE proxima_acao BETWEEN ? AND ? AND situacao != 'Concluído' ORDER BY proxima_acao");
  $stmt->execute([$hoje, $limite]);
  $demandas = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  if(count($demandas) > 0){
    $body = '<html><body style="font-family:Arial,sans-serif;background:#0d1117;color:#e6edf3;padding:30px">';
    $body .= '<div style="max-width:700px;margin:0 auto;background:#161b22;border:1px solid #30363d;border-radius:12px;padding:30px">';
    $body .= '<h2 style="color:#f0883e;margin:0 0 20px">Alerta: '.count($demandas).' demanda(s) com ação próxima</h2>';
    $body .= '<table style="width:100%;border-collapse:collapse;color:#e6edf3">';
    $body .= '<tr style="border-bottom:1px solid #30363d"><th style="padding:8px;text-align:left;color:#8b949e">Empresa</th><th style="padding:8px;text-align:left;color:#8b949e">Tipo</th><th style="padding:8px;text-align:left;color:#8b949e">Próx. Ação</th><th style="padding:8px;text-align:left;color:#8b949e">Beneficiário</th></tr>';
    foreach($demandas as $d){
      $body .= '<tr style="border-bottom:1px solid #21262d"><td style="padding:8px">'.htmlspecialchars($d['empresa']).'</td><td style="padding:8px">'.htmlspecialchars($d['tipo_demanda']).'</td><td style="padding:8px;color:#f0883e">'.date('d/m/Y',strtotime($d['proxima_acao'])).'</td><td style="padding:8px">'.htmlspecialchars($d['dados_beneficiario']).'</td></tr>';
    }
    $body .= '</table></div></body></html>';
    $h = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: INTERWEG Benefícios <noreply@interweg-beneficios.com.br>\r\n";
    $sent = mail($emailTo, 'Alerta: '.count($demandas).' demanda(s) com ação próxima - '.date('d/m/Y'), $body, $h);
    $results['alertas'] = ['sent'=>$sent, 'total'=>count($demandas)];
  } else {
    $results['alertas'] = ['sent'=>false, 'total'=>0, 'msg'=>'Nenhuma demanda vencendo'];
  }
}

// 2. RESUMO DIARIO
if($resumo !== 'off'){
  $enviar = ($resumo === 'todos') || ($resumo === 'uteis' && $diaSemana <= 5);
  if($enviar){
    $stats = [];
    $stats['total'] = db()->query("SELECT COUNT(*) FROM demandas")->fetchColumn();
    $stats['em_analise'] = db()->query("SELECT COUNT(*) FROM demandas WHERE situacao='Em Análise'")->fetchColumn();
    $stats['concluidas'] = db()->query("SELECT COUNT(*) FROM demandas WHERE situacao='Concluído'")->fetchColumn();
    $stats['pendentes'] = $stats['total'] - $stats['em_analise'] - $stats['concluidas'];
    
    $body = '<html><body style="font-family:Arial,sans-serif;background:#0d1117;color:#e6edf3;padding:30px">';
    $body .= '<div style="max-width:600px;margin:0 auto;background:#161b22;border:1px solid #30363d;border-radius:12px;padding:30px">';
    $body .= '<h2 style="color:#00c9a7;margin:0 0 20px">Resumo Diário - '.date('d/m/Y').'</h2>';
    $body .= '<table style="width:100%;border-collapse:collapse;color:#e6edf3">';
    $body .= '<tr><td style="padding:10px;font-size:16px">Total de demandas</td><td style="padding:10px;font-size:20px;font-weight:bold;text-align:right">'.$stats['total'].'</td></tr>';
    $body .= '<tr><td style="padding:10px;color:#3fb950">Concluídas</td><td style="padding:10px;font-size:20px;font-weight:bold;text-align:right;color:#3fb950">'.$stats['concluidas'].'</td></tr>';
    $body .= '<tr><td style="padding:10px;color:#58a6ff">Em Análise</td><td style="padding:10px;font-size:20px;font-weight:bold;text-align:right;color:#58a6ff">'.$stats['em_analise'].'</td></tr>';
    $body .= '<tr><td style="padding:10px;color:#f0883e">Pendentes/Outros</td><td style="padding:10px;font-size:20px;font-weight:bold;text-align:right;color:#f0883e">'.$stats['pendentes'].'</td></tr>';
    $body .= '</table></div></body></html>';
    $h = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: INTERWEG Benefícios <noreply@interweg-beneficios.com.br>\r\n";
    $sent = mail($emailTo, 'Resumo Diário - Demandas Benefícios - '.date('d/m/Y'), $body, $h);
    $results['resumo'] = ['sent'=>$sent];
  } else {
    $results['resumo'] = ['sent'=>false, 'msg'=>'Fim de semana - nao envia'];
  }
}

// 3. VIRADA AUTOMÁTICA DE COMPETÊNCIA (dia 01 de cada mês)
//    Duplica todas as linhas da competência anterior para a competência atual.
//    Vidas, prêmio, status, datas de emissão/vencimento/envio ficam em branco.
if(date('j') === '1'){
    $meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $compAtual = $meses[(int)date('n')-1] . '/' . substr(date('Y'),-2);
    // mês anterior (se janeiro, vem de dezembro do ano passado)
    $mAnt = (int)date('n') - 1;
    $yAnt = (int)date('Y');
    if($mAnt === 0){ $mAnt = 12; $yAnt--; }
    $compAnterior = $meses[$mAnt-1] . '/' . substr((string)$yAnt, -2);

    // Só duplica se ainda não tiver linhas no mês atual (evita duplicar 2x)
    $stChk = db()->prepare("SELECT COUNT(*) FROM faturamento WHERE competencia = ?");
    $stChk->execute([$compAtual]);
    if(intval($stChk->fetchColumn()) === 0){
        // Virada automática rotaciona datas em +1 mês (mês anterior -> atual)
        // Fallback: se data_emissao/vencimento do mês anterior estiver NULL,
        // busca no cadastro de empresas (LEFT JOIN por CNPJ + contrato + empresa + seguradora).
        $stmt = db()->prepare("
            INSERT INTO faturamento
                (origem, ramo, seguradora, numero_contrato, cnpj, grupo, empresa, razao_social,
                 analista_id, analista_nome, competencia,
                 data_emissao, data_vencimento, status_envio, data_envio, emails_envio,
                 numero_vidas, premio_liquido, percentual_comissao, valor_previsto,
                 comissao_recebida, observacoes)
            SELECT
                f.origem, f.ramo, f.seguradora, f.numero_contrato, f.cnpj, f.grupo, f.empresa, f.razao_social,
                f.analista_id, f.analista_nome, ?,
                DATE_ADD(COALESCE(f.data_emissao,    e.data_emissao),    INTERVAL 1 MONTH),
                DATE_ADD(COALESCE(f.data_vencimento, e.data_vencimento), INTERVAL 1 MONTH),
                'Pendente emissão', NULL, f.emails_envio,
                NULL, NULL, f.percentual_comissao, NULL,
                NULL, ''
            FROM faturamento f
            LEFT JOIN empresas e
                ON f.cnpj = e.cnpj
               AND f.numero_contrato = e.numero_contrato
               AND f.empresa = e.nome
               AND (f.seguradora = e.seguradora
                    OR (f.seguradora IS NULL AND e.seguradora IS NULL))
               AND e.ativo = 1
            WHERE f.competencia = ?
        ");
        $stmt->execute([$compAtual, $compAnterior]);
        $results['virar_competencia'] = [
            'feita' => true,
            'de' => $compAnterior, 'para' => $compAtual,
            'criadas' => $stmt->rowCount()
        ];
    } else {
        $results['virar_competencia'] = ['feita' => false, 'msg' => "Já existem linhas em $compAtual"];
    }
}

echo json_encode(['ok'=>true,'results'=>$results,'date'=>date('d/m/Y H:i:s')]);
?>