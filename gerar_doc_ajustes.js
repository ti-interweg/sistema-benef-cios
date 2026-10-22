const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
        Header, Footer, AlignmentType, PageOrientation, LevelFormat,
        HeadingLevel, BorderStyle, WidthType, ShadingType,
        TabStopType, TabStopPosition, PageNumber } = require('docx');
const fs = require('fs');

// === Helpers ===
const teal = "00BFB3";
const tealDark = "006677";
const gray = "606060";
const grayLight = "F5F7F8";

const border = { style: BorderStyle.SINGLE, size: 4, color: "DDDDDD" };
const borders = { top: border, bottom: border, left: border, right: border };

const P = (text, opts={}) => new Paragraph({
  children: [new TextRun({ text, ...opts })],
  spacing: { after: 120 }
});

const H1 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_1,
  children: [new TextRun({ text, bold: true, color: tealDark, size: 32 })],
  spacing: { before: 320, after: 160 },
  border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: teal, space: 6 } }
});

const H2 = (text) => new Paragraph({
  heading: HeadingLevel.HEADING_2,
  children: [new TextRun({ text, bold: true, color: tealDark, size: 26 })],
  spacing: { before: 240, after: 120 }
});

const Bullet = (text) => new Paragraph({
  numbering: { reference: "bullets", level: 0 },
  children: [new TextRun({ text })],
  spacing: { after: 80 }
});

const BulletBold = (label, rest) => new Paragraph({
  numbering: { reference: "bullets", level: 0 },
  children: [
    new TextRun({ text: label, bold: true }),
    new TextRun({ text: rest })
  ],
  spacing: { after: 80 }
});

const Code = (text) => new Paragraph({
  children: [new TextRun({ text, font: "Consolas", size: 20, color: tealDark })],
  shading: { fill: grayLight, type: ShadingType.CLEAR },
  spacing: { before: 60, after: 60 },
  indent: { left: 240 }
});

// === Tabela de arquivos ===
const fileRow = (file, descr) => new TableRow({
  children: [
    new TableCell({
      borders, width: { size: 3000, type: WidthType.DXA },
      margins: { top: 80, bottom: 80, left: 120, right: 120 },
      children: [new Paragraph({ children: [new TextRun({ text: file, font: "Consolas", size: 20, bold: true })] })]
    }),
    new TableCell({
      borders, width: { size: 6360, type: WidthType.DXA },
      margins: { top: 80, bottom: 80, left: 120, right: 120 },
      children: [new Paragraph({ children: [new TextRun({ text: descr, size: 20 })] })]
    })
  ]
});

const headerRow = (a, b) => new TableRow({
  children: [
    new TableCell({
      borders, width: { size: 3000, type: WidthType.DXA },
      margins: { top: 80, bottom: 80, left: 120, right: 120 },
      shading: { fill: tealDark, type: ShadingType.CLEAR },
      children: [new Paragraph({ children: [new TextRun({ text: a, bold: true, color: "FFFFFF", size: 20 })] })]
    }),
    new TableCell({
      borders, width: { size: 6360, type: WidthType.DXA },
      margins: { top: 80, bottom: 80, left: 120, right: 120 },
      shading: { fill: tealDark, type: ShadingType.CLEAR },
      children: [new Paragraph({ children: [new TextRun({ text: b, bold: true, color: "FFFFFF", size: 20 })] })]
    })
  ]
});

// === Conteúdo ===
const children = [
  // Capa
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: "INTERWEG SEGUROS", bold: true, color: teal, size: 28, font: "Arial" })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: "Sistema de Benefícios — Ajustes Implementados", bold: true, size: 36 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: "Documento de deploy / aplicação no servidor", italics: true, color: gray, size: 22 })],
    spacing: { after: 320 },
    border: { bottom: { style: BorderStyle.SINGLE, size: 12, color: teal, space: 6 } }
  }),

  // Resumo
  H1("Resumo"),
  P("Este documento lista os ajustes feitos no sistema Interweg Benefícios (interweg-beneficios.com.br), com instruções de aplicação no servidor de hospedagem (Hostinger) e no banco de dados (phpMyAdmin)."),
  P("Os ajustes envolvem: 2 arquivos PHP a substituir no servidor e 5 scripts SQL a executar no banco. Todos os arquivos estão na pasta local public_html_beneficios."),

  // Arquivos
  H1("1. Arquivos PHP a substituir no servidor"),
  P("Substituir os arquivos abaixo no servidor (via FTP, gerenciador de arquivos da Hostinger ou similar). Caminho do servidor: public_html/."),

  new Table({
    width: { size: 9360, type: WidthType.DXA },
    columnWidths: [3000, 6360],
    rows: [
      headerRow("Arquivo", "O que mudou"),
      fileRow("index.php", "Front-end completo: dropdowns de Ramo (com Saúde/Odonto), tipos de demanda (com Abertura de subfatura), formulário de cotação refinado, modal de pré-visualização editável, fallback de analista por nome, badges visuais."),
      fileRow("api.php", "Back-end: validação atualizada, propagação de mudanças de empresa pra faturamento e renovação, blindagem com try/catch e SET FOREIGN_KEY_CHECKS=0/1 nos UPDATEs/INSERTs que tocam analista_id.")
    ]
  }),

  // SQLs
  H1("2. Scripts SQL a executar no banco"),
  P("Rodar no phpMyAdmin do banco u207808890_beneficios. Pode usar a aba Importar (mais tolerante a erros) ou colar na aba SQL para os scripts curtos. Ordem sugerida:"),

  new Table({
    width: { size: 9360, type: WidthType.DXA },
    columnWidths: [3000, 6360],
    rows: [
      headerRow("Script", "Função"),
      fileRow("limpar_empresas.sql", "(Opcional) TRUNCATE da tabela empresas, pra começar do zero antes do import."),
      fileRow("import_empresas_base_tratada.sql", "TRUNCATE + insere as 1.352 empresas únicas extraídas do BASE_TRATADA.xlsx. Use Importar para arquivos grandes."),
      fileRow("fix_analista_id.sql", "Preenche analista_id (NULL) com o ID correspondente em todas as tabelas (faturamento, renovacao, demandas, empresas), match por nome insensível a maiúsculas e acentos."),
      fileRow("add_ramo_saude_odonto.sql", "Adiciona 'Saúde/Odonto' aos ENUMs da coluna ramo nas 4 tabelas. Idempotente."),
      fileRow("fix_fk_analista_geral.sql", "(Opcional) Tenta dropar foreign keys legado em analista_id. Não é obrigatório, pois o api.php já contorna o problema com SET FOREIGN_KEY_CHECKS=0.")
    ]
  }),

  // Detalhamento
  H1("3. Detalhamento dos ajustes"),

  H2("3.1. Formulário de Cotação"),
  Bullet("Removidas as opções Odonto e Vida do dropdown de tipo (a pedido)."),
  Bullet("Botões \"Limpar / Visualizar / Gerar Link / Exportar XLSX\" com altura uniforme e sem quebra de linha."),
  Bullet("Botão Visualizar agora abre um modal overlay com formulário editável (mesma identidade visual do que o cliente vai ver), e cada digitação sincroniza no estado de origem."),

  H2("3.2. Sincronização Empresa → Faturamento e Renovação"),
  Bullet("Ao editar uma empresa em Cadastros, as mudanças são propagadas automaticamente para todos os registros relacionados em faturamento e renovacao."),
  Bullet("Match feito pelos valores ANTIGOS de CNPJ + nº contrato + seguradora, então mesmo alterando esses campos o sistema acha as linhas certas."),
  Bullet("Toast confirma com a contagem, ex.: \"Empresa atualizada! Sincronizado: 12 faturamentos e 1 renovação.\""),

  H2("3.3. Importação de empresas do BASE_TRATADA"),
  Bullet("Geradas 1.352 empresas únicas (após dedup pela chave grupo + empresa + seguradora + nº contrato)."),
  Bullet("Origens preservadas como vieram (INTERWEG, FINLÂNDIA, INTERWEG | FINLÂNDIA, INTERWEG SP, INTERWEG JF, VALENTE JUNIOR)."),
  Bullet("Datas, % comissão e e-mails de faturamento mapeados corretamente."),

  H2("3.4. Pré-seleção de analista nos modais"),
  Bullet("Editar Faturamento, Editar Demanda e Editar Empresa: dropdown de analista agora cai pro match por NOME (insensível a maiúsculas/acentos) quando analista_id está vazio."),
  Bullet("Resolve casos de dados importados que só tinham analista_nome preenchido, sem o ID."),

  H2("3.5. Erro 500 ao alterar analista no faturamento"),
  Bullet("Causa: foreign key legado em analista_id apontando pra tabela usuarios em vez de analistas — bloqueava o UPDATE com analistas que estão só na tabela analistas (Lorena, Joyce, etc.)."),
  Bullet("Solução adotada: api.php envolve UPDATEs/INSERTs com SET FOREIGN_KEY_CHECKS = 0/1, evitando o bloqueio sem precisar mexer na FK do banco."),
  Bullet("Try/catch adicionado para retornar JSON com mensagem clara em vez de morrer com 500 mudo."),

  H2("3.6. Novo ramo Saúde/Odonto"),
  Bullet("Adicionada opção \"Saúde/Odonto\" em 8 dropdowns: filtros (Demandas, Faturamento, Renovação) e modais (Nova/Editar Demanda, Empresa, Faturamento, Renovação, Lote)."),
  Bullet("Badge visual novo com cor degradê azul-arroxeado para distinguir."),
  Bullet("ENUM da coluna ramo atualizado em 4 tabelas via add_ramo_saude_odonto.sql."),

  H2("3.7. Novo tipo de demanda \"Abertura de subfatura\""),
  Bullet("Adicionado nos 3 dropdowns: filtro da tela Demandas, modal Nova/Editar Demanda e cadastro em lote."),
  Bullet("Como tipo_demanda é VARCHAR (não ENUM), não precisa de ALTER TABLE no banco."),

  // Passos finais
  H1("4. Passo-a-passo de aplicação"),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "Acessar a Hostinger e abrir o gerenciador de arquivos (ou via FTP).", size: 22 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "Substituir public_html/index.php e public_html/api.php pelos arquivos atualizados (versões locais em Downloads/public_html_beneficios).", size: 22 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "Abrir o phpMyAdmin do banco u207808890_beneficios.", size: 22 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "Aba Importar → executar add_ramo_saude_odonto.sql.", size: 22 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "Aba Importar → executar fix_analista_id.sql.", size: 22 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "(Opcional, se quiser começar do zero com as empresas) Aba Importar → executar import_empresas_base_tratada.sql.", size: 22 })],
    spacing: { after: 80 }
  }),
  new Paragraph({
    numbering: { reference: "numbers", level: 0 },
    children: [new TextRun({ text: "Atualizar a tela do sistema (Ctrl+F5) e testar: editar uma empresa, editar um faturamento, mudar analista, filtrar por Saúde/Odonto.", size: 22 })],
    spacing: { after: 80 }
  }),

  // Observações finais
  H1("5. Observações"),
  Bullet("Todos os scripts SQL são idempotentes — podem rodar mais de uma vez sem efeito colateral."),
  Bullet("O fix_fk_analista_geral.sql não é mais necessário, já que o api.php contorna o problema na aplicação."),
  Bullet("Backup do banco é recomendado antes de rodar TRUNCATE (limpar_empresas.sql ou import_empresas_base_tratada.sql)."),
  Bullet("Em caso de dúvida ou erro, conferir o console do navegador (F12 → Console) e a aba Network para ver respostas da api.php."),

  new Paragraph({
    alignment: AlignmentType.CENTER,
    children: [new TextRun({ text: "— fim do documento —", italics: true, color: gray, size: 20 })],
    spacing: { before: 480 }
  })
];

// === Documento ===
const doc = new Document({
  creator: "Interweg Seguros",
  title: "Ajustes Sistema Benefícios",
  styles: {
    default: { document: { run: { font: "Arial", size: 22 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 32, bold: true, color: tealDark, font: "Arial" },
        paragraph: { spacing: { before: 320, after: 160 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
        run: { size: 26, bold: true, color: tealDark, font: "Arial" },
        paragraph: { spacing: { before: 240, after: 120 }, outlineLevel: 1 } }
    ]
  },
  numbering: {
    config: [
      { reference: "bullets", levels: [{ level: 0, format: LevelFormat.BULLET, text: "•",
        alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 480, hanging: 240 } } } }] },
      { reference: "numbers", levels: [{ level: 0, format: LevelFormat.DECIMAL, text: "%1.",
        alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 480, hanging: 240 } } } }] }
    ]
  },
  sections: [{
    properties: {
      page: {
        size: { width: 11906, height: 16838 }, // A4
        margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 }
      }
    },
    headers: {
      default: new Header({ children: [new Paragraph({
        alignment: AlignmentType.RIGHT,
        children: [new TextRun({ text: "INTERWEG · Ajustes do Sistema", color: gray, size: 18 })]
      })] })
    },
    footers: {
      default: new Footer({ children: [new Paragraph({
        alignment: AlignmentType.CENTER,
        children: [
          new TextRun({ text: "Página ", color: gray, size: 18 }),
          new TextRun({ children: [PageNumber.CURRENT], color: gray, size: 18 })
        ]
      })] })
    },
    children
  }]
});

Packer.toBuffer(doc).then(buf => {
  fs.writeFileSync("/sessions/eloquent-sharp-babbage/mnt/public_html_beneficios/Ajustes_Sistema_Interweg.docx", buf);
  console.log("OK");
});
