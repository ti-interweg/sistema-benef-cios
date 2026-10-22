/**
 * Apresentacao editavel + geracao de PPT.
 * Build marker: APRESENTACAO_PPT_V2026_05_05_FULL
 *
 * Fluxo:
 *  1. apAbrirPreviewEditavel() - abre modal com previa editavel dos slides
 *  2. usuario edita textos, valores, etc. (tudo e contenteditable)
 *  3. clica "Baixar PPT" -> apGerarPPT() gera o .pptx editavel
 */
console.log('[Interweg] apresentacao_ppt.js carregado - build APRESENTACAO_PPT_V2026_05_05_FULL');

const PPTXGEN_CDN     = 'https://cdn.jsdelivr.net/npm/pptxgenjs@3.12.0/dist/pptxgen.bundle.min.js';
const HTML2CANVAS_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
const JSPDF_CDN       = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
let pptxGenLoaded = false;
let html2canvasLoaded = false;
let jspdfLoaded = false;

function loadScript(src) {
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.onload = resolve;
    s.onerror = () => reject(new Error('Falhou ao carregar ' + src));
    document.head.appendChild(s);
  });
}

function loadPptxGen() {
  return new Promise((resolve, reject) => {
    if (pptxGenLoaded && window.PptxGenJS) { resolve(); return; }
    loadScript(PPTXGEN_CDN).then(() => { pptxGenLoaded = true; resolve(); }).catch(reject);
  });
}

async function loadPdfLibs() {
  // Carrega html2canvas e jsPDF independentes (mais confiável que html2pdf.bundle)
  if (!(html2canvasLoaded && window.html2canvas)) {
    await loadScript(HTML2CANVAS_CDN);
    html2canvasLoaded = true;
  }
  if (!(jspdfLoaded && (window.jspdf?.jsPDF || window.jsPDF))) {
    await loadScript(JSPDF_CDN);
    jspdfLoaded = true;
  }
}

const IW_TEAL       = '00BFB3';
const IW_TEAL_DARK  = '006677';
const IW_TEAL_MED   = '0E9488'; // teal um tom abaixo do verde — usado no cabeçalho da coluna "Plano Atual"
const IW_GRAY       = '808285';
const IW_GRAY_LIGHT = 'F5F7F8';
const IW_TEXT       = '1A2226';
// Destaque suave para a célula "PLANO ATUAL" da linha de TOTAL (teal bem claro,
// mesma família da cor usada nas células de TOTAL das cotações)
const IW_HIGHLIGHT  = 'E6F9F7';

// ════════════════════════════════════════════════════════════════
// ENTREGÁVEIS — templates por porte do contrato
// (extraído do PDF "benefícios - entregáveis", 4 páginas: até 29 / 30-99 / 100+ / 300+)
// Cada item tem nome e status. Status pode ser "✓" (check verde),
// texto livre ("Online", "A cada seis meses", "Sob avaliação..."), ou "" (não disponibilizado).
// ════════════════════════════════════════════════════════════════
const ENTREGAVEIS_TEMPLATES = {
  ate_29: {
    titulo: 'ATIVIDADES REALIZADAS ATÉ 29 VIDAS',
    atividades: [
      { nome: 'Movimentação',                                  status: '✓' },
      { nome: 'Faturamento',                                   status: '✓' },
      { nome: 'Apoio operacional¹',                            status: '✓' },
      { nome: 'Apoio assistencial²',                           status: '✓' },
      { nome: 'Análise de sinistralidade',                     status: '' },
      { nome: 'WeeBI*',                                        status: '' },
      { nome: 'Comitê de Saúde',                               status: '' },
      { nome: 'InterBaby',                                     status: '' },
      { nome: 'InterCuidado',                                  status: '' },
      { nome: 'Informativo de saúde',                          status: '✓' },
      { nome: 'Pit Stop (+ bioimpedância)',                    status: '' },
      { nome: 'Quick Massage',                                 status: '' },
      { nome: 'Palestras',                                     status: '' },
      { nome: 'Atendimento psicológico emergencial (pontual)', status: '' },
      { nome: 'Apoio psicológico (5 sessões)',                 status: '' },
      { nome: 'Estudo de mercado/Renovação',                   status: '✓' },
      { nome: 'Conexa',                                        status: '' },
      { nome: 'Mar',                                           status: '' },
      { nome: 'Flui',                                          status: '' }
    ]
  },
  faixa_30_99: {
    titulo: 'ATIVIDADES REALIZADAS - 30 A 99 VIDAS',
    atividades: [
      { nome: 'Movimentação',                                  status: '✓' },
      { nome: 'Faturamento',                                   status: '✓' },
      { nome: 'Apoio operacional¹',                            status: '✓' },
      { nome: 'Apoio assistencial²',                           status: '✓' },
      { nome: 'Análise de sinistralidade',                     status: '' },
      { nome: 'WeeBI*',                                        status: '' },
      { nome: 'Comitê de Saúde',                               status: '' },
      { nome: 'InterBaby',                                     status: '✓' },
      { nome: 'InterCuidado',                                  status: '✓' },
      { nome: 'Informativo de saúde',                          status: '✓' },
      { nome: 'Pit Stop (+ bioimpedância)',                    status: '' },
      { nome: 'Quick Massage',                                 status: '' },
      { nome: 'Palestras',                                     status: 'Online' },
      { nome: 'Atendimento psicológico emergencial (pontual)', status: '✓' },
      { nome: 'Apoio psicológico (5 sessões)',                 status: '' },
      { nome: 'Estudo de mercado/Renovação',                   status: '✓' },
      { nome: 'Conexa',                                        status: '' },
      { nome: 'Mar',                                           status: '' },
      { nome: 'Flui',                                          status: '' }
    ]
  },
  faixa_100_299: {
    titulo: 'ATIVIDADES REALIZADAS - A PARTIR DE 100 VIDAS',
    atividades: [
      { nome: 'Movimentação',                                  status: '✓' },
      { nome: 'Faturamento',                                   status: '✓' },
      { nome: 'Apoio operacional¹',                            status: '✓' },
      { nome: 'Apoio assistencial²',                           status: '✓' },
      { nome: 'Análise de sinistralidade',                     status: '✓' },
      { nome: 'WeeBI*',                                        status: '' },
      { nome: 'Comitê de Saúde',                               status: '' },
      { nome: 'InterBaby',                                     status: '✓' },
      { nome: 'InterCuidado',                                  status: '✓' },
      { nome: 'Informativo de saúde',                          status: '✓' },
      { nome: 'Pit Stop (+ bioimpedância)',                    status: 'A cada seis meses' },
      { nome: 'Quick Massage',                                 status: 'Avaliação sob demanda' },
      { nome: 'Palestras',                                     status: 'Online / Presencial' },
      { nome: 'Atendimento psicológico emergencial (pontual)', status: '✓' },
      { nome: 'Apoio psicológico (5 sessões)',                 status: '✓' },
      { nome: 'Estudo de mercado/Renovação',                   status: '✓' },
      { nome: 'Conexa',                                        status: 'Sob avaliação/definição comercial' },
      { nome: 'Mar',                                           status: '' },
      { nome: 'Flui',                                          status: 'Sob avaliação/definição comercial' }
    ]
  },
  faixa_300_mais: {
    titulo: 'ATIVIDADES REALIZADAS - A PARTIR DE 300 VIDAS',
    atividades: [
      { nome: 'Movimentação',                                  status: '✓' },
      { nome: 'Faturamento',                                   status: '✓' },
      { nome: 'Apoio operacional¹',                            status: '✓' },
      { nome: 'Apoio assistencial²',                           status: '✓' },
      { nome: 'Análise de sinistralidade',                     status: '✓' },
      { nome: 'WeeBI*',                                        status: '✓' },
      { nome: 'Comitê de Saúde',                               status: '✓' },
      { nome: 'InterBaby',                                     status: '✓' },
      { nome: 'InterCuidado',                                  status: '✓' },
      { nome: 'Informativo de saúde',                          status: '✓' },
      { nome: 'Pit Stop (+ bioimpedância)',                    status: 'A cada seis meses' },
      { nome: 'Quick Massage',                                 status: 'Avaliação sob demanda' },
      { nome: 'Palestras',                                     status: 'Online / Presencial' },
      { nome: 'Atendimento psicológico emergencial (pontual)', status: '✓' },
      { nome: 'Apoio psicológico (5 sessões)',                 status: '✓' },
      { nome: 'Estudo de mercado/Renovação',                   status: '✓' },
      { nome: 'Conexa',                                        status: 'Sob avaliação/definição comercial' },
      { nome: 'Mar',                                           status: 'Sob avaliação/definição comercial' },
      { nome: 'Flui',                                          status: 'Sob avaliação/definição comercial' }
    ]
  }
};

function detectarPorteEntregaveis(vidas) {
  const v = parseInt(vidas) || 0;
  if (v >= 300) return 'faixa_300_mais';
  if (v >= 100) return 'faixa_100_299';
  if (v >= 30)  return 'faixa_30_99';
  return 'ate_29';
}

function fmtBRL(v) {
  if (!v) return '';
  const s = String(v).trim();
  if (!s) return '';
  return s.startsWith('R$') ? s : 'R$ ' + s;
}
function escH(s) {
  return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Carrega o logo Interweg como base64 PNG (PowerPoint não suporta WebP nativamente)
let _logoCachedBase64 = null;
// Cache global de imagens de branding (carregadas uma vez por sessão)
const _imgCache = {};

// Carrega QUALQUER imagem do servidor e devolve como base64 PNG (formato que PptxGenJS aceita).
async function carregarImagem(url) {
  if (_imgCache[url]) return _imgCache[url];
  try {
    const resp = await fetch(url);
    if (!resp.ok) return null;
    const blob = await resp.blob();
    const bitmap = await createImageBitmap(blob);
    const canvas = document.createElement('canvas');
    canvas.width = bitmap.width;
    canvas.height = bitmap.height;
    canvas.getContext('2d').drawImage(bitmap, 0, 0);
    _imgCache[url] = canvas.toDataURL('image/png');
    return _imgCache[url];
  } catch (e) {
    console.warn('[carregarImagem] falhou:', url, e);
    return null;
  }
}

// Logo Interweg — versão CINZA monocromática (slides 1-6 com fundo claro,
// onde o colorido competia com o resto do design). A partir do slide 7 o
// código usa logo_interweg.png (colorido) explicitamente via branding.logoEscuro.
async function carregarLogoComoPng() {
  if (_logoCachedBase64) return _logoCachedBase64;
  for (const url of ['logo_cinza.png', 'img/logo-interweg.png', 'logo_interweg.webp', 'logo_interweg.png']) {
    const data = await carregarImagem(url);
    if (data) { _logoCachedBase64 = data; return data; }
  }
  return null;
}

// Carrega TODAS as imagens de branding usadas pelos slides.
// Cache-buster dia-a-dia: garante que mudanças de arte (institucional_interweg.png,
// quem_somos.png, etc.) sejam refletidas no PPT/preview no mesmo dia, sem precisar
// pedir Ctrl+F5 pra Valeria.
// Carrega o logo pra fundos escuros, preferindo PNG (com transparência) antes de JPG.
async function _carregarLogoEscuro(v) {
  for (const url of ['logo_interweg.png', 'logo_interweg.jpg', 'img/logo-rodape.png']) {
    const data = await carregarImagem(url + v);
    if (data) return data;
  }
  return null;
}

async function carregarBranding() {
  const v = '?v=' + new Date().toISOString().slice(0,10).replace(/-/g,'');
  const [logo, logoEscuro, capaEquipe, capaDecor, institucional, quemSomos, nossasSolucoes, gestaoSaude, comoAtuamos, iaMedicina, contato] = await Promise.all([
    carregarLogoComoPng(),
    _carregarLogoEscuro(v),  // PNG > JPG > white-rodape (pra fundos escuros)
    carregarImagem('img/capa-equipe.png' + v),
    carregarImagem('img/capa-decor.png' + v),
    carregarImagem('institucional_interweg.png' + v),
    carregarImagem('quem_somos.png' + v),
    carregarImagem('nossas_solucoes.png' + v),
    carregarImagem('gestao_saude.png' + v),
    carregarImagem('como_atuamos.png' + v),
    carregarImagem('ia_img.png' + v),
    carregarImagem('contato.png' + v)
  ]);
  // logoBranco mantido como alias pra compatibilidade
  return { logo, logoEscuro, logoBranco: logoEscuro, capaEquipe, capaDecor, institucional, quemSomos, nossasSolucoes, gestaoSaude, comoAtuamos, iaMedicina, contato };
}

// Adiciona o logo Interweg discreto no rodapé do slide
function addLogoRodape(slide, base64, posicao = 'bottom-right') {
  if (!base64) return;
  // Tamanho discreto pro rodapé (mantém proporção do logo 2:1 — width 1.4 / height 0.7)
  const w = 1.2, h = 0.6;
  const margem = 0.3;
  const W = 13.33, H = 7.5;
  let x = W - w - margem;
  let y = H - h - margem;
  if (posicao === 'top-right')   { x = W - w - margem; y = margem; }
  if (posicao === 'top-left')    { x = margem;         y = margem; }
  if (posicao === 'bottom-left') { x = margem;         y = H - h - margem; }
  slide.addImage({ data: base64, x, y, w, h });
}

// Estado da apresentação editável
let apPptState = null;

function apMontarStateInicial() {
  const d = (typeof apColetarDados === 'function') ? apColetarDados() : null;
  if (!d) return null;
  const dataAtual = new Date().toLocaleDateString('pt-BR', {day:'2-digit', month:'long', year:'numeric'});

  // União ordenada de faixas etárias dos planos
  const allFaixas = new Set();
  d.planos.forEach(p => p.faixas?.forEach(f => f.faixa && allFaixas.add(f.faixa)));
  const faixas = Array.from(allFaixas).sort();

  // "Plano Atual (Vigente)" é opcional — quando o cliente não tem plano vigente,
  // o analista exclui o bloco no formulário (apColetarDados devolve tudo vazio).
  // Detectamos isso aqui para que a coluna/linha "PLANO ATUAL" suma do
  // comparativo, do slide de informações gerais, do PPT e do PDF.
  const _pa = d.plano_atual || {};
  const tem_plano_atual = !!(
    _pa.operadora || _pa.plano || _pa.modalidade ||
    _pa.acomodacao || _pa.coparticipacao || _pa.valor || d.valor_vigente
  );

  return {
    cliente:    d.cliente || '',
    tipo:       d.tipo || 'Saúde',
    vidas:      d.vidas || '',
    valor_vigente: d.valor_vigente || '',
    plano_atual: d.plano_atual || { operadora:'', plano:'', modalidade:'', acomodacao:'', coparticipacao:'', valor:'' },
    tem_plano_atual,
    // Mapa de slides excluídos da apresentação ({ slide_id: true }) — usado tanto
    // no preview (esconde o slide e mostra um banner pra restaurar) quanto na
    // geração do PPT/PDF (pula o slide).
    slides_excluidos: {},
    // Rótulos da coluna de rótulos do Slide 3 — editáveis no preview
    lbl_modalidade:     'Modalidade',
    lbl_acomodacao:     'Acomodação',
    lbl_coparticipacao: 'Coparticipação',
    lbl_total_mensal:   'TOTAL MENSAL',
    // Linhas adicionais do comparativo (editáveis) — IOF, reembolsos e diferenças
    lbl_iof:                'IOF',
    lbl_reembolso:          'Reembolso',
    lbl_reembolso_consulta: 'Reembolso para consulta médica',
    lbl_dif_mensal:         'Diferença mensal',
    lbl_dif_anual:          'Diferença anual',
    // Textos auxiliares do Slide 3 (Comparativo) — editáveis no preview
    comp_legend: tem_plano_atual
      ? 'Coluna em destaque = plano atual do cliente. Demais colunas = cotações vigentes para análise.'
      : 'Cotações vigentes para análise.',
    comp_disclaimer: 'Valores e demais condições são determinadas pelas seguradoras e podem ser alteradas a qualquer momento.',
    vendedor:   d.vendedor || '',
    email:      d.email || '',
    tel:        d.tel || '',
    data:       dataAtual,
    planos:     d.planos.map(p => ({
      seguradora:   p.seguradora || '',
      plano:        p.plano || '',
      modalidade:   p.modalidade || '',
      acomodacao:   p.acomodacao || '',
      coparticipacao: p.coparticipacao || '',
      valor:        p.valor || '',
      // Campos adicionais editáveis (vazios por padrão — o analista preenche)
      iof:                '',
      reembolso:          '',
      reembolso_consulta: '',
      dif_mensal:         '',
      dif_anual:          '',
      faixas:       (p.faixas || []).filter(f => f.faixa)
    })),
    faixas, // só os labels, ordenados
    // Textos editáveis dos slides "padrão"
    quem_somos_1: 'A Interweg Seguros e Benefícios é hoje uma das mais conceituadas corretoras de seguros de Minas Gerais, atuando em todo Brasil com atendimento premium, humanizado e ágil.',
    quem_somos_2: 'Há 3 décadas, oferecemos soluções em benefícios, seguros corporativos, de automóveis e residências e bens de alto valor. Está em nosso DNA garantir a tranquilidade e bem-estar dos nossos clientes para que possam se dedicar em seus objetivos estratégicos e planejamento de vida.',
    // Slide "Nossas Soluções" — 3 cards à direita, foto institucional à esquerda
    solucoes_titulo: 'Nossas soluções',
    solucoes_card_1: 'Consultoria para a gestão de Riscos Corporativos',
    solucoes_card_2: 'Gestão integrada de Saúde e Benefícios',
    solucoes_card_3: 'Seguros para bens de alto valor e outras soluções',
    // Slide "Gestão da Saúde e Benefícios" — texto + 3 pílulas
    gestao_titulo: 'Gestão da\nSaúde e Benefícios',
    gestao_paragrafo: 'Levamos às empresas soluções em <b>Saúde, Odonto e Vida em grupo</b>, além da gestão desses benefícios com <b>agilidade e eficiência</b> no <b>acompanhamento financeiro</b> e das pessoas.',
    gestao_pill_1: 'Plano de Saúde',
    gestao_pill_2: 'Vida em Grupo',
    gestao_pill_3: 'Plano Odontológico',
    // Slide "Como atuamos" — fundo navy + 4 quadrantes com listas + quebra-cabeças teal no centro
    atuamos_titulo: 'Como atuamos',
    atuamos_p1_titulo: 'Gestão Operacional',
    atuamos_p1_itens: [
      'Suporte ao RH',
      'Administração das rotinas do benefício',
      'Apoio às demandas internas'
    ],
    atuamos_p2_titulo: 'Equipe Multidisciplinar',
    atuamos_p2_itens: [
      'Médico próprio para Comitê de Saúde, análises e monitoramento',
      'Equipe de enfermagem para acompanhamento dos colaboradores',
      'Psicóloga para acolhimento em casos graves'
    ],
    atuamos_p3_titulo: 'Gestão da Saúde',
    atuamos_p3_itens: [
      'Acompanhamento periódico dos resultados do contrato',
      'Auxílio na liberação de procedimentos, dentre outros',
      'Programas de Saúde para conscientização e prevenção'
    ],
    atuamos_p4_titulo: 'Gestão Estratégica',
    atuamos_p4_itens: [
      'Controle de renovações, negociações com operadoras e análise do mercado',
      'Plantões de dúvidas e palestras',
      'Implantação e reimplantação dos contratos'
    ],
    // Slide "Inteligência Artificial e medicina preventiva" — texto à esquerda + imagem à direita
    ia_titulo: 'Inteligência Artificial e medicina preventiva',
    ia_paragrafo: 'A Interweg é pioneira no uso de IA para prevenção em saúde. Nossa solução utiliza inteligência artificial para mapear todos os exames e consultas que o paciente precisa fazer, promovendo melhor desfecho clínico e possibilitando a identificação de riscos. Com isso, conseguimos reduzir a sinistralidade e prever custos.',
    // Slide "Contato" — fundo institucional teal com logo + 3 colunas de contatos
    contato_emp_titulo: 'Interweg Seguros e Benefícios',
    contato_emp_l1: 'www.interweg.com.br',
    contato_emp_l2: '@interwegseguros',
    contato_emp_l3: 'contato@interweg.com.br',
    contato_bh_titulo: 'Belo Horizonte',
    contato_bh_l1: 'Av. Raja Gabáglia, 4.987 – 3º andar',
    contato_bh_l2: 'Santa Lúcia – Belo Horizonte/MG',
    contato_bh_l3: 'Tel: (31) 3508-6000',
    contato_sp_titulo: 'São Paulo',
    contato_sp_l1: 'Av. Dr. Chucri Zaidan, 296 - 23º andar',
    contato_sp_l2: 'Vila Cordeiro – São Paulo/SP',
    contato_sp_l3: '',
    copart_intro: 'A coparticipação é o mecanismo onde o beneficiário participa financeiramente em parte dos procedimentos cobertos. Os valores variam conforme o plano contratado.',
    // Itens de coparticipação — agora um array dinâmico (permite adicionar/remover linhas)
    copart_itens: [
      'Consultas eletivas',
      'Atendimento em pronto-socorro',
      'Exames simples e especiais',
      'Procedimentos ambulatoriais',
      'Internações (valor fixo por internação)'
    ],
    carencias: [
      { proc: 'Urgência e emergência',                  carencia: '24 horas' },
      { proc: 'Consultas e exames simples',             carencia: '15 dias'  },
      { proc: 'Internações e procedimentos especiais',  carencia: '180 dias' },
      { proc: 'Parto a termo',                          carencia: '300 dias' },
      { proc: 'Transplantes',                           carencia: '180 dias' }
    ],
    ofertas: [
      'Analista dedicada acompanhando suas demandas com agilidade e proximidade',
      'Gestão de movimentações e faturamento, com fluxo claro e eficiente',
      'Interface constante com a seguradora, garantindo alinhamento e resoluções rápidas',
      'Apoio técnico na análise de sinistralidade e reajustes, com foco em controle e previsibilidade',
      'Acompanhamento do cumprimento das regras contratuais, assegurando conformidade'
    ],
    // Entregáveis — carrega TODAS as 4 tabelas (até 29 / 30-99 / 100+ / 300+) e a pessoa
    // remove as que não se aplicam pro cliente. Cada tabela vira um slide no PPT.
    // Deep clone pra não mutar o template original.
    entregaveis: {
      secoes: ['ate_29', 'faixa_30_99', 'faixa_100_299', 'faixa_300_mais'].map(k => {
        const tpl = ENTREGAVEIS_TEMPLATES[k];
        return {
          key: k,
          titulo: tpl.titulo,
          atividades: tpl.atividades.map(a => ({ nome: a.nome, status: a.status }))
        };
      })
    }
  };
}

// ── Manipulação dos Entregáveis (chamadas pelos botões do preview) ──
function apEntregaveisRemoverSecao(secaoIdx) {
  if (!apPptState?.entregaveis?.secoes) return;
  apPptState.entregaveis.secoes.splice(secaoIdx, 1);
  apRenderSlidesPreview();
}

function apEntregaveisRemoverLinha(secaoIdx, linhaIdx) {
  const sec = apPptState?.entregaveis?.secoes?.[secaoIdx];
  if (!sec || !Array.isArray(sec.atividades)) return;
  sec.atividades.splice(linhaIdx, 1);
  apRenderSlidesPreview();
}

function apEntregaveisAdicionarLinha(secaoIdx) {
  const sec = apPptState?.entregaveis?.secoes?.[secaoIdx];
  if (!sec) return;
  if (!Array.isArray(sec.atividades)) sec.atividades = [];
  sec.atividades.push({ nome: 'Nova atividade', status: '✓' });
  apRenderSlidesPreview();
}

// ── EXCLUIR / RESTAURAR SLIDES INDIVIDUAIS (botão X no topo de cada slide) ──
// Usado por todos os slides "normais" do preview. Cada slide tem um data-slide-id
// e o estado guarda quais foram removidos da apresentação.
function apExcluirSlide(slideId, label) {
  if (!apPptState) return;
  if (!apPptState.slides_excluidos) apPptState.slides_excluidos = {};
  const nome = label ? `"${label}"` : 'este slide';
  if (!confirm(`Remover ${nome} da apresentação? Você pode restaurar depois.`)) return;
  apPptState.slides_excluidos[slideId] = label || slideId;
  apRenderSlidesPreview();
}

function apRestaurarSlide(slideId) {
  if (!apPptState?.slides_excluidos) return;
  delete apPptState.slides_excluidos[slideId];
  apRenderSlidesPreview();
}

function apRestaurarTodosSlides() {
  if (!apPptState) return;
  apPptState.slides_excluidos = {};
  apRenderSlidesPreview();
}

function apEntregaveisRestaurar() {
  if (!apPptState) return;
  if (!confirm('Restaurar todas as 4 tabelas (até 29 / 30-99 / 100+ / 300+)? Isso vai sobrescrever as edições atuais nesta seção.')) return;
  apPptState.entregaveis = {
    secoes: ['ate_29', 'faixa_30_99', 'faixa_100_299', 'faixa_300_mais'].map(k => {
      const tpl = ENTREGAVEIS_TEMPLATES[k];
      return {
        key: k,
        titulo: tpl.titulo,
        atividades: tpl.atividades.map(a => ({ nome: a.nome, status: a.status }))
      };
    })
  };
  apRenderSlidesPreview();
}

// ──────────────────────────────────────────────────────────
// PREVIEW EDITÁVEL — abre overlay com slides em cards
// ──────────────────────────────────────────────────────────
function apAbrirPreviewEditavel() {
  const st = apMontarStateInicial();
  if (!st) { alert('Não foi possível coletar os dados'); return; }
  if (!st.cliente)   { toast('Informe o nome do cliente'); return; }
  if (!st.planos.length) { toast('Adicione pelo menos um plano'); return; }

  apPptState = st;

  // Remove overlay antigo se existir
  const old = document.getElementById('ap-ppt-overlay');
  if (old) old.remove();

  // Cache-buster — evita que o navegador segure versão antiga das imagens
  // institucional_interweg.png / quem_somos.png após a Valeria atualizar no servidor.
  const _imgVer = '?v=' + Date.now();

  const css = `
    #ap-ppt-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;overflow:auto;padding:24px 14px;animation:apFadeIn .15s ease-out}
    @keyframes apFadeIn{from{opacity:0}to{opacity:1}}
    /* Largura ampliada pra acomodar a coluna "Plano Atual" + várias cotações
       sem cortar a tabela do Comparativo. Usa quase toda a tela em monitores grandes. */
    #ap-ppt-overlay .app-doc{max-width:min(1360px,97vw);margin:0 auto;background:#fff;box-shadow:0 12px 40px rgba(0,0,0,.35);border-radius:12px;overflow:hidden;font-family:'Montserrat','Segoe UI',sans-serif;color:#1a2226}
    #ap-ppt-overlay .app-bar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e5e7e8;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;gap:12px}
    #ap-ppt-overlay .app-bar h2{margin:0;font-size:17px;color:#006677;font-weight:700}
    #ap-ppt-overlay .app-bar .actions{display:flex;gap:8px}
    #ap-ppt-overlay .app-bar button{border:1.5px solid #00bfb3;background:#fff;color:#006677;padding:8px 16px;border-radius:8px;cursor:pointer;font-family:inherit;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:6px}
    #ap-ppt-overlay .app-bar button:hover{background:#00bfb3;color:#fff}
    #ap-ppt-overlay .app-bar button.primary{background:#00bfb3;color:#fff}
    #ap-ppt-overlay .app-bar button.primary:hover{background:#006677;border-color:#006677}
    #ap-ppt-overlay .app-info{padding:10px 24px;background:#eff6f7;color:#006677;font-size:13px;border-bottom:1px solid #e5e7e8}
    #ap-ppt-overlay .app-info strong{color:#006677}
    #ap-ppt-overlay .app-slides{padding:18px}
    #ap-ppt-overlay .slide{background:#fff;border:1px solid #e0e3e5;border-radius:12px;margin-bottom:24px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    #ap-ppt-overlay .slide-tag{background:#006677;color:#fff;padding:8px 18px;font-size:11px;text-transform:uppercase;letter-spacing:1.5px;font-weight:700;display:flex;justify-content:space-between;align-items:center;gap:8px}
    /* Botão X de exclusão de slide — discreto até hover */
    #ap-ppt-overlay .slide-del-btn{background:transparent;border:1.5px solid rgba(255,255,255,.4);color:#fff;border-radius:6px;padding:2px 6px;font-size:13px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;justify-content:center;line-height:1;transition:background .12s,border-color .12s}
    #ap-ppt-overlay .slide-del-btn:hover{background:#d43d3d;border-color:#d43d3d}
    /* Banner de "slides excluídos" no topo do preview */
    #ap-ppt-overlay .slides-excluidos-banner{background:#FFF7E6;border:1px dashed #E0A800;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#7A5C00;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
    #ap-ppt-overlay .slides-excluidos-banner .label{font-weight:700;margin-right:4px}
    #ap-ppt-overlay .slides-excluidos-banner .chip{background:#fff;border:1px solid #E0A800;border-radius:14px;padding:2px 6px 2px 10px;display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#7A5C00}
    #ap-ppt-overlay .slides-excluidos-banner .chip button{background:transparent;border:none;color:#7A5C00;cursor:pointer;font-family:inherit;padding:0 4px;font-size:13px;line-height:1}
    #ap-ppt-overlay .slides-excluidos-banner .chip button:hover{color:#d43d3d}
    #ap-ppt-overlay .slides-excluidos-banner .restore-all{background:#fff;border:1px solid #E0A800;color:#7A5C00;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;margin-left:auto}
    #ap-ppt-overlay .slides-excluidos-banner .restore-all:hover{background:#E0A800;color:#fff}
    #ap-ppt-overlay .slide-body{padding:20px 14px}
    /* Capa (Slide 1) — fundo institucional Interweg com texto editavel à esquerda */
    #ap-ppt-overlay .slide-capa{position:relative;background:#F5F7F8;color:#006677;padding:0;height:560px;overflow:hidden}
    #ap-ppt-overlay .slide-capa .capa-bg{position:absolute;inset:0;background-image:url('institucional_interweg.png${_imgVer}');background-size:cover;background-position:right center;background-repeat:no-repeat}
    #ap-ppt-overlay .slide-capa .capa-text{position:relative;z-index:2;padding:90px 60px;max-width:55%}
    #ap-ppt-overlay .slide-capa h1{font-size:48px;font-weight:800;margin:0 0 80px;line-height:1.1;color:#006677;letter-spacing:-1px}
    #ap-ppt-overlay .slide-capa .cli{font-size:22px;font-weight:800;color:#006677;margin-bottom:14px;min-height:28px}
    #ap-ppt-overlay .slide-capa .cli:empty::before{content:'Nome da empresa';color:#9CA3AF;font-weight:500;font-style:italic}
    #ap-ppt-overlay .slide-capa .data{font-size:16px;font-weight:600;color:#006677;min-height:22px}
    #ap-ppt-overlay .slide-capa .data:empty::before{content:'Data';color:#9CA3AF;font-weight:500;font-style:italic}
    #ap-ppt-overlay .slide-titulo{font-size:24px;color:#006677;font-weight:800;margin-bottom:6px}
    #ap-ppt-overlay .slide-bar{width:60px;height:4px;background:#00bfb3;border-radius:3px;margin-bottom:18px}
    /* Slide 2 — Quem Somos: fundo navy à esquerda + foto à direita.
       Coluna direita usa background-size:contain pra não cortar a colagem
       de fotos (que é mais vertical que horizontal). */
    #ap-ppt-overlay .slide-quem{display:grid;grid-template-columns:1.2fr 1fr;background:#0E2A36;color:#fff;min-height:520px}
    #ap-ppt-overlay .slide-quem .quem-text{padding:60px 50px 50px;color:#fff}
    #ap-ppt-overlay .slide-quem .quem-titulo{font-size:42px;font-weight:800;color:#fff;margin:0 0 28px;line-height:1.05}
    #ap-ppt-overlay .slide-quem p{font-size:14px;line-height:1.65;color:#fff;margin:0 0 18px}
    #ap-ppt-overlay .slide-quem .hl{color:#00BFB3;font-weight:700}
    /* quem-img preenche a coluna inteira sem faixa navy ao redor.
       background-size:cover faz a imagem cobrir toda a área disponível;
       background-position:left center mantém o lado esquerdo das fotos
       (parte mais relevante da colagem) sempre visível mesmo que o lado
       direito fique levemente cortado. */
    #ap-ppt-overlay .slide-quem .quem-img{background:#0E2A36 url('quem_somos.png${_imgVer}') no-repeat left center;background-size:cover;min-height:520px}
    /* Slide "Nossas Soluções" — imagem como fundo decorativo + conteúdo centralizado */
    #ap-ppt-overlay .slide-sol{background:#F4F6F8 url('nossas_solucoes.png${_imgVer}') no-repeat center center;background-size:cover;min-height:520px;display:flex;align-items:center;justify-content:center;padding:50px 60px}
    #ap-ppt-overlay .slide-sol .sol-text{width:100%;max-width:640px;display:flex;flex-direction:column;justify-content:center;gap:18px;margin:0 auto}
    #ap-ppt-overlay .slide-sol .sol-titulo{font-size:36px;font-weight:800;color:#00BFB3;margin:0 0 18px;line-height:1.05;text-align:center}
    #ap-ppt-overlay .slide-sol .sol-card{display:flex;align-items:center;gap:14px;padding:14px 18px;border:2px solid #00BFB3;border-radius:14px;background:#fff;min-height:60px}
    #ap-ppt-overlay .slide-sol .sol-card-icon{width:42px;height:42px;flex-shrink:0;color:#00BFB3;display:flex;align-items:center;justify-content:center}
    #ap-ppt-overlay .slide-sol .sol-card-icon .material-symbols-rounded{font-size:34px}
    #ap-ppt-overlay .slide-sol .sol-card-text{flex:1;color:#006677;font-weight:600;font-size:15px;line-height:1.3;text-align:center}
    /* Slide "Gestão da Saúde e Benefícios" — texto à esquerda + foto à direita */
    #ap-ppt-overlay .slide-gestao{display:grid;grid-template-columns:1fr 1fr;background:#F4F6F8;min-height:520px;position:relative}
    #ap-ppt-overlay .slide-gestao .gestao-img{background:#F4F6F8 url('gestao_saude.png${_imgVer}') no-repeat right center;background-size:cover;min-height:520px;opacity:.55}
    #ap-ppt-overlay .slide-gestao .gestao-text{padding:50px 50px 40px;display:flex;flex-direction:column;justify-content:center;gap:14px;position:relative;z-index:2}
    #ap-ppt-overlay .slide-gestao .gestao-icon{width:64px;height:64px;color:#00BFB3;margin-bottom:6px}
    #ap-ppt-overlay .slide-gestao .gestao-icon .material-symbols-rounded{font-size:54px}
    #ap-ppt-overlay .slide-gestao .gestao-titulo{font-size:36px;font-weight:800;color:#00BFB3;margin:0 0 8px;line-height:1.1;white-space:pre-line}
    #ap-ppt-overlay .slide-gestao .gestao-paragrafo{font-size:15px;line-height:1.55;color:#1F3438;margin:0 0 14px;max-width:440px}
    #ap-ppt-overlay .slide-gestao .gestao-pills{display:flex;flex-direction:column;gap:8px;max-width:280px}
    #ap-ppt-overlay .slide-gestao .gestao-pill{display:flex;align-items:center;gap:10px;padding:9px 18px;background:#00BFB3;color:#fff;border-radius:8px;font-weight:600;font-size:14px}
    #ap-ppt-overlay .slide-gestao .gestao-pill::before{content:'✓';font-weight:800;font-size:13px}
    /* Slide "Como atuamos" — fundo navy + título centralizado + 4 quadrantes
       de lista (com check teal) ao redor do quebra-cabeças do centro */
    #ap-ppt-overlay .slide-atuamos{position:relative;background:#0E2A36;min-height:580px;padding:42px 50px 36px;color:#fff;overflow:hidden}
    #ap-ppt-overlay .slide-atuamos .atu-titulo{font-size:38px;font-weight:800;color:#fff;margin:0 0 30px;text-align:center;letter-spacing:1px}
    #ap-ppt-overlay .slide-atuamos .atu-grid{display:grid;grid-template-columns:1fr 280px 1fr;gap:30px;align-items:center;position:relative;z-index:2}
    #ap-ppt-overlay .slide-atuamos .atu-col{display:flex;flex-direction:column;gap:36px}
    #ap-ppt-overlay .slide-atuamos .atu-section{color:#fff}
    #ap-ppt-overlay .slide-atuamos .atu-section.right{text-align:left}
    #ap-ppt-overlay .slide-atuamos .atu-section.left{text-align:right}
    #ap-ppt-overlay .slide-atuamos .atu-section-titulo{font-size:18px;font-weight:700;color:#00BFB3;margin:0 0 10px;line-height:1.2}
    #ap-ppt-overlay .slide-atuamos .atu-section ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px}
    #ap-ppt-overlay .slide-atuamos .atu-section li{font-size:13px;line-height:1.4;color:#E6EEF1;display:flex;align-items:flex-start;gap:8px}
    #ap-ppt-overlay .slide-atuamos .atu-section.left li{flex-direction:row-reverse}
    #ap-ppt-overlay .slide-atuamos .atu-section li::before{content:'✓';display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:transparent;border:1.5px solid #00BFB3;color:#00BFB3;font-size:11px;font-weight:800;flex-shrink:0;margin-top:2px}
    #ap-ppt-overlay .slide-atuamos .atu-section li > span{flex:1}
    /* Centro: quebra-cabeças teal de 2x2 com circulinhos brancos com ícones */
    #ap-ppt-overlay .slide-atuamos .atu-center{position:relative;width:280px;height:280px;margin:0 auto}
    #ap-ppt-overlay .slide-atuamos .atu-piece{position:absolute;width:130px;height:130px;border-radius:14px;display:flex;align-items:center;justify-content:center}
    #ap-ppt-overlay .slide-atuamos .atu-piece.p1{top:0;left:0;background:#0a8a8d}
    #ap-ppt-overlay .slide-atuamos .atu-piece.p2{top:0;right:0;background:#0fb5ad}
    #ap-ppt-overlay .slide-atuamos .atu-piece.p3{bottom:0;left:0;background:#0fb5ad}
    #ap-ppt-overlay .slide-atuamos .atu-piece.p4{bottom:0;right:0;background:#0a8a8d}
    #ap-ppt-overlay .slide-atuamos .atu-piece-icon{width:54px;height:54px;border-radius:50%;background:#fff;color:#0a8a8d;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.2)}
    #ap-ppt-overlay .slide-atuamos .atu-piece-icon .material-symbols-rounded{font-size:30px}
    /* Slide "Inteligência Artificial e medicina preventiva" — texto + imagem */
    #ap-ppt-overlay .slide-ia{display:grid;grid-template-columns:1fr 1fr;background:#F4F6F8;min-height:520px}
    #ap-ppt-overlay .slide-ia .ia-text{padding:70px 50px 60px;display:flex;flex-direction:column;justify-content:center;gap:20px}
    #ap-ppt-overlay .slide-ia .ia-titulo{font-size:34px;font-weight:600;color:#0F4C5C;margin:0;line-height:1.15;max-width:480px}
    #ap-ppt-overlay .slide-ia .ia-paragrafo{font-size:14px;line-height:1.65;color:#1F3438;margin:0;max-width:520px}
    #ap-ppt-overlay .slide-ia .ia-img{background:#F4F6F8 url('ia_img.png${_imgVer}') no-repeat center;background-size:cover;min-height:520px;border-radius:0 0 0 24px}
    /* Slide "Contato" — fundo institucional teal com logo + 3 colunas no rodapé */
    #ap-ppt-overlay .slide-contato{position:relative;min-height:560px;background:#0a8a8d url('contato.png${_imgVer}') no-repeat center;background-size:cover;color:#fff;padding:50px 60px 40px;display:flex;flex-direction:column;justify-content:flex-end}
    #ap-ppt-overlay .slide-contato .ct-grid{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:30px;color:#fff;position:relative;z-index:2}
    #ap-ppt-overlay .slide-contato .ct-col{color:#fff}
    #ap-ppt-overlay .slide-contato .ct-titulo{font-size:18px;font-weight:800;color:#fff;margin:0 0 10px;line-height:1.2}
    #ap-ppt-overlay .slide-contato .ct-linha{font-size:13px;color:#fff;line-height:1.6;margin:0;font-weight:500}
    #ap-ppt-overlay .ed{display:inline-block;min-width:30px;outline:none;padding:2px 6px;margin:-2px -6px;border-radius:4px;transition:background .12s}
    #ap-ppt-overlay .ed:hover{background:#f0fbfa;cursor:text}
    #ap-ppt-overlay .ed:focus{background:#fff;box-shadow:0 0 0 2px #00bfb3}
    #ap-ppt-overlay .ed-block{display:block;width:100%;outline:none;padding:6px 8px;margin:-6px -8px;border-radius:6px;transition:background .12s;line-height:1.5}
    #ap-ppt-overlay .ed-block:hover{background:#f0fbfa;cursor:text}
    #ap-ppt-overlay .ed-block:focus{background:#fff;box-shadow:0 0 0 2px #00bfb3}
    /* Tabela comparativa — alinhada ao Modelo_Apresentacao_Interweg.pptx
       Sem table-layout:fixed para que cada coluna se ajuste ao conteúdo.
       Com min-width por tipo de célula e overflow-x no container, a tabela
       fica legível mesmo com 6+ cotações. */
    /* Tabela de comparativo/carências — largura controlada para garantir
       que cabem dentro do slide. max-width:820px deixa uma margem grande
       no slide-body, permitindo que a 4ª cotação apareça completa. */
    #ap-ppt-overlay .comp-table{width:100%;max-width:820px;border-collapse:collapse;font-size:12px;margin:8px auto 0;background:#fff}
    /* Durante a captura para PDF, força colunas com largura igual
       — previne que uma cotação com nome longo (ex.: "BRADESCO SEGUROS
       Nacional Plus 6") infle e empurre as outras pra fora do slide. */
    #ap-ppt-overlay.pdf-mode .comp-table{table-layout:fixed}
    /* Botões de adicionar/remover linha (Coparticipação e Carências) */
    #ap-ppt-overlay .ap-row-add{margin-top:12px;background:rgba(0,191,179,.12);color:#006677;border:1px dashed #00bfb3;border-radius:7px;padding:7px 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit}
    #ap-ppt-overlay .ap-row-add:hover{background:rgba(0,191,179,.22)}
    #ap-ppt-overlay .ap-row-del{background:transparent;border:none;color:#c0392b;cursor:pointer;font-size:13px;padding:2px 6px;border-radius:5px;line-height:1;flex-shrink:0}
    #ap-ppt-overlay .ap-row-del:hover{background:rgba(192,57,43,.12)}
    #ap-ppt-overlay .lista li{display:flex;align-items:center;gap:8px}
    #ap-ppt-overlay .ap-col-ctrl{width:36px}
    /* No modo PDF/PPT, esconde TODOS os controles de edição (botões e colunas de remover) */
    #ap-ppt-overlay.pdf-mode .ap-row-add,
    #ap-ppt-overlay.pdf-mode .ap-row-del,
    #ap-ppt-overlay.pdf-mode .ap-col-ctrl,
    #ap-ppt-overlay.pdf-mode .ent-add,
    #ap-ppt-overlay.pdf-mode .ent-del,
    #ap-ppt-overlay.pdf-mode .ent-del-secao,
    #ap-ppt-overlay.pdf-mode .ent-acao{display:none !important}
    /* PADRONIZA o tamanho de TODOS os slides (preview E pdf): força proporção
       16:9 (igual à página 960×540pt) pra que todos fiquem IDÊNTICOS, do tamanho
       da capa. Slides com pouco conteúdo ganham espaço; conteúdo extra é contido. */
    #ap-ppt-overlay .slide{width:100% !important;overflow:hidden;display:flex;flex-direction:column}
    #ap-ppt-overlay .slide .slide-tag{flex:0 0 auto}
    #ap-ppt-overlay .slide-capa,
    #ap-ppt-overlay .slide-quem{height:auto !important;min-height:0 !important;flex:1 1 auto}
    #ap-ppt-overlay .slide-body{flex:1 1 auto;height:auto;overflow:hidden;display:flex;flex-direction:column;justify-content:center}
    /* No PDF, remove borda/sombra/cantos e ocupa largura cheia (igual à capa) */
    #ap-ppt-overlay.pdf-mode .slide{width:100% !important;border:none !important;border-radius:0 !important;box-shadow:none !important;margin:0 !important}
    #ap-ppt-overlay.pdf-mode .slide-body{overflow:hidden}
    #ap-ppt-overlay .comp-table th,#ap-ppt-overlay .comp-table td{word-wrap:break-word;overflow-wrap:break-word}
    #ap-ppt-overlay .comp-table th,#ap-ppt-overlay .comp-table td{border:1px solid #fff;padding:9px 10px;text-align:center;vertical-align:middle}
    /* Cabeçalho: tudo teal escuro. white-space:normal força quebra de linha
       em nomes longos de seguradora ("BRADESCO SEGUROS Nacional Plus 6"). */
    #ap-ppt-overlay .comp-table th{background:#006677;color:#fff;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.5px;min-width:90px;white-space:normal;overflow:hidden;line-height:1.3;word-break:break-word;overflow-wrap:anywhere}
    /* Spans editáveis dentro do cabeçalho/células da tabela: deixam o texto
       quebrar em vez de estourar a coluna (sobrepondo a coluna ao lado). */
    #ap-ppt-overlay .comp-table th .ed,
    #ap-ppt-overlay .comp-table td .ed{display:inline;max-width:100%;word-break:break-word;overflow-wrap:anywhere;white-space:normal}
    /* Cabeçalho da coluna PLANO ATUAL — teal um tom abaixo dos demais cabeçalhos */
    #ap-ppt-overlay .comp-table th.vig{background:#0E9488}
    /* Coluna de rótulo (esquerda) — teal escuro com texto branco.
       white-space:normal pra que rótulos longos quebrem em 2 linhas em vez
       de estourar o cell e vazar visualmente pros lados. */
    #ap-ppt-overlay .comp-table td.lbl{text-align:center;font-weight:700;font-size:12px;color:#fff;background:#006677;min-width:110px;white-space:normal;text-transform:uppercase;letter-spacing:.3px;line-height:1.2}
    /* Tabela de Carências: coluna "Procedimento" alinhada à esquerda + ratio
       fixo 60/40 (procedimento/carência) pra deixar a CARÊNCIA com mais espaço. */
    #ap-ppt-overlay .slide[data-slide-id="carencias"] .comp-table{table-layout:fixed}
    #ap-ppt-overlay .slide[data-slide-id="carencias"] .comp-table td.lbl,
    #ap-ppt-overlay .slide[data-slide-id="carencias"] .comp-table th:first-child{width:60%;text-align:left;padding-left:18px;white-space:normal}
    /* Cabeçalho do canto superior-esquerdo (sem texto) — teal escuro pra fechar a "moldura" da coluna */
    #ap-ppt-overlay .comp-table th:first-child{min-width:110px;background:#006677}
    /* Coluna do PLANO ATUAL — fundo cinza claro suave + valores em teal escuro/negrito */
    #ap-ppt-overlay .comp-table td.vig{background:#F5F7F8;color:#006677;font-weight:700;min-width:85px}
    /* Colunas de cotações — min-width pequeno + max-width na tabela garante que
       4 cotações + label + PA caibam confortáveis (~700px) no max-width de 980px. */
    #ap-ppt-overlay .comp-table td:not(.lbl):not(.vig){min-width:90px}
    /* Linha de TOTAL — teal escuro no label, teal claro nos valores */
    #ap-ppt-overlay .comp-table tr.total td{background:#006677;color:#fff;font-weight:800;font-size:14px}
    #ap-ppt-overlay .comp-table tr.total td.vig{background:#E6F9F7;color:#006677}
    #ap-ppt-overlay .comp-table tr.total td.cot{background:#E6F9F7;color:#006677}
    /* Subtítulo opcional explicando o destaque (mesmo texto do .pptx) */
    #ap-ppt-overlay .comp-legend{font-size:11px;color:#888;font-style:italic;margin:0 0 8px}
    /* Slide Entregáveis (atividades realizadas) — visual igual ao PDF original */
    #ap-ppt-overlay .ent-table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
    #ap-ppt-overlay .ent-table td{padding:9px 12px;border:2px solid #fff;vertical-align:middle}
    #ap-ppt-overlay .ent-table td.ent-nome{background:#006677;color:#fff}
    #ap-ppt-overlay .ent-table td.ent-status{background:#00BFB3;color:#fff;font-weight:700;text-align:center}
    #ap-ppt-overlay .ent-table td.ent-acao{background:transparent;border:none;width:36px;text-align:center;padding:0 6px}
    #ap-ppt-overlay .ent-del{background:#fff;color:#d43d3d;border:1.5px solid #d43d3d;border-radius:50%;width:24px;height:24px;font-size:14px;font-weight:700;cursor:pointer;line-height:1;padding:0;display:inline-flex;align-items:center;justify-content:center}
    #ap-ppt-overlay .ent-del:hover{background:#d43d3d;color:#fff}
    #ap-ppt-overlay .ent-add{margin-top:14px;background:#fff;border:1.5px dashed #006677;color:#006677;padding:8px 16px;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit}
    #ap-ppt-overlay .ent-add:hover{background:#006677;color:#fff;border-style:solid}
    /* Botão "Excluir esta tabela" no topo de cada slide-card de entregáveis */
    #ap-ppt-overlay .ent-del-secao{background:#fff;color:#d43d3d;border:1.5px solid #d43d3d;border-radius:6px;padding:4px 10px;font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:4px}
    #ap-ppt-overlay .ent-del-secao:hover{background:#d43d3d;color:#fff}
    #ap-ppt-overlay .lista{list-style:none;padding:0;margin:0}
    #ap-ppt-overlay .lista li{display:flex;gap:12px;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f0f0f0}
    #ap-ppt-overlay .lista li:last-child{border-bottom:none}
    #ap-ppt-overlay .lista .dot{width:10px;height:10px;border-radius:50%;background:#00bfb3;flex-shrink:0;margin-top:6px}
    #ap-ppt-overlay .info-grid{display:grid;grid-template-columns:1fr 2fr;gap:8px 18px;font-size:13px}
    #ap-ppt-overlay .info-grid .lbl{font-weight:700;color:#006677;padding:8px 0;border-bottom:1px solid #eee}
    #ap-ppt-overlay .info-grid .val{padding:8px 0;border-bottom:1px solid #eee;color:#1a2226}
    #ap-ppt-overlay .slide-foot{background:#1a2226;color:#fff;padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:24px}
    #ap-ppt-overlay .slide-foot h3{margin:0 0 6px;color:#00bfb3;font-size:17px}
    #ap-ppt-overlay .slide-foot p{margin:0;font-size:13px;line-height:1.6;opacity:.95}
  `;

  const overlay = document.createElement('div');
  overlay.id = 'ap-ppt-overlay';

  overlay.innerHTML = `
    <style>${css}</style>
    <article class="app-doc">
      <div class="app-bar">
        <h2>Pré-visualização Editável da Apresentação</h2>
        <div class="actions">
          <button id="ap-ppt-fechar">Fechar</button>
          <button class="primary" id="ap-ppt-baixar-pdf" style="background:#D43D3D;border-color:#D43D3D"><span class="material-symbols-rounded" style="font-size:16px">picture_as_pdf</span> Baixar PDF</button>
          <button class="primary" id="ap-ppt-baixar"><span class="material-symbols-rounded" style="font-size:16px">slideshow</span> Baixar PPT</button>
        </div>
      </div>
      <div class="app-info">
        <strong>Tudo é editável.</strong> Clique em qualquer texto pra ajustar. Quando estiver pronto, clique em <strong>Baixar PPT</strong> e o arquivo .pptx vai com tudo o que você editou.
      </div>
      <div class="app-slides" id="ap-ppt-slides"></div>
    </article>
  `;
  document.body.appendChild(overlay);
  document.body.style.overflow = 'hidden';

  apRenderSlidesPreview();

  // Listeners
  overlay.querySelector('#ap-ppt-fechar').addEventListener('click', apFecharPreview);
  overlay.querySelector('#ap-ppt-baixar').addEventListener('click', apGerarPPT);
  overlay.querySelector('#ap-ppt-baixar-pdf').addEventListener('click', apGerarPDF);
  overlay.addEventListener('click', e => { if(e.target === overlay) apFecharPreview(); });
  document.addEventListener('keydown', function escListener(ev){
    if(ev.key === 'Escape'){ apFecharPreview(); document.removeEventListener('keydown', escListener); }
  });
}

function apFecharPreview() {
  const ov = document.getElementById('ap-ppt-overlay');
  if (ov) ov.remove();
  document.body.style.overflow = '';
}

// Renderiza os slides como HTML editável
function apRenderSlidesPreview() {
  const st = apPptState;
  const cont = document.getElementById('ap-ppt-slides');
  if (!cont) return;

  // Helpers pra criar campos editáveis com binding bidirecional
  // path = "cliente" ou "planos.0.valor" ou "carencias.2.proc" etc.
  // html = true → permite tags <b>...</b> renderizadas como negrito (caso do
  //         gestao_paragrafo). Salva de volta como HTML normalizado.
  const ed = (path, content, block=false, html=false) => {
    let cls = block ? 'ed-block' : 'ed';
    if (html) cls += ' ed-html';
    const inner = html
      ? String(content == null ? '' : content)
          .replace(/<b>/gi, '<strong>')
          .replace(/<\/b>/gi, '</strong>')
      : escH(content);
    return `<span class="${cls}" contenteditable="true" data-path="${escH(path)}">${inner}</span>`;
  };

  // Slide 1 – Capa (institucional Interweg de fundo + texto editável à esquerda)
  const capaHtml = `
    <div class="slide" data-slide-id="capa">
      <div class="slide-tag">Slide 1 - Capa</div>
      <div class="slide-capa">
        <div class="capa-bg"></div>
        <div class="capa-text">
          <h1>${ed('titulo_capa','Proposta de '+st.tipo+' Empresarial', true)}</h1>
          <div class="cli">${ed('cliente', st.cliente)}</div>
          <div class="data">${ed('data', st.data)}</div>
        </div>
      </div>
    </div>
  `;

  // Demais slides estáticos (Quem somos, Soluções, Atuação, etc.) — entram DEPOIS de
  // "Informações Gerais", que agora vai logo após a capa (slide 2).
  let html = `
    <div class="slide" data-slide-id="quem_somos">
      <div class="slide-tag">Slide 3 - Quem somos</div>
      <div class="slide-quem">
        <div class="quem-text">
          <h2 class="quem-titulo">${ed('quem_somos_titulo', 'Quem somos!')}</h2>
          <p>${ed('quem_somos_1', st.quem_somos_1, true)}</p>
          <p>${ed('quem_somos_2', st.quem_somos_2, true)}</p>
        </div>
        <div class="quem-img"></div>
      </div>
    </div>

    <div class="slide" data-slide-id="solucoes">
      <div class="slide-tag">Slide 3 - Nossas soluções</div>
      <div class="slide-sol">
        <div class="sol-text">
          <h2 class="sol-titulo">${ed('solucoes_titulo', st.solucoes_titulo)}</h2>
          <div class="sol-card">
            <div class="sol-card-icon"><span class="material-symbols-rounded">assignment_late</span></div>
            <div class="sol-card-text">${ed('solucoes_card_1', st.solucoes_card_1, true)}</div>
          </div>
          <div class="sol-card">
            <div class="sol-card-icon"><span class="material-symbols-rounded">health_and_safety</span></div>
            <div class="sol-card-text">${ed('solucoes_card_2', st.solucoes_card_2, true)}</div>
          </div>
          <div class="sol-card">
            <div class="sol-card-icon"><span class="material-symbols-rounded">savings</span></div>
            <div class="sol-card-text">${ed('solucoes_card_3', st.solucoes_card_3, true)}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="slide" data-slide-id="gestao">
      <div class="slide-tag">Slide 4 - Gestão da Saúde e Benefícios</div>
      <div class="slide-gestao">
        <div class="gestao-text">
          <div class="gestao-icon"><span class="material-symbols-rounded">assignment_add</span></div>
          <h2 class="gestao-titulo">${ed('gestao_titulo', st.gestao_titulo)}</h2>
          <p class="gestao-paragrafo">${ed('gestao_paragrafo', st.gestao_paragrafo, true, true)}</p>
          <div class="gestao-pills">
            <div class="gestao-pill">${ed('gestao_pill_1', st.gestao_pill_1)}</div>
            <div class="gestao-pill">${ed('gestao_pill_2', st.gestao_pill_2)}</div>
            <div class="gestao-pill">${ed('gestao_pill_3', st.gestao_pill_3)}</div>
          </div>
        </div>
        <div class="gestao-img"></div>
      </div>
    </div>

    <div class="slide" data-slide-id="atuamos">
      <div class="slide-tag">Slide 5 - Como atuamos</div>
      <div class="slide-atuamos">
        <h2 class="atu-titulo">${ed('atuamos_titulo', st.atuamos_titulo)}</h2>
        <div class="atu-grid">
          <div class="atu-col">
            <div class="atu-section left">
              <h3 class="atu-section-titulo">${ed('atuamos_p1_titulo', st.atuamos_p1_titulo)}</h3>
              <ul>
                ${(st.atuamos_p1_itens||[]).map((it,i) => `<li><span>${ed('atuamos_p1_itens.'+i, it, true)}</span></li>`).join('')}
              </ul>
            </div>
            <div class="atu-section left">
              <h3 class="atu-section-titulo">${ed('atuamos_p3_titulo', st.atuamos_p3_titulo)}</h3>
              <ul>
                ${(st.atuamos_p3_itens||[]).map((it,i) => `<li><span>${ed('atuamos_p3_itens.'+i, it, true)}</span></li>`).join('')}
              </ul>
            </div>
          </div>
          <div class="atu-center">
            <div class="atu-piece p1"><div class="atu-piece-icon"><span class="material-symbols-rounded">assignment</span></div></div>
            <div class="atu-piece p2"><div class="atu-piece-icon"><span class="material-symbols-rounded">verified_user</span></div></div>
            <div class="atu-piece p3"><div class="atu-piece-icon"><span class="material-symbols-rounded">search</span></div></div>
            <div class="atu-piece p4"><div class="atu-piece-icon"><span class="material-symbols-rounded">analytics</span></div></div>
          </div>
          <div class="atu-col">
            <div class="atu-section right">
              <h3 class="atu-section-titulo">${ed('atuamos_p2_titulo', st.atuamos_p2_titulo)}</h3>
              <ul>
                ${(st.atuamos_p2_itens||[]).map((it,i) => `<li><span>${ed('atuamos_p2_itens.'+i, it, true)}</span></li>`).join('')}
              </ul>
            </div>
            <div class="atu-section right">
              <h3 class="atu-section-titulo">${ed('atuamos_p4_titulo', st.atuamos_p4_titulo)}</h3>
              <ul>
                ${(st.atuamos_p4_itens||[]).map((it,i) => `<li><span>${ed('atuamos_p4_itens.'+i, it, true)}</span></li>`).join('')}
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="slide" data-slide-id="ia">
      <div class="slide-tag">Slide 6 - Inteligência Artificial e medicina preventiva</div>
      <div class="slide-ia">
        <div class="ia-text">
          <h2 class="ia-titulo">${ed('ia_titulo', st.ia_titulo)}</h2>
          <p class="ia-paragrafo">${ed('ia_paragrafo', st.ia_paragrafo, true)}</p>
        </div>
        <div class="ia-img"></div>
      </div>
    </div>
  `;

  // Slide 3 – Comparativo de Custos (mesmo design do Modelo_Apresentacao_Interweg.pptx)
  const planos = st.planos;
  const temPa  = !!st.tem_plano_atual;          // coluna "Plano Atual" opcional
  const colspan = (temPa ? 1 : 0) + planos.length;
  const pa = st.plano_atual || {};

  // Cabeçalho: "PLANO ATUAL" + nome da operadora; "COTAÇÃO N" + nome do plano
  const _h1 = (pa.operadora ? (pa.operadora + (pa.plano ? ' ' + pa.plano : '')) : '').toUpperCase();
  const _h2 = (p,i) => {
    const seg = (p.seguradora || '').toUpperCase();
    const plano = p.plano || '';
    return (seg + (plano ? ' ' + plano : '')).trim() || `COTAÇÃO ${i+1}`;
  };

  // Legenda e disclaimer agora vêm do estado (editáveis pelo usuário no preview)
  const compLegend = st.comp_legend || (temPa
    ? 'Coluna em destaque = plano atual do cliente. Demais colunas = cotações vigentes para análise.'
    : 'Cotações vigentes para análise.');
  const compDisclaimer = st.comp_disclaimer || 'Valores e demais condições são determinadas pelas seguradoras e podem ser alteradas a qualquer momento.';

  let comparativoHtml = `
    <div class="slide" data-slide-id="comparativo">
      <div class="slide-tag">Slide 3 - Comparativo de Custos</div>
      <div class="slide-body">
        <div class="slide-titulo">Comparativo de Custos</div>
        <div class="slide-bar"></div>
        <p class="comp-legend">${ed('comp_legend', compLegend)}</p>
        <div style="overflow-x:auto;max-width:100%">
        <table class="comp-table">
          <thead>
            <tr>
              <th></th>
              ${temPa ? `<th class="vig">PLANO ATUAL<br><span style="font-weight:600;text-transform:none;font-size:10px;letter-spacing:0">${ed('plano_atual.operadora', (pa.operadora||'').toUpperCase())}<br>${ed('plano_atual.plano', pa.plano||'')}</span></th>` : ''}
              ${planos.map((p,i) => `<th>COTAÇÃO ${i+1}<br><span style="font-weight:600;text-transform:none;font-size:10px;letter-spacing:0">${ed('planos.'+i+'.seguradora', (p.seguradora||'').toUpperCase())}<br>${ed('planos.'+i+'.plano', p.plano||'')}</span></th>`).join('')}
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="lbl">${ed('lbl_acomodacao', st.lbl_acomodacao || 'Acomodação')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.acomodacao', pa.acomodacao||'')}</td>` : ''}
              ${planos.map((p,i) => `<td>${ed('planos.'+i+'.acomodacao', p.acomodacao)}</td>`).join('')}
            </tr>
            <tr>
              <td class="lbl">${ed('lbl_modalidade', st.lbl_modalidade || 'Modalidade')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.modalidade', pa.modalidade||'')}</td>` : ''}
              ${planos.map((p,i) => `<td>${ed('planos.'+i+'.modalidade', p.modalidade)}</td>`).join('')}
            </tr>
            <tr>
              <td class="lbl">${ed('lbl_coparticipacao', st.lbl_coparticipacao || 'Coparticipação')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.coparticipacao', pa.coparticipacao||'')}</td>` : ''}
              ${planos.map((p,i) => `<td>${ed('planos.'+i+'.coparticipacao', p.coparticipacao)}</td>`).join('')}
            </tr>
            ${st.faixas.map((faixa, idx) => {
              const lblKey = 'lbl_faixa_' + idx;
              const lblVal = st[lblKey] || ('Faixa ' + faixa);
              return `<tr>
                <td class="lbl">${ed(lblKey, lblVal)}</td>
                ${temPa ? `<td class="vig"></td>` : ''}
                ${planos.map((p,i) => {
                  const f = (p.faixas||[]).find(x => x.faixa === faixa);
                  const val = f && f.valor ? fmtBRL(f.valor) : '';
                  const fi = (p.faixas||[]).findIndex(x => x.faixa === faixa);
                  return fi >= 0
                    ? `<td>${ed('planos.'+i+'.faixas.'+fi+'.valor', val)}</td>`
                    : `<td></td>`;
                }).join('')}
              </tr>`;
            }).join('')}
            <tr>
              <td class="lbl">${ed('lbl_iof', st.lbl_iof || 'IOF')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.iof', pa.iof||'')}</td>` : ''}
              ${planos.map((p,i) => `<td>${ed('planos.'+i+'.iof', p.iof||'')}</td>`).join('')}
            </tr>
            <tr>
              <td class="lbl">${ed('lbl_reembolso', st.lbl_reembolso || 'Reembolso')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.reembolso', pa.reembolso||'')}</td>` : ''}
              ${planos.map((p,i) => `<td>${ed('planos.'+i+'.reembolso', p.reembolso||'')}</td>`).join('')}
            </tr>
            <tr>
              <td class="lbl">${ed('lbl_reembolso_consulta', st.lbl_reembolso_consulta || 'Reembolso para consulta médica')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.reembolso_consulta', pa.reembolso_consulta||'')}</td>` : ''}
              ${planos.map((p,i) => `<td>${ed('planos.'+i+'.reembolso_consulta', p.reembolso_consulta||'')}</td>`).join('')}
            </tr>
            <tr class="total">
              <td>${ed('lbl_total_mensal', st.lbl_total_mensal || 'TOTAL MENSAL')}</td>
              ${temPa ? `<td class="vig">${ed('valor_vigente_fmt', fmtBRL(st.valor_vigente))}</td>` : ''}
              ${planos.map((p,i) => `<td class="cot">${ed('planos.'+i+'.valor_fmt', fmtBRL(p.valor))}</td>`).join('')}
            </tr>
            <!-- linha separadora -->
            <tr><td colspan="${1+colspan}" style="height:10px;border:none;background:transparent"></td></tr>
            <tr class="total">
              <td>${ed('lbl_dif_mensal', st.lbl_dif_mensal || 'Diferença mensal')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.dif_mensal', pa.dif_mensal||'')}</td>` : ''}
              ${planos.map((p,i) => `<td class="cot">${ed('planos.'+i+'.dif_mensal', p.dif_mensal||'')}</td>`).join('')}
            </tr>
            <tr class="total">
              <td>${ed('lbl_dif_anual', st.lbl_dif_anual || 'Diferença anual')}</td>
              ${temPa ? `<td class="vig">${ed('plano_atual.dif_anual', pa.dif_anual||'')}</td>` : ''}
              ${planos.map((p,i) => `<td class="cot">${ed('planos.'+i+'.dif_anual', p.dif_anual||'')}</td>`).join('')}
            </tr>
          </tbody>
        </table>
        </div>
        <p style="font-size:11px;color:#888;font-style:italic;margin-top:14px">${ed('comp_disclaimer', compDisclaimer)}</p>
      </div>
    </div>
  `;

  // Slide 5 – Coparticipações
  const copartHtml = `
    <div class="slide" data-slide-id="coparticipacao">
      <div class="slide-tag">Slide 4 - Coparticipações</div>
      <div class="slide-body">
        <div class="slide-titulo">Coparticipações</div>
        <div class="slide-bar"></div>
        <p style="font-size:15px;line-height:1.6;color:#333">${ed('copart_intro', st.copart_intro, true)}</p>
        <p style="font-size:14px;font-weight:700;color:#006677;margin-top:18px;margin-bottom:8px">Procedimentos com coparticipação típicos:</p>
        <ul class="lista">
          ${(st.copart_itens || []).map((t,i) => `<li><div class="dot"></div>
            <div style="flex:1">${ed('copart_itens.'+i, t)}</div>
            <button class="ap-row-del" title="Remover esta linha" onclick="apDelCopartItem(${i})">✕</button>
          </li>`).join('')}
        </ul>
        <button class="ap-row-add" onclick="apAddCopartItem()">+ Adicionar linha</button>
      </div>
    </div>
  `;

  // Slide 6 – Carências
  const carenciasHtml = `
    <div class="slide" data-slide-id="carencias">
      <div class="slide-tag">Slide 5 - Carências</div>
      <div class="slide-body">
        <div class="slide-titulo">Carências</div>
        <div class="slide-bar"></div>
        <table class="comp-table">
          <thead><tr><th>Procedimento</th><th>Carência</th><th class="ap-col-ctrl"></th></tr></thead>
          <tbody>
            ${st.carencias.map((c,i) => `<tr>
              <td class="lbl">${ed('carencias.'+i+'.proc', c.proc)}</td>
              <td>${ed('carencias.'+i+'.carencia', c.carencia)}</td>
              <td class="ap-col-ctrl"><button class="ap-row-del" title="Remover esta linha" onclick="apDelCarencia(${i})">✕</button></td>
            </tr>`).join('')}
          </tbody>
        </table>
        <button class="ap-row-add" onclick="apAddCarencia()">+ Adicionar linha</button>
      </div>
    </div>
  `;

  // Slide 2 – Informações Gerais (movido pra logo após a capa)
  const infoHtml = `
    <div class="slide" data-slide-id="info">
      <div class="slide-tag">Slide 2 - Informações Gerais</div>
      <div class="slide-body">
        <div class="slide-titulo">Informações Gerais</div>
        <div class="slide-bar"></div>
        <div class="info-grid">
          <div class="lbl">Cliente</div><div class="val">${ed('cliente', st.cliente)}</div>
          <div class="lbl">Modalidade</div><div class="val">${ed('tipo', st.tipo)}</div>
          <div class="lbl">Quantidade de Vidas</div><div class="val">${ed('vidas', st.vidas)}</div>
          ${st.tem_plano_atual ? `<div class="lbl">Valor Vigente Mensal</div><div class="val">${ed('valor_vigente_fmt', fmtBRL(st.valor_vigente))}</div>` : ''}
          <div class="lbl">Vendedor / Consultor</div><div class="val">${ed('vendedor', st.vendedor)}</div>
          <div class="lbl">E-mail</div><div class="val">${ed('email', st.email)}</div>
          <div class="lbl">Telefone</div><div class="val">${ed('tel', st.tel)}</div>
        </div>
      </div>
    </div>
  `;

  // Slides 7+ – Entregáveis / Atividades realizadas (uma seção por slide; cada slide pode
  // ser excluído inteiro pra deixar só os portes que se aplicam)
  const secoes = st.entregaveis?.secoes || [];
  const entregaveisHtml = (secoes.length === 0
    ? `
      <div class="slide">
        <div class="slide-tag">Entregáveis (vazio)</div>
        <div class="slide-body" style="text-align:center;padding:40px">
          <p style="color:#888;margin-bottom:14px">Nenhuma tabela de entregáveis. Você removeu todas as seções.</p>
          <button class="ent-add" onclick="apEntregaveisRestaurar()">↻ Restaurar todas as 4 tabelas</button>
        </div>
      </div>
    `
    : secoes.map((sec, si) => `
      <div class="slide">
        <div class="slide-tag" style="display:flex;justify-content:space-between;align-items:center">
          <span>Slide ${7 + si} - Entregáveis (${escH(sec.titulo || '')})</span>
          <button class="ent-del-secao" title="Remover esta tabela inteira do PPT" onclick="apEntregaveisRemoverSecao(${si})">
            <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">delete</span> Excluir esta tabela
          </button>
        </div>
        <div class="slide-body">
          <div class="slide-titulo">${ed('entregaveis.secoes.'+si+'.titulo', sec.titulo || 'ATIVIDADES REALIZADAS')}</div>
          <div class="slide-bar"></div>
          <p style="font-size:12px;color:#888;font-style:italic;margin:0 0 10px">
            Clique no nome ou status pra editar. Use × pra remover linhas que não se aplicam. Use o botão "Excluir esta tabela" no topo se essa faixa de porte não se aplica ao cliente.
          </p>
          <table class="ent-table">
            <colgroup><col style="width:70%"><col style="width:25%"><col style="width:5%"></colgroup>
            <tbody>
              ${(sec.atividades || []).map((a, li) => `
                <tr>
                  <td class="ent-nome">${ed('entregaveis.secoes.'+si+'.atividades.'+li+'.nome', a.nome || '')}</td>
                  <td class="ent-status">${ed('entregaveis.secoes.'+si+'.atividades.'+li+'.status', a.status || '')}</td>
                  <td class="ent-acao">
                    <button class="ent-del" title="Remover linha" onclick="apEntregaveisRemoverLinha(${si}, ${li})">×</button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `).join('')
  );

  // Slide 8 – O que oferecemos
  const ofertasHtml = `
    <div class="slide" data-slide-id="ofertas">
      <div class="slide-tag">Slide 8 - O que oferecemos</div>
      <div class="slide-body">
        <div class="slide-titulo">Cuidando dos seus benefícios</div>
        <div class="slide-bar"></div>
        <ul class="lista">
          ${st.ofertas.map((t,i) => `<li><div class="dot"></div><div>${ed('ofertas.'+i, t)}</div></li>`).join('')}
        </ul>
      </div>
    </div>
  `;

  // Slide Contato — usa contato.png como slide inteiro (já tem logo INTERWEG
  // centralizado e os 3 blocos de contatos baked-in). Sem overlay de texto
  // pra não duplicar.
  const contatoHtml = `
    <div class="slide" data-slide-id="contato">
      <div class="slide-tag">Slide - Contato</div>
      <div class="slide-contato"></div>
    </div>
  `;

  // Ordem dos slides: Capa → Informações Gerais → demais slides estáticos → Comparativo → ...
  cont.innerHTML = capaHtml + infoHtml + html + comparativoHtml + copartHtml + carenciasHtml + entregaveisHtml + ofertasHtml + contatoHtml;

  // ─── Botão X em cada slide + banner de slides excluídos ─────────────
  // Injeta um botão de exclusão no canto direito de cada .slide-tag e
  // esconde os slides que estão em apPptState.slides_excluidos.
  const excluidos = apPptState.slides_excluidos || {};
  cont.querySelectorAll('.slide[data-slide-id]').forEach(slide => {
    const id = slide.dataset.slideId;
    // Esconde slide excluído
    if (excluidos[id]) { slide.style.display = 'none'; return; }
    const tag = slide.querySelector('.slide-tag');
    if (!tag) return;
    // Texto do tag pra usar como rótulo na confirmação e no banner
    const rotulo = tag.textContent.trim();
    // Se já tem botão (re-render), não duplica
    if (tag.querySelector('.slide-del-btn')) return;
    const btn = document.createElement('button');
    btn.className = 'slide-del-btn';
    btn.title = 'Remover este slide da apresentação';
    btn.textContent = '✕';
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      apExcluirSlide(id, rotulo);
    });
    tag.appendChild(btn);
  });

  // Banner no topo listando os slides excluídos (com botão de restaurar individual)
  const idsExcluidos = Object.keys(excluidos);
  if (idsExcluidos.length) {
    const banner = document.createElement('div');
    banner.className = 'slides-excluidos-banner';
    banner.innerHTML =
      '<span class="label">Slides excluídos da apresentação:</span>' +
      idsExcluidos.map(id => {
        const nome = excluidos[id] || id;
        return `<span class="chip">${escH(nome)}<button title="Restaurar este slide" onclick="apRestaurarSlide('${id}')">↻</button></span>`;
      }).join('') +
      '<button class="restore-all" onclick="apRestaurarTodosSlides()">↻ Restaurar todos</button>';
    cont.insertBefore(banner, cont.firstChild);
  }

  // ─── Padroniza a altura de TODOS os slides ──────────────────────
  // Força cada slide a ter proporção 16:9 (altura = largura × 9/16),
  // igual à capa e à página do PDF. Determinístico via JS (mais confiável
  // que aspect-ratio CSS, que encolhia a largura de alguns slides).
  apPadronizaAlturaSlides();

  // Bind dos editáveis: ao digitar, atualiza apPptState
  cont.querySelectorAll('[contenteditable="true"]').forEach(el => {
    el.addEventListener('input', e => {
      const path = e.target.dataset.path;
      if (e.target.classList.contains('ed-html')) {
        // Preserva o negrito digitado (Ctrl+B) — normaliza <strong> ⇄ <b>
        // para que o PPT exportado entenda os marcadores corretamente.
        const h = e.target.innerHTML
          .replace(/<strong>/gi, '<b>')
          .replace(/<\/strong>/gi, '</b>')
          // remove tags inline indesejadas que o navegador às vezes injeta
          .replace(/<font[^>]*>|<\/font>/gi, '')
          .replace(/<span[^>]*>|<\/span>/gi, '')
          .trim();
        apSetPath(apPptState, path, h);
      } else {
        apSetPath(apPptState, path, e.target.innerText.trim());
      }
    });
    // Evita quebra de linha em edits inline
    if (!el.classList.contains('ed-block')) {
      el.addEventListener('keydown', e => { if(e.key === 'Enter') e.preventDefault(); });
    }
  });
}

// Força todos os slides a terem a mesma proporção (16:9), igual à capa.
// Roda após o render e em resize. Usa a largura real de cada slide.
function apPadronizaAlturaSlides() {
  const cont = document.getElementById('ap-ppt-slides');
  if (!cont) return;
  requestAnimationFrame(() => {
    cont.querySelectorAll('.slide').forEach(s => {
      const w = s.clientWidth;
      if (w > 0) s.style.height = Math.round(w * 9 / 16) + 'px';
    });
  });
}
window.addEventListener('resize', () => { if (document.getElementById('ap-ppt-slides')) apPadronizaAlturaSlides(); });

// ── Adicionar/remover linhas em Coparticipação e Carências ──────────
// (chamadas pelos botões "+ Adicionar linha" e "✕" no preview)
function apAddCopartItem() {
  if (!apPptState.copart_itens) apPptState.copart_itens = [];
  apPptState.copart_itens.push('Novo procedimento');
  apRenderSlidesPreview();
}
function apDelCopartItem(i) {
  if (apPptState.copart_itens) { apPptState.copart_itens.splice(i, 1); apRenderSlidesPreview(); }
}
function apAddCarencia() {
  if (!apPptState.carencias) apPptState.carencias = [];
  apPptState.carencias.push({ proc: 'Novo procedimento', carencia: '' });
  apRenderSlidesPreview();
}
function apDelCarencia(i) {
  if (apPptState.carencias) { apPptState.carencias.splice(i, 1); apRenderSlidesPreview(); }
}

// Helpers de path (para ler/escrever em apPptState pelo caminho "planos.0.valor")
function apSetPath(obj, path, value) {
  const parts = path.split('.');
  let cur = obj;
  for (let i = 0; i < parts.length - 1; i++) {
    const k = parts[i];
    if (cur[k] == null) cur[k] = (isNaN(parts[i+1]) ? {} : []);
    cur = cur[k];
  }
  // Tratamento especial pra valores formatados
  const last = parts[parts.length - 1];
  if (last === 'valor_fmt' || last === 'valor_vigente_fmt') {
    // remove "R$" e espaços, salva no campo correspondente sem _fmt
    const realKey = last.replace('_fmt','');
    const stripped = String(value).replace(/^R\$\s*/, '').trim();
    cur[realKey] = stripped;
  } else {
    cur[last] = value;
  }
}

// ──────────────────────────────────────────────────────────
// GERA PPT a partir de apPptState (já com edições do usuário)
// ──────────────────────────────────────────────────────────
async function apGerarPPT() {
  const st = apPptState;
  if (!st) { toast('Estado vazio. Abra o preview primeiro'); return; }

  toast('Gerando PPT…');
  try { await loadPptxGen(); }
  catch (e) { toast('Erro carregando biblioteca PPT'); return; }

  // Carrega imagens de branding (logo Interweg gráfico oficial + foto da equipe)
  // a partir de /img/ no servidor. Tudo em paralelo pra ser rápido.
  const branding = await carregarBranding();
  const logoPng       = branding.logo;
  const capaEquipe    = branding.capaEquipe;
  const institucional = branding.institucional;

  const pptx = new PptxGenJS();
  pptx.layout = 'LAYOUT_WIDE';
  pptx.title = `Proposta ${st.cliente}`;
  pptx.author = 'Interweg Seguros';
  pptx.company = 'Interweg Seguros e Benefícios';

  const W = 13.33, H = 7.5;

  // Helper: cria um slide normal OU devolve um "slide fantasma" que ignora todas
  // as chamadas, quando o usuário marcou esse slide como excluído no preview.
  // Permite manter o código de cada slide igual — só troca pptx.addSlide() por addSlide('id').
  const slidesExcluidos = st.slides_excluidos || {};
  const dummySlide = {
    background: null,
    addText:  () => {}, addImage: () => {},
    addShape: () => {}, addTable: () => {},
    addChart: () => {}, addNotes: () => {}
  };
  const addSlide = (id) => slidesExcluidos[id] ? dummySlide : pptx.addSlide();

  // ────────────────────────────────────────────────────────────
  // SLIDE 1: CAPA — usa institucional_interweg.png (foto da equipe + swirl
  // teal + logo Interweg branca, tudo numa peça só) como fundo e coloca
  // texto editável (título, cliente, data) por cima na lateral esquerda.
  // ────────────────────────────────────────────────────────────
  const s1 = addSlide('capa');
  s1.background = { color: 'F5F7F8' };  // fallback caso a imagem não carregue

  // Imagem institucional cobrindo o slide inteiro (ela já tem o degradê de
  // gray/branco na esquerda, foto da equipe, swirl e logo)
  if (institucional) {
    s1.addImage({ data: institucional, x: 0, y: 0, w: W, h: H, sizing:{ type:'cover', w:W, h:H } });
  } else if (capaEquipe) {
    // Fallback: mantém o layout antigo se institucional_interweg.png não existir
    s1.addImage({ data: capaEquipe, x: W * 0.45, y: 0.5, w: W * 0.55, h: H - 1.2 });
  }

  // Título principal em teal escuro grande, à esquerda
  s1.addText(st.titulo_capa || `Proposta de ${st.tipo}\nEmpresarial`, {
    x: 0.6, y: 1.2, w: 6.8, h: 2.2,
    fontSize: 50, color: IW_TEAL_DARK, bold: true, fontFace: 'Calibri', valign: 'top', lineSpacingMultiple: 0.95
  });

  // Nome da empresa (cliente) e data — sem rótulos cinza, só os valores em teal escuro
  s1.addText(st.cliente || '', {
    x: 0.6, y: 4.6, w: 6.8, h: 0.6,
    fontSize: 22, color: IW_TEAL_DARK, bold: true, fontFace: 'Calibri'
  });
  s1.addText(st.data || '', {
    x: 0.6, y: 5.5, w: 6.8, h: 0.45,
    fontSize: 16, color: IW_TEAL_DARK, bold: true, fontFace: 'Calibri'
  });

  // Logo Interweg já vem na imagem institucional, então não adiciona aqui

  // ────────────────────────────────────────────────────────────
  // SLIDE 2: INFORMAÇÕES GERAIS (movido pra logo após a capa)
  // ────────────────────────────────────────────────────────────
  const sInfo = addSlide('info');
  sInfo.background = { color:'FFFFFF' };
  sInfo.addText('Informações Gerais', { x:0.7, y:0.6, w:12, h:0.8, fontSize:34, color:IW_TEAL_DARK, bold:true, fontFace:'Arial' });
  sInfo.addShape('rect', { x:0.7, y:1.45, w:1.5, h:0.07, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });
  const infoItemsTop = [
    ['Cliente', st.cliente],
    ['Modalidade', st.tipo],
    ['Quantidade de Vidas', (st.vidas || '') + (st.vidas ? ' vidas' : '')],
    // "Valor Vigente Mensal" só aparece se o bloco "Plano Atual" não foi excluído
    ...(st.tem_plano_atual ? [['Valor Vigente Mensal', fmtBRL(st.valor_vigente)]] : []),
    ['Vigência', 'Conforme contrato'],
    ['Vendedor / Consultor', st.vendedor],
    ['E-mail de contato', st.email],
    ['Telefone', st.tel]
  ];
  infoItemsTop.forEach((it, i) => {
    const y = 2.1 + i*0.55;
    sInfo.addText(it[0], { x:0.7, y, w:4, h:0.5, fontSize:13, bold:true, color:IW_TEAL_DARK, fontFace:'Arial' });
    sInfo.addText(it[1] || '', { x:5, y, w:7.5, h:0.5, fontSize:13, color:IW_TEXT, fontFace:'Arial' });
    sInfo.addShape('line', { x:0.7, y:y+0.45, w:11.9, h:0, line:{color:'EEEEEE', width:0.5} });
  });
  addLogoRodape(sInfo, branding.logoEscuro || logoPng);

  // SLIDE 3: QUEM SOMOS — fundo navy à esquerda, foto institucional (quem_somos.png) à direita
  const s2 = addSlide('quem_somos');
  s2.background = { color:'0E2A36' }; // navy escuro (cobre o slide inteiro como fundo)
  // Bloco navy explícito à esquerda (garante contraste mesmo se a foto for transparente)
  s2.addShape('rect', { x:0, y:0, w:W*0.55, h:H, fill:{color:'0E2A36'}, line:{color:'0E2A36'} });
  // Foto institucional (mosaico de fotos do escritório) na metade direita.
  // sizing:cover preenche o bloco inteiro sem faixa navy ao redor das fotos
  // (que ficava feio na borda esquerda e direita). Pequeno corte vertical
  // é aceitável — a colagem mantém todas as 3 fotos reconhecíveis.
  const quemSomos = branding.quemSomos;
  if (quemSomos) {
    s2.addImage({
      data: quemSomos,
      x: W*0.55, y: 0, w: W*0.45, h: H,
      sizing: { type:'cover', w: W*0.45, h: H }
    });
  }
  // Título "Quem somos!" branco grande
  s2.addText(st.quem_somos_titulo || 'Quem somos!', {
    x:0.7, y:0.9, w:6.5, h:1.0,
    fontSize:38, color:'FFFFFF', bold:true, fontFace:'Calibri'
  });
  // Parágrafos brancos com "Interweg Seguros e Benefícios" destacado em teal
  // Usa array de runs para colorir só a parte que importa (sem hardcodear o restante).
  const _hl = (txt) => {
    const m = String(txt).match(/(.*?)(Interweg Seguros e Benef[ií]cios)(.*)/);
    if (!m) return [{ text: String(txt), options:{ color:'FFFFFF' } }];
    return [
      { text: m[1], options:{ color:'FFFFFF' } },
      { text: m[2], options:{ color:'00BFB3', bold:true } },
      { text: m[3], options:{ color:'FFFFFF' } }
    ];
  };
  s2.addText(_hl(st.quem_somos_1), {
    x:0.7, y:2.2, w:6.5, h:1.8,
    fontSize:14, fontFace:'Calibri', valign:'top', lineSpacingMultiple:1.4
  });
  s2.addText(_hl(st.quem_somos_2), {
    x:0.7, y:4.2, w:6.5, h:2.8,
    fontSize:14, fontFace:'Calibri', valign:'top', lineSpacingMultiple:1.4
  });
  // Logo de rodapé não vai aqui — design não pede e o fundo é escuro

  // ────────────────────────────────────────────────────────────
  // SLIDE 3: NOSSAS SOLUÇÕES — foto institucional à esquerda + título e 3 cards
  // ────────────────────────────────────────────────────────────
  const sSol = addSlide('solucoes');
  sSol.background = { color:'F4F6F8' };
  // Imagem como fundo decorativo à esquerda (não coluna): ocupa ~38% da
  // largura, centralizada verticalmente, em altura cheia.
  if (branding.nossasSolucoes) {
    sSol.addImage({
      data: branding.nossasSolucoes,
      x: 0, y: 0, w: W*0.38, h: H,
      sizing: { type:'cover', w: W*0.38, h: H }
    });
  }
  // Conteúdo (título + 3 cards) centralizado horizontalmente no slide inteiro
  const cardW = W*0.55;            // ~7.3 pol
  const cardX = (W - cardW) / 2;   // centro horizontal real
  // Título
  sSol.addText(st.solucoes_titulo || 'Nossas soluções', {
    x: cardX, y: 0.9, w: cardW, h: 0.8,
    fontSize: 32, color: IW_TEAL, bold: true, fontFace:'Calibri', align:'center'
  });
  // 3 cards (retângulos com borda teal e texto teal escuro)
  const cards = [
    st.solucoes_card_1 || 'Consultoria para a gestão de Riscos Corporativos',
    st.solucoes_card_2 || 'Gestão integrada de Saúde e Benefícios',
    st.solucoes_card_3 || 'Seguros para bens de alto valor e outras soluções'
  ];
  cards.forEach((txt, idx) => {
    const cy = 2.4 + idx * 1.20;
    sSol.addShape('roundRect', {
      x: cardX, y: cy, w: cardW, h: 0.95,
      fill:{ color:'FFFFFF' }, line:{ color:'00BFB3', width:2 },
      rectRadius: 0.12
    });
    sSol.addText(txt, {
      x: cardX, y: cy, w: cardW, h: 0.95,
      fontSize: 14, color: IW_TEAL_DARK, bold: true, fontFace:'Calibri',
      align:'center', valign:'middle'
    });
  });
  addLogoRodape(sSol, branding.logoEscuro || logoPng, 'top-right');

  // ────────────────────────────────────────────────────────────
  // SLIDE 4: GESTÃO DA SAÚDE E BENEFÍCIOS — texto à esquerda + foto à direita
  // ────────────────────────────────────────────────────────────
  const sGest = addSlide('gestao');
  sGest.background = { color:'F4F6F8' };
  if (branding.gestaoSaude) {
    sGest.addImage({
      data: branding.gestaoSaude,
      x: W*0.50, y: 0, w: W*0.50, h: H,
      sizing: { type:'cover', w: W*0.50, h: H },
      transparency: 45 // foto bem clarinha tipo marca d'água
    });
  }
  // Título grande em teal vibrante
  sGest.addText(st.gestao_titulo || 'Gestão da\nSaúde e Benefícios', {
    x: 0.7, y: 1.6, w: 6.2, h: 1.6,
    fontSize: 34, color: IW_TEAL, bold: true, fontFace:'Calibri', valign:'top', lineSpacingMultiple: 1.05
  });
  // Parágrafo (com partes em negrito processadas runtime)
  const _gestParagrafo = String(st.gestao_paragrafo || '').replace(/<\/?b>/g, '|||');
  const _gestParts = _gestParagrafo.split('|||');
  const _gestRuns = _gestParts.map((t,i) => ({ text: t, options: { bold: i % 2 === 1, color:'1F3438' } }));
  sGest.addText(_gestRuns, {
    x: 0.7, y: 3.3, w: 5.8, h: 1.6,
    fontSize: 13, fontFace:'Calibri', valign:'top', lineSpacingMultiple: 1.4
  });
  // 3 pílulas teal com check
  const pills = [
    st.gestao_pill_1 || 'Plano de Saúde',
    st.gestao_pill_2 || 'Vida em Grupo',
    st.gestao_pill_3 || 'Plano Odontológico'
  ];
  pills.forEach((p, idx) => {
    const py = 5.1 + idx * 0.55;
    sGest.addShape('roundRect', {
      x: 0.7, y: py, w: 2.8, h: 0.45,
      fill:{ color:'00BFB3' }, line:{ color:'00BFB3' },
      rectRadius: 0.07
    });
    sGest.addText('  ✓   ' + p, {
      x: 0.7, y: py, w: 2.8, h: 0.45,
      fontSize: 12, color:'FFFFFF', bold:true, fontFace:'Calibri',
      align:'left', valign:'middle'
    });
  });
  addLogoRodape(sGest, branding.logoEscuro || logoPng, 'top-right');

  // ────────────────────────────────────────────────────────────
  // SLIDE 5: COMO ATUAMOS — fundo navy + título centralizado
  // + 4 quadrantes (com bullet points em check teal) ao redor do quebra-cabeças
  // ────────────────────────────────────────────────────────────
  const sAtu = addSlide('atuamos');
  sAtu.background = { color:'0E2A36' };
  // Título "Como atuamos" centralizado no topo
  sAtu.addText(st.atuamos_titulo || 'Como atuamos', {
    x: 0, y: 0.4, w: W, h: 0.7,
    fontSize: 32, color:'FFFFFF', bold:true, fontFace:'Calibri', align:'center'
  });

  // Centro: 4 retângulos teal formando um "quebra-cabeças" 2x2 (simplificação)
  const cX = W/2, cY = H/2 + 0.3, pW = 1.2, pH = 1.2;
  const pieceColors = ['0a8a8d','0fb5ad','0fb5ad','0a8a8d'];
  const piecePos = [[-pW,-pH],[0,-pH],[-pW,0],[0,0]];
  piecePos.forEach((pos, i) => {
    sAtu.addShape('roundRect', {
      x: cX + pos[0], y: cY + pos[1], w: pW, h: pH,
      fill:{ color: pieceColors[i] }, line:{ color: pieceColors[i] },
      rectRadius: 0.12
    });
  });
  // 4 círculos brancos com ícones (emojis Unicode que renderizam universalmente
  // em PPT). Ordem: top-left = Operacional (clipboard), top-right = Multidisciplinar
  // (escudo/check), bottom-left = Gestão da Saúde (lupa), bottom-right = Estratégica (gráfico).
  const iconPos   = [[-pW/2,-pH/2],[pW/2,-pH/2],[-pW/2,pH/2],[pW/2,pH/2]];
  const iconChars = ['📋','🛡','🔍','📊'];
  iconPos.forEach((pos, i) => {
    const icX = cX + pos[0] - 0.32;
    const icY = cY + pos[1] - 0.32;
    sAtu.addShape('ellipse', {
      x: icX, y: icY, w: 0.64, h: 0.64,
      fill:{ color:'FFFFFF' }, line:{ color:'FFFFFF' }
    });
    sAtu.addText(iconChars[i], {
      x: icX, y: icY, w: 0.64, h: 0.64,
      fontSize: 22, color: '0a8a8d', fontFace: 'Segoe UI Emoji',
      align: 'center', valign: 'middle'
    });
  });

  // 4 seções de listas: 2 à esquerda (alinhadas à direita) + 2 à direita (alinhadas à esquerda)
  const fmtBullets = (itens) => (itens || []).map(it => ({ text: it, options:{ bullet:{ code:'25CF' }, color:'E6EEF1' } }));
  const sec = [
    // [titulo, itens, x, y, w, alinhamento]
    [st.atuamos_p1_titulo, st.atuamos_p1_itens, 0.5, 1.5, 4.5, 'right'], // top-left
    [st.atuamos_p2_titulo, st.atuamos_p2_itens, W-5.0, 1.5, 4.5, 'left'], // top-right
    [st.atuamos_p3_titulo, st.atuamos_p3_itens, 0.5, 4.6, 4.5, 'right'], // bottom-left
    [st.atuamos_p4_titulo, st.atuamos_p4_itens, W-5.0, 4.6, 4.5, 'left']  // bottom-right
  ];
  sec.forEach(([titulo, itens, x, y, w, align]) => {
    sAtu.addText(titulo || '', {
      x, y, w, h:0.4,
      fontSize:15, bold:true, color:'00BFB3', fontFace:'Calibri', align
    });
    // Bullets como check com cor teal (pptxgenjs limita customização — usa círculo teal)
    const runs = (itens || []).map(it => ({
      text: '✓  ' + it + '\n',
      options:{ color:'E6EEF1', fontSize:11, fontFace:'Calibri' }
    }));
    sAtu.addText(runs, {
      x, y: y + 0.45, w, h: 1.8,
      align, valign:'top', lineSpacingMultiple:1.4, fontSize:11, fontFace:'Calibri'
    });
  });
  // Como Atuamos tem fundo navy escuro — usa logo_interweg.jpg que foi
  // preparado especificamente pra fundos escuros. Se não existir, cai no
  // logo cinza padrão.
  addLogoRodape(sAtu, branding.logoEscuro || logoPng, 'top-right');

  // ────────────────────────────────────────────────────────────
  // SLIDE 6: INTELIGÊNCIA ARTIFICIAL E MEDICINA PREVENTIVA
  // (texto à esquerda + imagem tech à direita)
  // ────────────────────────────────────────────────────────────
  const sIA = addSlide('ia');
  sIA.background = { color:'F4F6F8' };
  if (branding.iaMedicina) {
    sIA.addImage({
      data: branding.iaMedicina,
      x: W*0.50, y: 0, w: W*0.50, h: H,
      sizing: { type:'cover', w: W*0.50, h: H }
    });
  }
  // Título grande em teal escuro
  sIA.addText(st.ia_titulo || 'Inteligência Artificial e medicina preventiva', {
    x: 0.7, y: 1.6, w: 5.5, h: 1.8,
    fontSize: 30, color:'0F4C5C', fontFace:'Calibri', valign:'top', lineSpacingMultiple: 1.05
  });
  sIA.addText(st.ia_paragrafo || '', {
    x: 0.7, y: 3.6, w: 5.5, h: 3.0,
    fontSize: 13, color:'1F3438', fontFace:'Calibri', valign:'top', lineSpacingMultiple: 1.5
  });
  // Logo no canto superior esquerdo (canto direito tem foto cobrindo metade do slide)
  addLogoRodape(sIA, branding.logoEscuro || logoPng, 'top-left');

  // SLIDE 3 (legacy): COMPARATIVO DE CUSTOS — design alinhado ao Modelo_Apresentacao_Interweg.pptx
  const s3 = addSlide('comparativo');
  s3.background = { color:'FFFFFF' };
  // Título grande em caixa alta, igual ao modelo
  s3.addText('COMPARATIVO DE CUSTOS', {
    x:0.30, y:0.20, w:9.5, h:0.55,
    fontSize:30, color:IW_TEAL_DARK, bold:true, fontFace:'Calibri', charSpacing:1
  });
  const temPaSlide3 = !!st.tem_plano_atual; // coluna "Plano Atual" opcional
  // Legenda de orientação — vem do estado (editável no preview)
  const _compLegend = st.comp_legend || (temPaSlide3
    ? 'Coluna em destaque = plano atual do cliente. Demais colunas = cotações vigentes para análise.'
    : 'Cotações vigentes para análise.');
  s3.addText(_compLegend, {
    x:0.30, y:0.78, w:10, h:0.30,
    fontSize:9, italic:true, color:IW_GRAY, fontFace:'Calibri'
  });

  // Limite de 4 cotações por slide no PPT/PDF — com 5+ a tabela estoura a
  // largura do slide e a última coluna aparece cortada (filete). O preview
  // HTML continua mostrando todas com scroll horizontal pra edição.
  const MAX_COT_NO_SLIDE = 4;
  const planos = st.planos.slice(0, MAX_COT_NO_SLIDE);
  const numCols = (temPaSlide3 ? 1 : 0) + planos.length;   // Plano Atual (opcional) + Cotações
  const tableX = 0.30, tableY = 1.10, tableW = W - 0.60;
  const labelW = 2.40;
  const valColW = (tableW - labelW) / numCols;
  const colW = [labelW].concat(Array(numCols).fill(valColW));

  // Estilos — espelham o modelo: cabeçalho teal escuro, Plano Atual em bege com texto teal escuro,
  // cotações em zebra (cinza muito claro / branco), bordas brancas finas.
  const BORDER = { type:'solid', color:'FFFFFF', pt:0.5 };
  const headOpts        = { fontFace:'Calibri', fontSize:11, bold:true, color:'FFFFFF',  align:'center', valign:'middle', fill:{color:IW_TEAL_DARK}, border:BORDER };
  // Cabeçalho da coluna "PLANO ATUAL" — teal médio, um tom abaixo do verde escuro dos demais cabeçalhos
  const headPaOpts      = { fontFace:'Calibri', fontSize:11, bold:true, color:'FFFFFF',  align:'center', valign:'middle', fill:{color:IW_TEAL_MED},  border:BORDER };
  // Cabeçalho do canto superior-esquerdo (vazio) — teal escuro, fechando a "moldura" com a coluna de rótulos
  const headEmptyOpts   = { fontFace:'Calibri', fontSize:11, bold:true, color:'FFFFFF',  align:'center', valign:'middle', fill:{color:IW_TEAL_DARK}, border:BORDER };
  // Rótulos da coluna esquerda — teal escuro com texto branco, alinhados ao centro
  const labelOptsBase   = { fontFace:'Calibri', fontSize:10, bold:true, color:'FFFFFF',  align:'center', valign:'middle', fill:{color:IW_TEAL_DARK}, border:BORDER };
  // Células do corpo da coluna PLANO ATUAL — cinza claro suave + texto em teal escuro/negrito (diferencia das cotações sem o amarelo antigo)
  const vigOptsBase     = { fontFace:'Calibri', fontSize:10, bold:true, color:IW_TEAL_DARK, align:'center', valign:'middle', fill:{color:IW_GRAY_LIGHT}, border:BORDER };
  const cellOptsBase    = { fontFace:'Calibri', fontSize:10, color:IW_TEXT, align:'center', valign:'middle', border:BORDER };

  const pa = st.plano_atual || {};
  const rows = [];

  // Cabeçalho: vazio | (PLANO ATUAL opcional) | COTAÇÃO 1, 2, 3...
  // Sempre 3 linhas: "Título" / "SEGURADORA" / "Plano" — evita que nomes longos
  // (BRADESCO SEGUROS Nacional Plus 6) infrem a coluna e quebrem de forma feia.
  const segPa = (pa.operadora || '').toUpperCase();
  const paLabel = segPa
    ? 'PLANO ATUAL\n' + segPa + (pa.plano ? '\n' + pa.plano : '')
    : 'PLANO ATUAL';
  const hdr = [ { text:'', options: headEmptyOpts } ];
  if (temPaSlide3) hdr.push({ text: paLabel, options: headPaOpts });
  planos.forEach((p, i) => {
    const seg   = (p.seguradora || '').toUpperCase();
    const plano = p.plano || '';
    const linha2 = seg || `COTAÇÃO ${i+1}`;
    const linha3 = plano ? '\n' + plano : '';
    hdr.push({ text: `COTAÇÃO ${i+1}\n` + linha2 + linha3, options: headOpts });
  });
  rows.push(hdr);

  // Helper: linha do corpo. Coluna de rótulo herda o teal escuro de labelOptsBase;
  // coluna do Plano Atual herda o cinza claro de vigOptsBase; cotações em branco.
  function dataRow(label, vigVal, vals) {
    const labelOpts = { ...labelOptsBase };
    const vigOpts   = { ...vigOptsBase };
    const cellOpts  = { ...cellOptsBase,  fill: { color: 'FFFFFF' } };
    const row = [ { text: label, options: labelOpts } ];
    if (temPaSlide3) row.push({ text: (vigVal || ''), options: vigOpts });
    vals.forEach(v => row.push({ text: v || '', options: cellOpts }));
    return row;
  }
  // Usa os labels editáveis do estado (com fallback para o padrão)
  rows.push(dataRow(st.lbl_acomodacao     || 'Acomodação',     pa.acomodacao,     planos.map(p => p.acomodacao)));
  rows.push(dataRow(st.lbl_modalidade     || 'Modalidade',     pa.modalidade,     planos.map(p => p.modalidade)));
  rows.push(dataRow(st.lbl_coparticipacao || 'Coparticipação', pa.coparticipacao, planos.map(p => p.coparticipacao)));

  st.faixas.forEach((faixa, idx) => {
    const valoresFaixa = planos.map(p => {
      const f = (p.faixas||[]).find(x => x.faixa === faixa);
      return f && f.valor ? fmtBRL(f.valor) : '';
    });
    const lblFaixa = st['lbl_faixa_' + idx] || ('Faixa ' + faixa);
    rows.push(dataRow(lblFaixa, '', valoresFaixa));
  });

  // Linha de Total — mesma família visual mas mais forte (label em teal escuro, Plano Atual mantém destaque)
  const totalLblOpts = { ...labelOptsBase, fill: { color: IW_TEAL_DARK }, color: 'FFFFFF', fontSize: 11 };
  // Total do PLANO ATUAL — destaque suave em teal bem claro (mesma família dos totais das cotações)
  const totalVigOpts = { ...vigOptsBase, fontSize: 12, fill: { color: IW_HIGHLIGHT } };
  const totalCotOpts = { ...cellOptsBase, bold: true, fontSize: 12, fill: { color: 'E6F9F7' }, color: IW_TEAL_DARK };
  const totalRow = [ { text: (st.lbl_total_mensal || 'TOTAL MENSAL'), options: totalLblOpts } ];
  if (temPaSlide3) totalRow.push({ text: fmtBRL(st.valor_vigente), options: totalVigOpts });
  planos.forEach(p => totalRow.push({ text: fmtBRL(p.valor), options: totalCotOpts }));

  // Linhas adicionais (IOF, Reembolso, Reembolso consulta) — só entram no PPT
  // se houver algum valor preenchido, pra não gerar linhas vazias.
  const _temValor = (vig, vals) => (vig && String(vig).trim()) || vals.some(v => v && String(v).trim());
  if (_temValor(pa.iof, planos.map(p=>p.iof)))
    rows.push(dataRow(st.lbl_iof || 'IOF', pa.iof, planos.map(p=>p.iof)));
  if (_temValor(pa.reembolso, planos.map(p=>p.reembolso)))
    rows.push(dataRow(st.lbl_reembolso || 'Reembolso', pa.reembolso, planos.map(p=>p.reembolso)));
  if (_temValor(pa.reembolso_consulta, planos.map(p=>p.reembolso_consulta)))
    rows.push(dataRow(st.lbl_reembolso_consulta || 'Reembolso para consulta médica', pa.reembolso_consulta, planos.map(p=>p.reembolso_consulta)));
  // TOTAL MENSAL é a última linha do bloco de custos (depois de IOF/Reembolsos)
  rows.push(totalRow);

  // Diferença mensal / anual — linhas de destaque (só se preenchidas)
  function difRow(label, vigVal, vals){
    const row = [ { text: label, options: { ...totalLblOpts, fill:{color:IW_TEAL} } } ];
    if (temPaSlide3) row.push({ text: (vigVal||''), options: totalVigOpts });
    vals.forEach(v => row.push({ text: v||'', options: totalCotOpts }));
    return row;
  }
  if (_temValor(pa.dif_mensal, planos.map(p=>p.dif_mensal)))
    rows.push(difRow(st.lbl_dif_mensal || 'Diferença mensal', pa.dif_mensal, planos.map(p=>p.dif_mensal)));
  if (_temValor(pa.dif_anual, planos.map(p=>p.dif_anual)))
    rows.push(difRow(st.lbl_dif_anual || 'Diferença anual', pa.dif_anual, planos.map(p=>p.dif_anual)));

  s3.addTable(rows, { x: tableX, y: tableY, w: tableW, colW, rowH: 0.36 });
  // Disclaimer do rodapé — vem do estado (editável no preview)
  s3.addText(st.comp_disclaimer || 'Valores e demais condições são determinadas pelas seguradoras e podem ser alteradas a qualquer momento.',
    { x:0.30, y:H-0.45, w:W-0.60, h:0.30, fontSize:8.5, color:IW_GRAY, italic:true, fontFace:'Calibri', align:'center' });
  addLogoRodape(s3, branding.logoEscuro || logoPng, 'top-right');

  // SLIDE 4: COPARTICIPAÇÕES
  const s4 = addSlide('coparticipacao');
  s4.background = { color:'FFFFFF' };
  s4.addText('Coparticipações', { x:0.7, y:0.6, w:12, h:0.8, fontSize:34, color:IW_TEAL_DARK, bold:true, fontFace:'Arial' });
  s4.addShape('rect', { x:0.7, y:1.45, w:1.5, h:0.07, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });
  s4.addText(st.copart_intro, { x:0.7, y:2.0, w:12, h:1.0, fontSize:16, color:IW_TEXT, fontFace:'Arial' });
  s4.addText('Procedimentos com coparticipação típicos:', { x:0.7, y:3.2, w:12, h:0.5, fontSize:18, color:IW_TEAL_DARK, bold:true, fontFace:'Arial' });
  (st.copart_itens || []).forEach((t, i) => {
    if (!t) return;
    s4.addShape('ellipse', { x:0.9, y:3.95+i*0.5, w:0.18, h:0.18, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });
    s4.addText(t, { x:1.3, y:3.85+i*0.5, w:11, h:0.4, fontSize:14, color:IW_TEXT, fontFace:'Arial' });
  });
  addLogoRodape(s4, branding.logoEscuro || logoPng);

  // SLIDE 5: CARÊNCIAS
  const s5 = addSlide('carencias');
  s5.background = { color:'FFFFFF' };
  s5.addText('Carências', { x:0.7, y:0.6, w:12, h:0.8, fontSize:34, color:IW_TEAL_DARK, bold:true, fontFace:'Arial' });
  s5.addShape('rect', { x:0.7, y:1.45, w:1.5, h:0.07, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });
  const carHdr = [
    {text:'Procedimento', options:{fill:{color:IW_TEAL_DARK}, color:'FFFFFF', bold:true, fontSize:12, align:'center', fontFace:'Arial', border:{type:'solid', color:'FFFFFF', pt:0.5}}},
    {text:'Carência',     options:{fill:{color:IW_TEAL_DARK}, color:'FFFFFF', bold:true, fontSize:12, align:'center', fontFace:'Arial', border:{type:'solid', color:'FFFFFF', pt:0.5}}}
  ];
  const carRowOpts = {fontSize:13, fontFace:'Arial', color:IW_TEXT, valign:'middle', border:{type:'solid', color:'CCCCCC', pt:0.5}};
  const carRows = [carHdr];
  st.carencias.forEach(c => {
    carRows.push([
      {text:c.proc, options:carRowOpts},
      {text:c.carencia, options:{...carRowOpts, align:'center'}}
    ]);
  });
  s5.addTable(carRows, { x:1, y:2.2, w:11.3, colW:[7, 4.3], rowH:0.55 });
  addLogoRodape(s5, branding.logoEscuro || logoPng);

  // (O slide "Informações Gerais" foi movido pra logo após a capa — ver sInfo acima.)

  // SLIDES DE ENTREGÁVEIS / ATIVIDADES REALIZADAS
  // Cada seção (até 29 / 30-99 / 100+ / 300+) vira UM slide. A pessoa remove
  // as seções que não se aplicam pro cliente direto no preview.
  if (st.entregaveis && Array.isArray(st.entregaveis.secoes)) {
    // Estilo das células — fundo teal, texto branco, bordas brancas (igual ao PDF original)
    const BORDER_W = { type:'solid', color:'FFFFFF', pt:1.2 };
    const cellNomeOpts   = { fontFace:'Arial', fontSize:11, color:'FFFFFF', align:'left',   valign:'middle', fill:{color:IW_TEAL_DARK}, border:BORDER_W, margin:0.05 };
    const cellStatusOpts = { fontFace:'Arial', fontSize:12, bold:true, color:'FFFFFF', align:'center', valign:'middle', fill:{color:IW_TEAL}, border:BORDER_W, margin:0.05 };

    st.entregaveis.secoes.forEach(sec => {
      if (!sec || !Array.isArray(sec.atividades) || sec.atividades.length === 0) return;
      const sEnt = pptx.addSlide();
      sEnt.background = { color:'FFFFFF' };
      // Logo no canto superior direito
      addLogoRodape(sEnt, branding.logoEscuro || logoPng, 'top-right');

      // Título com largura limitada pra não bater no logo
      sEnt.addText(sec.titulo || 'ATIVIDADES REALIZADAS', {
        x:0.7, y:0.5, w:10.5, h:0.6,
        fontSize:22, color:IW_TEAL_DARK, bold:true, fontFace:'Arial', charSpacing:1
      });
      sEnt.addShape('rect', { x:0.7, y:1.1, w:1.5, h:0.06, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });

      const entRows = sec.atividades.map(a => [
        { text: a.nome || '', options: cellNomeOpts },
        { text: a.status || '', options: cellStatusOpts }
      ]);

      // Tabela mais compacta: começa logo abaixo do divisor, termina com margem
      // generosa no rodapé. Cada linha tem altura entre 0.22 (padrão) e o que
      // couber. Tabela ocupa ~75% da largura (centralizada).
      const yStart  = 1.4;
      const yEnd    = H - 0.6;
      const altDisp = yEnd - yStart;
      const rowH    = Math.min(0.26, altDisp / Math.max(entRows.length, 1));
      const tableW  = 10.0;
      const tableX  = (W - tableW) / 2;

      sEnt.addTable(entRows, {
        x: tableX, y: yStart, w: tableW, colW: [7.0, 3.0],
        rowH: rowH
      });
    });
  }

  // SLIDE 8: O QUE OFERECEMOS
  const s7 = addSlide('ofertas');
  s7.background = { color:'FFFFFF' };
  s7.addText('Cuidando dos seus benefícios', { x:0.7, y:0.6, w:12, h:0.8, fontSize:32, color:IW_TEAL_DARK, bold:true, fontFace:'Arial' });
  s7.addShape('rect', { x:0.7, y:1.45, w:1.5, h:0.07, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });
  ['ofertas.0','ofertas.1','ofertas.2','ofertas.3','ofertas.4'].forEach((k, i) => {
    const t = st.ofertas[i] || '';
    const y = 2.3 + i*0.85;
    s7.addShape('rect', { x:0.7, y, w:0.15, h:0.6, fill:{color:IW_TEAL}, line:{color:IW_TEAL} });
    s7.addText(t, { x:1.1, y, w:11.5, h:0.6, fontSize:14, color:IW_TEXT, fontFace:'Arial', valign:'middle' });
  });
  addLogoRodape(s7, branding.logoEscuro || logoPng);

  // SLIDE FINAL: CONTATO — usa contato.png como slide inteiro (logo + 3 blocos
  // de contatos já vêm baked-in na imagem). Sem texto sobreposto pra não duplicar.
  const s8 = addSlide('contato');
  s8.background = { color:'0a8a8d' }; // fallback se contato.png não carregar
  if (branding.contato) {
    s8.addImage({
      data: branding.contato,
      x: 0, y: 0, w: W, h: H,
      sizing: { type:'cover', w: W, h: H }
    });
  }

  const fileName = `Proposta_${(st.cliente||'Cliente').replace(/[^a-zA-Z0-9]/g,'_')}.pptx`;
  await pptx.writeFile({ fileName });
  toast('PPT baixado!');
}

// ──────────────────────────────────────────────────────────
// GERA PDF a partir do preview HTML do modal (mesmo conteúdo do PPT)
// ──────────────────────────────────────────────────────────
async function apGerarPDF() {
  const slidesContainer = document.querySelector('#ap-ppt-overlay .app-slides');
  if (!slidesContainer) { toast('Abra o preview primeiro'); return; }
  const st = apPptState;
  if (!st) { toast('Estado vazio. Abra o preview primeiro'); return; }

  toast('Gerando PDF…');
  try { await loadPdfLibs(); }
  catch (e) { toast('Erro carregando bibliotecas PDF: ' + e.message); console.error(e); return; }

  const html2canvas = window.html2canvas;
  const jsPDF       = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
  if (!html2canvas || !jsPDF) {
    toast('Bibliotecas html2canvas/jsPDF não carregaram');
    console.error('html2canvas:', html2canvas, 'jsPDF:', jsPDF, 'window.jspdf:', window.jspdf);
    return;
  }

  // Ativa o modo PDF no overlay — força table-layout:fixed na .comp-table pra
  // que colunas com texto longo (ex.: "BRADESCO SEGUROS Nacional Plus 6")
  // não inflem e empurrem as outras cotações pra fora do slide.
  const _overlayEl = document.getElementById('ap-ppt-overlay');
  if (_overlayEl) _overlayEl.classList.add('pdf-mode');

  // Esconde temporariamente as etiquetas "Slide N — Título" e ajustes visuais
  const tags = slidesContainer.querySelectorAll('.slide-tag');
  tags.forEach(t => { t.dataset._oldDisplay = t.style.display; t.style.display = 'none'; });
  // Pula slides marcados como excluídos pelo usuário (display:none no preview)
  const slides = Array.from(slidesContainer.querySelectorAll('.slide'))
    .filter(s => s.style.display !== 'none');
  const oldStyles = [];
  slides.forEach(s => {
    oldStyles.push({ el:s, mb:s.style.marginBottom, bs:s.style.boxShadow, br:s.style.borderRadius });
    s.style.marginBottom = '0';
    s.style.boxShadow    = 'none';
    s.style.borderRadius = '0';
  });

  // PDF A4 paisagem (842 x 595 pt). Vamos renderizar cada slide em um canvas
  // independente e jogar como imagem na página, mantendo proporção.
  // Formato widescreen 16:9 (igual ao slide padrão do PowerPoint: 13.33" × 7.5")
  // Em vez de A4 landscape (842×595), uso 960×540pt pra que a página acomode
  // melhor o slide capturado (que é ~1052px de largura ≈ aspect 2:1).
  const pdf = new jsPDF({ orientation:'landscape', unit:'pt', format:[960, 540] });
  const pageW = pdf.internal.pageSize.getWidth();
  const pageH = pdf.internal.pageSize.getHeight();

  try {
    for (let i = 0; i < slides.length; i++) {
      const slide = slides[i];

      // LIMITE DE 4 COTAÇÕES NO PDF: se este é o slide do Comparativo e há
      // mais de 4 cotações no formulário, esconde temporariamente as colunas
      // a partir da 5ª (cabeçalhos + corpo) antes de capturar. A 5ª estourava
      // a largura do slide e aparecia cortada (filete) no PDF.
      const compTable = slide.dataset.slideId === 'comparativo' ? slide.querySelector('.comp-table') : null;
      const _hiddenCotCells = [];
      const _savedCompTableStyle = { tableLayout: '', maxWidth: '' };
      if (compTable) {
        const _temPa = !!(apPptState && apPptState.tem_plano_atual);
        const cotStartIdx = 1 + (_temPa ? 1 : 0); // pula label e PA
        const cutoff = cotStartIdx + 4;            // mantém só até a 4ª cotação
        compTable.querySelectorAll('tr').forEach(tr => {
          for (let k = cutoff; k < tr.children.length; k++) {
            const cell = tr.children[k];
            _hiddenCotCells.push({ el: cell, oldDisplay: cell.style.display });
            cell.style.display = 'none';
          }
        });
        // Força table-layout:fixed + width fixo via inline style — só pra
        // comparativo (carências fica com seu próprio layout). Usa !important
        // pra sobrescrever qualquer CSS, e força um reflow sincronamente
        // antes do html2canvas pra garantir que o layout seja recomputado.
        _savedCompTableStyle.tableLayout = compTable.style.tableLayout;
        _savedCompTableStyle.maxWidth    = compTable.style.maxWidth;
        _savedCompTableStyle.width       = compTable.style.width;
        compTable.style.setProperty('table-layout', 'fixed', 'important');
        compTable.style.setProperty('width',     '940px', 'important');
        compTable.style.setProperty('max-width', '940px', 'important');
        // Força reflow sincronamente — sem isso, html2canvas pode capturar
        // a layout antiga antes do navegador reaplicar os estilos.
        void compTable.offsetWidth;
        // Pequena pausa pro browser confirmar o layout
        await new Promise(r => requestAnimationFrame(r));
      }

      // Captura o slide como canvas (alta resolução).
      // windowWidth:1200 dá espaço pro overlay (que tem padding:24px) não
      // constranger o modal (max-width:1100) — caso contrário o modal fica em
      // 1052px e o slide-body fica em apenas ~976px, cortando a tabela.
      const canvas = await html2canvas(slide, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        windowWidth: 1200
      });

      // Restaura as células de cotações que foram escondidas pelo limite de 4
      _hiddenCotCells.forEach(h => { h.el.style.display = h.oldDisplay; });
      // Restaura os estilos inline da comp-table (table-layout, width e max-width)
      if (compTable) {
        compTable.style.removeProperty('table-layout');
        compTable.style.removeProperty('width');
        compTable.style.removeProperty('max-width');
        // Restaura os valores antigos (se eram diferentes do default)
        if (_savedCompTableStyle.tableLayout) compTable.style.tableLayout = _savedCompTableStyle.tableLayout;
        if (_savedCompTableStyle.width)       compTable.style.width       = _savedCompTableStyle.width;
        if (_savedCompTableStyle.maxWidth)    compTable.style.maxWidth    = _savedCompTableStyle.maxWidth;
      }
      // Calcula proporção pra encaixar na página A4 paisagem
      const cW = canvas.width, cH = canvas.height;
      const ratio = cW / cH;
      let imgW = pageW, imgH = pageW / ratio;
      if (imgH > pageH) { imgH = pageH; imgW = pageH * ratio; }
      const x = (pageW - imgW) / 2;
      const y = (pageH - imgH) / 2;
      const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
      if (i > 0) pdf.addPage('a4', 'l');
      pdf.addImage(dataUrl, 'JPEG', x, y, imgW, imgH);
    }
    const filename = `Proposta_${(st.cliente||'Cliente').replace(/[^a-zA-Z0-9]/g,'_')}.pdf`;
    pdf.save(filename);
    toast('PDF baixado!');
  } catch (e) {
    toast('Erro ao gerar PDF: ' + e.message);
    console.error(e);
  } finally {
    // Remove o modo PDF (volta o table-layout ao normal pro preview)
    if (_overlayEl) _overlayEl.classList.remove('pdf-mode');
    tags.forEach(t => { t.style.display = t.dataset._oldDisplay || ''; delete t.dataset._oldDisplay; });
    oldStyles.forEach(o => {
      o.el.style.marginBottom = o.mb;
      o.el.style.boxShadow    = o.bs;
      o.el.style.borderRadius = o.br;
    });
  }
}