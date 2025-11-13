# Correção do Erro "has no container set"

## Problema Original

```
"MauticPlugin\MauticAspectFileBundle\Controller\BatchController" has no container set,
did you forget to define it as a service subscriber?
```

Este é um erro comum no Symfony 5+/6+ quando controllers tentam usar métodos que dependem do container (como `generateUrl()`) sem estarem configurados corretamente.

## Causa Raiz

O `AbstractController` do Symfony fornece métodos convenientes como `generateUrl()` que dependem do service locator (container). No Mautic 7/Symfony 6, isso requer configuração específica.

## Solução Implementada

### 1. Injeção Explícita de Dependências

Ao invés de depender do container, injetamos todas as dependências explicitamente no construtor:

```php
class BatchController extends AbstractController
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private Translator $translator,
        private FlashBag $flashBag,
        private Environment $twig,
        private AspectFileModel $aspectFileModel,
        private UrlGeneratorInterface $urlGenerator  // ← IMPORTANTE!
    ) {}
}
```

### 2. Substituição de Métodos do Container

**Antes (dependia do container):**
```php
$this->generateUrl('route_name', ['id' => $id])
```

**Depois (injeção explícita):**
```php
$this->urlGenerator->generate('route_name', ['id' => $id])
```

### 3. Configuração do Serviço (config.php)

```php
'mautic.aspectfile.controller.batch' => [
    'class' => \MauticPlugin\MauticAspectFileBundle\Controller\BatchController::class,
    'arguments' => [
        'doctrine',
        'translator',
        'mautic.core.service.flashbag',
        'twig',
        'mautic.aspectfile.model.aspectfile',
        'router',  // ← UrlGeneratorInterface
    ],
    'public' => true,
    'tags' => [
        'controller.service_arguments',  // ← IMPORTANTE!
    ],
],
```

A tag `controller.service_arguments` é crucial para habilitar a injeção automática do `Request`.

### 4. Configuração Moderna (services.php)

Também criamos um arquivo `services.php` moderno usando o ContainerConfigurator do Symfony:

```php
return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    $services->set(BatchController::class)
        ->arg('$doctrine', service('doctrine'))
        ->arg('$translator', service('translator'))
        ->arg('$flashBag', service('mautic.core.service.flashbag'))
        ->arg('$twig', service('twig'))
        ->arg('$aspectFileModel', service('mautic.aspectfile.model.aspectfile'))
        ->arg('$urlGenerator', service('router'))
        ->tag('controller.service_arguments');
};
```

## Comparação de Abordagens

### ❌ Abordagem Problemática (não funciona no Mautic 7)

```php
class MyController extends AbstractController
{
    public function myAction(): Response
    {
        // Depende do container implícito
        $url = $this->generateUrl('route_name');
        return new RedirectResponse($url);
    }
}
```

**Problema:** `generateUrl()` precisa do container, mas ele não está configurado.

### ✅ Abordagem Correta (funciona no Mautic 5+/7+)

```php
class MyController extends AbstractController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function myAction(): Response
    {
        // Usa injeção explícita
        $url = $this->urlGenerator->generate('route_name');
        return new RedirectResponse($url);
    }
}
```

**Vantagens:**
- ✅ Não depende do container
- ✅ Testável (fácil de mockar)
- ✅ Explícito sobre dependências
- ✅ Compatível com Symfony 5+/6+
- ✅ Segue melhores práticas

## Outros Métodos que Requerem Container

Se seu controller usa estes métodos do `AbstractController`, você precisará injetar os serviços correspondentes:

| Método do AbstractController | Serviço a Injetar | Interface |
|------------------------------|-------------------|-----------|
| `$this->generateUrl()` | `'router'` | `UrlGeneratorInterface` |
| `$this->forward()` | `'http_kernel'` | `HttpKernelInterface` |
| `$this->redirect()` | Não precisa | Use `new RedirectResponse()` |
| `$this->render()` | `'twig'` | `Environment` |
| `$this->renderView()` | `'twig'` | `Environment` |
| `$this->addFlash()` | `'session'` | `SessionInterface` |
| `$this->isGranted()` | `'security.authorization_checker'` | `AuthorizationCheckerInterface` |
| `$this->getUser()` | `'security.token_storage'` | `TokenStorageInterface` |
| `$this->getDoctrine()` | `'doctrine'` | `ManagerRegistry` |

## Request como Parâmetro

Em vez de usar métodos do controller para obter o Request, injete-o como parâmetro:

```php
// ✅ Correto
public function myAction(Request $request): Response
{
    $page = $request->query->get('page', 1);
}

// ❌ Evite (requer container)
public function myAction(): Response
{
    $request = $this->getCurrentRequest(); // Não funciona sem container configurado
}
```

## Benefícios da Solução

1. **Compatibilidade Total** - Funciona no Mautic 5, 6, 7 e Symfony 5+/6+
2. **Sem Magia Negra** - Todas as dependências são explícitas
3. **Testabilidade** - Fácil mockar serviços injetados
4. **Performance** - Sem overhead do service locator
5. **Manutenibilidade** - Código mais claro e direto
6. **Type Safety** - PHP 8+ type hints garantem tipos corretos

## Verificação

Para verificar se o serviço está configurado corretamente:

```bash
php bin/console debug:container mautic.aspectfile.controller.batch
```

Deve mostrar:
- ✅ Todos os 6 argumentos injetados
- ✅ Tag `controller.service_arguments`
- ✅ Public = yes

## Troubleshooting

### Erro persiste após correção

1. **Limpar cache:**
   ```bash
   php bin/console cache:clear
   ```

2. **Verificar configuração do serviço:**
   ```bash
   php bin/console debug:container nome.do.servico
   ```

3. **Verificar argumentos:**
   Certifique-se de que a ordem dos argumentos no config.php corresponde à ordem no construtor.

### ArgumentCountError

Se receber erro sobre número de argumentos:
- Verifique se todos os serviços no `arguments` do config.php existem
- Verifique se a ordem está correta
- Verifique se o construtor tem os mesmos parâmetros

### Service not found

Se o Symfony não encontrar um serviço:
- Use `php bin/console debug:container` para listar serviços disponíveis
- Verifique o nome exato do serviço no config
- Alguns serviços têm aliases (ex: `'router'` é alias para `'router.default'`)

## Referências

- [Symfony Controller Best Practices](https://symfony.com/doc/current/controller.html)
- [Symfony Service Container](https://symfony.com/doc/current/service_container.html)
- [Mautic Plugin Development](https://developer.mautic.org/)
