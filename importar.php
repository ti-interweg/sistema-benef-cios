<?php
$conn = new mysqli("localhost", "u207808890_beneficios", "COLOQUE_A_SENHA_AQUI", "u207808890_beneficios");

function formatarData($data) {
    if (!$data) return null;
    $d = DateTime::createFromFormat('d/m/Y', trim($data));
    return $d ? $d->format('Y-m-d') : null;
}

$arquivo = fopen("dados.csv", "r");

while (($linha = fgetcsv($arquivo, 1000, ",")) !== FALSE) {

    //  Corrige acentuação
    $linha = array_map(function ($v) {
        return mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
    }, $linha);

    //  Mapeamento
    $grupo              = $linha[0] ?? null;
    $empresa            = $linha[1] ?? null;
    $seguradora         = $linha[2] ?? null;
    $ramo               = str_replace("Saúde", "Saude", $linha[3] ?? null);
    $tipo_demanda       = $linha[4] ?? null;
    $situacao           = str_replace("Análise", "Analise", $linha[5] ?? null);
    $recebimento        = formatarData($linha[6] ?? null);
    $inicio_tratativa   = formatarData($linha[7] ?? null);
    $proxima_acao       = formatarData($linha[8] ?? null);
    $data_conclusao     = formatarData($linha[9] ?? null);
    $dados_beneficiario = $linha[10] ?? null;
    $descricao_demanda  = $linha[11] ?? null;
    $assunto_email      = $linha[12] ?? null;
    $protocolo          = $linha[13] ?? null;
    $detalhamento       = $linha[14] ?? null;

    //  Analista fixo (exemplo Anna)
    $analista_id = 2;
    $analista_nome = 'Anna Luiza';

    $stmt = $conn->prepare("INSERT INTO demandas (
        grupo, empresa, seguradora, ramo, tipo_demanda, situacao,
        recebimento, inicio_tratativa, proxima_acao, data_conclusao,
        dados_beneficiario, descricao_demanda, assunto_email, protocolo,
        detalhamento, analista_id, analista_nome
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sssssssssssssssis",
        $grupo,
        $empresa,
        $seguradora,
        $ramo,
        $tipo_demanda,
        $situacao,
        $recebimento,
        $inicio_tratativa,
        $proxima_acao,
        $data_conclusao,
        $dados_beneficiario,
        $descricao_demanda,
        $assunto_email,
        $protocolo,
        $detalhamento,
        $analista_id,
        $analista_nome
    );

    $stmt->execute();
}

fclose($arquivo);

echo "Importação concluída!";