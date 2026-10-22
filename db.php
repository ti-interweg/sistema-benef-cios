<?php
// Conexão com o banco + criação das tabelas (idempotente).
declare(strict_types=1);

function ad_config(): array {
    static $c = null;
    if ($c === null) { $c = require __DIR__ . '/config.php'; }
    return $c;
}

function ad_db(): ?PDO {
    static $pdo = null; static $tentou = false;
    if ($tentou) { return $pdo; }
    $tentou = true;
    $d = ad_config()['db'] ?? [];
    if (empty($d['nome']) || strpos((string)$d['nome'], 'COLOQUE') !== false) { ad_db_erro('config.php sem os dados do banco.'); return null; }
    try {
        $pdo = new PDO(
            "mysql:host={$d['host']};dbname={$d['nome']};charset=utf8mb4",
            $d['usuario'], $d['senha'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        ad_ensure_tables($pdo);
    } catch (Throwable $e) { $pdo = null; ad_db_erro($e->getMessage()); }
    return $pdo;
}

/** Guarda/retorna o último erro de conexão com o banco (para diagnóstico na tela). */
function ad_db_erro(?string $set = null): ?string {
    static $erro = null;
    if ($set !== null) { $erro = $set; }
    return $erro;
}

/** Traduz o erro técnico numa dica prática para corrigir. */
function ad_db_dica(string $erro): string {
    if (stripos($erro, 'Access denied') !== false) { return 'Usuário ou senha do banco incorretos no config.php (confira no painel da Hostinger, em Bancos de Dados → MySQL).'; }
    if (stripos($erro, 'Unknown database') !== false) { return 'O banco de dados ainda não foi criado no painel da Hostinger, ou o nome no config.php está diferente do nome real.'; }
    if (stripos($erro, 'getaddrinfo') !== false || stripos($erro, 'refused') !== false || stripos($erro, 'No such file') !== false || stripos($erro, 'timed out') !== false) { return 'Não foi possível alcançar o servidor MySQL. Confira o host no config.php (na Hostinger normalmente é "localhost"). Se estiver abrindo o sistema fora da Hostinger, este aviso é esperado.'; }
    if (stripos($erro, 'config.php') !== false) { return 'Preencha host, nome do banco, usuário e senha no config.php.'; }
    return 'Confira host, nome do banco, usuário e senha no config.php.';
}

/** Registra um evento no histórico de auditoria (login, acesso ou alteração). Nunca quebra a página. */
function ad_log(?PDO $pdo, string $evento, string $processo, string $registro = '', string $detalhe = '', string $usuario = '', string $email = ''): void {
    if (!$pdo) { return; }
    try {
        $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        if (strpos($ip, ',') !== false) { $ip = trim(explode(',', $ip)[0]); }
        $pdo->prepare("INSERT INTO auditoria (quando, usuario, email, ip, evento, processo, registro, detalhe) VALUES (NOW(),?,?,?,?,?,?,?)")
            ->execute([mb_substr($usuario, 0, 150), mb_substr($email, 0, 150), mb_substr($ip, 0, 45), $evento, mb_substr($processo, 0, 80), mb_substr($registro, 0, 190), $detalhe !== '' ? $detalhe : null]);
    } catch (Throwable $e) { /* ignora */ }
}

function ad_ensure_tables(PDO $pdo): void {
    // Histórico e auditoria: quem acessou e o que foi alterado.
    $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        quando DATETIME NOT NULL,
        usuario VARCHAR(150) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        evento VARCHAR(20) NOT NULL,
        processo VARCHAR(80) NOT NULL DEFAULT '',
        registro VARCHAR(190) NOT NULL DEFAULT '',
        detalhe TEXT NULL,
        INDEX idx_aud_quando (quando),
        INDEX idx_aud_evento (evento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS avaliacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario VARCHAR(120) NOT NULL,
        cargo_base VARCHAR(40) NULL,
        avaliador VARCHAR(90) NULL,
        nota DECIMAL(5,2) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_func (funcionario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS avaliacao_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        avaliacao_id INT NOT NULL,
        competencia VARCHAR(90) NOT NULL,
        nivel VARCHAR(20) NOT NULL,
        pontos INT NOT NULL,
        observacao TEXT NULL,
        INDEX idx_av (avaliacao_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migração: adiciona a coluna 'observacao' em bancos criados antes desta versão.
    try {
        $col = $pdo->query("SHOW COLUMNS FROM avaliacao_itens LIKE 'observacao'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE avaliacao_itens ADD COLUMN observacao TEXT NULL"); }
    } catch (Throwable $e) { /* ignora */ }
    // Migração: observação geral do avaliador (relatório modelo Sólides).
    try {
        $col = $pdo->query("SHOW COLUMNS FROM avaliacoes LIKE 'observacao_geral'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE avaliacoes ADD COLUMN observacao_geral TEXT NULL"); }
    } catch (Throwable $e) { /* ignora */ }
    // Cadastro de funcionários (editável pelo sistema).
    $pdo->exec("CREATE TABLE IF NOT EXISTS funcionarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL DEFAULT '',
        tipo VARCHAR(20) NOT NULL DEFAULT 'CLT',
        setor VARCHAR(80) NOT NULL DEFAULT '',
        cargo VARCHAR(150) NOT NULL DEFAULT '',
        admissao DATE NULL,
        superior VARCHAR(150) NOT NULL DEFAULT '',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_func_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migração: admissão e superior direto em tabelas criadas antes desta versão.
    try {
        $col = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'admissao'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE funcionarios ADD COLUMN admissao DATE NULL, ADD COLUMN superior VARCHAR(150) NOT NULL DEFAULT ''"); }
    } catch (Throwable $e) { /* ignora */ }
    // Migração: ficha completa do colaborador (padrão Sólides).
    try {
        $col = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'cpf'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE funcionarios
                ADD COLUMN nascimento DATE NULL,
                ADD COLUMN genero VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN estado_civil VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN escolaridade VARCHAR(40) NOT NULL DEFAULT '',
                ADD COLUMN curso VARCHAR(120) NOT NULL DEFAULT '',
                ADD COLUMN nacionalidade VARCHAR(60) NOT NULL DEFAULT '',
                ADD COLUMN naturalidade VARCHAR(80) NOT NULL DEFAULT '',
                ADD COLUMN nome_pai VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN nome_mae VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN pcd VARCHAR(10) NOT NULL DEFAULT '',
                ADD COLUMN raca_etnia VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN cep VARCHAR(12) NOT NULL DEFAULT '',
                ADD COLUMN logradouro VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN numero VARCHAR(15) NOT NULL DEFAULT '',
                ADD COLUMN complemento VARCHAR(60) NOT NULL DEFAULT '',
                ADD COLUMN bairro VARCHAR(80) NOT NULL DEFAULT '',
                ADD COLUMN cidade VARCHAR(80) NOT NULL DEFAULT '',
                ADD COLUMN estado VARCHAR(40) NOT NULL DEFAULT '',
                ADD COLUMN pais VARCHAR(40) NOT NULL DEFAULT 'Brasil',
                ADD COLUMN salario DECIMAL(12,2) NULL,
                ADD COLUMN matricula VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN grau_hierarquico VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN turno VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN demissao DATE NULL,
                ADD COLUMN motivo_demissao VARCHAR(190) NOT NULL DEFAULT '',
                ADD COLUMN cpf VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN rg VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN rg_expedicao DATE NULL,
                ADD COLUMN rg_orgao VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN cnh VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN reservista VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN titulo_eleitor VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN zona_eleitoral VARCHAR(10) NOT NULL DEFAULT '',
                ADD COLUMN secao_eleitoral VARCHAR(10) NOT NULL DEFAULT '',
                ADD COLUMN pis VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN ctps_num VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN ctps_serie VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN banco VARCHAR(60) NOT NULL DEFAULT '',
                ADD COLUMN agencia VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN conta VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN telefone VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN celular VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN tel_emergencia VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN email_pessoal VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN qtd_filhos VARCHAR(5) NOT NULL DEFAULT ''");
        }
    } catch (Throwable $e) { /* ignora */ }
    // Importação inicial: se a tabela estiver vazia, traz os funcionários do arquivo dados_funcionarios.php.
    try {
        $qtd = (int)$pdo->query("SELECT COUNT(*) FROM funcionarios")->fetchColumn();
        if ($qtd === 0 && is_file(__DIR__ . '/dados_funcionarios.php')) {
            $lista = require __DIR__ . '/dados_funcionarios.php';
            if (is_array($lista)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO funcionarios (nome, email, tipo, setor, cargo, criado_em) VALUES (?,?,?,?,?,NOW())");
                foreach ($lista as $f) {
                    $nome = trim((string)($f['nome'] ?? ''));
                    if ($nome === '') { continue; }
                    $ins->execute([
                        $nome,
                        trim((string)($f['email'] ?? '')),
                        trim((string)($f['tipo'] ?? '')) !== '' ? trim((string)$f['tipo']) : 'CLT',
                        trim((string)($f['setor'] ?? '')),
                        trim((string)($f['cargo'] ?? '')),
                    ]);
                }
            }
        }
    } catch (Throwable $e) { /* ignora */ }
    // Mapa de competências editável: por cargo e por funcionário (personalização individual).
    $pdo->exec("CREATE TABLE IF NOT EXISTS mapa_cargo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cargo VARCHAR(60) NOT NULL,
        competencia VARCHAR(90) NOT NULL,
        ordem INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_mc (cargo, competencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mapa_funcionario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario VARCHAR(150) NOT NULL,
        competencia VARCHAR(90) NOT NULL,
        ordem INT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_mf (funcionario, competencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Avaliação de período de experiência (etapas de 45 e 90 dias).
    $pdo->exec("CREATE TABLE IF NOT EXISTS avaliacoes_exp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario VARCHAR(150) NOT NULL,
        etapa VARCHAR(10) NOT NULL,
        avaliador VARCHAR(90) NULL,
        nota DECIMAL(5,2) NULL,
        decisao VARCHAR(40) NULL,
        observacao_geral TEXT NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_exp (funcionario, etapa)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS avaliacao_exp_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        avaliacao_id INT NOT NULL,
        criterio VARCHAR(120) NOT NULL,
        nivel VARCHAR(20) NOT NULL,
        pontos INT NOT NULL,
        observacao TEXT NULL,
        INDEX idx_ae (avaliacao_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Treinamentos: cursos e inscrições dos funcionários.
    $pdo->exec("CREATE TABLE IF NOT EXISTS cursos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(190) NOT NULL,
        descricao TEXT NULL,
        categoria VARCHAR(80) NOT NULL DEFAULT '',
        carga_horaria INT NOT NULL DEFAULT 0,
        obrigatorio TINYINT NOT NULL DEFAULT 0,
        modalidade VARCHAR(20) NOT NULL DEFAULT '',
        data_inicio DATETIME NULL,
        data_fim DATETIME NULL,
        link_aula VARCHAR(300) NULL,
        local VARCHAR(150) NULL,
        endereco VARCHAR(250) NULL,
        ativo TINYINT NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inscricoes_curso (
        id INT AUTO_INCREMENT PRIMARY KEY,
        curso_id INT NOT NULL,
        funcionario_id INT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'inscrito',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_insc (curso_id, funcionario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Folha de pagamento: um lançamento por funcionário e competência (mês).
    $pdo->exec("CREATE TABLE IF NOT EXISTS folha (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        competencia VARCHAR(7) NOT NULL,
        salario_bruto DECIMAL(12,2) NOT NULL DEFAULT 0,
        comissoes DECIMAL(12,2) NOT NULL DEFAULT 0,
        premiacoes DECIMAL(12,2) NOT NULL DEFAULT 0,
        assistencia_medica DECIMAL(12,2) NOT NULL DEFAULT 0,
        assist_dependentes DECIMAL(12,2) NOT NULL DEFAULT 0,
        outros_descontos DECIMAL(12,2) NOT NULL DEFAULT 0,
        observacoes TEXT NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_folha (funcionario_id, competencia),
        INDEX idx_folha_comp (competencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Migração: dependentes para IR na ficha do funcionário.
    try {
        $col = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'dependentes'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE funcionarios ADD COLUMN dependentes VARCHAR(5) NOT NULL DEFAULT ''"); }
    } catch (Throwable $e) { /* ignora */ }
    // Ausências: férias, atestados, licenças, folgas e day off.
    $pdo->exec("CREATE TABLE IF NOT EXISTS ausencias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT 'ferias',
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        dias INT NOT NULL DEFAULT 0,
        motivo VARCHAR(190) NULL,
        documento VARCHAR(300) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'planejada',
        observacoes TEXT NULL,
        criado_por VARCHAR(150) NOT NULL DEFAULT '',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_aus_func (funcionario_id),
        INDEX idx_aus_ini (data_inicio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Reembolsos e adiantamentos: solicitações com aprovação e pagamento em folha.
    $pdo->exec("CREATE TABLE IF NOT EXISTS reembolsos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        tipo VARCHAR(20) NOT NULL DEFAULT 'reembolso',
        categoria VARCHAR(60) NOT NULL DEFAULT 'Outros',
        data_despesa DATE NULL,
        valor DECIMAL(12,2) NOT NULL DEFAULT 0,
        descricao TEXT NULL,
        anexo VARCHAR(300) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        aprovador VARCHAR(150) NOT NULL DEFAULT '',
        aprovado_em DATETIME NULL,
        observacao_aprovador TEXT NULL,
        competencia_pagamento VARCHAR(7) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_reemb_func (funcionario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Empréstimos: controle de descontos consignados em folha.
    $pdo->exec("CREATE TABLE IF NOT EXISTS emprestimos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        instituicao VARCHAR(120) NOT NULL DEFAULT '',
        inicio_contrato DATE NULL,
        comp_inicial_desc VARCHAR(7) NULL,
        valor_parcela DECIMAL(12,2) NOT NULL DEFAULT 0,
        total_parcelas INT NOT NULL DEFAULT 0,
        parcelas_pagas INT NOT NULL DEFAULT 0,
        valor_emprestimo DECIMAL(12,2) NOT NULL DEFAULT 0,
        valor_liberado DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'ativo',
        observacoes TEXT NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_emp_func (funcionario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Comunicados: mural de avisos, políticas e novidades da empresa.
    $pdo->exec("CREATE TABLE IF NOT EXISTS comunicados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(190) NOT NULL,
        conteudo TEXT NOT NULL,
        autor VARCHAR(150) NOT NULL DEFAULT '',
        publico VARCHAR(20) NOT NULL DEFAULT 'todos',
        setor VARCHAR(80) NOT NULL DEFAULT '',
        prioridade VARCHAR(20) NOT NULL DEFAULT 'normal',
        expira_em DATE NULL,
        ativo TINYINT NOT NULL DEFAULT 1,
        publicado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Vale-Transporte: histórico mensal de cálculos (um registro por mês de referência).
    $pdo->exec("CREATE TABLE IF NOT EXISTS vt_meses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(60) NOT NULL,
        ordem VARCHAR(7) NULL,
        dados LONGTEXT NOT NULL,
        usuario VARCHAR(150) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_vt_ref (referencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Vale Refeição/Alimentação: histórico mensal de cálculos (um registro por mês de referência).
    $pdo->exec("CREATE TABLE IF NOT EXISTS vr_meses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(60) NOT NULL,
        ordem VARCHAR(7) NULL,
        dados LONGTEXT NOT NULL,
        usuario VARCHAR(150) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_vr_ref (referencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Plano de Saúde Unimed: histórico mensal (mensalidades + coparticipação).
    $pdo->exec("CREATE TABLE IF NOT EXISTS ps_meses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referencia VARCHAR(60) NOT NULL,
        ordem VARCHAR(7) NULL,
        dados LONGTEXT NOT NULL,
        usuario VARCHAR(150) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_ps_ref (referencia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Usuários do sistema: senha gerenciada pelo próprio sistema (hash seguro, troca pela tela Trocar senha).
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios_sistema (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(150) NOT NULL,
        nome VARCHAR(150) NOT NULL DEFAULT '',
        senha_hash VARCHAR(255) NOT NULL,
        precisa_trocar TINYINT NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_us_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Seed: qualquer usuário do config.php que ainda não exista no banco é criado com a senha
    // inicial do config e troca obrigatória no primeiro acesso. Depois disso, a senha vale a do banco.
    try {
        $cfgU = (array)(ad_config()['usuarios'] ?? []);
        if ($cfgU) {
            $insU = $pdo->prepare("INSERT IGNORE INTO usuarios_sistema (email, nome, senha_hash, precisa_trocar, criado_em) VALUES (?,?,?,1,NOW())");
            foreach ($cfgU as $emU => $dU) {
                $insU->execute([mb_strtolower(trim((string)$emU)), (string)($dU['nome'] ?? ''), password_hash((string)($dU['senha'] ?? ''), PASSWORD_DEFAULT)]);
            }
        }
    } catch (Throwable $e) { /* ignora */ }
    // PDI: Plano de Desenvolvimento Individual, gerado a partir da avaliação de desempenho.
    $pdo->exec("CREATE TABLE IF NOT EXISTS pdi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario VARCHAR(150) NOT NULL,
        ciclo VARCHAR(20) NOT NULL DEFAULT '',
        objetivo TEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'em_andamento',
        criado_por VARCHAR(150) NOT NULL DEFAULT '',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_pdi (funcionario, ciclo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS pdi_itens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pdi_id INT NOT NULL,
        competencia VARCHAR(120) NOT NULL,
        nota INT NULL,
        observacao_gestor TEXT NULL,
        acao TEXT NULL,
        prazo DATE NULL,
        responsavel VARCHAR(150) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_pdi (pdi_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Registros: diário de bordo do colaborador (anotações internas do RH, com data e hora).
    $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        tipo VARCHAR(40) NOT NULL DEFAULT 'Outros',
        classificacao VARCHAR(10) NOT NULL DEFAULT 'neutro',
        data_registro DATE NOT NULL,
        valor VARCHAR(20) NOT NULL DEFAULT '',
        comentario TEXT NULL,
        criado_por VARCHAR(150) NOT NULL DEFAULT '',
        criado_por_email VARCHAR(150) NOT NULL DEFAULT '',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_reg_func (funcionario_id),
        INDEX idx_reg_data (data_registro)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Feedbacks: registrados pelo RH e enviados por comando ao e-mail do colaborador.
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedbacks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        data_feedback DATE NOT NULL,
        classificacao VARCHAR(15) NOT NULL DEFAULT 'neutro',
        comentario TEXT NOT NULL,
        criado_por VARCHAR(150) NOT NULL DEFAULT '',
        criado_por_email VARCHAR(150) NOT NULL DEFAULT '',
        enviado_em DATETIME NULL,
        enviado_para VARCHAR(150) NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_fb_func (funcionario_id),
        INDEX idx_fb_data (data_feedback)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Candidatos: recebidos pelo formulário público de candidatura.
    // (a função ad_ficha_aplicar_funcionario, mais abaixo, leva a Ficha de Registro para a ficha do funcionário)
    $pdo->exec("CREATE TABLE IF NOT EXISTS candidatos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL DEFAULT '',
        celular VARCHAR(30) NOT NULL DEFAULT '',
        nascimento DATE NULL,
        cidade VARCHAR(80) NOT NULL DEFAULT '',
        estado VARCHAR(40) NOT NULL DEFAULT '',
        escolaridade VARCHAR(40) NOT NULL DEFAULT '',
        cargo_desejado VARCHAR(150) NOT NULL DEFAULT '',
        pretensao VARCHAR(30) NOT NULL DEFAULT '',
        curriculo_link VARCHAR(300) NOT NULL DEFAULT '',
        experiencia TEXT NULL,
        origem VARCHAR(40) NOT NULL DEFAULT '',
        status VARCHAR(30) NOT NULL DEFAULT 'Novo',
        observacoes TEXT NULL,
        funcionario_id INT NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        INDEX idx_cand_status (status),
        INDEX idx_cand_cargo (cargo_desejado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Ficha de Cadastro do processo seletivo (campos do formulário público, modelo Interweg).
    try {
        $col = $pdo->query("SHOW COLUMNS FROM candidatos LIKE 'endereco'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE candidatos
                ADD COLUMN endereco VARCHAR(200) NOT NULL DEFAULT '',
                ADD COLUMN cep VARCHAR(12) NOT NULL DEFAULT '',
                ADD COLUMN genero VARCHAR(60) NOT NULL DEFAULT '',
                ADD COLUMN estado_civil VARCHAR(40) NOT NULL DEFAULT '',
                ADD COLUMN nacionalidade VARCHAR(60) NOT NULL DEFAULT '',
                ADD COLUMN naturalidade VARCHAR(80) NOT NULL DEFAULT '',
                ADD COLUMN nome_pai VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN nome_mae VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN telefone VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN emergencia_nome VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN emergencia_parentesco VARCHAR(40) NOT NULL DEFAULT '',
                ADD COLUMN emergencia_tel VARCHAR(30) NOT NULL DEFAULT '',
                ADD COLUMN rg VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN rg_orgao VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN rg_expedicao DATE NULL,
                ADD COLUMN cpf VARCHAR(20) NOT NULL DEFAULT '',
                ADD COLUMN lgpd_aceite_em DATETIME NULL");
        }
    } catch (Throwable $e) { /* ignora */ }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM candidatos LIKE 'emergencia_tel'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE candidatos ADD COLUMN emergencia_tel VARCHAR(30) NOT NULL DEFAULT ''"); }
    } catch (Throwable $e) { /* ignora */ }
    // Ficha de registro do candidato aprovado (preenchida por link enviado por e-mail).
    try {
        $col = $pdo->query("SHOW COLUMNS FROM candidatos LIKE 'ficha_json'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE candidatos
                ADD COLUMN ficha_json MEDIUMTEXT NULL,
                ADD COLUMN ficha_enviada_em DATETIME NULL,
                ADD COLUMN ficha_preenchida_em DATETIME NULL");
        }
    } catch (Throwable $e) { /* ignora */ }
    // Documentos da admissão enviados pelo candidato na Ficha de Registro.
    $pdo->exec("CREATE TABLE IF NOT EXISTS candidato_docs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        candidato_id INT NOT NULL,
        arquivo VARCHAR(80) NOT NULL,
        nome_original VARCHAR(200) NOT NULL DEFAULT '',
        tipo VARCHAR(60) NOT NULL DEFAULT '',
        tamanho INT NOT NULL DEFAULT 0,
        enviado_em DATETIME NOT NULL,
        INDEX idx_cdoc_cand (candidato_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try {
        $col = $pdo->query("SHOW COLUMNS FROM candidato_docs LIKE 'tipo'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE candidato_docs ADD COLUMN tipo VARCHAR(60) NOT NULL DEFAULT ''"); }
    } catch (Throwable $e) { /* ignora */ }
    // Engenharia de cargos (catálogo de cargos, no padrão Sólides).
    $pdo->exec("CREATE TABLE IF NOT EXISTS cargos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(150) NOT NULL,
        perfil VARCHAR(5) NOT NULL DEFAULT '',
        senioridade VARCHAR(30) NOT NULL DEFAULT '',
        cbo VARCHAR(100) NOT NULL DEFAULT '',
        piso DECIMAL(12,2) NULL,
        descricao TEXT NULL,
        atividades TEXT NULL,
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_cargo_nome (nome)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Campos extras da Engenharia de Cargo (modelo Sólides).
    try {
        $col = $pdo->query("SHOW COLUMNS FROM cargos LIKE 'cargo_superior'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE cargos
                ADD COLUMN cargo_superior VARCHAR(150) NOT NULL DEFAULT '',
                ADD COLUMN habilidades TEXT NULL,
                ADD COLUMN assistencias VARCHAR(400) NOT NULL DEFAULT '',
                ADD COLUMN superiores VARCHAR(400) NOT NULL DEFAULT ''");
        }
    } catch (Throwable $e) { /* ignora */ }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM cargos LIKE 'comportamental'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE cargos ADD COLUMN comportamental TEXT NULL"); }
    } catch (Throwable $e) { /* ignora */ }
    // Entrevistas de desligamento (uma por colaborador desligado).
    $pdo->exec("CREATE TABLE IF NOT EXISTS entrevistas_desligamento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        funcionario_id INT NOT NULL,
        data_entrevista DATE NOT NULL,
        motivo_saida TEXT NULL,
        relacionamento_gestor TEXT NULL,
        ambiente_trabalho TEXT NULL,
        pontos_positivos TEXT NULL,
        pontos_melhorar TEXT NULL,
        recomendaria VARCHAR(20) NOT NULL DEFAULT '',
        comentarios TEXT NULL,
        entrevistador VARCHAR(150) NOT NULL DEFAULT '',
        entrevistador_email VARCHAR(150) NOT NULL DEFAULT '',
        criado_em DATETIME NOT NULL,
        atualizado_em DATETIME NULL,
        UNIQUE KEY uq_entdesl_func (funcionario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Aviso prévio na ficha do funcionário (usado pelo formulário de desligamento).
    try {
        $col = $pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'aviso_previo'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE funcionarios ADD COLUMN aviso_previo VARCHAR(40) NOT NULL DEFAULT ''"); }
    } catch (Throwable $e) { /* ignora */ }
    // Importação inicial do mapa: se estiver vazio, traz do arquivo dados_mapa_cargo.php.
    try {
        $qtd = (int)$pdo->query("SELECT COUNT(*) FROM mapa_cargo")->fetchColumn();
        if ($qtd === 0 && is_file(__DIR__ . '/dados_mapa_cargo.php')) {
            $mapa = require __DIR__ . '/dados_mapa_cargo.php';
            if (is_array($mapa)) {
                $ins = $pdo->prepare("INSERT IGNORE INTO mapa_cargo (cargo, competencia, ordem) VALUES (?,?,?)");
                foreach ($mapa as $cargo => $comps) {
                    $o = 0;
                    foreach ((array)$comps as $c) { $ins->execute([(string)$cargo, (string)$c, ++$o]); }
                }
            }
        }
    } catch (Throwable $e) { /* ignora */ }
}

/**
 * Aplica os dados da Ficha de Registro (JSON preenchido pelo candidato)
 * na ficha do funcionário. Só preenche o que veio com valor; nada é apagado.
 * Usada na contratação e também quando o candidato já contratado envia a ficha.
 */
function ad_ficha_aplicar_funcionario(PDO $pdo, array $fj, int $funcionarioId): void {
    if ($funcionarioId <= 0 || !$fj) { return; }
    $v = function (string $k) use ($fj): string { return trim((string)($fj[$k] ?? '')); };
    $qtdDeps = 0;
    for ($iD = 1; $iD <= 4; $iD++) { if ($v('dep' . $iD . '_nome') !== '') { $qtdDeps++; } }
    $mapa = [
        'genero'          => $v('sexo'),
        'estado_civil'    => $v('estado_civil'),
        'escolaridade'    => $v('grau_instrucao'),
        'naturalidade'    => trim($v('local_nascimento') . ($v('estado_nascimento') !== '' ? ' / ' . $v('estado_nascimento') : ''), ' /'),
        'nacionalidade'   => $v('pais_nascimento') !== '' && mb_strtolower($v('pais_nascimento')) !== 'brasil' ? $v('pais_nascimento') : ($v('pais_nascimento') !== '' ? 'Brasileiro(a)' : ''),
        'raca_etnia'      => $v('cor_raca'),
        'pcd'             => $v('deficiente'),
        'nome_pai'        => $v('nome_pai'),
        'nome_mae'        => $v('nome_mae'),
        'logradouro'      => $v('residencia'),
        'numero'          => $v('numero'),
        'bairro'          => $v('bairro'),
        'cep'             => $v('cep'),
        'cpf'             => $v('cpf'),
        'rg'              => $v('identidade'),
        'rg_orgao'        => $v('identidade_estado') !== '' ? 'SSP-' . mb_strtoupper($v('identidade_estado')) : '',
        'pis'             => $v('pis'),
        'ctps_num'        => $v('ctps_num'),
        'ctps_serie'      => $v('ctps_serie'),
        'titulo_eleitor'  => $v('titulo_eleitor'),
        'zona_eleitoral'  => $v('titulo_zona'),
        'secao_eleitoral' => $v('titulo_secao'),
        'cnh'             => $v('cnh_num'),
        'reservista'      => $v('doc_militar'),
        'telefone'        => $v('telefone'),
        'tel_emergencia'  => $v('emergencia_tel'),
        'qtd_filhos'      => $qtdDeps > 0 ? (string)$qtdDeps : '',
        'dependentes'     => $qtdDeps > 0 ? (string)$qtdDeps : '',
    ];
    if ($v('nascimento') !== '') { $mapa['nascimento'] = $v('nascimento'); }
    if ($v('cidade') !== '') { $mapa['cidade'] = $v('cidade'); }
    if ($v('estado') !== '') { $mapa['estado'] = $v('estado'); }
    if ($v('celular') !== '') { $mapa['celular'] = $v('celular'); }
    if ($v('identidade_emissao') !== '') { $mapa['rg_expedicao'] = $v('identidade_emissao'); }
    $sets = []; $vals = [];
    foreach ($mapa as $col => $val) { if ($val !== '') { $sets[] = $col . '=?'; $vals[] = mb_substr($val, 0, 150); } }
    if (!$sets) { return; }
    $vals[] = $funcionarioId;
    try {
        $pdo->prepare("UPDATE funcionarios SET " . implode(', ', $sets) . ", atualizado_em=NOW() WHERE id=?")->execute($vals);
    } catch (Throwable $e) { /* ignora */ }
}
