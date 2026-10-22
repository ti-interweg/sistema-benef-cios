<?php
$currentPage = 'sinistralidade';
$pageTitle   = 'Informativo de Sinistralidade — Interweg Benefícios';
$pageScripts = ['assets/sinistralidade.js?v=' . time(), 'assets/sinistralidade_ia.js?v=' . time()];

require_once __DIR__ . '/_header.php';
// Admin e Enfermeiras têm acesso
if (!$isAdmin && !$isEnfermeira) { header('Location: demandas.php'); exit; }
?>

<style>
  #sin-form .fg{margin-bottom:14px;gap:5px}
  #sin-form input,#sin-form select,#sin-form textarea{padding:8px 11px;font-size:13px;border-radius:7px}
  #sin-form label{font-size:11px}
  .sin-var-btn{flex:1;border:2px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;text-align:center;background:var(--bg-card);transition:.15s}
  .sin-var-btn.active{border-color:var(--accent);background:rgba(0,191,179,.06)}
  .sin-var-btn .vt{font-weight:700;font-size:14px;margin-bottom:4px}
  .sin-var-btn .vs{font-size:11px;color:var(--muted)}
  #sin-grid{border-collapse:collapse;font-size:12px;min-width:760px}
  #sin-grid th,#sin-grid td{border:1px solid var(--border);padding:3px}
  #sin-grid input{width:100%;min-width:88px;padding:5px 6px;font-size:11.5px;border-radius:5px}
  #sin-grid input.col-h{font-weight:700;background:rgba(0,191,179,.06)}
  .sin-bi-bloco{display:none}
  body.sin-bi #sin-form .sin-bi-bloco{display:block}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Informativo de Sinistralidade</div>
      <div class="page-sub">Gere o informativo do cliente (PPT/PDF editável) — 3 variações</div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline" onclick="scmOpen()"><span class="material-symbols-rounded" style="font-size:17px">fact_check</span> Controle de Sinistralidade</button>
    </div>
  </div>

  <!-- STEP 1: Formulário -->
  <div id="sin-form" class="table-wrap" style="padding:28px">

    <!-- Variação -->
    <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:14px">
      <span class="material-symbols-rounded">tune</span> Variação do informativo
    </h3>
    <div style="display:flex;gap:12px;margin-bottom:14px">
      <div class="sin-var-btn active" data-var="positivo" onclick="sinSetVar('positivo')">
        <div class="vt" style="color:#0a8a8d">Resultado Positivo</div>
        <div class="vs">Dentro do ponto de equilíbrio (verde)</div>
      </div>
      <div class="sin-var-btn" data-var="atencao" onclick="sinSetVar('atencao')">
        <div class="vt" style="color:#c0392b">Atenção</div>
        <div class="vs">Acima do limite da operadora (vermelho)</div>
      </div>
    </div>
    <label style="display:flex;align-items:center;gap:9px;padding:10px 14px;border:2px solid var(--border);border-radius:10px;background:var(--bg-card);cursor:pointer;margin-bottom:24px;font-size:13px;user-select:none">
      <input type="checkbox" id="sin-com-bi" onchange="sinSetBi(this.checked)" style="width:16px;height:16px;cursor:pointer"/>
      <span class="material-symbols-rounded" style="font-size:18px;color:#0a8a8d">insights</span>
      <span style="font-weight:600">Incluir análise de BI</span>
      <span style="color:var(--muted);font-size:11px">— uso, tendências, perfil demográfico</span>
    </label>

    <!-- Dados gerais -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px">
      <div class="fg"><label>Empresa / Cliente</label><input id="sin-empresa" type="text" placeholder="Ex: Powerpoxi Tecnologia"/></div>
      <div class="fg"><label>Mês de referência</label><input id="sin-competencia" type="text" placeholder="Ex: mar/26"/></div>
      <div class="fg"><label>Título do informativo de saúde</label>
        <div style="display:flex;gap:6px">
          <input id="sin-info-titulo" type="text" placeholder="Ex: Informativo Maio" style="flex:1"/>
          <select id="sin-info-mes" onchange="sinSetInfoMes(this.value)" style="width:130px" title="Escolha o mês para preencher o título">
            <option value="">Mês...</option>
            <option>Janeiro</option><option>Fevereiro</option><option>Março</option><option>Abril</option>
            <option>Maio</option><option>Junho</option><option>Julho</option><option>Agosto</option>
            <option>Setembro</option><option>Outubro</option><option>Novembro</option><option>Dezembro</option>
          </select>
        </div>
      </div>
    </div>
    <div class="fg"><label>Texto de análise (cabeçalho)</label>
      <textarea id="sin-analise" rows="4" placeholder="Texto que aparece na faixa do informativo...">A sinistralidade do mês de março encontra-se dentro do ponto de equilíbrio preconizado pela operadora. No entanto, ao analisarmos o acumulado dos últimos 12 meses, é possível identificar que, devido às oscilações ocorridas nos meses anteriores, a sinistralidade ainda ultrapassa o limite técnico estabelecido pela operadora.</textarea>
    </div>

    <!-- Indicadores -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin:8px 0 18px">
      <div class="fg"><label>Sinistralidade do mês</label><input id="sin-mes" type="text" placeholder="Ex: 52,86%"/></div>
      <div class="fg"><label>Acumulado 12 meses</label><input id="sin-acum" type="text" placeholder="Ex: 62,44%"/></div>
      <div class="fg"><label>Breakeven Operadora</label><input id="sin-break" type="text" placeholder="Ex: 60%"/></div>
    </div>

    <!-- Tabela de competências (grade editável) -->
    <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:6px 0 8px">
      <span class="material-symbols-rounded">table_chart</span> Tabela de competências
    </h3>
    <p style="font-size:12px;color:var(--muted);margin-bottom:10px">Edite os <strong>títulos das colunas</strong> e os valores. A coluna de <strong>Sinistralidade</strong> acima do Breakeven fica vermelha no informativo. Pode importar de um Excel.</p>
    <div style="overflow-x:auto">
      <table id="sin-grid">
        <thead><tr id="sin-grid-head"></tr></thead>
        <tbody id="sin-grid-body"></tbody>
      </table>
    </div>
    <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
      <button class="btn btn-outline btn-sm" onclick="sinAddRow()"><span class="material-symbols-rounded" style="font-size:15px">add</span> Linha</button>
      <button class="btn btn-outline btn-sm" onclick="sinAddCol()"><span class="material-symbols-rounded" style="font-size:15px">view_column</span> Coluna</button>
      <span style="display:inline-flex;align-items:center;gap:6px">
        <input id="sin-mes-ini" type="text" placeholder="Mês inicial (ex: Abril/2025)" style="padding:6px 9px;font-size:12px;border-radius:6px;width:180px"/>
        <button class="btn btn-outline btn-sm" onclick="sinPreencherMeses()"><span class="material-symbols-rounded" style="font-size:15px">event</span> Preencher meses</button>
        <button class="btn btn-outline btn-sm" type="button" onclick="sinAbreviarMeses()" title="Abrevia os meses já preenchidos (Abril/2026 → abr/26)"><span class="material-symbols-rounded" style="font-size:15px">short_text</span> Abreviar</button>
      </span>
      <button class="btn btn-outline btn-sm" type="button" onclick="sinCalcTotal()" title="Soma os meses e recalcula a sinistralidade na linha Total"><span class="material-symbols-rounded" style="font-size:15px">functions</span> Calcular Total</button>
      <button class="btn btn-outline btn-sm" onclick="sinTemplateXlsx()"><span class="material-symbols-rounded" style="font-size:15px">description</span> Modelo Excel</button>
      <button class="btn btn-outline btn-sm" onclick="document.getElementById('sin-xlsx').click()"><span class="material-symbols-rounded" style="font-size:15px">upload_file</span> Importar Excel</button>
      <input id="sin-xlsx" type="file" accept=".xlsx,.xls,.csv" style="display:none" onchange="sinImportXlsx(this)"/>
      <label style="font-size:12px;display:inline-flex;align-items:center;gap:5px;margin-left:6px" title="Marcado: meses na primeira linha (colunas) e métricas nas laterais. Desmarcado: meses nas linhas.">
        <input type="checkbox" id="sin-transpor" checked/> Meses nas colunas (transpor)
      </label>
    </div>
    <div style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--border)">
      <label style="font-size:12px;color:var(--muted)"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">image_search</span> Ou suba a <strong>imagem da tabela</strong> — o sistema lê (OCR) e preenche a grade acima automaticamente (revise os valores depois):</label><br/>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:8px">
        <label style="font-size:12px;color:var(--muted);display:inline-flex;align-items:center;gap:6px">
          <span class="material-symbols-rounded" style="font-size:15px">domain</span> Operadora:
          <select id="sin-operadora" onchange="sinSetOperadora(this.value)" style="font-size:12px;padding:4px 8px;border:1px solid var(--border);border-radius:6px">
            <option value="auto">Detectar automático</option>
            <optgroup label="✓ Regra pronta">
              <option value="amil_odonto">Amil Odonto</option>
              <option value="amil_saude">Amil Saúde</option>
              <option value="aurora_saude">Aurora Saúde</option>
              <option value="bb_dental">BB Dental</option>
              <option value="bradesco_odonto">Bradesco Odonto</option>
              <option value="metlife_odonto">MetLife Odonto</option>
              <option value="unimed_bh">Unimed BH</option>
            </optgroup>
            <optgroup label="Detecta automático (sem amostra ainda)">
              <option value="bradesco_saude">Bradesco Saúde</option>
              <option value="caberj_saude">Caberj Saúde</option>
              <option value="hapvida">Hapvida</option>
              <option value="odontoprev">Odontoprev</option>
              <option value="unimed_saude">Seguros Unimed Saúde</option>
              <option value="sulamerica_odonto">SulAmérica Odonto</option>
              <option value="sulamerica_saude">SulAmérica Saúde</option>
              <option value="unimed_gov_valadares">Unimed Gov Valadares</option>
              <option value="unimed_ma">Unimed MA</option>
            </optgroup>
          </select>
        </label>
        <input id="sin-tab-img" type="file" accept="image/*" onchange="sinTabImgUp(this)" style="font-size:12px;color:var(--muted)"/>
      </div>
      <div id="sin-tab-thumb" style="margin-top:10px"></div>
    </div>

    <!-- Bloco BI (só na variação Com BI) -->
    <div class="sin-bi-bloco" style="margin-top:24px;padding-top:18px;border-top:1px solid var(--border)">
      <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <span class="material-symbols-rounded">insights</span> Análise de uso e tendências (BI)
      </h3>
      <div style="background:rgba(0,191,179,.05);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px">
        <label style="font-size:12px;color:var(--muted)"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">upload_file</span> Suba a <strong>planilha bruta de utilização</strong> (Excel) — a IA analisa e preenche os campos abaixo automaticamente:</label><br/>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:8px">
          <input id="sin-bi-xlsx" type="file" accept=".xlsx,.xls,.csv" onchange="sinImportarBI(this)" style="font-size:12px;color:var(--muted)"/>
          <span id="sin-bi-status" style="font-size:12px"></span>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="fg"><label>High Users</label><textarea id="sin-bi-high" rows="2" placeholder="Ex: Os 10 maiores usuários concentraram 88,30% do custo..."></textarea></div>
        <div class="fg"><label>Consultas</label><textarea id="sin-bi-consultas" rows="2"></textarea></div>
        <div class="fg"><label>Exames</label><textarea id="sin-bi-exames" rows="2"></textarea></div>
        <div class="fg"><label>Internações</label><textarea id="sin-bi-internacoes" rows="2"></textarea></div>
        <div class="fg"><label>Terapias</label><textarea id="sin-bi-terapias" rows="2"></textarea></div>
        <div class="fg"><label>Prestadores</label><textarea id="sin-bi-prestadores" rows="2"></textarea></div>
        <div class="fg"><label>Utilização por produto</label><textarea id="sin-bi-produtos" rows="2"></textarea></div>
        <div class="fg"><label>Ranking de utilização</label><textarea id="sin-bi-ranking" rows="2"></textarea></div>
        <div class="fg" style="grid-column:1/3"><label>Perfil demográfico</label><textarea id="sin-bi-demografico" rows="2"></textarea></div>
      </div>
    </div>

    <!-- Envio por e-mail (sempre disponível, com ou sem análise de BI) -->
    <div class="sin-email-bloco" style="margin-top:24px;padding:18px;border:1px solid var(--accent);border-radius:10px;background:rgba(0,191,179,.04)">
      <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin:0 0 6px">
        <span class="material-symbols-rounded">mail</span> Envio do informativo por e-mail
      </h3>
      <p style="font-size:12px;color:var(--muted);margin-bottom:14px">
        Após gerar o informativo (botão abaixo), envie por e-mail com os dados de acesso ao Power BI / WeeBi da empresa.
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div class="fg">
          <label>Destinatário(s) <span style="color:var(--muted);font-weight:400">(separe por ; ou vírgula)</span></label>
          <input id="sin-em-para" type="text" placeholder="rh@empresa.com.br; financeiro@empresa.com.br"/>
        </div>
        <div class="fg">
          <label>Assunto</label>
          <input id="sin-em-assunto" type="text" placeholder="Informativo de Sinistralidade — mês/ano — Empresa"/>
        </div>
      </div>
      <div class="fg">
        <label>Link do Power BI / WeeBi</label>
        <input id="sin-em-link" type="text" placeholder="https://app.powerbi.com/groups/me/reports/..."/>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div class="fg"><label>Login do BI</label><input id="sin-em-login" type="text" placeholder="usuario@empresa.com.br"/></div>
        <div class="fg"><label>Senha do BI</label><input id="sin-em-senha" type="text" placeholder="senha"/></div>
      </div>
      <small style="display:block;margin-top:6px;font-size:11px;color:var(--muted);font-style:italic">
        * Preencha o link/login/senha aqui. O envio é feito pelo botão <strong>Enviar e-mail de notificação</strong> que aparece após gerar o informativo (no preview). A assinatura é adicionada automaticamente do usuário logado.
      </small>
    </div>

    <!-- Informativo de saúde -->
    <div style="margin-top:24px;padding-top:18px;border-top:1px solid var(--border)">
      <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:14px">
        <span class="material-symbols-rounded">favorite</span> Informativo de saúde (rodapé)
      </h3>
      <div class="fg"><label>Texto do informativo</label>
        <textarea id="sin-info-texto" rows="4" placeholder="Texto do informativo de saúde...">A hipertensão arterial muitas vezes é silenciosa e não apresenta sintomas, ela pode trazer sérias complicações para a saúde quando não controlada. Pequenas mudanças na rotina podem fazer grande diferença para a saúde do coração. ✓ Reduza alimentos ricos em sódio ✓ Beba bastante água ✓ Durma bem ✓ Pratique atividades físicas. Prevenir é sempre o melhor cuidado.</textarea>
      </div>
      <!-- Gerar a imagem com IA (Gemini) -->
      <div class="fg" style="background:rgba(0,191,179,.04);border:1px solid var(--border);border-radius:8px;padding:14px">
        <label style="font-weight:700;color:var(--accent)"><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">auto_awesome</span> Gerar a imagem do informativo com IA</label>
        <p style="font-size:11.5px;color:var(--muted);margin:4px 0 8px">1) Digite o tema abaixo (o prompt se monta sozinho) · 2) Copie · 3) Abra o Gemini e gere a imagem · 4) Baixe e suba no campo "Escolher arquivo".</p>
        <div class="fg" style="margin:0 0 8px"><label style="font-size:11.5px">Tema da imagem</label>
          <input id="sin-info-tema" type="text" value="Hipertensão Arterial" oninput="sinAtualizarPrompt()" placeholder="Ex: Diabetes, Colesterol, Saúde Mental, Vacinação"/>
        </div>
        <textarea id="sin-info-prompt" rows="7" style="font-size:12px">Crie uma fotografia realista e profissional (fotorrealista, alta qualidade, elementos reais — sem ilustração, desenho ou cartoon), em formato de banner horizontal (proporção aproximada 16:6), estilo moderno e clean, para um informativo de saúde corporativo com o tema: HIPERTENSÃO ARTERIAL.

Paleta de cores da marca Interweg: verde/teal como cor predominante e detalhes em vermelho.

Mostre objetos e elementos REAIS diretamente ligados ao tema "Hipertensão Arterial", dispostos de forma elegante sobre uma superfície clean. Iluminação suave e natural, profundidade de campo com fundo desfocado de um ambiente clínico/hospitalar moderno.

Deixe uma área limpa e livre no canto superior esquerdo para inserir o título depois. NÃO inclua nenhum texto, letra ou número na imagem. Resolução alta e nitidez profissional.</textarea>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
          <button class="btn btn-outline btn-sm" type="button" onclick="sinCopiarPrompt()"><span class="material-symbols-rounded" style="font-size:14px">content_copy</span> Copiar prompt</button>
          <a class="btn btn-outline btn-sm" href="https://gemini.google.com/app?hl=pt-BR" target="_blank" rel="noopener"><span class="material-symbols-rounded" style="font-size:14px">open_in_new</span> Abrir Gemini</a>
        </div>
      </div>
      <div class="fg"><label><span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle">image</span> Suba a imagem do informativo (a que você gerou ou um banner pronto), entra no lugar do texto</label>
        <input id="sin-info-img" type="file" accept="image/*" onchange="sinInfoImgUp(this)" style="font-size:12px;color:var(--muted)"/>
        <div id="sin-info-thumb" style="margin-top:8px"></div>
      </div>
    </div>

    <div style="display:flex;gap:12px;margin-top:24px">
      <button class="btn btn-primary" onclick="sinGerar()" style="flex:1">
        <span class="material-symbols-rounded" style="font-size:17px">slideshow</span> Gerar Informativo Editável
      </button>
    </div>
  </div>

  <!-- STEP 2: Preview -->
  <div id="sin-preview" style="display:none">
    <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
      <button class="btn btn-outline" onclick="sinVoltar()"><span class="material-symbols-rounded" style="font-size:16px">arrow_back</span> Voltar e Editar</button>
      <button class="btn btn-primary" onclick="sinBaixarPPT()"><span class="material-symbols-rounded" style="font-size:16px">download</span> Baixar PPT</button>
      <button class="btn btn-outline" onclick="sinBaixarPDF()"><span class="material-symbols-rounded" style="font-size:16px">picture_as_pdf</span> Baixar PDF</button>
      <button class="btn btn-outline" onclick="sinBaixarImagem()"><span class="material-symbols-rounded" style="font-size:16px">image</span> Baixar Imagem</button>
      <button class="btn btn-primary" onclick="sinAbrirEnvioEmail()" style="background:var(--accent);border-color:var(--accent)"><span class="material-symbols-rounded" style="font-size:16px">mail</span> Enviar e-mail de notificação</button>
      <span style="font-size:11.5px;color:var(--muted);display:inline-flex;align-items:center;gap:5px"><span class="material-symbols-rounded" style="font-size:15px;color:#e74c3c">touch_app</span> Dica: duplo-clique numa célula de Sinistralidade marca/desmarca o vermelho manualmente.</span>
    </div>
    <div id="sin-render" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.3)"></div>
  </div>
</main>

<!-- ───── MODAL: REVISAR E ENVIAR INFORMATIVO POR E-MAIL ───── -->
<div class="ov" id="mod-sin-email">
  <style>
    #mod-sin-email .modal-title{font-size:21px}
    #mod-sin-email .modal-sub{font-size:14px}
    #mod-sin-email .fg label{font-size:14px}
    #mod-sin-email input, #mod-sin-email textarea{font-size:16px;padding:11px 13px}
    #mod-sin-email small{font-size:13px !important}
  </style>
  <div class="modal" style="width:1180px;max-width:96vw;max-height:94vh;overflow-y:auto">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">mail</span> Enviar Informativo de Sinistralidade</div>
    <div class="modal-sub">Revise o conteúdo antes de enviar. A assinatura é puxada automaticamente do usuário logado.</div>

    <div class="fg"><label>Para</label>
      <input type="text" id="sin-em-modal-para" placeholder="rh@empresa.com.br; financeiro@empresa.com.br"/>
      <small style="font-size:11px;color:var(--muted);font-style:italic">Separe múltiplos e-mails por ; ou vírgula.</small>
    </div>
    <div class="fg"><label>Assunto</label>
      <input type="text" id="sin-em-modal-assunto"/>
    </div>
    <div class="fg"><label>Mensagem</label>
      <textarea id="sin-em-modal-corpo" rows="16" style="font-family:inherit;line-height:1.5"></textarea>
      <small style="font-size:11px;color:var(--muted);font-style:italic">Edite livremente. A assinatura institucional é adicionada automaticamente no final.</small>
    </div>

    <div class="fg" id="sin-em-modal-img-wrap" style="display:none">
      <label style="display:flex;align-items:center;gap:6px"><span class="material-symbols-rounded" style="font-size:17px;color:#2e7d32">check_circle</span> Informativo que será anexado ao e-mail</label>
      <div style="border:1px solid #ddd;border-radius:8px;padding:8px;background:#fafafa;max-height:520px;overflow:auto;text-align:center">
        <img id="sin-em-modal-img" alt="Prévia do informativo" style="max-width:100%;height:auto;display:inline-block"/>
      </div>
      <small style="font-size:11px;color:var(--muted);font-style:italic">Esta imagem vai embutida no corpo do e-mail, logo após a frase de abertura.</small>
    </div>
    <div class="fg" id="sin-em-modal-img-aviso" style="display:none">
      <small style="font-size:12px;color:#c62828;font-weight:600">⚠ Não foi possível capturar o informativo. Volte, gere o informativo novamente e tente outra vez.</small>
    </div>

    <div class="mfooter" style="display:flex;justify-content:flex-end;gap:8px">
      <button class="btn btn-outline" onclick="closeModal('mod-sin-email')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" id="sin-em-btn-enviar" onclick="sinEnviarEmail(this)"><span class="material-symbols-rounded" style="font-size:17px">send</span> Enviar</button>
    </div>
  </div>
</div>


  <!-- ── CONTROLE DE SINISTRALIDADE (Controle mensal + Enviados) ── -->
  <div class="ov" id="mod-scm" onclick="if(event.target===this)scmClose()">
    <div class="modal" style="width:1150px;max-width:97vw;max-height:92vh;overflow-y:auto">
      <div class="modal-title" style="display:flex;align-items:center;justify-content:space-between">
        <span><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">fact_check</span> Controle de Sinistralidade</span>
        <button class="btn btn-outline btn-sm" onclick="scmClose()"><span class="material-symbols-rounded" style="font-size:16px">close</span></button>
      </div>
      <div class="scm-tabs" style="display:flex;gap:6px;border-bottom:1px solid var(--border);margin:6px 0 14px">
        <button class="scm-tab" data-t="mensal" onclick="scmTab('mensal')" style="padding:8px 14px;border:none;background:none;cursor:pointer;font-weight:700;font-size:13px;color:var(--accent);border-bottom:3px solid var(--accent);margin-bottom:-1px">Controle mensal</button>
        <button class="scm-tab" data-t="enviados" onclick="scmTab('enviados')" style="padding:8px 14px;border:none;background:none;cursor:pointer;font-weight:700;font-size:13px;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px">Enviados (hist&oacute;rico)</button>
      </div>
      <div id="scm-tab-mensal">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:0 0 12px">
          <label style="font-size:13px;color:var(--muted)">Compet&ecirc;ncia
            <input type="month" id="scm-comp" onchange="scmLoad()" style="margin-left:6px;padding:7px 10px;border:1px solid var(--border);border-radius:8px">
          </label>
          <button class="btn btn-outline btn-sm" onclick="scmBaixar()" title="Baixar a compet&ecirc;ncia atual em Excel"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">download</span> Baixar</button>
          <button class="btn btn-outline btn-sm" onclick="document.getElementById('scm-file').click()" title="Atualizar a partir de um Excel"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">upload</span> Importar</button>
          <input type="file" id="scm-file" accept=".xlsx,.xls" style="display:none" onchange="scmImportarFile(this)">
          <button class="btn btn-outline btn-sm" onclick="scmVirarMes()" title="Avan&ccedil;a para o pr&oacute;ximo m&ecirc;s"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">event_repeat</span> Virar m&ecirc;s &rarr;</button>
          <button class="btn btn-primary btn-sm" onclick="scmNovaEmpresa()" title="Incluir uma nova empresa no controle"><span class="material-symbols-rounded" style="font-size:15px;vertical-align:middle">add</span> Nova empresa</button>
          <select id="scm-filtro" onchange="scmRender()" title="Filtrar por situa&ccedil;&atilde;o de envio" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px">
            <option value="">Todos</option>
            <option value="pend">Pendentes de envio</option>
            <option value="env">Enviados</option>
          </select>
          <input type="text" id="scm-busca" placeholder="Buscar empresa / operadora..." oninput="scmRender()" style="flex:1;min-width:160px;padding:9px 12px;border:1px solid var(--border);border-radius:8px">
          <span id="scm-resumo" style="font-size:12px;color:var(--muted)"></span>
        </div>
        <div id="scm-body"><div style="text-align:center;color:var(--muted);padding:40px">Selecione a compet&ecirc;ncia&hellip;</div></div>
      </div>
      <div id="scm-tab-enviados" style="display:none">
        <input type="text" id="scm-log-busca" placeholder="Buscar empresa / assunto..." oninput="scmRenderLog()" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:8px;margin-bottom:12px">
        <div id="scm-log-body"><div style="text-align:center;color:var(--muted);padding:40px">Carregando&hellip;</div></div>
      </div>
    </div>
  </div>

  <!-- Modal: incluir nova empresa no controle de sinistralidade -->
  <div class="ov" id="mod-scm-nova" style="z-index:99000" onclick="if(event.target===this)scmNovaFechar()">
    <div class="modal" style="width:470px;max-width:95vw">
      <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">add_business</span> <span id="scm-nova-titulo">Nova empresa no controle</span></div>
      <input type="hidden" id="scm-nova-old-emp"><input type="hidden" id="scm-nova-old-op">
      <div class="fg" style="margin-top:12px"><label>Empresa</label><input id="scm-nova-emp" type="text" placeholder="Ex: Powerpoxi Tecnologia"></div>
      <div class="fg"><label>Operadora</label><input id="scm-nova-op" type="text" placeholder="Ex: Unimed BH"></div>
      <div class="fg"><label>E-mails <span style="color:var(--muted);font-weight:400">(separe por ; ou vírgula)</span></label><input id="scm-nova-emails" type="text" placeholder="rh@empresa.com.br; financeiro@empresa.com.br"></div>
      <div class="fg" style="max-width:170px"><label>Índice 12M (%)</label><input id="scm-nova-12m" type="text" inputmode="decimal" placeholder="Ex: 72,50"></div>
      <div id="scm-nova-situacao" style="display:none;margin-top:6px;padding:12px 14px;border:1px solid var(--border);border-radius:10px;background:var(--bg-hover,#f3f5f7)">
        <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:8px">Situação da competência <span id="scm-nova-comp-lbl"></span></div>
        <div style="display:flex;gap:18px;flex-wrap:wrap">
          <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer"><input type="checkbox" id="scm-nova-bi" onchange="scmDetToggle('bi',this)" style="width:17px;height:17px;accent-color:var(--accent)"> BI</label>
          <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer"><input type="checkbox" id="scm-nova-rec" onchange="scmDetToggle('recebimento',this)" style="width:17px;height:17px;accent-color:var(--accent)"> Recebimento</label>
          <label style="display:flex;align-items:center;gap:7px;font-size:13px;cursor:pointer"><input type="checkbox" id="scm-nova-env" onchange="scmDetToggle('envio',this)" style="width:17px;height:17px;accent-color:var(--accent)"> Envio p/ empresa</label>
        </div>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:16px">
        <button class="btn btn-danger" id="scm-nova-excluir" style="display:none" onclick="scmExcluirLinha(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">delete</span> Excluir</button>
        <span style="flex:1"></span>
        <button class="btn btn-outline" onclick="scmNovaFechar()">Cancelar</button>
        <button class="btn btn-primary" onclick="scmNovaSalvar(this)"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">save</span> Salvar</button>
      </div>
    </div>
  </div>

<script>
(function(){
  const esc = s => { const d=document.createElement('div'); d.textContent=(s==null?'':s); return d.innerHTML; };
  const norm = s => String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
  const apiGet = window.apiGet || ((a,p={})=>fetch('api.php?'+new URLSearchParams({action:a,...p})).then(r=>r.json()));
  const apiPost = (action,body)=>fetch('api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body||{})}).then(r=>r.json());
  const fdt = s => { if(!s) return '—'; const m=String(s).match(/(\d{4})-(\d{2})-(\d{2})[ T]?(\d{2}:\d{2})?/); return m?(m[3]+'/'+m[2]+'/'+m[1]+(m[4]?(' '+m[4]):'')):String(s); };
  let _log=[], _scmData=[], _scmComp='';

  window.scmTab = function(t){
    document.querySelectorAll('.scm-tab').forEach(b=>{ const on=b.dataset.t===t; b.style.color=on?'var(--accent)':'var(--muted)'; b.style.borderBottomColor=on?'var(--accent)':'transparent'; });
    const tm=document.getElementById('scm-tab-mensal'), te=document.getElementById('scm-tab-enviados');
    if(tm) tm.style.display=(t==='mensal')?'':'none';
    if(te) te.style.display=(t==='enviados')?'':'none';
    if(t==='enviados') scmRenderLog();
  };
  function logEnviado(empresa){
    if(!_scmComp) return null;
    const p=_scmComp.split('-'); const mmYYYY=p[1]+'/'+p[0]; const ne=norm(empresa);
    for(const r of _log){
      if(r.status!=='enviado') continue;
      if(String(r.assunto||'').indexOf(mmYYYY)<0) continue;
      if(norm(r.assunto).includes(ne)||norm(r.empresa).includes(ne)) return r.criado_em;
    }
    return null;
  }
  window.scmOpen = async function(){
    const m=document.getElementById('mod-scm'); m.classList.add('show'); m.style.display='flex';
    scmTab('mensal');
    const el=document.getElementById('scm-comp');
    if(!el.value){ const d=new Date(); el.value=d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0'); }
    try{ const r=await apiGet('listar_envios_sinistralidade'); _log=(r&&r.log)||[]; }catch(e){ _log=[]; }
    scmLoad();
  };
  window.scmClose = function(){ const m=document.getElementById('mod-scm'); m.classList.remove('show'); m.style.display='none'; };
  // ── Incluir nova empresa no controle ──
  const scmSet=(id,v)=>{ const e=document.getElementById(id); if(e) e.value=(v==null?'':v); };
  window.scmNovaEmpresa = function(){
    scmSet('scm-nova-old-emp',''); scmSet('scm-nova-old-op','');
    ['scm-nova-emp','scm-nova-op','scm-nova-emails','scm-nova-12m'].forEach(id=>scmSet(id,''));
    const t=document.getElementById('scm-nova-titulo'); if(t) t.textContent='Nova empresa no controle';
    const bx=document.getElementById('scm-nova-excluir'); if(bx) bx.style.display='none';
    const sit=document.getElementById('scm-nova-situacao'); if(sit) sit.style.display='none';
    const m=document.getElementById('mod-scm-nova'); if(m){ m.classList.add('show'); m.style.display='flex'; }
    setTimeout(()=>{ const e=document.getElementById('scm-nova-emp'); if(e) e.focus(); }, 30);
  };
  window.scmEditarLinha = function(el){
    const emp=el.getAttribute('data-emp')||'', op=el.getAttribute('data-op')||'';
    const emails=el.getAttribute('data-emails')||'', idx=el.getAttribute('data-idx')||'';
    scmSet('scm-nova-old-emp',emp); scmSet('scm-nova-old-op',op);
    scmSet('scm-nova-emp',emp); scmSet('scm-nova-op',op); scmSet('scm-nova-emails',emails); scmSet('scm-nova-12m',idx);
    const t=document.getElementById('scm-nova-titulo'); if(t) t.textContent='Detalhes da empresa';
    const bx=document.getElementById('scm-nova-excluir'); if(bx) bx.style.display='';
    // Situação da competência
    const sit=document.getElementById('scm-nova-situacao'); if(sit) sit.style.display='';
    const cl=document.getElementById('scm-nova-comp-lbl'); if(cl){ const p=(_scmComp||'').split('-'); cl.textContent=(p[1]?(p[1]+'/'+p[0]):''); }
    const sb=document.getElementById('scm-nova-bi'), sr=document.getElementById('scm-nova-rec'), se=document.getElementById('scm-nova-env');
    if(sb) sb.checked=(el.getAttribute('data-bi')==='1');
    if(sr) sr.checked=(el.getAttribute('data-rec')==='1');
    if(se) se.checked=(el.getAttribute('data-env')==='1');
    const m=document.getElementById('mod-scm-nova'); if(m){ m.classList.add('show'); m.style.display='flex'; }
  };
  // Altera BI/Recebimento/Envio direto do modal (grava na competência atual)
  window.scmDetToggle = async function(campo, el){
    const g=id=>((document.getElementById(id)||{}).value||'').trim();
    const emp=g('scm-nova-old-emp')||g('scm-nova-emp'), op=g('scm-nova-old-op')||g('scm-nova-op');
    if(!emp || !_scmComp) return;
    const valor=el.checked?1:0;
    try{
      const r=await apiPost('sin_etapa_salvar',{empresa:emp,operadora:op,competencia:_scmComp,campo:campo,valor:valor});
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao salvar'); el.checked=!el.checked; return; }
      const row=_scmData.find(x=>x.empresa===emp && (x.operadora||'')===op); if(row) row[campo]=valor;
      scmRender();
    }catch(e){ alert('Erro de conexão'); el.checked=!el.checked; }
  };
  window.scmNovaFechar = function(){ const m=document.getElementById('mod-scm-nova'); if(m){ m.classList.remove('show'); m.style.display='none'; } };
  window.scmNovaSalvar = async function(btn){
    const g=id=>((document.getElementById(id)||{}).value||'').trim();
    const emp=g('scm-nova-emp'), op=g('scm-nova-op'), emails=g('scm-nova-emails'), v12=g('scm-nova-12m');
    const oldEmp=g('scm-nova-old-emp'), oldOp=g('scm-nova-old-op');
    if(!emp){ alert('Informe o nome da empresa.'); const e=document.getElementById('scm-nova-emp'); if(e) e.focus(); return; }
    if(btn) btn.disabled=true;
    try{
      let r;
      if(oldEmp){ r=await apiPost('sin_base_editar',{old_empresa:oldEmp,old_operadora:oldOp,empresa:emp,operadora:op,emails:emails,indice_12m:(v12===''?'':v12)}); }
      else { r=await apiPost('sin_base_salvar',{empresa:emp,operadora:op,emails:emails,indice_12m:(v12===''?'':v12),ativo:1}); }
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao salvar'); return; }
      scmNovaFechar();
      scmLoad();
    }catch(e){ alert('Erro de conexão'); } finally{ if(btn) btn.disabled=false; }
  };
  window.scmExcluirLinha = async function(btn){
    const g=id=>((document.getElementById(id)||{}).value||'').trim();
    const emp=g('scm-nova-old-emp')||g('scm-nova-emp'), op=g('scm-nova-old-op')||g('scm-nova-op');
    if(!emp) return;
    const msg='Excluir "'+emp+(op?(' ('+op+')'):'')+'" do controle de sinistralidade?\n\nEla sai da lista; o histórico de envios é mantido.';
    const ok = (typeof window.uiConfirm==='function') ? await window.uiConfirm(msg,'Excluir empresa') : confirm(msg);
    if(!ok) return;
    if(btn) btn.disabled=true;
    try{
      const r=await apiPost('sin_base_excluir',{empresa:emp,operadora:op});
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao excluir'); return; }
      scmNovaFechar();
      scmLoad();
    }catch(e){ alert('Erro de conexão'); } finally{ if(btn) btn.disabled=false; }
  };
  window.scmLoad = async function(){
    _scmComp=(document.getElementById('scm-comp')||{}).value||'';
    if(!_scmComp) return;
    document.getElementById('scm-body').innerHTML='<div style="text-align:center;color:var(--muted);padding:40px">Carregando&hellip;</div>';
    try{ const r=await apiGet('sin_etapas_listar',{competencia:_scmComp}); _scmData=(r&&r.ok)?r.data:[]; }catch(e){ _scmData=[]; }
    scmRender();
  };
  const chk=(on,emp,op,campo)=>'<input type="checkbox" '+(on?'checked':'')+' data-emp="'+esc(emp)+'" data-op="'+esc(op||'')+'" data-campo="'+campo+'" onchange="scmToggle(this)" style="width:17px;height:17px;cursor:pointer;accent-color:var(--accent)">';

  window.scmRender = function(){
    const box=document.getElementById('scm-body'); const q=norm((document.getElementById('scm-busca')||{}).value||'');
    const filtro=(document.getElementById('scm-filtro')||{}).value||'';
    let rows=_scmData.filter(r=>!q||norm(r.empresa).includes(q)||norm(r.operadora).includes(q));
    if(filtro==='env') rows=rows.filter(r=>+r.envio===1);
    else if(filtro==='pend') rows=rows.filter(r=>+r.envio!==1);
    scmResumo();
    const tot=_scmData.length;
    if(!tot){ box.innerHTML='<div style="text-align:center;color:var(--muted);padding:30px">Base vazia. Importe a planilha (rode o seed_sinistralidade.sql).</div>'; return; }
    if(!rows.length){ box.innerHTML='<div style="text-align:center;color:var(--muted);padding:30px">Nenhuma empresa encontrada.</div>'; return; }
    let h='<div style="overflow:auto;border:1px solid var(--border);border-radius:10px"><table style="width:100%;border-collapse:collapse;font-size:12.5px">'
      +'<thead><tr style="background:var(--bg)">'
      +'<th style="padding:8px;text-align:left">Empresa</th>'
      +'<th style="padding:8px;text-align:left">Operadora</th>'
      +'<th style="padding:8px;text-align:center">12M</th>'
      +'<th style="padding:8px;text-align:center" title="BI recebido">BI</th>'
      +'<th style="padding:8px;text-align:center">Recebimento</th>'
      +'<th style="padding:8px;text-align:center">Envio p/ empresa</th>'
      +'<th style="padding:8px;text-align:left">E-mails</th></tr></thead><tbody>';
    rows.forEach(r=>{
      const idxVal=(r.indice_12m==null||r.indice_12m==='')?'':String(r.indice_12m).replace('.',',');
      const env=logEnviado(r.empresa);
      h+='<tr style="border-top:1px solid var(--border)">'
        +'<td style="padding:7px"><b onclick="scmEditarLinha(this)" data-emp="'+esc(r.empresa||'')+'" data-op="'+esc(r.operadora||'')+'" data-emails="'+esc(r.emails||'')+'" data-idx="'+esc(idxVal)+'" data-bi="'+(+r.bi===1?1:0)+'" data-rec="'+(+r.recebimento===1?1:0)+'" data-env="'+(+r.envio===1?1:0)+'" title="Ver detalhes / editar / excluir" style="cursor:pointer;border-bottom:1px dashed var(--accent)">'+esc(r.empresa||'—')+'</b></td>'
        +'<td style="padding:7px">'+esc(r.operadora||'—')+'</td>'
        +'<td style="padding:7px;text-align:center;white-space:nowrap"><input type="text" inputmode="decimal" value="'+idxVal+'" data-emp="'+esc(r.empresa)+'" data-op="'+esc(r.operadora||'')+'" onchange="scmSaveIdx(this)" placeholder="%" title="Indice 12 meses (editavel)" style="width:66px;text-align:center;padding:4px 6px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:var(--bg-card);color:var(--text)"></td>'
        +'<td style="padding:7px;text-align:center">'+chk(+r.bi===1,r.empresa,r.operadora,'bi')+'</td>'
        +'<td style="padding:7px;text-align:center">'+chk(+r.recebimento===1,r.empresa,r.operadora,'recebimento')+'</td>'
        +'<td style="padding:7px;text-align:center">'+chk(+r.envio===1,r.empresa,r.operadora,'envio')+(env?'<div style="font-size:10px;color:#256029;font-weight:700;margin-top:2px" title="Envio registrado no historico">&#10003; enviado '+String(fdt(env)).split(' ')[0]+'</div>':'')+'</td>'
        +'<td style="padding:7px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted)" title="'+esc(r.emails||'')+'">'+esc(r.emails||'—')+'</td>'
        +'</tr>';
    });
    h+='</tbody></table></div>';
    box.innerHTML=h;
  };
  function scmResumo(){
    const res=document.getElementById('scm-resumo'); if(!res) return;
    const tot=_scmData.length,
      cBI=_scmData.filter(r=>+r.bi===1).length, cRe=_scmData.filter(r=>+r.recebimento===1).length, cEn=_scmData.filter(r=>+r.envio===1).length;
    res.innerHTML='Empresas: <b>'+tot+'</b> &middot; BI: <b>'+cBI+'</b> &middot; Recebimento: <b>'+cRe+'</b> &middot; <span style="color:#256029">Enviados: <b>'+cEn+'</b></span> &middot; <span style="color:#c0392b">Pendentes: <b>'+(tot-cEn)+'</b></span>';
  }
  window.scmSaveIdx = async function(el){
    const emp=el.dataset.emp, op=el.dataset.op||'';
    const raw=(el.value||'').trim().replace('%','').replace(',','.');
    let val=''; if(raw!==''){ const n=parseFloat(raw); if(isNaN(n)){ alert('Valor invalido'); return; } val=n; }
    const row=_scmData.find(x=>x.empresa===emp&&(x.operadora||'')===op);
    const emails=row?(row.emails||''):'';
    el.disabled=true;
    try{ const r=await apiPost('sin_base_salvar',{empresa:emp,operadora:op,emails:emails,indice_12m:(val===''?'':val),ativo:1});
      if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao salvar'); } else if(row){ row.indice_12m=(val===''?null:val); }
    }catch(e){ alert('Falha de conexao'); }
    el.disabled=false;
  };
  window.scmBaixar = function(){
    if(typeof XLSX==='undefined'){ alert('Biblioteca de planilha nao carregou. Recarregue a pagina.'); return; }
    if(!_scmComp){ alert('Selecione a competencia.'); return; }
    const aoa=[['Empresa','Operadora','12M','BI','Recebimento','Envio','E-mails','Competencia']];
    _scmData.forEach(r=>{ aoa.push([ r.empresa||'', r.operadora||'', (r.indice_12m==null||r.indice_12m==='')?'':Number(r.indice_12m), (+r.bi===1?'OK':''), (+r.recebimento===1?'OK':''), (+r.envio===1?'OK':''), r.emails||'', _scmComp ]); });
    const ws=XLSX.utils.aoa_to_sheet(aoa); const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Sinistralidade');
    XLSX.writeFile(wb,'controle_sinistralidade_'+_scmComp+'.xlsx');
  };
  window.scmImportarFile = async function(input){
    const file=input.files&&input.files[0]; input.value=''; if(!file) return;
    if(typeof XLSX==='undefined'){ alert('Biblioteca de planilha nao carregou. Recarregue a pagina.'); return; }
    try{
      const buf=await file.arrayBuffer(); const wb=XLSX.read(new Uint8Array(buf),{type:'array'});
      const ws=wb.Sheets[wb.SheetNames[0]]; const rows=XLSX.utils.sheet_to_json(ws,{header:1,defval:'',raw:true});
      if(!rows.length){ alert('Planilha vazia.'); return; }
      const nn=s=>String(s||'').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'').trim();
      let hr=-1, col={};
      for(let i=0;i<Math.min(rows.length,10);i++){ const cs=(rows[i]||[]).map(nn); if(cs.indexOf('empresa')>=0){ hr=i; cs.forEach((c,j)=>{ if(col[c]==null) col[c]=j; }); break; } }
      if(hr<0){ alert('Nao achei o cabecalho (precisa ter a coluna "Empresa").'); return; }
      const g=(row,name)=>{ const j=col[name]; return j==null?'':row[j]; };
      const ok=v=>{ const s=String(v==null?'':v).toLowerCase().trim(); return (s==='ok'||s==='x'||s==='sim'||s==='1')?1:0; };
      const num=v=>{ if(v===''||v==null) return ''; const n=parseFloat(String(v).replace('%','').replace(',','.')); return isNaN(n)?'':n; };
      const linhas=[];
      for(let i=hr+1;i<rows.length;i++){ const r=rows[i]; if(!r) continue; const emp=String(g(r,'empresa')||'').trim(); if(!emp) continue;
        let comp=String(g(r,'competencia')||'').trim(); if(!/^\d{4}-\d{2}$/.test(comp)) comp=_scmComp;
        linhas.push({ empresa:emp, operadora:String(g(r,'operadora')||'').trim(), emails:String(g(r,'e-mails')||g(r,'emails')||'').trim(), indice_12m:num(g(r,'12m')), competencia:comp, bi:ok(g(r,'bi')), recebimento:ok(g(r,'recebimento')), envio:ok(g(r,'envio')) }); }
      if(!linhas.length){ alert('Nenhuma linha valida.'); return; }
      const _mi='Importar '+linhas.length+' linha(s)? Atualiza e-mails, 12M e as etapas da competência de cada linha.';
      const _oki=(typeof window.uiConfirm==='function')?await window.uiConfirm(_mi,'Importar planilha'):confirm(_mi);
      if(!_oki) return;
      const resp=await apiPost('sin_importar',{linhas});
      if(!resp||!resp.ok){ alert((resp&&resp.msg)||'Erro ao importar'); return; }
      alert('Importado: '+resp.base+' empresa(s), '+resp.etapas+' etapa(s).'); scmLoad();
    }catch(e){ alert('Erro ao ler planilha: '+e.message); }
  };
  window.scmVirarMes = async function(){
    const el=document.getElementById('scm-comp'); if(!el||!el.value) return;
    const p=el.value.split('-'); let y=+p[0], m=+p[1]+1; if(m>12){ m=1; y++; }
    const prox=y+'-'+String(m).padStart(2,'0');
    const _mv='Virar para '+prox+'?\n\nO mês atual ('+el.value+') fica guardado como está, e o próximo mês é criado com as fichas em branco.';
    const _okv=(typeof window.uiConfirm==='function')?await window.uiConfirm(_mv,'Virar mês'):confirm(_mv);
    if(!_okv) return;
    try{ const r=await apiPost('sin_virar_mes',{competencia:prox}); if(!r||!r.ok){ alert((r&&r.msg)||'Erro ao virar o mes'); return; } }catch(e){ alert('Falha de conexao'); return; }
    el.value=prox; scmLoad();
  };
  window.scmToggle = async function(el){
    const emp=el.dataset.emp, op=el.dataset.op, campo=el.dataset.campo, valor=el.checked?1:0;
    el.disabled=true;
    try{ const r=await apiPost('sin_etapa_salvar',{empresa:emp,operadora:op,competencia:_scmComp,campo:campo,valor:valor});
      if(!r||!r.ok){ el.checked=!el.checked; alert((r&&r.msg)||'Erro ao salvar'); }
      else{ const row=_scmData.find(x=>x.empresa===emp&&(x.operadora||'')===(op||'')); if(row) row[campo]=valor; scmResumo(); }
    }catch(e){ el.checked=!el.checked; alert('Falha de conexao'); }
    el.disabled=false;
  };
  window.scmRenderLog = function(){
    const box=document.getElementById('scm-log-body'); if(!box) return;
    const q=norm((document.getElementById('scm-log-busca')||{}).value||'');
    let rows=_log.filter(r=>!q||norm(r.empresa).includes(q)||norm(r.assunto).includes(q)||norm(r.enviado_por_nome).includes(q));
    const enviados=_log.filter(r=>r.status==='enviado').length, erros=_log.filter(r=>r.status==='erro').length;
    let h='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">'
      +'<span style="background:var(--bg-card);border:1px solid var(--border);border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700">Total: '+_log.length+'</span>'
      +'<span style="background:#d3f2d8;color:#256029;border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700">Enviados: '+enviados+'</span>'
      +'<span style="background:#fdecec;color:#c0392b;border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700">Erros: '+erros+'</span></div>';
    if(!rows.length){ box.innerHTML=h+'<div style="text-align:center;color:var(--muted);padding:30px">Nenhum envio registrado.</div>'; return; }
    h+='<div style="overflow:auto;border:1px solid var(--border);border-radius:10px"><table style="width:100%;border-collapse:collapse;font-size:12.5px">'
      +'<thead><tr style="background:var(--bg)"><th style="padding:8px;text-align:left">Empresa</th><th style="padding:8px;text-align:left">Assunto</th><th style="padding:8px;text-align:left">Destinatarios</th><th style="padding:8px;text-align:left">Enviado por</th><th style="padding:8px;text-align:left">Quando</th><th style="padding:8px;text-align:left">Status</th></tr></thead><tbody>';
    rows.forEach(r=>{ const ok=r.status==='enviado';
      h+='<tr style="border-top:1px solid var(--border)"><td style="padding:8px"><b>'+esc(r.empresa||'—')+'</b></td>'
        +'<td style="padding:8px">'+esc(r.assunto||'—')+'</td>'
        +'<td style="padding:8px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="'+esc(r.destinatarios||'')+'">'+esc(r.destinatarios||'—')+'</td>'
        +'<td style="padding:8px">'+esc(r.enviado_por_nome||'—')+'</td>'
        +'<td style="padding:8px;white-space:nowrap">'+fdt(r.criado_em)+'</td>'
        +'<td style="padding:8px"><span style="padding:2px 8px;border-radius:20px;font-size:10.5px;font-weight:800;background:'+(ok?'#d3f2d8;color:#256029':'#fdecec;color:#c0392b')+'">'+(ok?'Enviado':'Erro')+'</span></td></tr>';
    });
    h+='</tbody></table></div>'; box.innerHTML=h;
  };
})();
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
