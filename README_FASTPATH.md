# FastPath SOAP Integration

## Visão Geral

Este documento descreve a implementação da integração individual com o serviço FastPath SOAP, que permite enviar dados de leads individualmente (não em lote) para o endpoint FastPath.

## Arquivos Criados/Modificados

### Novos Arquivos

1. **`Service/FastPathSender.php`**
   - Serviço responsável por enviar leads individuais via SOAP
   - Usa PHP SoapClient para comunicação com o serviço FastPath
   - Formata dados do lead usando schema selecionado
   - Retorna sucesso/erro para cada envio

2. **`Form/Type/FastPathActionType.php`**
   - Formulário de configuração da action na campanha
   - Campos configuráveis:
     - Schema (obrigatório)
     - WSDL URL (obrigatório, pré-preenchido)
     - FastList Name (obrigatório)
     - Function Type (obrigatório, padrão: 1)
     - Timeout (opcional, padrão: 30s)
     - Custom Fields 1, 2, 3 (opcionais)
     - Response URI (opcional)

### Arquivos Modificados

1. **`EventListener/CampaignSubscriber.php`**
   - Adicionado novo evento: `fastpath.send_individual`
   - Novo método: `onFastPathTriggerAction()`
   - Processa leads individualmente (não em batch)

2. **`Config/config.php`**
   - Registrado serviço: `mautic.aspectfile.service.fastpath_sender`
   - Registrado form: `mautic.aspectfile.form.type.fastpath_action`
   - Atualizado CampaignSubscriber com nova dependência

## Como Funciona

### Estrutura SOAP

O serviço FastPath expõe o método `FeedRecord` que aceita um objeto `FeedRecordMsg`:

```xml
<FeedRecordMsg>
    <MessageId>string</MessageId>          <!-- ID único da mensagem -->
    <FunctionType>int</FunctionType>       <!-- Tipo de função -->
    <FastList>string</FastList>            <!-- Nome da lista -->
    <Record>string</Record>                <!-- Dados formatados (linha fixed-width) -->
    <ResponseURI>string</ResponseURI>      <!-- URI de resposta (opcional) -->
    <CustomField1>string</CustomField1>    <!-- Campo customizado 1 (opcional) -->
    <CustomField2>string</CustomField2>    <!-- Campo customizado 2 (opcional) -->
    <CustomField3>string</CustomField3>    <!-- Campo customizado 3 (opcional) -->
</FeedRecordMsg>
```

### Fluxo de Processamento

1. **Trigger de Campanha**: Lead entra na action "Send to FastPath (Individual)"
2. **Validação**: Verifica se schema e configurações estão corretas
3. **Mapeamento**: `FieldMapper` mapeia campos do lead para o schema
4. **Formatação**: `FileGenerator` gera linha formatada (fixed-width)
5. **Envio SOAP**: `FastPathSender` envia via SOAP para o endpoint
6. **Resultado**:
   - ✅ Sucesso: Lead marcado como "passed"
   - ❌ Erro: Lead marcado como "failed" (pode ser retentado)

### Exemplo de Uso na Campanha

1. Criar/selecionar um Schema no menu AspectFile
2. Criar uma Campanha
3. Adicionar action "Send to FastPath (Individual)"
4. Configurar:
   - **Schema**: Selecionar schema apropriado
   - **WSDL URL**: `http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl`
   - **FastList Name**: Nome da lista (ex: `BRADESCO_COMERCIAL_PJ`)
   - **Function Type**: `1`
   - **Timeout**: `30` (segundos)
5. Salvar e publicar campanha

## Diferenças vs Evento Batch

| Característica | Batch (aspectfile.generate) | Individual (fastpath.send_individual) |
|---------------|----------------------------|----------------------------------|
| **Processamento** | Acumula leads → Gera arquivo | Envia imediatamente cada lead |
| **Destino** | MinIO/S3/Network (arquivo) | SOAP Endpoint (HTTP) |
| **Storage** | Cria batch no database | Sem storage intermediário |
| **Velocidade** | Otimizado para volume | Tempo real |
| **Retry** | Reprocessa batch inteiro | Retry individual por lead |
| **Rastreamento** | Via batch_id + arquivo | Via message_id |

## Logs e Debug

### Logs Gerados

Todos os logs são gravados em `var/logs/mautic_prod.log` (ou dev) com prefixo `FastPath:`:

```log
[INFO] FastPath: Campaign action triggered
[INFO] FastPath: Preparing to send lead (lead_id: 123, schema: BradescoComercial)
[INFO] FastPath: Generated record line (length: 500)
[INFO] FastPath: Sending SOAP request (message_id: MAUTIC_123_20250119120000)
[DEBUG] FastPath: SOAP Request (XML completo)
[DEBUG] FastPath: SOAP Response (XML completo)
[INFO] FastPath: Successfully sent lead (message_id: MAUTIC_123_20250119120000)
```

### Troubleshooting

**Erro: "SOAP Fault: [faultcode] message"**
- Verificar se WSDL está acessível: `curl http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl`
- Verificar conectividade de rede
- Validar estrutura dos dados enviados

**Erro: "Schema not found"**
- Verificar se schema está publicado
- Conferir ID do schema na configuração

**Erro: "Missing fast_list configuration"**
- Preencher campo "FastList Name" na configuração da action

**Timeout**
- Aumentar valor do campo "Timeout" na configuração
- Verificar performance do servidor FastPath

## Teste Manual

Para testar o envio SOAP individualmente, você pode criar um script PHP simples:

```php
<?php
// test-fastpath.php
require_once __DIR__ . '/../../app/autoload.php';

$container = \Mautic\CoreBundle\Factory\MauticFactory::getContainer();
$fastPathSender = $container->get('mautic.aspectfile.service.fastpath_sender');
$leadModel = $container->get('mautic.lead.model.lead');
$schemaRepo = $container->get('doctrine.orm.entity_manager')
    ->getRepository(\MauticPlugin\MauticAspectFileBundle\Entity\Schema::class);

// Buscar um lead e schema
$lead = $leadModel->getEntity(1); // ID do lead
$schema = $schemaRepo->find(1);   // ID do schema

// Configuração
$config = [
    'wsdl_url' => 'http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl',
    'fast_list' => 'TEST_LIST',
    'function_type' => 1,
    'timeout' => 30,
];

// Enviar
$result = $fastPathSender->send($lead, $schema, $config);

print_r($result);
```

Execute: `php test-fastpath.php`

## Configurações Adicionais

### Desabilitar Cache WSDL (Desenvolvimento)

No código `FastPathSender.php`, linha 69:
```php
'cache_wsdl' => WSDL_CACHE_NONE, // Desabilita cache
```

Para produção, altere para:
```php
'cache_wsdl' => WSDL_CACHE_BOTH, // Cache completo
```

### Ajustar Timeout Global

No PHP.ini:
```ini
default_socket_timeout = 60
max_execution_time = 120
```

## Segurança

- ✅ Validação de entrada (schema_id, fast_list)
- ✅ Tratamento de exceções SOAP
- ✅ Logs detalhados para auditoria
- ✅ Timeout configurável
- ⚠️  **TODO**: Validar whitelist de URLs WSDL permitidas
- ⚠️  **TODO**: Implementar autenticação SOAP (se necessário)

## Próximas Melhorias

1. **Retry Automático**: Implementar retry exponencial em caso de falha temporária
2. **Batch Async**: Criar fila assíncrona para evitar timeout em grandes volumes
3. **Validação de Resposta**: Parsear XML de resposta e validar status
4. **Métricas**: Adicionar contadores de sucesso/erro no Mautic
5. **Webhooks**: Implementar callbacks via ResponseURI
6. **Autenticação**: Suporte a WS-Security se necessário

## Contato

Para dúvidas ou problemas, consulte os logs do Mautic e verifique a conectividade com o servidor FastPath.
