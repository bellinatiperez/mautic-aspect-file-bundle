# Sistema de Gestão de Lotes AspectFile

## Visão Geral

O Sistema de Gestão de Lotes fornece uma interface web completa para gerenciar e monitorar batches de processamento de arquivos AspectFile no Mautic.

## Compatibilidade

✅ **Mautic 5.x** | ✅ **Mautic 6.x** | ✅ **Mautic 7.0+**

O sistema foi desenvolvido usando as melhores práticas do Mautic 5+ e é totalmente compatível com todas as versões modernas do Mautic. Veja [COMPATIBILITY.md](COMPATIBILITY.md) para detalhes técnicos.

## Funcionalidades

### 📋 Listagem de Lotes

**URL:** `/s/aspectfile/batches`

**Recursos:**
- Lista todos os lotes criados pelas campanhas
- Paginação automática (20 lotes por página)
- Visualização rápida de status
- Contadores de leads por status (pendente, gerado, falho)
- Informações de arquivo (nome e tamanho)
- Data de criação

**Ações Disponíveis:**
- 👁️ **Ver Detalhes**: Visualizar informações completas do lote
- 🔄 **Reprocessar**: Resetar lote falhado para nova tentativa
- 🗑️ **Excluir**: Remover lote e todos os leads associados
- ⚡ **Processar Agora**: Processar todos os lotes pendentes manualmente

### 🔍 Visualização de Detalhes

**URL:** `/s/aspectfile/batch/{id}`

**Informações Exibidas:**

#### Informações do Lote
- ID do lote
- Status atual
- Schema utilizado
- Campanha e evento relacionados
- Bucket MinIO/S3
- Nome e caminho do arquivo
- Tamanho do arquivo
- Data de criação, geração e upload
- Mensagem de erro (se houver)

#### Estatísticas
- Total de leads
- Leads pendentes
- Leads gerados
- Leads falhados

#### Lista de Leads
- Tabela com todos os leads do lote
- Nome, email, ID do lead
- Status individual de cada lead
- Link direto para o contato no Mautic

## Status dos Lotes

| Status | Descrição | Cor |
|--------|-----------|-----|
| **PENDING** | Aguardando processamento | 🟡 Amarelo |
| **GENERATING** | Gerando arquivo | 🔵 Azul |
| **UPLOADING** | Enviando para MinIO/S3 | 🔵 Azul |
| **UPLOADED** | Concluído com sucesso | 🟢 Verde |
| **FAILED** | Falhou durante processamento | 🔴 Vermelho |

## Status dos Leads

| Status | Descrição |
|--------|-----------|
| **PENDING** | Lead aguardando processamento |
| **GENERATED** | Lead processado com sucesso |
| **FAILED** | Falha ao processar lead |

## Ações Principais

### 1. Reprocessar Lote

**Quando usar:**
- Após corrigir erro de configuração (schema, bucket, etc)
- Após resolver problema de rede/conectividade
- Para tentar novamente um lote que falhou

**O que acontece:**
1. Status do lote volta para `PENDING`
2. Todos os leads voltam para `PENDING`
3. Mensagem de erro é limpa
4. Informações de arquivo são resetadas
5. Lote fica disponível para reprocessamento

**Como usar:**
- Na lista de lotes: clique no botão 🔄 ao lado do lote
- Na página de detalhes: clique no botão "Reprocessar"
- Via comando: `php bin/console mautic:aspectfile:process`

### 2. Processar Agora

**Quando usar:**
- Para processar lotes pendentes imediatamente
- Em vez de esperar o cron job
- Para testar após correção de erros

**Como usar:**
- Na lista de lotes: clique em "Processar Agora"
- Via comando: `php bin/console mautic:aspectfile:process --limit=10`

### 3. Excluir Lote

**Quando usar:**
- Para remover lotes antigos ou desnecessários
- Para limpar lotes de teste
- Para liberar espaço no banco de dados

**Atenção:** ⚠️ Esta ação é **irreversível**! Todos os leads associados também serão removidos (CASCADE DELETE).

## Acesso ao Sistema

### Menu Mautic

O sistema adiciona dois itens ao menu "Channels" do Mautic:

1. **AspectFile Schemas** - Gestão de schemas
2. **AspectFile Batches** - Gestão de lotes (novo!)

### Rotas Disponíveis

| Rota | Método | Descrição |
|------|--------|-----------|
| `/s/aspectfile/batches` | GET | Lista de lotes |
| `/s/aspectfile/batches/{page}` | GET | Lista paginada |
| `/s/aspectfile/batch/{id}` | GET | Detalhes do lote |
| `/s/aspectfile/batch/{id}/reprocess` | POST | Reprocessar lote |
| `/s/aspectfile/batch/{id}/delete` | POST | Excluir lote |
| `/s/aspectfile/batches/process` | POST | Processar lotes pendentes |

## Monitoramento e Troubleshooting

### Verificar Lotes Pendentes

```bash
# Via SQL
ddev exec "mysql -e 'SELECT COUNT(*) FROM aspect_file_batches WHERE status = \"PENDING\"'"

# Via interface web
# Acesse: /s/aspectfile/batches
```

### Verificar Lotes Falhados

```bash
# Via SQL
ddev exec "mysql -e 'SELECT id, error_message, created_at FROM aspect_file_batches WHERE status = \"FAILED\" ORDER BY created_at DESC'"

# Via interface web
# Acesse: /s/aspectfile/batches e filtre visualmente pelos badges vermelhos
```

### Logs

Os logs são gravados em:
- `var/logs/dev-YYYY-MM-DD.php` (desenvolvimento)
- `var/logs/prod-YYYY-MM-DD.php` (produção)

Buscar por:
- `AspectFile: Processing batch`
- `AspectFile: Batch processing failed`
- `AspectFile: Resetting batch leads`

## Fluxo de Processamento

```
1. Campanha adiciona lead ao lote (PENDING)
   ↓
2. Comando processa lote (GENERATING)
   ↓
3. Arquivo gerado localmente
   ↓
4. Lote marcado como UPLOADING
   ↓
5. Upload para MinIO/S3
   ↓
6. Sucesso → UPLOADED
   Erro → PENDING (para retry automático)
```

## Tratamento de Erros

### Sistema de Retry Automático

Quando um lote falha:

1. ✅ **Status resetado** para `PENDING`
2. ✅ **Leads resetados** para `PENDING`
3. ✅ **Erro registrado** no campo `error_message`
4. ✅ **Disponível para reprocessamento** automático

### Tipos de Erro

#### Erros Temporários (Retry Automático)
- Falha de rede
- Timeout de conexão
- MinIO/S3 indisponível
- Banco de dados temporariamente indisponível

**Solução:** O sistema tentará novamente automaticamente

#### Erros de Configuração (Requer Intervenção)
- Schema não existe
- Bucket não configurado
- Credenciais inválidas
- Campos obrigatórios faltando

**Solução:**
1. Corrigir a configuração
2. Usar botão "Reprocessar" na interface

## Integração com Campanhas

Os lotes são criados automaticamente quando:

1. Uma campanha tem uma ação "Generate Aspect File"
2. Um contato alcança esta ação
3. O sistema cria:
   - Um batch (se não existir para aquele evento)
   - Um registro batch_lead ligando o contato ao batch

## Arquivos Criados

### Controller
`plugins/MauticAspectFileBundle/Controller/BatchController.php`
- `indexAction()` - Lista de lotes
- `viewAction()` - Detalhes do lote
- `reprocessAction()` - Reprocessar lote
- `deleteAction()` - Excluir lote
- `processAction()` - Processar lotes manualmente

### Views
- `plugins/MauticAspectFileBundle/Views/Batch/list.html.twig`
- `plugins/MauticAspectFileBundle/Views/Batch/view.html.twig`

### Traduções
- `plugins/MauticAspectFileBundle/Translations/en_US/messages.ini`
- `plugins/MauticAspectFileBundle/Translations/pt_BR/messages.ini`

### Configuração
- `plugins/MauticAspectFileBundle/Config/config.php` (atualizado)

## Permissões

O sistema utiliza as permissões padrão do Mautic:
- Requer integração AspectFile habilitada
- Acesso ao menu "Channels"

## Suporte

Para problemas:
1. Verificar logs em `var/logs/`
2. Verificar status no banco: `SELECT * FROM aspect_file_batches WHERE status = 'FAILED'`
3. Tentar reprocessar via interface
4. Executar comando manualmente: `php bin/console mautic:aspectfile:process -vvv`
