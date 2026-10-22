<?php
// =========================================================
//  backfill_datas_faturamento.php
//  Script único para preencher data_emissao / data_vencimento
//  em faturamentos existentes que estão NULL, puxando do
//  cadastro de empresas (base mestre).
//
//  Acesso: https://interweg-beneficios.com.br/backfill_datas_faturamento.php?key=intw2026fix
//  REMOVER após uso.
// =========================================================

$key = $_GET['key'] ?? '';
if ($key !== 'intw2026fix') { http_response_code(403); exit('Acesso negado'); }

require __DIR__ . '/config.php';
header('Content-Type: text/html; charset=UTF-8');

$dryRun = isset($_GET['dry']) && $_GET['dry'] === '1';

echo "<pre style='font-family:Consolas,monospace;background:#0d1117;color:#e6edf3;padding:20px;line-height:1.6'>";
echo "BACKFILL — Datas de Emissão e Vencimento em Faturamento\n";
echo "Modo: " . ($dryRun ? "DRY-RUN (apenas conta, não altera)" : "EXECUÇÃO REAL") . "\n";
echo str_repeat('=', 60) . "\n\n";

try {
    // 1) Faturamentos com datas NULL
    $totFat = (int) db()->query("
        SELECT COUNT(*) FROM faturamento
        WHERE data_emissao IS NULL OR data_vencimento IS NULL
    ")->fetchColumn();
    echo "Faturamentos sem data (emissão OU vencimento): $totFat\n\n";

    if ($totFat === 0) {
        echo "Nada a fazer. Tudo preenchido.\n</pre>";
        exit;
    }

    // 2) Match por cnpj + numero_contrato + empresa (+ seguradora)
    //    Atualiza só onde o faturamento estiver NULL — não sobrescreve nada.
    $sql = "
        UPDATE faturamento f
        INNER JOIN empresas e
            ON f.cnpj = e.cnpj
           AND f.numero_contrato = e.numero_contrato
           AND f.empresa = e.nome
           AND (f.seguradora = e.seguradora
                OR (f.seguradora IS NULL AND e.seguradora IS NULL))
        SET
            f.data_emissao    = COALESCE(f.data_emissao,    e.data_emissao),
            f.data_vencimento = COALESCE(f.data_vencimento, e.data_vencimento)
        WHERE e.ativo = 1
          AND e.data_emissao IS NOT NULL
          AND (f.data_emissao IS NULL OR f.data_vencimento IS NULL)
    ";

    if ($dryRun) {
        // simula contando quantas linhas seriam afetadas
        $sqlCount = "
            SELECT COUNT(*) FROM faturamento f
            INNER JOIN empresas e
                ON f.cnpj = e.cnpj
               AND f.numero_contrato = e.numero_contrato
               AND f.empresa = e.nome
               AND (f.seguradora = e.seguradora
                    OR (f.seguradora IS NULL AND e.seguradora IS NULL))
            WHERE e.ativo = 1
              AND e.data_emissao IS NOT NULL
              AND (f.data_emissao IS NULL OR f.data_vencimento IS NULL)
        ";
        $afetadas = (int) db()->query($sqlCount)->fetchColumn();
        echo "Linhas que SERIAM atualizadas: $afetadas\n";
    } else {
        $afetadas = db()->exec($sql);
        echo "Linhas atualizadas: $afetadas\n";
    }

    // 3) Quantos ainda continuam sem data depois (sem cadastro correspondente com data)
    $restam = (int) db()->query("
        SELECT COUNT(*) FROM faturamento
        WHERE data_emissao IS NULL OR data_vencimento IS NULL
    ")->fetchColumn();
    echo "\nFaturamentos ainda sem data após o backfill: $restam\n";

    if ($restam > 0) {
        echo "\nAmostra dos que continuam sem data (até 20 linhas):\n";
        $rows = db()->query("
            SELECT id, grupo, empresa, cnpj, numero_contrato, seguradora, competencia
            FROM faturamento
            WHERE data_emissao IS NULL OR data_vencimento IS NULL
            ORDER BY id DESC LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            echo sprintf(
                "  #%-6d %-15s %-30s CNPJ:%s  Contrato:%s  Seg:%s  Comp:%s\n",
                $r['id'],
                substr($r['grupo'] ?? '', 0, 14),
                substr($r['empresa'] ?? '', 0, 29),
                $r['cnpj'] ?? '',
                $r['numero_contrato'] ?? '',
                $r['seguradora'] ?? '',
                $r['competencia'] ?? ''
            );
        }
        echo "\nEsses não têm cadastro de empresa correspondente com data preenchida.\n";
        echo "Solução: edite o cadastro da empresa, preencha as datas e salve — o sistema\n";
        echo "já está corrigido para propagar via COALESCE.\n";
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "Concluído em " . date('d/m/Y H:i:s') . "\n";
    echo "\nIMPORTANTE: APAGUE este arquivo após o uso.\n";

} catch (Throwable $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
echo "</pre>";
