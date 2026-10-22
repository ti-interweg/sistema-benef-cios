<?php
$currentPage = 'passagem_bastao';
$pageTitle   = 'Passagem de Bastão — Interweg Benefícios';
$pageScripts = ['assets/passagem_bastao.js?v=' . time()];
require_once __DIR__ . '/_header.php';
?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Passagem de Bastão</div>
      <div class="page-sub">Empresas recebidas na passagem do comercial para a operação.</div>
    </div>
    <button class="btn btn-primary" onclick="pbNova()"><span class="material-symbols-rounded" style="font-size:19px">add</span> Nova passagem</button>
  </div>

  <div style="display:flex;gap:8px;margin-bottom:16px">
    <button class="btn btn-outline" id="pb-tab-lista" onclick="pbShowTab('lista')" style="border-color:var(--accent);color:var(--accent)">Lista</button>
    <button class="btn btn-outline" id="pb-tab-nova" onclick="pbNova()">Nova passagem</button>
  </div>

  <!-- ABA: LISTA -->
  <div id="pb-pane-lista">
    <div class="table-wrap">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)">
        <div style="font-size:13px;font-weight:700" id="pb-count">Passagens de Bastão</div>
        <button class="btn btn-outline btn-sm" onclick="pbCarregarLista()"><span class="material-symbols-rounded" style="font-size:16px">refresh</span> Atualizar</button>
      </div>
      <div style="overflow-x:auto;width:100%">
        <table class="iw-table" style="min-width:980px">
          <thead><tr>
            <th>#</th><th>Empresa</th><th>CNPJ</th><th>Operadora</th><th>Contrato</th>
            <th>Ramo</th><th>Vidas</th><th>Vigência</th><th>Comercial</th><th>Analista</th><th>Criado em</th><th>Ações</th>
          </tr></thead>
          <tbody id="pb-lista-tbody"><tr><td colspan="12" style="text-align:center;padding:30px;color:var(--muted)">Carregando...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ABA: NOVA (formulário completo) -->
  <div id="pb-pane-nova" style="display:none">
  <div class="card" style="padding:22px 24px;text-align:left">

    <div style="display:flex;align-items:center;gap:10px;background:rgba(0,191,179,.06);border:1px solid var(--border);border-radius:8px;padding:10px 14px;margin-bottom:18px">
      <span class="material-symbols-rounded" style="color:var(--accent)">upload_file</span>
      <div style="flex:1;font-size:12.5px;color:var(--muted)">Tem a planilha "Modelo - Passagem de Bastão" preenchida? Importe e o formulário preenche sozinho.</div>
      <input type="file" id="pb-import-file" accept=".xlsx,.xls" style="display:none" onchange="pbImportarPlanilha(this)"/>
      <a class="btn btn-outline btn-sm" href="modelo_passagem_bastao.xlsx" download><span class="material-symbols-rounded" style="font-size:16px">download</span> Baixar modelo</a>
      <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('pb-import-file').click()"><span class="material-symbols-rounded" style="font-size:16px">upload</span> Importar planilha</button>
    </div>

    <!-- ID oculto pra modo de edição (preenchido por pbVerNaIntegra) -->
    <input type="hidden" id="pb-id" value=""/>
    <!-- Aviso visual quando o form estiver em modo edição -->
    <div id="pb-edit-banner" style="display:none;background:rgba(255,193,7,.15);border-left:4px solid #ffc107;padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:13px">
      <span class="material-symbols-rounded" style="font-size:18px;vertical-align:middle;color:#b8860b">edit_note</span>
      <strong>Modo edição:</strong> você está editando uma passagem de bastão existente (#<span id="pb-edit-id-label">—</span>).
      <button type="button" class="btn btn-outline btn-sm" style="margin-left:12px" onclick="pbLimparEdit()">Cancelar edição</button>
    </div>

    <div class="sec">Identificação do contrato</div>
    <div class="row3">
      <div class="fg"><label>Entrada/Origem</label>
        <select id="pb-origem">
          <option value="">—</option>
          <option value="Interweg MG">Belo Horizonte</option>
          <option value="Interweg SP">São Paulo</option>
          <option value="Interweg JF">Juiz de Fora</option>
          <option value="Finlândia">Finlândia</option>
          <option value="Fred">Fred</option>
          <option value="Parceiro - Bernardo">Parceiro - Bernardo</option>
          <option value="Parceiro - Elmo">Parceiro - Elmo</option>
        </select>
      </div>
      <div class="fg"><label>Operadora</label><select id="pb-seguradora"><option value="">—</option></select></div>
      <div class="fg"><label>Ramo</label>
        <select id="pb-ramo"><option value="">—</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida em Grupo</option><option>Vida Individual</option><option>Seguro Estagiário</option></select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Número do contrato</label><textarea id="pb-numero_contrato" rows="2" style="resize:vertical" placeholder="Ex: 12345-6 (pode usar Enter para quebrar linhas)"></textarea></div>
      <div class="fg"><label>CNPJ</label>
        <div style="display:flex;gap:6px">
          <input id="pb-cnpj" type="text" maxlength="18" placeholder="00.000.000/0000-00" style="flex:1"/>
          <button type="button" class="btn btn-outline btn-sm" onclick="pbConsultarCnpj(this)" title="Buscar dados do CNPJ na Receita" style="white-space:nowrap"><span class="material-symbols-rounded" style="font-size:16px">search</span></button>
        </div>
        <div id="pb-cnpj-info" style="font-size:11.5px;margin-top:3px;color:var(--muted)"></div>
      </div>
      <div class="fg"><label>Estipulante / Subestipulante</label>
        <select id="pb-tipo_estipulacao"><option value="">—</option><option>Estipulante</option><option>Subestipulante</option></select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Tipo</label>
        <select id="pb-tipo_passagem">
          <option value="">— Selecione —</option>
          <option value="Seguro Novo">Seguro Novo</option>
          <option value="Seguro Novo de Cliente Interweg">Seguro Novo de Cliente Interweg</option>
          <option value="Nomeação">Nomeação</option>
          <option value="Renovação">Renovação</option>
        </select>
      </div>
      <div class="fg"><label>Início de vigência</label><input id="pb-inicio_vigencia" type="date"/></div>
    </div>

    <div class="sec">Empresa</div>
    <div class="row3">
      <div class="fg"><label>Grupo</label><input id="pb-grupo" type="text" list="pb-grupo-list" autocomplete="off" placeholder="Grupo econômico (se vazio, usa a razão social)"/><datalist id="pb-grupo-list"></datalist></div>
      <div class="fg"><label>Razão Social / Empresa *</label><input id="pb-nome" type="text" list="pb-nome-list" autocomplete="off" placeholder="Razão social"/><datalist id="pb-nome-list"></datalist></div>
      <div class="fg"><label>Porte da empresa</label>
        <select id="pb-porte"><option value="">—</option><option>Até 30 vidas</option><option>Entre 30 e 99 vidas</option><option>Entre 100 e 199 vidas</option><option>Acima de 200 vidas</option></select>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Comercial Interweg</label><input id="pb-comercial_interweg" type="text"/></div>
      <div class="fg"><label>Corretora</label>
        <select id="pb-corretora">
          <option value="">— Selecione —</option>
          <option value="08.278.697/0001-79 (Antiga Wegman)">08.278.697/0001-79 (ADM - Antiga Wegman)</option>
          <option value="02.017.033/0001-70 (Antiga Intermezzo)">02.017.033/0001-70 (Benefícios - Antiga Intermezzo)</option>
          <option value="22.624.916/0001-55 (Antiga Gecorp)">22.624.916/0001-55 (São Paulo - Antiga Gecorp)</option>
        </select>
      </div>
    </div>

    <div class="sec" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span>Plano e contratação</span>
      <button type="button" class="btn btn-outline btn-sm" data-edit-only onclick="pbPlanoAdd()" style="display:inline-flex;align-items:center;gap:4px;font-size:12px">
        <span class="material-symbols-rounded" style="font-size:16px">add</span> Novo Plano
      </button>
    </div>
    <div id="pb-planos-list" style="display:flex;flex-direction:column;gap:12px"></div>
    <!-- Campos ocultos (compat.): espelham o 1º plano; a lista completa vai em pb-planos (JSON) -->
    <input id="pb-planos" type="hidden"/>
    <input id="pb-numero_vidas" type="hidden"/>
    <input id="pb-modalidade_contratacao" type="hidden"/>
    <input id="pb-permanencia_contratual" type="hidden"/>

    <div class="sec">Subfaturas e coparticipação</div>
    <div class="row2">
      <div class="fg"><label>Possui subfaturas?</label><select id="pb-possui_subfaturas"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg">
        <label>Se "sim" para subfatura(s), descrever razão social e CNPJ</label>
        <div id="pb-subfaturas-list" style="display:flex;flex-direction:column;gap:8px"></div>
        <button type="button" class="btn btn-outline btn-sm" data-edit-only onclick="pbSubfaturaAdd()" style="margin-top:8px;align-self:flex-start;display:inline-flex;align-items:center;gap:4px">
          <span class="material-symbols-rounded" style="font-size:16px">add</span> Nova Subfatura
        </button>
        <textarea id="pb-subfaturas_desc" style="display:none"></textarea>
      </div>
    </div>
    <div class="row2">
      <div class="fg"><label>Possui coparticipação?</label><select id="pb-possui_copart"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg"><label>Se sim, descrever</label><textarea id="pb-copart_desc" rows="3" style="resize:vertical" placeholder="Descreva a coparticipação (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>

    <div class="sec">Valores e cobrança</div>
    <div class="row3">
      <div class="fg"><label>Tipo de custo</label><input id="pb-tipo_custo" type="text"/></div>
      <div class="fg"><label>Valor do 1º faturamento</label>
        <div style="position:relative;display:flex;align-items:center">
          <span style="position:absolute;left:10px;color:var(--muted);font-size:13px;pointer-events:none">R$</span>
          <input id="pb-valor_fatura" type="text" inputmode="decimal" placeholder="4.710,94" style="padding-left:32px;width:100%" oninput="pbMaskMoney(this)"/>
        </div>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Data vencimento 1º faturamento</label><input id="pb-data_venc_boleto" type="date"/></div>
      <div class="fg"><label>Necessidade de rateio?</label><input id="pb-necessidade_rateio" type="text"/></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Data de emissão de faturamento</label><input id="pb-data_emissao" type="text" inputmode="numeric" maxlength="2" placeholder="Dia (ex: 15)" oninput="this.value=this.value.replace(/\D/g,'').slice(0,2)"/></div>
      <div class="fg"><label>Data vencimento demais faturamentos</label><input id="pb-data_venc_demais" type="text" inputmode="numeric" maxlength="2" placeholder="Dia (ex: 20)" oninput="this.value=this.value.replace(/\D/g,'').slice(0,2)"/></div>
    </div>

    <div class="fg">
      <label>Agenciamento (%) <span style="color:var(--muted);font-weight:400;text-transform:none">(informe as parcelas; a tabela mostra o total)</span></label>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <div class="pb-agwrap" id="pb-agwrap-1" style="flex:1;min-width:78px;position:relative;display:flex;align-items:center"><input type="text" id="pb-agenc-1" placeholder="1º" style="width:100%;box-sizing:border-box;padding-right:22px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparParcela(1)" title="Excluir esta parcela" style="position:absolute;right:7px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-agwrap" id="pb-agwrap-2" style="flex:1;min-width:78px;position:relative;display:none;align-items:center"><input type="text" id="pb-agenc-2" placeholder="2º" style="width:100%;box-sizing:border-box;padding-right:22px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparParcela(2)" title="Excluir esta parcela" style="position:absolute;right:7px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-agwrap" id="pb-agwrap-3" style="flex:1;min-width:78px;position:relative;display:none;align-items:center"><input type="text" id="pb-agenc-3" placeholder="3º" style="width:100%;box-sizing:border-box;padding-right:22px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparParcela(3)" title="Excluir esta parcela" style="position:absolute;right:7px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-agwrap" id="pb-agwrap-4" style="flex:1;min-width:78px;position:relative;display:none;align-items:center"><input type="text" id="pb-agenc-4" placeholder="4º" style="width:100%;box-sizing:border-box;padding-right:22px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparParcela(4)" title="Excluir esta parcela" style="position:absolute;right:7px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-agwrap" id="pb-agwrap-5" style="flex:1;min-width:78px;position:relative;display:none;align-items:center"><input type="text" id="pb-agenc-5" placeholder="5º" style="width:100%;box-sizing:border-box;padding-right:22px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparParcela(5)" title="Excluir esta parcela" style="position:absolute;right:7px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <button type="button" id="pb-agenc-add" onclick="pbAddAgenc()" class="btn btn-outline btn-sm" style="white-space:nowrap;flex:0 0 auto"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">add</span> agenciamento</button>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:5px">Total agenciamento: <strong id="pb-agenc-total" style="color:var(--accent)">0,00%</strong></div>
    </div>
    <div class="fg">
      <label>Competência recebimento de AG <span style="color:var(--muted);font-weight:400;text-transform:none">(informe o mês/ano de cada parcela)</span></label>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <div class="pb-ftwrap" id="pb-ftwrap-1" style="flex:1;min-width:140px;position:relative;display:flex;align-items:center"><input type="month" id="pb-fatura-1" title="1ª parcela" style="width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:18px;padding:9px 46px 9px 14px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparCompetencia(1)" title="Limpar competência" style="position:absolute;right:32px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-ftwrap" id="pb-ftwrap-2" style="flex:1;min-width:140px;position:relative;display:none;align-items:center"><input type="month" id="pb-fatura-2" title="2ª parcela" style="width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:18px;padding:9px 46px 9px 14px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparCompetencia(2)" title="Limpar competência" style="position:absolute;right:32px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-ftwrap" id="pb-ftwrap-3" style="flex:1;min-width:140px;position:relative;display:none;align-items:center"><input type="month" id="pb-fatura-3" title="3ª parcela" style="width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:18px;padding:9px 46px 9px 14px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparCompetencia(3)" title="Limpar competência" style="position:absolute;right:32px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-ftwrap" id="pb-ftwrap-4" style="flex:1;min-width:140px;position:relative;display:none;align-items:center"><input type="month" id="pb-fatura-4" title="4ª parcela" style="width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:18px;padding:9px 46px 9px 14px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparCompetencia(4)" title="Limpar competência" style="position:absolute;right:32px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <div class="pb-ftwrap" id="pb-ftwrap-5" style="flex:1;min-width:140px;position:relative;display:none;align-items:center"><input type="month" id="pb-fatura-5" title="5ª parcela" style="width:100%;box-sizing:border-box;border:1px solid var(--border);border-radius:18px;padding:9px 46px 9px 14px" oninput="pbParcelasTotal()" onchange="pbParcelasTotal()"/><span onclick="pbLimparCompetencia(5)" title="Limpar competência" style="position:absolute;right:32px;cursor:pointer;color:#c0392b;font-weight:800;font-size:14px;line-height:1">×</span></div>
        <button type="button" id="pb-fatura-add" onclick="pbAddCompetencia()" class="btn btn-outline btn-sm" style="white-space:nowrap;flex:0 0 auto"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">add</span> competência</button>
      </div>
      <div style="font-size:12px;color:var(--muted);margin-top:5px">Total de competências: <strong id="pb-fatura-total" style="color:var(--accent)">0</strong></div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-start">
      <div class="fg" style="flex:1;min-width:180px;max-width:220px">
        <label>% de comissão</label>
        <div style="position:relative;display:flex;align-items:center">
          <input id="pb-percentual_comissao" type="text" inputmode="decimal" placeholder="Ex: 7,5" style="padding:9px 26px 9px 14px;width:100%;border:1px solid var(--border);border-radius:18px" oninput="pbMaskPercent(this)"/>
          <span style="position:absolute;right:10px;color:var(--muted);font-size:13px;pointer-events:none">%</span>
        </div>
      </div>
      <div class="fg" style="flex:1;min-width:220px;max-width:280px">
        <label>Competência Recebimento de Comissão</label>
        <input type="month" id="pb-comp_receb_vitalicio" style="border:1px solid var(--border);border-radius:18px;padding:9px 14px;width:100%"/>
      </div>
    </div>
    <textarea id="pb-agenc_parcelas" style="display:none"></textarea>
    <textarea id="pb-fatura_parcelas" style="display:none"></textarea>

    <div class="sec">Odonto conjugado</div>
    <div class="row2">
      <div class="fg"><label>Possui plano odontológico conjugado?</label><select id="pb-possui_odonto_conjugado"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg"><label>Se sim, descrever o nome do plano</label><textarea id="pb-odonto_conjugado_desc" rows="3" style="resize:vertical" placeholder="Descreva (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>
    <div class="sec">Movimentações e documentação</div>
    <div class="row2">
      <div class="fg"><label>Precisa enviar documentação p/ movimentações?</label><select id="pb-necessita_doc_mov"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg"><label>Se sim, quais documentos</label><textarea id="pb-doc_mov_desc" rows="3" style="resize:vertical" placeholder="Liste os documentos (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Data corte para inclusão</label><textarea id="pb-data_corte_inclusao" rows="3" style="resize:vertical" placeholder="Descreva (pode usar Enter para quebrar linhas)"></textarea></div>
      <div class="fg"><label>Data corte para exclusão</label><textarea id="pb-data_corte_exclusao" rows="3" style="resize:vertical" placeholder="Descreva (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>
    <div class="fg"><label>Cópia do contrato e aditivos assinados</label><input id="pb-copia_contrato_aditivos" type="text" placeholder="Link/observação"/></div>
    <div class="row2">
      <div class="fg"><label>Possui relatório de sinistralidade?</label><select id="pb-possui_relatorio_sinistralidade"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg"><label>Caminho da pasta do cliente (rede)</label><input id="pb-caminho_pasta_rede" type="text"/></div>
    </div>

    <div class="sec">Analista</div>
    <div class="row2">
      <div class="fg"><label>Analista responsável</label><select id="pb-analista"><option value="">—</option></select></div>
    </div>
    <!-- Data de vencimento removida da tela, mas preservada (oculta) para não apagar o valor já salvo -->
    <input id="pb-data_vencimento" type="hidden"/>

    <div class="sec">Contatos e Acessos da Empresa (dia a dia)</div>
    <div class="row4" style="grid-template-columns:1.3fr .9fr 1.4fr 1fr 160px">
      <div class="fg"><label>Nome do Responsável</label><input id="pb-resp_nome" type="text"/></div>
      <div class="fg"><label>Cargo</label><input id="pb-resp_cargo" type="text"/></div>
      <div class="fg"><label>E-mail</label><input id="pb-resp_email" type="text"/></div>
      <div class="fg"><label>Telefone</label><input id="pb-resp_telefone" type="text"/></div>
      <div class="fg"><label>Data de Nascimento</label><input id="pb-data_nascimento" type="date"/></div>
    </div>
    <div style="margin-top:8px">
      <button type="button" class="btn btn-outline btn-sm" onclick="pbContatoDiaAdd()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">add</span> Novo contato</button>
    </div>
    <div id="pb-contatos-dia-list" style="display:flex;flex-direction:column;gap:8px;margin-top:8px"></div>
    <textarea id="pb-contatos_diaadia" style="display:none"></textarea>
    <div class="fg" style="margin-top:8px"><label>Acessos a Portais</label><textarea id="pb-acessos_portais" rows="2"></textarea></div>

    <div class="sec">Contatos e Acessos da Empresa (Renovação)</div>
    <div class="row4" style="grid-template-columns:1.3fr .9fr 1.4fr 1fr 160px">
      <div class="fg"><label>Nome do Responsável</label><input id="pb-resp_renov_nome" type="text"/></div>
      <div class="fg"><label>Cargo</label><input id="pb-resp_renov_cargo" type="text"/></div>
      <div class="fg"><label>E-mail</label><input id="pb-resp_renov_email" type="text"/></div>
      <div class="fg"><label>Telefone</label><input id="pb-resp_renov_telefone" type="text"/></div>
      <div class="fg"><label>Data de Nascimento</label><input id="pb-data_nascimento_renov" type="date"/></div>
    </div>
    <div style="margin-top:8px">
      <button type="button" class="btn btn-outline btn-sm" onclick="pbContatoRenovAdd()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">add</span> Novo contato</button>
    </div>
    <div id="pb-contatos-renov-list" style="display:flex;flex-direction:column;gap:8px;margin-top:8px"></div>
    <textarea id="pb-contatos_renov" style="display:none"></textarea>

    <div class="sec">Faturamento</div>
    <div class="fg"><label>E-mails para faturamento <span style="color:var(--muted);font-weight:400;text-transform:none">(separe por ; ou vírgula)</span></label><textarea id="pb-emails_faturamento" rows="2" style="resize:vertical"></textarea></div>
    <div class="sec">Sinistralidade</div>
    <div class="fg"><label>E-mails para sinistralidade <span style="color:var(--muted);font-weight:400;text-transform:none">(separe por ; ou vírgula)</span></label><textarea id="pb-emails_sinistralidade" rows="2" style="resize:vertical" placeholder="ex: sinistralidade@empresa.com.br; contato@empresa.com.br"></textarea></div>

    <div class="sec">Executivo Responsável na Operadora</div>
    <div class="row3" style="grid-template-columns:1.4fr 1.6fr 1fr">
      <div class="fg"><label>Nome do Responsável</label><input id="pb-executivo_operadora" type="text"/></div>
      <div class="fg"><label>E-mail</label><input id="pb-executivo_operadora_email" type="text"/></div>
      <div class="fg"><label>Telefone</label><input id="pb-executivo_operadora_telefone" type="text"/></div>
    </div>
    <div style="margin-top:8px">
      <button type="button" class="btn btn-outline btn-sm" onclick="pbExecAdd()"><span class="material-symbols-rounded" style="font-size:16px;vertical-align:middle">add</span> Novo contato</button>
    </div>
    <div id="pb-execs-list" style="display:flex;flex-direction:column;gap:8px;margin-top:8px"></div>
    <textarea id="pb-executivos_extras" style="display:none"></textarea>
    <!-- "Contato da empresa (dia a dia)" virou redundante com "Nome do Responsável" — campo
         removido do form mas mantido como hidden pra não quebrar JS/API que referenciam o id. -->
    <input type="hidden" id="pb-contato_dia_a_dia" value=""/>

    <div class="sec">Outros</div>
    <div class="row2">
      <div class="fg"><label>Possui agregados?</label><select id="pb-possui_agregados"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg"><label>Se sim, descrever o grau de parentesco</label><textarea id="pb-agregados_desc" rows="3" style="resize:vertical" placeholder="Descreva (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Idade limite para inclusão de filhos</label><textarea id="pb-idade_limite_filho" rows="2" style="resize:vertical" placeholder="Descreva (pode usar Enter para quebrar linhas)"></textarea></div>
      <div class="fg"><label>Filhos excluídos automaticamente na idade limite?</label><select id="pb-exclui_filho_auto"><option value="">—</option><option>Sim</option><option>Não</option></select></div>
      <div class="fg"><label>Prazo para inclusão sem carências</label><textarea id="pb-prazo_inclusao_sem_carencia" rows="2" style="resize:vertical" placeholder="Descreva (pode usar Enter para quebrar linhas)"></textarea></div>
    </div>
    <div class="fg"><label>Política de benefícios</label><textarea id="pb-politica_beneficios" rows="2"></textarea></div>
    <div class="fg"><label>Particularidades adicionais</label><textarea id="pb-particularidades" rows="2"></textarea></div>
    <div class="fg"><label>Observações</label><textarea id="pb-observacao" rows="2"></textarea></div>

    <div class="sec">Notificação por e-mail (após salvar a passagem)</div>
    <div class="fg" style="padding:10px 14px;background:rgba(0,191,179,.06);border-left:3px solid var(--accent);border-radius:6px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:600">
        <input type="checkbox" id="pb-notificar_email_passagem" onchange="pbToggleNotifEmail()" checked style="width:16px;height:16px;cursor:pointer"/>
        Quero notificar contatos por e-mail sobre a conclusão dessa passagem de bastão
      </label>
      <div id="pb-notif-email-box" style="display:block;margin-top:10px">
        <label style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:600">E-mails para notificar <span style="font-weight:400;text-transform:none;letter-spacing:0">(separe por ; ou vírgula)</span></label>
        <input type="text" id="pb-destinatarios_email_passagem" placeholder="rh@empresa.com.br; financeiro@empresa.com.br" value="beneficios3@interweg.com.br; beneficios1@interweg.com.br; comercialbeneficios@interweg.com.br; secretariadiretoria@interweg.com.br; adm1@interweg.com.br; adm2@interweg.com.br; adm3@interweg.com.br" style="margin-top:4px"/>
        <small style="display:block;margin-top:4px;font-size:11px;color:var(--muted);font-style:italic">* Depois de salvar, o botão <strong>Enviar e-mail de notificação</strong> aparece pra você revisar o assunto/corpo antes de mandar.</small>
        <div id="pb-email-enviado-info" style="display:none;margin-top:8px;padding:6px 10px;background:rgba(76,175,80,.12);border-left:3px solid #4caf50;border-radius:4px;font-size:12px">
          <span class="material-symbols-rounded" style="font-size:14px;vertical-align:middle;color:#2e7d32">check_circle</span>
          <span style="color:#2e7d32;font-weight:600">E-mail já enviado em </span><span id="pb-email-enviado-quando"></span>
        </div>
      </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:18px">
      <button class="btn btn-outline" id="pb-btn-enviar-email" style="display:none;border-color:var(--accent);color:var(--accent)" onclick="pbAbrirEnvioEmail()"><span class="material-symbols-rounded" style="font-size:17px">mail</span> Enviar e-mail de notificação</button>
      <button class="btn btn-outline" id="pb-btn-salvar" data-edit-only onclick="pbSalvar(this,'rascunho')"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar rascunho</button>
      <button class="btn btn-primary" id="pb-btn-concluir" data-edit-only onclick="pbSalvar(this,'cliente')"><span class="material-symbols-rounded" style="font-size:17px">check_circle</span> Concluir (vira cliente)</button>
    </div>
  </div>
  </div>
</main>

<!-- ───── MODAL: REVISAR E ENVIAR E-MAIL DE NOTIFICAÇÃO ───── -->
<div class="ov" id="mod-pb-email">
  <div class="modal" style="width:920px;max-width:96vw;max-height:92vh;overflow-y:auto">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">mail</span> Enviar e-mail de notificação</div>
    <div class="modal-sub">Revise o conteúdo antes de enviar. A assinatura é puxada automaticamente do analista logado.</div>
    <input type="hidden" id="pb-em-empresa-id"/>

    <div class="fg"><label>Para</label>
      <input type="text" id="pb-em-para" placeholder="rh@empresa.com.br; financeiro@empresa.com.br"/>
      <small style="font-size:11px;color:var(--muted);font-style:italic">Separe múltiplos e-mails por ; ou vírgula.</small>
    </div>
    <div class="fg"><label>Assunto</label>
      <input type="text" id="pb-em-assunto"/>
    </div>
    <div class="fg"><label>Mensagem</label>
      <textarea id="pb-em-corpo" rows="10" style="font-family:inherit;line-height:1.5"></textarea>
      <small style="font-size:11px;color:var(--muted);font-style:italic">Edite livremente. A assinatura abaixo é adicionada automaticamente no final.</small>
    </div>

    <?php
      $sigNome  = trim($_SESSION['user_nome'] ?? '') ?: 'Equipe Interweg';
      $sigCargo = (($_SESSION['user_tipo'] ?? '') === 'novos_negocios')
                    ? 'Analista de Placement - Novos Negócios'
                    : 'Analista de Benefícios';
    ?>
    <div class="fg"><label>Assinatura (editável)</label>
      <div style="border:1px solid var(--border);border-radius:10px;padding:14px 16px;background:var(--bg-card)">
        <div style="font-size:13px;color:var(--text);margin-bottom:8px">Atenciosamente,</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <input id="pb-em-sig-nome" value="<?= htmlspecialchars($sigNome, ENT_QUOTES, 'UTF-8') ?>" placeholder="Nome"
                 style="flex:1;min-width:180px;font-weight:700;color:#0a8a8d;font-size:15px;padding:6px 8px;border:1px solid var(--border);border-radius:7px"/>
          <input id="pb-em-sig-cargo" value="<?= htmlspecialchars($sigCargo, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cargo"
                 style="flex:1;min-width:180px;color:#555;font-size:13px;padding:6px 8px;border:1px solid var(--border);border-radius:7px"/>
        </div>
        <div style="font-size:12px;color:#888;margin-top:8px">Tel. (31) 3508-6000 &nbsp;|&nbsp; interweg.com.br &nbsp;|&nbsp; Av. Raja Gabáglia, 4.987 &ndash; 302 &ndash; Belo Horizonte/MG</div>
        <img src="assinatura_interweg.png" alt="Interweg Seguros e Benefícios" style="max-width:420px;width:100%;height:auto;display:block;margin-top:10px"/>
      </div>
      <small style="font-size:11px;color:var(--muted);font-style:italic">Nome e cargo podem ser editados antes de enviar. Contato e banner são fixos.</small>
    </div>

    <div class="mfooter" style="display:flex;justify-content:flex-end;gap:8px">
      <button class="btn btn-outline" onclick="pbCancelarEnvio()"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" id="pb-em-btn-enviar" onclick="pbEnviarEmail(this)"><span class="material-symbols-rounded" style="font-size:17px">send</span> Enviar</button>
    </div>
  </div>
</div>

<!-- ───── MODAL: CONFIRMAÇÃO DE ENVIO ───── -->
<div class="ov" id="mod-pb-confirm">
  <div class="modal" style="width:480px;max-width:94vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">mark_email_read</span> Confirmar envio</div>
    <div class="modal-sub">Tem certeza que deseja enviar o e-mail de notificação?</div>
    <div style="margin:10px 0;padding:12px 14px;background:var(--bg);border:1px solid var(--border);border-radius:8px;font-size:13px">
      <div style="margin-bottom:6px"><strong>Para:</strong> <span id="pb-confirm-para" style="word-break:break-all"></span></div>
      <div><strong>Assunto:</strong> <span id="pb-confirm-assunto"></span></div>
    </div>
    <div class="mfooter" style="display:flex;justify-content:flex-end;gap:8px">
      <button class="btn btn-outline" onclick="closeModal('mod-pb-confirm')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Não</button>
      <button class="btn btn-primary" onclick="pbConfirmarEnvio(this)"><span class="material-symbols-rounded" style="font-size:17px">send</span> Sim, enviar</button>
    </div>
  </div>
</div>

<!-- ───── MODAL: HISTÓRICO DE EDIÇÕES DA PASSAGEM ───── -->
<div class="ov" id="mod-pb-hist">
  <div class="modal" style="width:900px;max-width:96vw;max-height:88vh;overflow-y:auto">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">history</span> Histórico de Edições</div>
    <div class="modal-sub">Cada linha é uma versão (snapshot) da passagem naquele momento.</div>
    <div id="pb-hist-empresa" style="font-weight:600;color:var(--accent);margin-bottom:12px"></div>
    <div id="pb-hist-content" style="max-height:60vh;overflow-y:auto"></div>
    <div class="mfooter">
      <button class="btn btn-outline" onclick="closeModal('mod-pb-hist')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Fechar</button>
    </div>
  </div>
</div>

<!-- ───── MODAL: EDITAR (somente campos de Empresa/Contrato) ───── -->
<div class="ov" id="mod-pb-edit" onclick="if(event.target===this)closeModal('mod-pb-edit')">
  <div class="modal" style="width:920px;max-width:96vw;max-height:90vh;overflow-y:auto">
    <div class="modal-title" id="pe-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">edit</span> Editar Empresa / Contrato</div>
    <div class="modal-sub">Base mestre — campos do contrato. (Os demais campos da passagem não são alterados aqui.)</div>
    <input type="hidden" id="pe-id"/>

    <div class="sec">Identificação do contrato</div>
    <div class="row3">
      <div class="fg"><label>Entrada/Origem</label>
        <select id="pe-origem">
          <option value="">—</option>
          <option value="Interweg MG">Belo Horizonte</option>
          <option value="Interweg SP">São Paulo</option>
          <option value="Interweg JF">Juiz de Fora</option>
          <option value="Finlândia">Finlândia</option>
          <option value="Fred">Fred</option>
          <option value="Parceiro - Bernardo">Parceiro - Bernardo</option>
          <option value="Parceiro - Elmo">Parceiro - Elmo</option>
        </select>
      </div>
      <div class="fg"><label>Ramo</label>
        <select id="pe-ramo"><option value="">—</option><option>Saúde</option><option>Odonto</option><option>Saúde/Odonto</option><option>Vida em Grupo</option><option>Vida Individual</option><option>Seguro Estagiário</option></select>
      </div>
      <div class="fg"><label>Operadora / Seguradora</label><select id="pe-seguradora"><option value="">—</option></select></div>
    </div>
    <div class="row3">
      <div class="fg"><label>Número do contrato</label><input id="pe-numero_contrato" type="text"/></div>
      <div class="fg"><label>CNPJ</label><input id="pe-cnpj" type="text" maxlength="18" placeholder="00.000.000/0000-00"/></div>
      <div class="fg"><label>Estipulante / Subestipulante</label>
        <select id="pe-tipo_estipulacao"><option value="">—</option><option>Estipulante</option><option>Subestipulante</option></select>
      </div>
    </div>
    <div class="row3">
      <div class="fg"><label>Tipo</label>
        <select id="pe-tipo_passagem">
          <option value="">— Selecione —</option>
          <option value="Seguro Novo">Seguro Novo</option>
          <option value="Seguro Novo de Cliente Interweg">Seguro Novo de Cliente Interweg</option>
          <option value="Nomeação">Nomeação</option>
          <option value="Renovação">Renovação</option>
        </select>
      </div>
    </div>

    <div class="sec">Empresa</div>
    <div class="row3">
      <div class="fg"><label>Grupo *</label><input id="pe-grupo" type="text"/></div>
      <div class="fg"><label>Empresa *</label><input id="pe-nome" type="text"/></div>
      <div class="fg"><label>Porte da empresa</label>
        <select id="pe-porte"><option value="">—</option><option>Até 30 vidas</option><option>Entre 30 e 99 vidas</option><option>Entre 100 e 199 vidas</option><option>Acima de 200 vidas</option></select>
      </div>
    </div>

    <div class="sec">Analista e datas</div>
    <div class="row3">
      <div class="fg"><label>Analista responsável</label><select id="pe-analista"><option value="">—</option></select></div>
      <div class="fg"><label>Data de emissão de faturamento</label><input id="pe-data_emissao" type="date"/></div>
      <div class="fg"><label>Data de vencimento</label><input id="pe-data_vencimento" type="date"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>Início de vigência</label><input id="pe-inicio_vigencia" type="date"/></div>
      <div class="fg"><label>Número de vidas</label><input id="pe-numero_vidas" type="number" min="0"/></div>
    </div>
    <div class="row2">
      <div class="fg"><label>% de comissão</label><input id="pe-percentual_comissao" type="text" placeholder="Ex: 7,5"/></div>
      <div class="fg"><label>E-mails para faturamento (separe por ; ou vírgula)</label><input id="pe-emails_faturamento" type="text"/></div>
    </div>

    <div class="sec">Executivo Responsável na Operadora</div>
    <div class="row3">
      <div class="fg"><label>Nome do Responsável</label><input id="pe-resp_nome" type="text"/></div>
      <div class="fg"><label>E-mail</label><input id="pe-resp_email" type="text"/></div>
      <div class="fg"><label>Telefone</label><input id="pe-resp_telefone" type="text"/></div>
    </div>
    <div class="fg"><label>Observações</label><textarea id="pe-observacao" rows="3"></textarea></div>

    <div class="modal-actions" style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:16px;flex-wrap:wrap">
      <button class="btn btn-danger" id="pe-btn-excluir" onclick="pbExcluir()" title="Apaga essa passagem de bastão (com confirmação)"><span class="material-symbols-rounded" style="font-size:17px">delete</span> Excluir</button>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-outline" onclick="closeModal('mod-pb-edit')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
        <button class="btn btn-outline" style="border-color:var(--accent);color:var(--accent)" onclick="pbVerNaIntegra()" title="Abre o formulário completo com TODOS os campos editáveis"><span class="material-symbols-rounded" style="font-size:17px">open_in_full</span> Visualizar / Editar na íntegra</button>
        <button class="btn btn-primary" id="pe-btn-salvar" onclick="pbSalvarEdit(this)"><span class="material-symbols-rounded" style="font-size:17px">save</span> Salvar alterações (cadastro)</button>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/_footer.php'; ?>
