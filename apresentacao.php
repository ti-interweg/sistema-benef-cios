<?php
$currentPage = 'apresentacao';
$pageTitle   = 'Apresentação de Orçamento — Interweg';
// Os scripts originais (apresentacao.js + apresentacao_ppt.js) ficam no /public_html/ raiz, não em /assets/
$pageScripts = ['apresentacao.js', 'apresentacao_ppt.js'];

require_once __DIR__ . '/_header.php';
if (!$isAdmin && !$isNovosNeg && !$isVisualizador) { header('Location: demandas.php'); exit; }
?>

<style>
  #apres-form .fg{margin-bottom:14px;gap:5px}
  #apres-form input,#apres-form select{padding:8px 11px;font-size:13px;border-radius:7px}
  #apres-form label{font-size:11px}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Apresentação de Orçamento</div>
      <div class="page-sub">Crie apresentações profissionais (PPT/PDF editável) para envio ao cliente</div>
    </div>
  </div>

  <!-- STEP 1: Formulário -->
  <div id="apres-form" class="table-wrap" style="padding:28px">

    <!-- Dados do Vendedor + Cliente -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px">
      <div>
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:16px">
          <span class="material-symbols-rounded">person</span> Dados do Vendedor
        </h3>
        <div class="fg"><label>Nome do Vendedor / Corretor</label><input id="ap-vendedor" type="text" placeholder="Ex: Luiza Marques"/></div>
        <div class="fg"><label>E-mail</label><input id="ap-email" type="email" placeholder="luiza@interweg.com.br"/></div>
        <div class="fg"><label>Telefone</label><input id="ap-tel" type="text" placeholder="(31) 97138-4328"/></div>
      </div>
      <div>
        <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:16px">
          <span class="material-symbols-rounded">business</span> Dados do Cliente
        </h3>
        <div class="fg"><label>Nome da Empresa / Cliente</label><input id="ap-cliente" type="text" placeholder="Ex: STUDIODWG"/></div>
        <div class="fg"><label>Tipo de Cotação</label>
          <select id="ap-tipo-cot">
            <option value="Saúde">Cotação Saúde</option>
            <option value="Odonto">Cotação Odonto</option>
            <option value="Saúde + Odonto">Cotação Saúde + Odonto</option>
          </select>
        </div>
        <div class="fg"><label>Quantidade de Vidas</label><input id="ap-vidas" type="number" placeholder="Ex: 3" min="1"/></div>
        <div class="fg"><label>Link da Cotação <span style="color:var(--muted);font-weight:400;text-transform:none">(Cotaço)</span></label><input id="ap-link" type="url" placeholder="https://app.cotaco.es/cotacoes/..."/></div>
      </div>
    </div>

    <!-- Plano Atual -->
    <div id="ap-plano-atual-bloco" style="position:relative;margin-bottom:24px;padding:16px;border:1px solid var(--border);border-radius:10px;background:rgba(0,191,179,.04)">
      <button type="button" onclick="apExcluirPlanoAtual()" title="Excluir bloco Plano Atual (cliente sem plano vigente)"
              style="position:absolute;top:10px;right:10px;background:transparent;border:none;color:var(--muted);cursor:pointer;padding:4px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center"
              onmouseover="this.style.color='var(--danger)';this.style.background='rgba(0,0,0,.04)'"
              onmouseout="this.style.color='var(--muted)';this.style.background='transparent'">
        <span class="material-symbols-rounded" style="font-size:20px">close</span>
      </button>
      <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-right:28px">
        <span class="material-symbols-rounded">history</span> Plano Atual (Vigente)
        <span style="font-size:12px;color:var(--muted);font-weight:400;text-transform:none">— o que o cliente tem hoje</span>
      </h3>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
        <div class="fg"><label>Operadora Atual</label><input id="ap-pa-operadora" type="text" placeholder="Ex: Unimed BH"/></div>
        <div class="fg"><label>Plano Atual</label><input id="ap-pa-plano" type="text" placeholder="Ex: Alfa 800"/></div>
        <div class="fg"><label>Modalidade Atual</label><input id="ap-pa-modalidade" type="text" placeholder="Ex: Saúde PME"/></div>
        <div class="fg"><label>Acomodação Atual</label>
          <select id="ap-pa-acomodacao">
            <option value="">Selecione...</option>
            <option>Enfermaria</option><option>Apartamento</option><option>Ambos</option>
          </select>
        </div>
        <div class="fg"><label>Coparticipação Atual</label>
          <select id="ap-pa-coparticipacao">
            <option value="">Selecione...</option>
            <option>Sim</option><option>Não</option><option>Parcial</option>
          </select>
        </div>
        <div class="fg"><label>Valor Mensal Atual (R$)</label><input id="ap-valor-vigente" type="text" placeholder="Ex: 4.850,00"/></div>
      </div>
    </div>

    <!-- Placeholder mostrado quando o bloco "Plano Atual (Vigente)" é excluído -->
    <div id="ap-plano-atual-placeholder" style="display:none;margin-bottom:24px;padding:14px 18px;border:1px dashed var(--border);border-radius:10px;background:rgba(0,191,179,.04);justify-content:space-between;align-items:center;gap:12px">
      <span style="color:var(--muted);font-size:13px;display:inline-flex;align-items:center;gap:6px">
        <span class="material-symbols-rounded" style="font-size:16px">history_toggle_off</span>
        Bloco "Plano Atual (Vigente)" excluído desta apresentação.
      </span>
      <button type="button" class="btn btn-outline btn-sm" onclick="apRestaurarPlanoAtual()" style="border-color:#00bfb3;color:#00bfb3">
        <span class="material-symbols-rounded" style="font-size:14px">undo</span> Restaurar
      </button>
    </div>

    <!-- Planos cotados -->
    <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:16px">
      <span class="material-symbols-rounded">table_chart</span> Planos Cotados
      <span style="font-size:12px;color:var(--muted);font-weight:400;text-transform:none">(adicione as opções)</span>
    </h3>
    <div id="ap-planos-container"></div>
    <button class="btn btn-outline" onclick="apAddPlano()" style="margin-top:12px">
      <span class="material-symbols-rounded" style="font-size:16px">add</span> Adicionar Plano
    </button>

    <!-- Importar PDF -->
    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
      <h3 style="color:var(--accent);display:flex;align-items:center;gap:8px;margin-bottom:16px">
        <span class="material-symbols-rounded">upload_file</span> Ou importe de um PDF
      </h3>
      <p style="font-size:12px;color:var(--muted);margin-bottom:10px">Faça upload do PDF de cotação gerado pelo sistema e os dados serão preenchidos automaticamente.</p>
      <input id="ap-pdf-upload" type="file" accept=".pdf" style="font-size:12px;color:var(--muted)" onchange="apImportPDF(this)"/>
    </div>

    <div style="display:flex;gap:12px;margin-top:24px">
      <button class="btn btn-primary" onclick="apAbrirPreviewEditavel()" style="flex:1">
        <span class="material-symbols-rounded" style="font-size:17px">slideshow</span> Gerar Apresentação Editável
      </button>
    </div>
  </div>

  <!-- STEP 2: Preview -->
  <div id="apres-preview" style="display:none">
    <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
      <button class="btn btn-outline" onclick="apVoltarForm()"><span class="material-symbols-rounded" style="font-size:16px">arrow_back</span> Voltar e Editar</button>
      <button class="btn btn-primary" onclick="apPrint()"><span class="material-symbols-rounded" style="font-size:16px">print</span> Imprimir / Salvar PDF</button>
      <button class="btn btn-outline" onclick="apCopyLink()"><span class="material-symbols-rounded" style="font-size:16px">link</span> Copiar Link</button>
    </div>
    <div id="apres-render" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.3)"></div>
  </div>
</main>

<?php require_once __DIR__ . '/_footer.php'; ?>