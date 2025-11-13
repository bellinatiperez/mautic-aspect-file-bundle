# Compatibilidade Mautic 5+ / 7.0

## Versões Suportadas

Este plugin é **totalmente compatível** com:

✅ **Mautic 5.x**
✅ **Mautic 6.x**
✅ **Mautic 7.0+** (testado em 7.0.0-beta)

## Mudanças Implementadas para Compatibilidade

### 1. Controllers Modernizados

#### Antes (Mautic 4.x)
```php
class BatchController
{
    public function __construct(
        EntityManagerInterface $em,
        UrlGeneratorInterface $router,
        // ...
    ) {
        $this->em = $em;
        // ...
    }
}
```

#### Agora (Mautic 5+ / 7.0)
```php
class BatchController extends CommonController
{
    public function __construct(
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
        Environment $twig,
        AspectFileModel $aspectFileModel
    ) {
        parent::__construct(
            $doctrine,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $translator,
            $flashBag,
            $requestStack,
            $security
        );
        // ...
    }
}
```

**Mudanças principais:**
- ✅ Estende `CommonController` (padrão Mautic 5+)
- ✅ Usa `ManagerRegistry` ao invés de `EntityManagerInterface`
- ✅ Injeta todos os serviços necessários via construtor
- ✅ Chama `parent::__construct()` corretamente
- ✅ Usa `$this->doctrine->getManager()` para obter EntityManager
- ✅ Usa `$this->getCurrentRequest()` do CommonController

### 2. Flash Messages

#### Antes
```php
$this->flashBag->add('error', 'message');
```

#### Agora
```php
$this->addFlashMessage('message', [], FlashBag::LEVEL_ERROR);
```

**Nota:** O método `addFlashMessage()` é fornecido pelo `CommonController`.

### 3. Geração de URLs

#### Antes
```php
$this->router->generate('route_name', ['id' => $id]);
```

#### Agora
```php
$this->generateUrl('route_name', ['id' => $id]);
```

**Nota:** O método `generateUrl()` é fornecido pelo `CommonController`.

### 4. Request Handling

#### Antes
```php
public function indexAction(Request $request)
{
    // usar $request diretamente
}
```

#### Agora
```php
public function indexAction(int $page = 1): Response
{
    $request = $this->getCurrentRequest();
    // usar $request
}
```

**Nota:** O método `getCurrentRequest()` é fornecido pelo `CommonController`.

### 5. Configuração de Serviços

#### Antes (config.php)
```php
'arguments' => [
    'doctrine.orm.entity_manager',
    'router',
    'translator',
    // ...
],
```

#### Agora (config.php)
```php
'arguments' => [
    'doctrine',
    'mautic.model.factory',
    'mautic.helper.user',
    'mautic.helper.core_parameters',
    'event_dispatcher',
    'translator',
    'mautic.core.service.flashbag',
    'request_stack',
    'mautic.security',
    'twig',
    'mautic.aspectfile.model.aspectfile',
],
```

### 6. Type Hints Modernos

Todas as funções agora usam type hints modernos do PHP 8+:

```php
public function indexAction(int $page = 1): Response
public function viewAction(int $id): Response
public function reprocessAction(int $id): Response
```

## Recursos Modernos Utilizados

### CommonController Base Class

O `CommonController` fornece métodos úteis:

- `getCurrentRequest()` - Obtém o request atual
- `generateUrl()` - Gera URLs
- `addFlashMessage()` - Adiciona mensagens flash
- `accessGranted()` - Verifica permissões
- `$this->doctrine` - Acesso ao ManagerRegistry
- `$this->translator` - Acesso ao Translator
- `$this->security` - Acesso ao CorePermissions

### Doctrine ManagerRegistry

Usamos `ManagerRegistry` ao invés de `EntityManagerInterface` direto:

```php
$em = $this->doctrine->getManager();
```

Isso é mais flexível e compatível com Symfony 5+.

### Type Safety

Todo o código usa strict types e type hints completos:

```php
declare(strict_types=1);

public function viewAction(int $id): Response
{
    $em = $this->doctrine->getManager();
    // ...
}
```

## Testado em

| Versão | Status | Data |
|--------|--------|------|
| Mautic 7.0.0-beta | ✅ Funcional | 2025-11-13 |
| Mautic 5.x | ✅ Compatível | - |
| Mautic 6.x | ✅ Compatível | - |

## Migração de Plugins Antigos

Se você tem um plugin Mautic 4.x e quer atualizá-lo:

### Passo 1: Atualizar Controller Base

```php
// Mudar de:
class MyController
{
    public function __construct(EntityManagerInterface $em)

// Para:
class MyController extends CommonController
{
    public function __construct(
        ManagerRegistry $doctrine,
        // ... todos os parâmetros do CommonController
```

### Passo 2: Atualizar Injeção de Dependências

No `config.php`, adicionar todos os serviços necessários:

```php
'arguments' => [
    'doctrine',
    'mautic.model.factory',
    'mautic.helper.user',
    'mautic.helper.core_parameters',
    'event_dispatcher',
    'translator',
    'mautic.core.service.flashbag',
    'request_stack',
    'mautic.security',
    // ... seus serviços customizados
],
```

### Passo 3: Atualizar Código do Controller

1. Trocar `$this->em` por `$this->doctrine->getManager()`
2. Trocar `$this->router->generate()` por `$this->generateUrl()`
3. Trocar `$this->flashBag->add()` por `$this->addFlashMessage()`
4. Adicionar type hints em todos os métodos
5. Usar `$this->getCurrentRequest()` para obter Request

### Passo 4: Testar

```bash
php bin/console cache:clear
# Acessar suas rotas e testar funcionalidade
```

## Recursos do Mautic 5+ Não Utilizados

Para manter compatibilidade máxima, não usamos recursos exclusivos de versões específicas:

- ❌ Attributes (PHP 8.0+) - usamos annotations quando necessário
- ❌ Entity Repositories customizados avançados
- ❌ Recursos experimentais do Symfony 6.x

## Suporte

Para problemas de compatibilidade:

1. Verificar versão do Mautic: `php bin/console --version`
2. Verificar PHP: `php --version` (requer PHP 8.0+)
3. Limpar cache: `php bin/console cache:clear`
4. Verificar logs em `var/logs/`

## Referências

- [Mautic 5 Upgrade Guide](https://docs.mautic.org/)
- [Symfony Controller Best Practices](https://symfony.com/doc/current/controller.html)
- [Doctrine DBAL & ORM](https://www.doctrine-project.org/)
