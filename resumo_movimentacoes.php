<?php
$currentPage = 'resumo_movimentacoes';
$pageTitle   = 'Resumo Mensal de Movimentações — Interweg Benefícios';
$pageScripts = ['assets/resumo_movimentacoes.js?v=' . time()];

require_once __DIR__ . '/_header.php';
// Financeiro não envia resumos; demais perfis autenticados acessam.
?>

<style>
  .rm-tabs{display:flex;gap:6px;border-bottom:1px solid var(--border);margin:6px 0 22px;flex-wrap:wrap}
  .rm-tab{display:inline-flex;align-items:center;gap:7px;padding:11px 18px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:700;color:var(--muted);border-bottom:3px solid transparent;margin-bottom:-1px;border-radius:8px 8px 0 0;transition:color .15s ease,background .15s ease}
  .rm-tab:hover{color:var(--text);background:rgba(0,191,179,.05)}
  .rm-tab.active{color:var(--accent);border-bottom-color:var(--accent)}
  .rm-toolbar{display:flex;flex-wrap:wrap;align-items:flex-end;gap:14px;padding:18px 20px;background:var(--bg-card);border:1px solid var(--border);border-radius:16px;box-shadow:0 1px 2px rgba(16,24,40,.04),0 10px 30px -18px rgba(16,24,40,.12);margin-bottom:20px}
  .rm-fg{display:flex;flex-direction:column;gap:6px}
  .rm-fg label{font-size:11px;font-weight:800;letter-spacing:.4px;text-transform:uppercase;color:var(--muted)}
  .rm-fg input,.rm-fg select{background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:9px 12px;font-size:13px;color:var(--text);height:40px;outline:none;transition:border-color .15s ease}
  .rm-fg input:focus,.rm-fg select:focus{border-color:var(--accent)}
  .rm-cli{display:flex;align-items:center;gap:14px;padding:15px 18px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;margin-bottom:12px;box-shadow:0 1px 2px rgba(16,24,40,.04);transition:box-shadow .2s ease,transform .2s ease}
  .rm-cli:hover{box-shadow:0 10px 28px -16px rgba(16,24,40,.22);transform:translateY(-1px)}
  .rm-cli-icon{width:40px;height:40px;border-radius:50%;background:rgba(0,191,179,.12);color:var(--accent);display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .rm-cli-info{flex:1;min-width:0}
  .rm-cli-nome{font-weight:700;font-size:14px;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .rm-cli-meta{display:flex;align-items:center;gap:8px;margin-top:4px;min-width:0}
  .rm-badge{flex-shrink:0;font-size:11px;font-weight:700;background:rgba(0,191,179,.10);color:var(--accent);padding:2px 9px;border-radius:999px;white-space:nowrap}
  .rm-emails{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
  .rm-noemail{color:#c0392b;font-weight:600}
  .rm-cli-btn{flex-shrink:0;white-space:nowrap;box-shadow:0 4px 12px -5px rgba(0,191,179,.55)}
</style>

<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title">Resumo Mensal de Movimentações</div>
      <div class="page-sub">Gere e envie o resumo das movimentações do período para validação do cliente.</div>
    </div>
  </div>

  <!-- Abas -->
  <div class="rm-tabs">
    <button class="rm-tab active" id="rm-tab-gerar" onclick="rmShowTab('gerar')"><span class="material-symbols-rounded" style="font-size:18px">summarize</span> Gerar resumos</button>
    <button class="rm-tab" id="rm-tab-hist" onclick="rmShowTab('hist')"><span class="material-symbols-rounded" style="font-size:18px">history</span> Histórico de envios</button>
  </div>

  <!-- ABA: GERAR -->
  <div id="rm-pane-gerar">
    <div class="rm-toolbar">
      <div class="rm-fg"><label>De</label><input type="date" id="rm-de"/></div>
      <div class="rm-fg"><label>Até</label><input type="date" id="rm-ate"/></div>
      <div class="rm-fg" style="min-width:210px"><label>Analista</label>
        <select id="rm-analista"><option value="">Todos os analistas</option></select>
      </div>
      <button class="btn btn-primary" onclick="rmGerar()" style="height:40px;white-space:nowrap"><span class="material-symbols-rounded" style="font-size:17px">summarize</span> Gerar resumos</button>
    </div>
    <div id="rm-clientes" class="cards" style="display:block">
      <div style="padding:40px;text-align:center;color:var(--muted)">Selecione o período e clique em "Gerar resumos".</div>
    </div>
  </div>

  <!-- ABA: HISTÓRICO -->
  <div id="rm-pane-hist" style="display:none">
    <div class="table-wrap">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)">
        <div style="font-size:13px;font-weight:700">Histórico de Envios</div>
        <button class="btn btn-outline btn-sm" onclick="rmHistorico()"><span class="material-symbols-rounded" style="font-size:16px">refresh</span> Atualizar</button>
      </div>
      <div style="overflow-x:auto;width:100%">
        <table class="iw-table" style="min-width:900px">
          <thead><tr>
            <th>#</th><th>Cliente</th><th>Competência</th><th>Destinatário</th>
            <th>Movimentações</th><th>Status</th><th>Usuário</th><th>Data/Hora</th>
          </tr></thead>
          <tbody id="rm-hist-tbody"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">—</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<!-- ────── MODAL: PRÉ-VISUALIZAÇÃO / ENVIO ────── -->
<div class="ov" id="mod-rm" onclick="if(event.target===this)closeModal('mod-rm')">
  <div class="modal" style="width:920px;max-width:96vw">
    <div class="modal-title"><span class="material-symbols-rounded" style="vertical-align:middle;color:var(--accent)">mail</span> Pré-visualização do Resumo</div>
    <div class="modal-sub" id="rm-modal-sub">—</div>

    <div class="fg" style="margin-top:10px"><label>Destinatário (e-mails de faturamento — separe por ; ou vírgula)</label>
      <input type="text" id="rm-dest" placeholder="contato@empresa.com.br"/>
    </div>
    <div class="fg"><label>Assunto</label>
      <input type="text" id="rm-assunto"/>
    </div>
    <div class="fg"><label>Corpo do e-mail (editável)</label>
      <div id="rm-corpo" contenteditable="true" style="border:1.5px solid var(--border);border-radius:8px;padding:14px 16px;min-height:300px;max-height:50vh;overflow:auto;background:#fff;color:#222;font-size:13px;line-height:1.5"></div>
    </div>

    <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px">
      <button class="btn btn-outline" onclick="closeModal('mod-rm')"><span class="material-symbols-rounded" style="font-size:17px">close</span> Cancelar</button>
      <button class="btn btn-primary" id="rm-btn-enviar" onclick="rmEnviar(this)"><span class="material-symbols-rounded" style="font-size:17px">send</span> Enviar Resumo Mensal</button>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
