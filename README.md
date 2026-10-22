# Sistema Interweg Benefícios

Sistema interno da área de Benefícios da **Interweg Seguros**, usado para controlar demandas, cotações, faturamento, sinistralidade, ações de saúde e o relacionamento com os clientes corporativos.

- Produção: https://interweg-beneficios.com.br
- Homologação: https://homolog.interweg-beneficios.com.br

## Tecnologias

- PHP 8 com MySQL (PDO)
- JavaScript puro (sem framework) e CSS próprio em `assets/`
- Apache com regras em `.htaccess` (URLs amigáveis e bloqueio de arquivos sensíveis)
- API da Anthropic (Claude) para leitura de documentos, OCR de sinistralidade, análises e apresentações
- Hospedagem na Hostinger (plano Premium)
- CI/CD com GitHub Actions e deploy por FTP

## Principais módulos

| Módulo | Arquivos principais |
|---|---|
| Login, senha e MFA (2º fator) | `login.php`, `mfa*.php`, `esqueci_senha.php`, `redefinir_senha.php`, `senha_politica.php` |
| Dashboard e indicadores | `dashboard.php`, `_indicadores_dados.php`, `indicadores_publico.php` |
| Demandas | `demandas.php`, `demandas_assistenciais.php`, `demandas_mercantil.php` |
| Cotações e fechamento | `controle_cotacao.php`, `cotacao_cliente.php`, `formcot.php`, `fechamento.php` |
| Faturamento | `faturamento.php`, `faturamento_automacao.php`, `ler_fatura_automacao.php`, `fat_email_lib.php` |
| Renovação | `renovacao.php` |
| Sinistralidade | `sinistralidade.php`, `ocr_sinistralidade.php`, `api_sinistralidade_email.php` |
| Saúde (ações, anamnese, programas, enfermagem) | `acoes_saude.php`, `anamnese.php`, `programas_saude.php`, `devolutiva_nutricional.php`, `api_enfermagem.php` |
| Base de clientes e cadastros | `base_clientes.php`, `cadastros.php`, `analistas.php` |
| Passagem de bastão | `passagem_bastao.php`, `api_passagem.php` |
| Atas de visita | `atas_visitas.php`, `api_ata_email.php` |
| Apresentações (PPT e IA) | `apresentacao.php`, `apresentacao_ia.php`, `apresentacao_ppt.js` |
| Auditoria | `auditoria.php`, `auditoria_admin.php`, `auditoria_indicadores.php` |
| Notificações e rotinas agendadas | `api_notificacoes.php`, `cron.php`, `cron_backup_faturamento.php` |

## Estrutura

```
/                     páginas PHP e APIs (api_*.php)
assets/               JavaScript e CSS de cada tela
formularios/          formulários de cotação (modelos .xlsx)
modelos/              modelo de apresentação (.pptx)
logos_seguradoras/    logos usadas nas apresentações
uploads/              arquivos enviados pelos usuários (não versionado)
.github/workflows/    pipeline de CI/CD
```

## Configuração

Os arquivos com senhas e chaves **não ficam no repositório**. Em um servidor novo:

1. Copie `config.example.php` para `config.php` e preencha os dados do banco MySQL e a chave da Anthropic.
2. Copie `chave_ia.example.php` para `chave_ia.php` e coloque a chave da Anthropic.
3. Em `mail_smtp.php`, a senha da caixa `noreply` é lida do arquivo `smtp_senha.txt`, que deve ficar **uma pasta acima** do `public_html`.
4. Rode `setup.php` uma única vez para criar as tabelas e depois troque as senhas dos usuários iniciais.

Arquivos que nunca devem ser enviados ao GitHub: `config.php`, `chave_ia.php`, `smtp_senha.txt`, `smtp_usuario.txt`, `.env`, arquivos `.sql`, `.log` e `.bak`, a pasta `homolog/` e o conteúdo de `uploads/`. O `.gitignore` já bloqueia todos eles.

## Branches e deploy

| Branch | Ambiente | Deploy |
|---|---|---|
| `homolog` | Homologação | Automático a cada push |
| `main` | Produção | Automático a cada push (ou merge de Pull Request) |

Fluxo recomendado:

1. Trabalhar na branch `homolog`.
2. Fazer push e testar em homologação.
3. Abrir um Pull Request de `homolog` para `main`.
4. Depois do merge, o pipeline publica em produção.

### O que o pipeline faz

**CI** (roda em todo push e Pull Request):
- Verifica a sintaxe de todos os arquivos PHP e JavaScript.
- Barra o envio se algum arquivo de segredo entrar no repositório.
- Procura chaves e senhas escritas no código.

**CD** (roda só em push para `homolog` ou `main`):
- Envia por FTP apenas os arquivos que mudaram.
- Não apaga arquivos que existem só no servidor (`config.php`, `chave_ia.php`, `uploads/`).
- Nunca envia `config.php`, `chave_ia.php`, `mail_smtp.php` e `importar.php`.
- Grava um `versao.txt` no servidor com o commit publicado.

### Configuração do GitHub

Em **Settings > Secrets and variables > Actions**:

| Secret | Uso |
|---|---|
| `FTP_SERVER_PROD`, `FTP_USERNAME_PROD`, `FTP_PASSWORD_PROD` | FTP de produção |
| `FTP_SERVER_HOMOLOG`, `FTP_USERNAME_HOMOLOG`, `FTP_PASSWORD_HOMOLOG` | FTP de homologação |
| `FTP_DIR_PROD`, `FTP_DIR_HOMOLOG` | Pasta de destino no FTP, sempre terminando com `/` (ex.: `public_html/`) |

Opcionais (aba Variables): `SITE_URL_PROD` e `SITE_URL_HOMOLOG`, usados para testar se o site responde depois do deploy.

## Comandos do dia a dia

```bash
git checkout homolog
git pull
# editar os arquivos
git add .
git commit -m "Descreva a mudança"
git push
```

Para voltar uma versão publicada:

```bash
git revert <commit>
git push
```

## Segurança

- Repositório **privado**: o código contém regras de negócio e e-mails internos.
- Se uma senha ou chave for enviada ao GitHub por engano, troque a credencial imediatamente. Apagar o arquivo não basta, porque ele continua no histórico.
- O `.htaccess` bloqueia o acesso direto a `config.php`, `db.php`, `mail_smtp.php`, `chave_ia.php` e a arquivos `.sql`, `.log`, `.md` e `.bak`.

## Responsável

Área de Tecnologia da Interweg Seguros.
