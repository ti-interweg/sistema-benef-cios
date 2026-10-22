/**
 * Apresentação de Orçamento — Interweg Seguros
 * Extração de PDF no navegador via pdf.js
 */

const PDFJS_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
const PDFJS_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
let pdfjsLoaded = false;

function loadPdfJs() {
  return new Promise((resolve, reject) => {
    if (pdfjsLoaded && window.pdfjsLib) { resolve(); return; }
    const s = document.createElement('script');
    s.src = PDFJS_CDN;
    s.onload = () => { window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS_WORKER; pdfjsLoaded = true; resolve(); };
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

// ── Extrair words posicionados de TODAS as páginas ───────────────
async function apGetWords(file) {
  await loadPdfJs();
  const buf = await file.arrayBuffer();
  const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
  const totalPages = pdf.numPages;
  const allWords = [];
  for (let p = 1; p <= totalPages; p++) {
    const page = await pdf.getPage(p);
    const tc = await page.getTextContent();
    const words = tc.items.filter(it => it.str.trim()).map(it => ({
      text: it.str.trim(),
      x: Math.round(it.transform[4]),
      y: Math.round(it.transform[5]),
      w: Math.round(it.width),
      page: p
    }));
    allWords.push({ page: p, words });
  }
  return allWords;
}

// ── Agrupar words em linhas (por Y) ──────────────────────────────
// Tolerância de 8px para agrupar items que o pdf.js coloca em Y levemente diferente
function groupLines(words, yTolerance = 8) {
  const groups = {};
  words.forEach(w => {
    const key = Object.keys(groups).find(k => Math.abs(Number(k) - w.y) < yTolerance);
    const gk = key || String(w.y);
    if (!groups[gk]) groups[gk] = [];
    groups[gk].push(w);
  });
  // pdf.js Y cresce de baixo para cima, então sort desc = top to bottom
  return Object.entries(groups)
    .sort((a, b) => Number(b[0]) - Number(a[0]))
    .map(([y, ws]) => {
      const sorted = ws.sort((a, b) => a.x - b.x);
      return { y: Number(y), items: sorted, text: sorted.map(i => i.text).join(' ') };
    });
}

// ── Clusterizar items por posição X em N colunas ─────────────────
// Dado items numéricos de uma linha, agrupa os que estão próximos em X
function clusterByX(items, gap = 40) {
  if (!items.length) return [];
  const sorted = [...items].sort((a, b) => a.x - b.x);
  const clusters = [{ x: sorted[0].x, items: [sorted[0]] }];
  for (let i = 1; i < sorted.length; i++) {
    const last = clusters[clusters.length - 1];
    const lastItem = last.items[last.items.length - 1];
    if (sorted[i].x - (lastItem.x + lastItem.w) < gap) {
      last.items.push(sorted[i]);
    } else {
      clusters.push({ x: sorted[i].x, items: [sorted[i]] });
    }
  }
  return clusters.map(c => ({
    x: c.x,
    cx: c.x + c.items.reduce((s, i) => s + i.w, 0) / 2,
    text: c.items.map(i => i.text).join(' ')
  }));
}

// ── Mapear clusters para colunas ─────────────────────────────────
function mapToColumns(clusters, colCenters) {
  const result = new Array(colCenters.length).fill('');
  clusters.forEach(cl => {
    let bestIdx = 0, bestDist = 99999;
    colCenters.forEach((cx, idx) => {
      const dist = Math.abs(cl.cx - cx);
      if (dist < bestDist) { bestDist = dist; bestIdx = idx; }
    });
    if (bestDist < 80) result[bestIdx] = cl.text;
  });
  return result;
}

// ── Parser principal ──────────────────────────────────────────────
function apParseCotacao(lines) {
  const result = {
    cliente: '', vendedor: '', email: '', telefone: '',
    vidas: '', tipo: 'Saúde', planos: []
  };

  // ── Cabeçalho ──
  for (const line of lines.slice(0, 20)) {
    const t = line.text;
    // Título com COTAÇÃO
    const tm = t.match(/(.+?)\s*[-–]\s*COTAÇÃO\s+(.*?)\s*\((\d+)\s*vidas?\)/i);
    if (tm) {
      result.cliente = tm[1].trim();
      const tr = tm[2].toLowerCase();
      result.vidas = tm[3];
      // Placement não trata Seguro de Vida — só Saúde, Odonto ou Saúde + Odonto
      if (tr.includes('odonto') && (tr.includes('saúde') || tr.includes('saude'))) result.tipo = 'Saúde + Odonto';
      else if (tr.includes('odonto')) result.tipo = 'Odonto';
      else result.tipo = 'Saúde';
      continue;
    }
    // Cabeçalho pode ter "COTAÇÃO" separado em outra linha
    if (/COTAÇÃO/i.test(t) && !result.cliente) {
      const tm2 = t.match(/COTAÇÃO\s+(.*?)\s*\((\d+)\s*vidas?\)/i);
      if (tm2) {
        result.vidas = tm2[2];
        const tr = tm2[1].toLowerCase();
        // Placement não trata Seguro de Vida — só Saúde, Odonto ou Saúde + Odonto
        if (tr.includes('odonto') && (tr.includes('saúde') || tr.includes('saude'))) result.tipo = 'Saúde + Odonto';
        else if (tr.includes('odonto')) result.tipo = 'Odonto';
        else result.tipo = 'Saúde';
      }
    }
    if (/^Corretor/i.test(t)) result.vendedor = t.replace(/^Corretor\s*/i, '').trim();
    if (/^E-mail/i.test(t)) result.email = t.replace(/^E-mail\s*/i, '').trim();
    if (/^Telefone/i.test(t)) result.telefone = t.replace(/^Telefone\s*/i, '').trim();
    // Tentar pegar nome do cliente da linha anterior a COTAÇÃO
    if (!result.cliente && lines.indexOf(line) < 5 && t.length > 3 && !/^(Corretor|E-mail|Telefone|Geral)/i.test(t) && !/COTAÇÃO/i.test(t)) {
      // Pode ser o nome da empresa (primeira linha com texto significativo)
    }
  }

  // ── Passo 1: Encontrar a linha de totais (a mais confiável) ──
  // Estratégia: linha com pelo menos 3 valores numéricos tipo "1.234,56"
  let totaisLine = null;
  let totaisIdx = -1;

  // Tentativa 1: linha onde TODOS os items são numéricos
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const numItems = line.items.filter(it => /^\d[\d.]*,\d{2}$/.test(it.text));
    if (numItems.length >= 3 && numItems.length === line.items.length) {
      totaisLine = line;
      totaisIdx = i;
      break;
    }
  }

  // Tentativa 2: linha com label "Total" ou similar + valores numéricos
  if (!totaisLine) {
    for (let i = lines.length - 1; i >= 0; i--) {
      const line = lines[i];
      const numItems = line.items.filter(it => /^\d[\d.]*,\d{2}$/.test(it.text));
      if (numItems.length >= 3) {
        // Verificar se a parte não numérica é label (Total, Subtotal, etc)
        const nonNum = line.items.filter(it => !/^\d[\d.]*,\d{2}$/.test(it.text));
        const isLabel = nonNum.every(it => /^(Total|Subtotal|Valor|Mensalidade)/i.test(it.text));
        if (nonNum.length === 0 || isLabel) {
          // Usar apenas os items numéricos como referência de coluna
          totaisLine = { ...line, items: numItems };
          totaisIdx = i;
          break;
        }
      }
    }
  }

  // Tentativa 3: última linha com 3+ valores numéricos (mais permissiva)
  if (!totaisLine) {
    for (let i = lines.length - 1; i >= 0; i--) {
      const line = lines[i];
      const numItems = line.items.filter(it => /^\d[\d.]*,\d{2}$/.test(it.text));
      if (numItems.length >= 3) {
        totaisLine = { ...line, items: numItems };
        totaisIdx = i;
        break;
      }
    }
  }

  if (!totaisLine) {
    return result;
  }

  // ── Passo 2: Usar posições X dos totais como referência de colunas ──
  const colCenters = totaisLine.items.map(it => it.x + it.w / 2);
  const numCols = colCenters.length;

  const totais = totaisLine.items.map(it => it.text);

  // ── Passo 3: Encontrar as seguradoras e nomes dos planos ──
  // A linha "Geral" pode estar mesclada com planos e seguradoras (Y próximo).
  // Estratégia: encontrar o item "Geral" e separar items da mesma linha por Y:
  //   items com Y > Geral.Y = nomes dos planos (ficam acima no PDF)
  //   items com Y < Geral.Y = seguradoras (ficam abaixo no PDF)
  //   items com Y ≈ Geral.Y = labels (Geral, etc)
  let geralIdx = -1;
  for (let i = 0; i < totaisIdx; i++) {
    if (lines[i].items.some(it => it.text === 'Geral')) {
      geralIdx = i;
      break;
    }
  }

  const planoNames = new Array(numCols).fill('');
  const segNames = new Array(numCols).fill('');

  if (geralIdx >= 0) {
    const geralLine = lines[geralIdx];

    // Encontrar o item "Geral" para obter seu Y exato
    const geralItem = geralLine.items.find(it => it.text === 'Geral');
    const geralY = geralItem ? geralItem.y : geralLine.y;

    // Coletar todos Y únicos dos items (excluindo labels da esquerda)
    const nonLabelItems = geralLine.items.filter(it =>
      !/^(Geral)$/i.test(it.text) && it.x > 100
    );

    // Agrupar items por Y com clustering (items com Y similar ficam juntos)
    const yGroups = {};
    nonLabelItems.forEach(it => {
      const existingKey = Object.keys(yGroups).find(k => Math.abs(Number(k) - it.y) < 3);
      const key = existingKey || String(it.y);
      if (!yGroups[key]) yGroups[key] = [];
      yGroups[key].push(it);
    });

    const ySorted = Object.keys(yGroups).map(Number).sort((a, b) => b - a); // desc = top first

    // O grupo com Y MAIS ALTO = planos (ficam acima no PDF)
    // O grupo com Y MAIS BAIXO = seguradoras (ficam abaixo no PDF)
    let planoItems = [];
    let segItems = [];

    if (ySorted.length >= 2) {
      // Temos pelo menos 2 sub-linhas distintas
      const highestY = ySorted[0];
      const lowestY = ySorted[ySorted.length - 1];
      const highKey = Object.keys(yGroups).find(k => Math.abs(Number(k) - highestY) < 3);
      const lowKey = Object.keys(yGroups).find(k => Math.abs(Number(k) - lowestY) < 3);
      planoItems = yGroups[highKey] || [];
      segItems = yGroups[lowKey] || [];
    } else if (ySorted.length === 1) {
      // Todos na mesma Y → itens não estão mesclados, usar fallback para linhas adjacentes
    }


    // Se encontramos items separados por Y, usar eles
    if (planoItems.length > 0) {
      const planoClusters = clusterByX(planoItems, 15);
      const mapped = mapToColumns(planoClusters, colCenters);
      mapped.forEach((v, i) => { planoNames[i] = v; });
    } else if (geralIdx > 0) {
      // Fallback: tentar linha acima
      const planoLine = lines[geralIdx - 1];
      const planoClusters = clusterByX(planoLine.items.filter(it => !/^(Geral)/i.test(it.text)), 15);
      const mapped = mapToColumns(planoClusters, colCenters);
      mapped.forEach((v, i) => { planoNames[i] = v; });
    }

    if (segItems.length > 0) {
      const segClusters = clusterByX(segItems, 20);
      const mapped = mapToColumns(segClusters, colCenters);
      mapped.forEach((v, i) => { segNames[i] = v; });
    } else if (geralIdx + 1 < lines.length) {
      // Fallback: tentar linha abaixo
      const segLine = lines[geralIdx + 1];
      const segClusters = clusterByX(segLine.items, 20);
      const mapped = mapToColumns(segClusters, colCenters);
      mapped.forEach((v, i) => { segNames[i] = v; });
    }
  } else {
  }

  // ── Passo 4: Ler Modalidade, Acomodação, Coparticipação ──
  const modalidades = new Array(numCols).fill('');
  const acomodacoes = new Array(numCols).fill('');
  const coparticipacoes = new Array(numCols).fill('');
  const faixas = [];

  // geralIdx + 1 porque seguradoras já estão DENTRO da linha Geral (mesma Y com offset)
  // Não precisa pular linha extra de seguradoras
  for (let i = (geralIdx >= 0 ? geralIdx + 1 : 0); i < totaisIdx; i++) {
    const line = lines[i];
    const t = line.text;
    // Excluir labels e pegar só os valores das colunas
    const dataItems = line.items.filter(it =>
      !/^(Modalidade|Acomodação|Coparticipação|Geral)/i.test(it.text) &&
      !/^\d+\s*$/.test(it.text) // excluir números sozinhos tipo "2" "x"
    );

    if (/^Modalidade/i.test(t)) {
      const clusters = clusterByX(dataItems, 10);
      mapToColumns(clusters, colCenters).forEach((v, j) => { modalidades[j] = v; });
    } else if (/^Acomodação/i.test(t)) {
      const clusters = clusterByX(dataItems, 10);
      mapToColumns(clusters, colCenters).forEach((v, j) => { acomodacoes[j] = v; });
    } else if (/^Coparticipação/i.test(t)) {
      const clusters = clusterByX(dataItems, 10);
      mapToColumns(clusters, colCenters).forEach((v, j) => { coparticipacoes[j] = v; });
    } else {
      // Faixa etária: detecção flexível — "NN a NN" na linha
      const faixaMatch = t.match(/(\d+\s*a\s*\d+)/);
      if (faixaMatch && !/Geral|Modalidade|Acomodação|Coparticipação/i.test(t)) {
        const faixaLabel = faixaMatch[1].replace(/\s+/g, ' ').trim();

        // Encontrar QTD — pode estar nesta linha ou na mesma célula
        const qm = t.match(/(\d+)\s*[x×]/i);
        const qtdVal = qm ? qm[1] + ' x' : '';

        // ═══ Extrair valores monetários ═══
        // Combinar items desta linha + próximas linhas se necessário
        let allItems = [...line.items];
        let searchText = t;

        // Se não tem valores nesta linha, olhar a próxima (pdf.js pode ter Y diferente)
        if (i + 1 < totaisIdx) {
          const nextLine = lines[i + 1];
          const nextT = nextLine.text;
          // Se a próxima linha NÃO é outro label/faixa e TEM conteúdo numérico
          if (!/^(Modalidade|Acomodação|Coparticipação|\d+\s*a\s*\d+)/i.test(nextT)) {
            allItems = allItems.concat(nextLine.items);
            searchText += ' ' + nextT;
          }
        }


        // Extrair valores: múltiplas estratégias

        // Estratégia A: items individuais que já são valores completos
        let numItems = allItems.filter(it => /^\d[\d.]*,\d{2}$/.test(it.text));

        // Estratégia B: reconstruir items adjacentes ("1.105" + ",84")
        if (numItems.length < 2) {
          numItems = [];
          const sortedItems = [...allItems].sort((a, b) => a.x - b.x);
          for (let j = 0; j < sortedItems.length; j++) {
            const it = sortedItems[j];
            if (/^\d[\d.]*,\d{2}$/.test(it.text)) {
              numItems.push(it);
            } else if (/^\d[\d.]*$/.test(it.text) && j + 1 < sortedItems.length) {
              const next = sortedItems[j + 1];
              if (/^,\d{2}$/.test(next.text) && (next.x - (it.x + it.w)) < 10) {
                numItems.push({ text: it.text + next.text, x: it.x, y: it.y, w: it.w + next.w });
                j++;
              }
            }
          }
        }

        // Estratégia C: normalizar texto e extrair via regex
        if (numItems.length < 2) {
          let normalized = searchText
            .replace(/(\d)\s*\.\s*(\d)/g, '$1.$2')
            .replace(/(\d)\s*,\s*(\d)/g, '$1,$2');
          const textVals = [...normalized.matchAll(/\d[\d.]*,\d{2}/g)].map(m => m[0]);
          if (textVals.length >= 2) {
            // Criar items sintéticos nas posições das colunas
            numItems = textVals.slice(0, numCols).map((v, idx) => ({
              text: v, x: colCenters[idx] || idx * 100, y: line.y, w: 30
            }));
          }
        }

        // Estratégia D: juntar items sem espaço e re-extrair
        if (numItems.length < 2) {
          const rawConcat = allItems.sort((a, b) => a.x - b.x).map(it => it.text).join('');
          const textVals = [...rawConcat.matchAll(/(\d[\d.]*,\d{2})/g)].map(m => m[0]);
          if (textVals.length >= 2) {
            numItems = textVals.slice(0, numCols).map((v, idx) => ({
              text: v, x: colCenters[idx] || idx * 100, y: line.y, w: 30
            }));
          }
        }


        // Mapear para colunas
        let valores;
        if (numItems.length > 0 && numItems[0].x !== undefined && numItems.length === numCols) {
          // Se temos posições X, mapear por proximidade
          const clusters = clusterByX(numItems, 15);
          valores = mapToColumns(clusters, colCenters);
        } else {
          // Fallback: atribuir em ordem
          valores = new Array(numCols).fill('');
          numItems.forEach((it, idx) => { if (idx < numCols) valores[idx] = it.text; });
        }

        // QTD: se não encontrou no texto, verificar próxima linha
        let finalQtd = qtdVal;
        if (!finalQtd && i + 1 < totaisIdx) {
          const nextQm = lines[i + 1].text.match(/(\d+)\s*[x×]/i);
          if (nextQm) finalQtd = nextQm[1] + ' x';
        }

        faixas.push({ faixa: faixaLabel, qtd: finalQtd, valores });
      }
    }
  }

  // ── Passo 5: Montar os planos ──
  for (let c = 0; c < numCols; c++) {
    result.planos.push({
      seguradora: segNames[c] || '',
      plano: planoNames[c] || '',
      modalidade: modalidades[c] || '',
      acomodacao: acomodacoes[c] || '',
      coparticipacao: coparticipacoes[c] || '',
      valor: totais[c] || '',
      faixas: faixas.map(fx => ({
        faixa: fx.faixa,
        qtd: fx.qtd,
        valor: fx.valores[c] || ''
      }))
    });
  }

  result.planos.forEach((p, i) => {
    p.faixas.forEach(f => console.log(`    ${f.faixa} ${f.qtd}: ${f.valor}`));
  });
  return result;
}

// ── Upload e processamento ────────────────────────────────────────
async function apImportPDF(input) {
  if (!input.files || !input.files[0]) return;
  toast('Carregando PDF...');
  try {
    const file = input.files[0];
    const pagesData = await apGetWords(file);
    toast(`Processando ${pagesData.length} páginas...`);

    // ── Detectar PDF "só imagem" antes de tentar parsear ────────────
    // PDFs gerados por print/screenshot não têm texto vetorial — pdf.js
    // retorna 0 palavras em todas as páginas. Mostrar um aviso claro
    // pro usuário em vez de "veja o console" genérico.
    const totalWords = pagesData.reduce((acc, pg) => acc + pg.words.length, 0);
    console.log(`[apImportPDF] Total de palavras extraídas em ${pagesData.length} página(s): ${totalWords}`);
    if (totalWords === 0) {
      alert(
        'Este PDF é apenas imagem (não contém texto extraível).\n\n' +
        'Como resolver:\n' +
        '• Use o PDF ORIGINAL gerado pelo Cotaço (não um screenshot/print salvo em PDF)\n' +
        '• Ou preencha os dados manualmente nos campos acima\n\n' +
        'Dica: PDFs do Cotaço são vetoriais — você consegue marcar e copiar o texto deles. PDFs "imagem" não permitem isso.'
      );
      return;
    }

    // Processar APENAS a página 1 para extração de planos/cotação
    // Páginas 2+ são Rede Credenciada e "Mais informações" (processadas depois)
    // Processar todas as páginas causava falsos positivos (ex: página 45 SulAmérica)
    let mergedData = null;

    // Página 1: extração de cotação (planos, faixas, totais)
    const pg1 = pagesData.find(pg => pg.page === 1);
    if (pg1 && pg1.words.length >= 5) {
      pg1.words.slice(0, 15).forEach(w => console.log(`  "${w.text}" x=${w.x} y=${w.y} w=${w.w}`));
      const lines = groupLines(pg1.words);
      lines.forEach((l, i) => console.log(`  L${i}: Y=${l.y} "${l.text}"`));
      mergedData = apParseCotacao(lines);
    } else {
      console.warn('[apImportPDF] Página 1 com poucas palavras (' + (pg1 ? pg1.words.length : 0) + '). Não dá pra parsear.');
    }

    if (!mergedData || !mergedData.planos.length) {
      // Mensagem mais informativa: provavelmente o PDF tem texto mas em layout diferente
      // do esperado pelo parser (que assume formato Cotaço com label "Geral" + linha de totais).
      alert(
        'Não consegui identificar os planos neste PDF.\n\n' +
        'O parser espera o formato padrão do Cotaço (com a palavra "Geral" e linha de totais em R$).\n\n' +
        'Possíveis causas:\n' +
        '• O PDF é de outro sistema (Cotaco.es Studio, Bitrix, planilha exportada, etc)\n' +
        '• O layout do Cotaço mudou\n' +
        '• Faltam dados essenciais (totais, modalidade, acomodação)\n\n' +
        'Você pode preencher os campos manualmente ou abrir o console (F12) para ver as linhas que foram lidas.'
      );
      return;
    }
    // ── Fallback do nome do cliente ──────────────────────────────
    // Se o parser não conseguiu extrair o nome da empresa do conteúdo
    // (formato de título diferente), usa o NOME DO ARQUIVO como base —
    // limpando extensão, a palavra "cotação", separadores e números.
    if (!mergedData.cliente || !String(mergedData.cliente).trim()) {
      let nome = (file.name || '')
        .replace(/\.pdf$/i, '')          // tira extensão
        .replace(/cota[çc][ãa]o/ig, '')  // tira "cotação"/"cotacao"
        .replace(/\bsa[úu]de\b/ig, '')   // tira "saúde"
        .replace(/\bodonto\b/ig, '')     // tira "odonto"
        .replace(/[_\-]+/g, ' ')         // _ e - viram espaço
        .replace(/\b\d{2,}\b/g, '')      // tira números longos (datas/ids)
        .replace(/\(.*?\)/g, '')         // tira parênteses
        .replace(/\s+/g, ' ')
        .trim();
      if (nome) {
        mergedData.cliente = nome;
        console.log('[apImportPDF] Nome do cliente vazio no conteúdo — usando nome do arquivo:', nome);
      }
    }

    // Extrair páginas de detalhe (2 em diante)
    toast('Extraindo páginas de detalhe...');
    const detailPages = await apGetDetailPages(file);
    mergedData.detailPages = detailPages;

    apPreencherDoPDF(mergedData);
    toast(`Importado: ${mergedData.planos.length} planos + ${detailPages.length} páginas de detalhe`);
  } catch (e) {
    console.error('Erro PDF:', e);
    alert('Erro ao processar PDF: ' + e.message);
  }
}

// ── Extrair texto completo de páginas de detalhe ─────────────────
async function apGetDetailPages(file) {
  await loadPdfJs();
  const buf = await file.arrayBuffer();
  const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
  const totalPages = pdf.numPages;
  const details = [];

  // Primeira passagem: agrupar páginas de Rede Credenciada
  const redePages = [];  // {page, words[]}
  const otherPages = []; // {page, words[]}

  for (let p = 2; p <= totalPages; p++) {
    const page = await pdf.getPage(p);
    const tc = await page.getTextContent();
    const words = tc.items.filter(it => it.str.trim()).map(it => ({
      text: it.str.trim(),
      x: Math.round(it.transform[4]),
      y: Math.round(it.transform[5]),
      w: Math.round(it.width)
    }));
    if (words.length < 3) continue;
    const fullText = words.map(w => w.text).join(' ');

    if (/rede\s*credenciada/i.test(fullText) || (redePages.length > 0 && /São\s*Paulo|H\s|Hospital|Clínic/i.test(fullText))) {
      redePages.push({ page: p, words });
    } else {
      otherPages.push({ page: p, words });
    }
  }

  // Processar Rede Credenciada (pode ter múltiplas páginas)
  if (redePages.length > 0) {
    const table = parseRedeCredenciada(redePages);
    details.push({ page: redePages[0].page, type: 'rede_credenciada', table });
  }

  // Processar outras páginas como texto
  for (const pg of otherPages) {
    const lines = groupLines(pg.words);
    details.push({
      page: pg.page,
      type: 'text',
      lines: lines.map(l => l.text)
    });
  }

  return details;
}

// ── Parser de Rede Credenciada (múltiplas páginas) ──────────────
function parseRedeCredenciada(pages) {
  const result = {
    title: 'Rede Credenciada',
    subtitle: '',
    columns: [],
    colCenters: [],
    sections: []
  };

  // Combinar todas as palavras e agrupar em linhas
  // Cada página separada, mas processamos sequencialmente
  let allLines = [];
  for (const pg of pages) {
    const lines = groupLines(pg.words);
    // Marcar cada linha com a página de origem
    lines.forEach(l => { l.page = pg.page; });
    allLines = allLines.concat(lines);
  }


  // Encontrar título e subtítulo
  let startIdx = 0;
  for (let i = 0; i < Math.min(10, allLines.length); i++) {
    if (/rede\s*credenciada/i.test(allLines[i].text)) {
      result.title = 'Rede Credenciada';
      startIdx = i + 1;
      break;
    }
  }
  if (startIdx < allLines.length && /princip/i.test(allLines[startIdx].text)) {
    result.subtitle = allLines[startIdx].text;
    startIdx++;
  }

  // Encontrar cabeçalho dos planos — coletar TODAS as linhas com nomes de plano
  // Os nomes podem estar espalhados em 2-3 linhas consecutivas
  let headerIdx = -1;
  let headerItems = [];
  let headerEndIdx = -1;
  const headerPattern = /S\d{3,}|Nacional|Executivo|Essential|Especial|Premium/i;
  for (let i = startIdx; i < Math.min(startIdx + 15, allLines.length); i++) {
    const t = allLines[i].text;
    if (headerPattern.test(t)) {
      if (headerIdx < 0) headerIdx = i;
      headerEndIdx = i;
      headerItems = headerItems.concat(allLines[i].items);
      // Continuar verificando as próximas linhas (até 3 consecutivas)
    } else if (headerIdx >= 0) {
      // Parou de encontrar nomes de plano, sair do loop
      break;
    }
  }

  if (headerItems.length > 0) {
    // Filtrar apenas items que sejam nomes de plano (ignorar logos, "Amil", etc.)
    const planoKeywords = /S\d{3,}|Nacional|Plus|Executivo|Essential|Especial|Premium|R\d/i;
    const filteredItems = headerItems.filter(it => planoKeywords.test(it.text));

    // Usar items filtrados se temos pelo menos 2, senão usar todos
    const itemsToCluster = filteredItems.length >= 2 ? filteredItems : headerItems;
    const clusters = clusterByX(itemsToCluster, 40);
    result.columns = clusters.map(c => c.text);
    result.colCenters = clusters.map(c => c.cx);
  }

  // Parsear linhas da tabela
  // Padrão do PDF: cada hospital tem 2 linhas:
  //   Linha 1: Nome do hospital (sem valores)
  //   Linha 2: Bairro + valores (H¹,PS¹ | H,PS | H,PS)
  // OU em alguns casos, hospital+bairro+valores numa só linha
  let currentSection = { region: '', rows: [] };
  let pendingHospitalName = null; // Nome do hospital aguardando bairro+valores na próxima linha

  for (let i = (headerEndIdx >= 0 ? headerEndIdx + 1 : (headerIdx >= 0 ? headerIdx + 1 : startIdx)); i < allLines.length; i++) {
    const line = allLines[i];
    const t = line.text.trim();
    if (!t || /informativo\s*referencial/i.test(t)) continue;
    // Pular texto do rodapé que pode vazar entre páginas
    if (/se\s*dará\s*apenas|assinatura\s*do\s*contrato|vinculando\s*esta\s*oferta|prestação\s*do\s*serviço|corrigir\s*eventuais/i.test(t)) continue;
    // Pular linhas de cabeçalho repetido em novas páginas
    if (/rede\s*credenciada/i.test(t) || /princip.*hospit/i.test(t)) continue;
    if (/S\d{3,}|Nacional.*Plus|Executivo.*R\d/i.test(t) && line.items.length <= 4) continue;

    // Detectar seção regional
    const isRegion = /^[◎⊙●○®©⊕]\s/.test(t) ||
      (/são\s*paulo|rio\s*de|interior|grande|campinas|santos|abc|guarulhos/i.test(t) && t.length < 80 && !/(H\s|Hospital|Clínic|PS|M,)/i.test(t));

    if (isRegion) {
      // Flush pending hospital name antes de mudar de seção
      if (pendingHospitalName) {
        currentSection.rows.push({ name: pendingHospitalName, values: result.colCenters.map(() => '-') });
        pendingHospitalName = null;
      }
      if (currentSection.rows.length > 0 || currentSection.region) {
        result.sections.push(currentSection);
      }
      currentSection = { region: t.replace(/^[◎⊙●○®©⊕]\s*/, ''), rows: [] };
      continue;
    }

    // Linha normal: hospital + valores
    if (result.colCenters.length > 0) {
      const firstColX = result.colCenters[0] - 80;
      const nameItems = line.items.filter(it => it.x < firstColX);
      const valItems = line.items.filter(it => it.x >= firstColX);

      const name = nameItems.map(it => it.text).join(' ').trim();
      if (!name) continue;

      const valClusters = clusterByX(valItems, 20);
      const values = mapToColumns(valClusters, result.colCenters);
      const hasValues = valItems.length > 0;

      if (!hasValues) {
        // Linha sem valores
        if (pendingHospitalName) {
          // Já temos um hospital pendente e veio outra linha sem valores
          // O pendente era subtitle do row anterior, e este é um novo hospital
          if (currentSection.rows.length > 0) {
            const prevRow = currentSection.rows[currentSection.rows.length - 1];
            prevRow.subtitle = pendingHospitalName;
          } else {
            // Sem row anterior, criar row com valores vazios
            currentSection.rows.push({ name: pendingHospitalName, values: result.colCenters.map(() => '-') });
          }
          pendingHospitalName = name;
        } else {
          // Guardar como nome pendente — pode ser:
          //   a) Nome do hospital (próxima linha terá bairro+valores)
          //   b) Subtitle/bairro do hospital anterior (se row anterior existe)
          pendingHospitalName = name;
        }
      } else {
        // Linha COM valores
        if (pendingHospitalName) {
          // Temos um hospital pendente — esta linha tem bairro + valores
          // Combinar: hospital = pendingName, subtitle = name, values = desta linha
          currentSection.rows.push({ name: pendingHospitalName, subtitle: name, values });
          pendingHospitalName = null;
        } else {
          // Sem pendente — hospital+bairro+valores numa só linha (nomes já merged pelo groupLines)
          currentSection.rows.push({ name, values });
        }
      }
    } else {
      if (t.length > 2) currentSection.rows.push({ name: t, values: [] });
    }
  }

  // Flush último pendingHospitalName
  if (pendingHospitalName) {
    if (currentSection.rows.length > 0) {
      const prevRow = currentSection.rows[currentSection.rows.length - 1];
      prevRow.subtitle = pendingHospitalName;
    } else {
      currentSection.rows.push({ name: pendingHospitalName, values: result.colCenters.map(() => '-') });
    }
    pendingHospitalName = null;
  }

  if (currentSection.rows.length > 0 || currentSection.region) {
    result.sections.push(currentSection);
  }

  return result;
}

function apPreencherDoPDF(data) {
  if (data.planos) {
    data.planos.forEach((p, i) => {
      if (p.faixas) p.faixas.forEach((f, j) => {
      });
    });
  }
  if (data.cliente) document.getElementById('ap-cliente').value = data.cliente;
  if (data.vendedor) document.getElementById('ap-vendedor').value = data.vendedor;
  if (data.email) document.getElementById('ap-email').value = data.email;
  if (data.telefone) document.getElementById('ap-tel').value = data.telefone;
  if (data.vidas) document.getElementById('ap-vidas').value = data.vidas;
  if (data.tipo) {
    const sel = document.getElementById('ap-tipo-cot');
    for (const opt of sel.options) {
      if (opt.value === data.tipo) { sel.value = opt.value; break; }
    }
  }
  document.getElementById('ap-planos-container').innerHTML = '';
  apPlanos = []; apPlanoId = 0;
  if (data.planos && data.planos.length) {
    data.planos.forEach(p => apAddPlano(p));
  }
  // Salvar páginas de detalhe para incluir no preview
  window._apDetailPages = data.detailPages || [];
  if (window._apDetailPages.length) {
  }
}

// ════════════════════════════════════════════════════════════════
// FUNÇÕES DE FORMULÁRIO — recuperadas do index.php.bak2 (linhas 2953–3050)
// Quando o JS foi extraído pra arquivo separado, essas funções e o
// estado (apPlanos, apPlanoId) ficaram pra trás. Sem elas:
//   - Botão "+ Adicionar Plano" lança ReferenceError
//   - Importar PDF chama apAddPlano e quebra em "is not defined"
//   - Gerar Apresentação Editável retorna null em apMontarStateInicial
//     (porque apColetarDados não existe) → "Não foi possível coletar os dados"
// ════════════════════════════════════════════════════════════════

let apPlanos = [];
let apPlanoId = 0;

function apAddPlano(data = {}) {
  const id = apPlanoId++;
  apPlanos.push(id);
  const div = document.createElement('div');
  div.id = 'ap-plano-' + id;
  div.style.cssText = 'border:1px solid var(--border);border-radius:10px;padding:16px;margin-bottom:12px;background:var(--bg)';
  div.innerHTML = `
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <span style="font-weight:700;color:var(--accent);font-size:13px">Opção ${apPlanos.length}</span>
      <button class="btn btn-danger btn-sm" onclick="apRemPlano(${id})" style="padding:2px 8px"><span class="material-symbols-rounded" style="font-size:14px">close</span></button>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
      <div class="fg"><label style="font-size:11px">Seguradora</label><input class="ap-seg" type="text" value="${esc(data.seguradora || '')}" placeholder="Ex: Amil"/></div>
      <div class="fg"><label style="font-size:11px">Plano</label><input class="ap-plano-nome" type="text" value="${esc(data.plano || '')}" placeholder="Ex: S2500 R2"/></div>
      <div class="fg"><label style="font-size:11px">Modalidade</label><input class="ap-mod" type="text" value="${esc(data.modalidade || '')}" placeholder="Ex: Saúde PME"/></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px">
      <div class="fg"><label style="font-size:11px">Acomodação</label><input class="ap-acom" type="text" value="${esc(data.acomodacao || '')}" placeholder="Ex: Apartamento"/></div>
      <div class="fg"><label style="font-size:11px">Coparticipação</label><input class="ap-copart" type="text" value="${esc(data.coparticipacao || '')}" placeholder="Ex: Parcial"/></div>
      <div class="fg"><label style="font-size:11px">Valor Total (R$)</label><input class="ap-valor" type="text" value="${esc(data.valor || '')}" placeholder="Ex: 5.219,85"/></div>
    </div>
    <div id="ap-faixas-${id}">
      <div style="font-size:11px;font-weight:600;color:var(--muted);margin-bottom:6px;margin-top:8px">Faixas Etárias</div>
    </div>
    <button class="btn btn-outline btn-sm" onclick="apAddFaixa(${id})" style="font-size:11px;margin-top:4px"><span class="material-symbols-rounded" style="font-size:13px">add</span> Faixa</button>
  `;
  document.getElementById('ap-planos-container').appendChild(div);
  // Adiciona faixas se tiver dados
  if (data.faixas && data.faixas.length) {
    data.faixas.forEach(f => apAddFaixa(id, f));
  }
}

function apAddFaixa(planoId, data = {}) {
  const container = document.getElementById('ap-faixas-' + planoId);
  if (!container) return;
  const row = document.createElement('div');
  row.className = 'ap-faixa-row';
  row.style.cssText = 'display:grid;grid-template-columns:1fr 80px 1fr 24px;gap:6px;align-items:end;margin-bottom:4px';
  row.innerHTML = `
    <div class="fg" style="margin:0"><input class="ap-fx-nome" type="text" value="${esc(data.faixa || '')}" placeholder="00 a 18" style="font-size:12px;padding:5px 8px"/></div>
    <div class="fg" style="margin:0"><input class="ap-fx-qtd" type="text" value="${esc(data.qtd || '')}" placeholder="2x" style="font-size:12px;padding:5px 8px;text-align:center"/></div>
    <div class="fg" style="margin:0"><input class="ap-fx-val" type="text" value="${esc(data.valor || '')}" placeholder="1.105,84" style="font-size:12px;padding:5px 8px;text-align:right"/></div>
    <button class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="padding:2px 6px;height:28px"><span class="material-symbols-rounded" style="font-size:12px">close</span></button>
  `;
  container.appendChild(row);
}

function apRemPlano(id) {
  const el = document.getElementById('ap-plano-' + id);
  if (el) el.remove();
  apPlanos = apPlanos.filter(p => p !== id);
}

// ── EXCLUIR / RESTAURAR BLOCO "PLANO ATUAL (VIGENTE)" ───────────
// Quando o cliente não tem plano vigente, o analista pode esconder o bloco.
// Os dados não vão para a apresentação enquanto o bloco estiver oculto
// (ver apColetarDados — devolve plano_atual vazio nesse caso).
function apExcluirPlanoAtual() {
  const bloco = document.getElementById('ap-plano-atual-bloco');
  const ph    = document.getElementById('ap-plano-atual-placeholder');
  if (!bloco) return;
  if (bloco.style.display === 'none') return;
  if (!confirm('Excluir o bloco "Plano Atual (Vigente)" desta apresentação?')) return;
  bloco.style.display = 'none';
  if (ph) ph.style.display = 'flex';
}

function apRestaurarPlanoAtual() {
  const bloco = document.getElementById('ap-plano-atual-bloco');
  const ph    = document.getElementById('ap-plano-atual-placeholder');
  if (bloco) bloco.style.display = '';
  if (ph)    ph.style.display    = 'none';
}

function _apPlanoAtualVisivel() {
  const bloco = document.getElementById('ap-plano-atual-bloco');
  if (!bloco) return true; // compat caso o bloco não exista no DOM
  return bloco.style.display !== 'none';
}

function apColetarDados() {
  const vendedor = document.getElementById('ap-vendedor')?.value.trim() || '';
  const email    = document.getElementById('ap-email')?.value.trim()    || '';
  const tel      = document.getElementById('ap-tel')?.value.trim()      || '';
  const cliente  = document.getElementById('ap-cliente')?.value.trim()  || '';
  const tipo     = document.getElementById('ap-tipo-cot')?.value        || 'Saúde';
  const vidas    = document.getElementById('ap-vidas')?.value.trim()    || '';
  const link     = document.getElementById('ap-link')?.value.trim()     || '';
  // Bloco "Plano Atual (Vigente)" pode ter sido excluído pelo analista
  // (cliente sem plano vigente) — nesse caso devolvemos campos vazios
  // para que a apresentação omita a comparação.
  const planoAtualAtivo = _apPlanoAtualVisivel();
  const valor_vigente = planoAtualAtivo
    ? (document.getElementById('ap-valor-vigente')?.value.trim() || '')
    : '';
  // Plano Atual completo (vigente)
  const plano_atual = planoAtualAtivo ? {
    operadora:      document.getElementById('ap-pa-operadora')?.value.trim()       || '',
    plano:          document.getElementById('ap-pa-plano')?.value.trim()           || '',
    modalidade:     document.getElementById('ap-pa-modalidade')?.value.trim()      || '',
    acomodacao:     document.getElementById('ap-pa-acomodacao')?.value.trim()      || '',
    coparticipacao: document.getElementById('ap-pa-coparticipacao')?.value.trim()  || '',
    valor:          valor_vigente
  } : {
    operadora: '', plano: '', modalidade: '',
    acomodacao: '', coparticipacao: '', valor: ''
  };
  // Coletar planos cotados
  const planos = [];
  document.querySelectorAll('[id^="ap-plano-"]').forEach(div => {
    const seg    = div.querySelector('.ap-seg')?.value        || '';
    const plano  = div.querySelector('.ap-plano-nome')?.value || '';
    const mod    = div.querySelector('.ap-mod')?.value        || '';
    const acom   = div.querySelector('.ap-acom')?.value       || '';
    const copart = div.querySelector('.ap-copart')?.value     || '';
    const valor  = div.querySelector('.ap-valor')?.value      || '';
    const faixas = [];
    div.querySelectorAll('.ap-fx-nome').forEach(fNome => {
      // input → div.fg → div.ap-faixa-row
      const row = fNome.parentElement?.parentElement;
      if (!row) return;
      const qtdInput = row.querySelector('.ap-fx-qtd');
      const valInput = row.querySelector('.ap-fx-val');
      faixas.push({
        faixa: fNome.value,
        qtd:   qtdInput ? qtdInput.value : '',
        valor: valInput ? valInput.value : ''
      });
    });
    // Inclui a cotação se QUALQUER campo estiver preenchido — antes a condição era
    // só "seg || plano", o que silenciosamente descartava cotações onde o usuário
    // tinha preenchido valor/faixas mas ainda não tinha colocado nome da seguradora.
    const temAlgo = seg || plano || mod || acom || copart || valor
      || (Array.isArray(faixas) && faixas.some(f => f && (f.faixa || f.qtd || f.valor)));
    if (temAlgo) {
      planos.push({ seguradora: seg, plano, modalidade: mod, acomodacao: acom, coparticipacao: copart, valor, faixas });
    }
  });
  return { vendedor, email, tel, cliente, tipo, vidas, link, valor_vigente, plano_atual, planos };
}
