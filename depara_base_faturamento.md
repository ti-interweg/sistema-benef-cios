# De/Para — BASE AUTOMATIZAÇÃO BENEFÍCIOS → Sistema Interweg

Planilha analisada: **BASE AUTOMATICAÇÕ BENEFÍCIOS.xlsx** · 1.391 linhas

Esse documento lista as divergências entre os dados da planilha e o que o sistema (módulo Faturamento) aceita. Por favor, preencher a coluna "Converter para" para cada caso.

---

## 1. Mapeamento de colunas (planilha → sistema)

| Coluna na planilha | Campo no sistema (faturamento) | Observação |
|---|---|---|
| ORIGEM | origem | Precisa normalização (ver §3) |
| RAMO | ramo | Precisa normalização (ver §4) |
| SEGURADORA | seguradora | Há duplicidades por espaço/quebra de linha (ver §5) |
| Apólice/Contrato | numero_contrato | OK |
| CNPJ | cnpj | Sistema valida pelo algoritmo da Receita |
| EMPRESA | empresa | OK |
| RAZÃO SOCIAL | (não existe campo separado) | **Decisão**: descartar OU concatenar com EMPRESA OU criar campo? |
| GRUPO | grupo | OK |
| ANALISTA | analista_nome | Precisa cadastrar analistas faltantes (ver §6) |
| COMPETÊNCIA | competencia | **Vazio na planilha em todas as linhas** |
| EMISSÃO | data_emissao | **Planilha tem só o dia (1, 5, 10...)**, sistema espera data completa |
| DATA VENCIMENTO | data_vencimento | **Planilha tem regra textual** ("10 mesmo mês"), sistema espera data |
| DATA DE ENVIO | data_envio | Vazio na planilha |
| VIDAS | numero_vidas | **Vazio na planilha em todas as linhas** |
| PRÊMIO LÍQUIDO | premio_liquido | **Vazio na planilha em todas as linhas** |
| COMISSÃO | percentual_comissao | Planilha está em decimal (0,1 = 10%) — sistema usa %. Converter? |
| E-MAIL | emails_envio | Tem e-mails com aspas e barras inválidas — limpar |

---

## 2. Campos obrigatórios do sistema que a planilha NÃO tem

Para gerar um faturamento completo no sistema, esses campos precisam vir de algum lugar:

- **Competência** (ex.: "Mai/26") — qual competência usar para essa carga? Mês atual? Vamos importar histórico?
- **Data de emissão** (data completa) — gerar a partir do dia + competência?
- **Data de vencimento** (data completa) — gerar a partir da regra "X mesmo mês" + competência?
- **Número de vidas** — vem de outra base?
- **Prêmio líquido** — vem de outra base?

> **Sugestão**: importar essa planilha como **base mestre de contratos** (não como faturamentos prontos), e a cada mês gerar os faturamentos a partir dela preenchendo competência, vidas e prêmio.

---

## 3. ORIGEM — valores na planilha vs valores aceitos pelo sistema

**Sistema aceita apenas**: `Interweg MG`, `Interweg JF`, `Interweg SP`

| Valor na planilha | Quantidade | Converter para |
|---|---|---|
| INTERWEG | 1.240 | ❓ (Interweg MG?) |
| INTERWEG \| FINLÂNDIA | 44 | ❓ |
| FINLÂNDIA | 44 | ❓ |
| INTERWEG SP | 34 | Interweg SP |
| INTERWEG JF | 17 | Interweg JF |
| ` FINLÂNDIA` (com espaço) | 11 | ❓ |
| VALENTE JUNIOR | 1 | ❓ |

---

## 4. RAMO — valores na planilha vs valores aceitos pelo sistema

**Sistema aceita apenas**: `Saúde`, `Odonto`

| Valor na planilha | Quantidade | Converter para |
|---|---|---|
| SAÚDE | 845 | Saúde |
| ODONTO | 313 | Odonto |
| SAUDE (sem acento) | 156 | Saúde |
| SAÚDE (com espaço final) | 21 | Saúde |
| SAUDE/DENTAL | 15 | ❓ (criar combo? duplicar linha?) |
| ODONTO (com espaço final) | 14 | Odonto |
| DENTAL | 13 | Odonto |
| SAÚDE/DENTAL | 13 | ❓ |
| SAÚDE/ODONTO | 1 | ❓ |

---

## 5. SEGURADORA — limpezas sugeridas

Estes precisam ser revistos (algumas duplicidades por espaços/quebra de linha):

- `AMIL` (107) e `AMIL ` com espaço final (2) → unificar como **AMIL**
- `HAPVIDA` (27) e `HAPVIDA ` com espaço final (1) → unificar como **HAPVIDA**
- `HAPVIDA / GNDI` (23) → unificar com HAPVIDA ou manter separado?
- `GNDI` (11) → manter ou unificar com HAPVIDA?
- `CLINIPAM GNDISUL \n` (1) → tirar o `\n`, virar **CLINIPAM GNDISUL**

---

## 6. ANALISTA — pessoas na planilha vs cadastro do sistema

**Cadastro atual no sistema**: Anna Luiza, Bruna, Pâmela, Enf. Carolina, Enf. Talita, Ângela, Enf. Isabella, Nayara

| Analista na planilha | Quantidade | Está no sistema? |
|---|---|---|
| PAMELA | 360 | Sim (Pâmela) — cuidar do acento |
| NAYARA | 313 | Sim |
| GABRIELA | 249 | **NÃO — precisa cadastrar** |
| BRUNA | 234 | Sim |
| ANGELA | 164 | Sim (Ângela) — cuidar do acento |
| ANNA LUIZA | 55 | Sim |
| JOYCE | 16 | **NÃO — precisa cadastrar** |

> Carolina, Talita e Isabella estão no cadastro mas não aparecem na planilha — confirmar se isso está correto.

---

## 7. Decisões pendentes (resumo para responder)

1. Onde gravar essa base? (sugestão: tabela nova `base_contratos`, e gerar faturamentos a partir dela mês a mês)
2. Como preencher Competência / Data de emissão / Data de vencimento / Vidas / Prêmio?
3. De/para de ORIGEM (especialmente FINLÂNDIA, INTERWEG \| FINLÂNDIA, VALENTE JUNIOR)
4. Como tratar RAMO duplo (SAÚDE/DENTAL, SAÚDE/ODONTO)
5. SEGURADORA: unificar GNDI com HAPVIDA?
6. Cadastrar Joyce e Gabriela?
7. Campo "Razão Social" da planilha — descartar ou criar campo extra?
