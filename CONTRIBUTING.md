# 🤝 Contribuindo para a Loja Online

Obrigado por considerar contribuir para o projeto! Este guia explica como colaborar da melhor forma.

---

## 📋 Índice

1. [Código de Conduta](#-código-de-conduta)
2. [Como Começar](#-como-começar)
3. [Processo de Contribuição](#-processo-de-contribuição)
4. [Padrões de Código](#-padrões-de-código)
5. [Testes](#-testes)
6. [Commits e Pull Requests](#-commits-e-pull-requests)
7. [Feedback](#-feedback)

---

## 💭 Código de Conduta

Somos uma comunidade inclusiva e amigável. Todos são bem-vindos independentemente de:
- Experiência prévia
- Gênero, identidade, orientação sexual
- Origem étnica, nacionalidade
- Deficiências físicas ou mentais
- Religião

**Esperamos que você:**
- Trate todos com respeito
- Seja aberto a críticas construtivas
- Foque no que é melhor para a comunidade
- Reporte comportamentos inapropriados

---

## 🚀 Como Começar

### 1. Conheça o Projeto

Antes de começar, leia:
- [README.md](./README.md) — visão geral
- [ROADMAP.md](./ROADMAP.md) — fases do projeto
- [docs/ARQUITETURA.md](./docs/ARQUITETURA.md) — estrutura técnica
- [docs/INSTALACAO_RAPIDA.md](./docs/INSTALACAO_RAPIDA.md) — setup

### 2. Configure o Ambiente

Siga [docs/INSTALACAO_RAPIDA.md](./docs/INSTALACAO_RAPIDA.md).

**Estado atual:** o ambiente roda (Laravel 12 + Livewire + Vite), mas nenhuma
funcionalidade de e-commerce foi escrita. O trabalho começa pela Fase 2 do
[ROADMAP](./ROADMAP.md) — CMS e painel administrativo.

### 3. Escolha uma Issue

**Procure por issues com as labels:**
- `good first issue` — bom para iniciantes
- `help wanted` — ajuda necessária
- `enhancement` — melhorias
- `bug` — correção de bugs

Ou [crie uma nova issue](#criando-issues) se tiver uma ideia!

---

## 🔄 Processo de Contribuição

### Passo 1: Crie uma Branch

```bash
# Sempre crie uma branch a partir de main/develop
git checkout main
git pull origin main

# Crie sua branch (use prefixo descritivo)
git checkout -b feature/sua-feature
git checkout -b fix/seu-bug
git checkout -b docs/sua-documentacao
```

### Passo 2: Implemente a Mudança

- Siga os [padrões de código](#-padrões-de-código) abaixo
- Faça commits regulares com mensagens descritivas
- Escreva testes para novas funcionalidades
- Atualize documentação se necessário

### Passo 3: Teste Localmente

```bash
docker compose exec app composer test        # PHPUnit
docker compose exec app vendor/bin/pint      # formatação (Laravel Pint)
```

Análise estática (PHPStan) ainda não foi adicionada — está no escopo da Fase 8.

### Passo 4: Faça o Push

```bash
git push origin feature/sua-feature
```

### Passo 5: Abra um Pull Request

- Use o template: [.github/PULL_REQUEST_TEMPLATE.md](./.github/PULL_REQUEST_TEMPLATE.md)
- Adicione descrição clara do que foi feito
- Referencie a issue: `Fecha #123`
- Solicite review

### Passo 6: Responda ao Feedback

Revisores podem pedir ajustes. Isso é normal!

Depois de aceitar feedback:
```bash
git add .
git commit -m "feedback: esclarecer lógica em CartService"
git push origin feature/sua-feature
```

### Passo 7: Merge

Após aprovação, um mantenedor fará o merge.

---

## 📝 Padrões de Código

### PHP (PSR-12)

```php
// ✅ Bom
class UserController {
    public function store(CreateUserRequest $request) {
        $user = User::create($request->validated());
        return redirect()->route('users.show', $user);
    }
}

// ❌ Ruim
class UserController{
public function store(CreateUserRequest $request){
$user=User::create($request->all());
return back();
}}
```

### Convenções

| O quê | Conveção | Exemplo |
|-------|----------|---------|
| Classes | PascalCase | `ProductController` |
| Métodos | camelCase | `getUserByEmail()` |
| Constantes | UPPER_SNAKE_CASE | `MAX_RETRIES` |
| Variáveis | camelCase | `$productId` |
| Arquivos DB | snake_case | `product_images` |
| Rotas | kebab-case | `/minha-conta`, `/meus-pedidos` |

### Blade Templates

```blade
{{-- ✅ Bom --}}
<div class="card">
    <h2>{{ $product->name }}</h2>
    <p>{{ $product->description }}</p>
</div>

{{-- ❌ Ruim --}}
<div>
<h2><?php echo $product->name; ?></h2>
<p><?php echo $product->description; ?></p>
</div>
```

### Nomes Significativos

```php
// ✅ Bom
public function calculateShippingCost(Order $order): float {
    return $order->weight * $this->costPerKilo;
}

// ❌ Ruim
public function calc($o): float {
    return $o->w * $this->c;
}
```

### Comprimento de Métodos

- Máximo: 20 linhas
- Se passar, quebrar em submétodos ou Service

```php
// ✅ Bom
public function store(Request $request) {
    $validated = $this->validate($request);
    $user = $this->userService->create($validated);
    return redirect()->route('users.show', $user);
}

// ❌ Ruim
public function store(Request $request) {
    // 50 linhas de lógica aqui...
}
```

### Sem Lógica nos Controllers

```php
// ✅ Bom: Usar Service
class OrderController {
    public function __construct(private OrderService $orderService) {}
    
    public function create(CreateOrderRequest $request) {
        $order = $this->orderService->create($request->validated());
        return view('orders.confirmation', compact('order'));
    }
}

// ❌ Ruim: Lógica no controller
class OrderController {
    public function create(CreateOrderRequest $request) {
        // 100 linhas de SQL e lógica aqui...
    }
}
```

### Comentários (Minimal)

```php
// ✅ Bom: Nome descritivo, sem comentário
public function calculateDiscountedPrice(Product $product): float {
    return $product->price * (1 - $product->discount_percentage / 100);
}

// ❌ Ruim: Comentário óbvio
public function calculateDiscountedPrice(Product $product): float {
    // Calcula o preço com desconto
    return $product->price * (1 - $product->discount_percentage / 100);
}

// ✅ Bom: Comentário explica o "por quê"
public function calculateShippingCost(Order $order): float {
    // Multiplicamos por 1.15 para cobrir impostos (Resolução 30/08)
    return $order->weight * $this->costPerKilo * 1.15;
}
```

---

## 🧪 Testes

### Escrever Testes para Novas Features

```php
// tests/Feature/OrderTest.php
class OrderTest extends TestCase {
    public function test_user_can_create_order() {
        $user = User::factory()->create();
        $cart = Cart::factory()->for($user)->create();
        CartItem::factory()
            ->for($cart)
            ->for(Product::factory()->create())
            ->create();
        
        $response = $this->actingAs($user)
            ->post('/pedido', [
                'shipping_address' => '...',
                'payment_method' => 'credit_card',
            ]);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
        ]);
    }
    
    public function test_order_requires_authentication() {
        $this->post('/pedido')->assertRedirect('/login');
    }
}
```

### Rodar Testes

```bash
docker compose exec app composer test
```

Scripts adicionais (`test:feature`, `test:unit`, `test:coverage`) serão
definidos na Fase 8, junto com a meta de 70% de cobertura.

### Mínimo de Cobertura

- **70%** de linhas de código
- **80%** de Services (lógica crítica)
- **60%** de Controllers

---

## 💬 Commits e Pull Requests

### Mensagens de Commit

Siga o padrão [Conventional Commits](https://www.conventionalcommits.org/):

```
tipo(escopo): descrição curta

Descrição mais longa explicando o quê e por quê.

Fecha #123
```

**Tipos válidos:**
- `feat` — nova funcionalidade
- `fix` — correção de bug
- `docs` — documentação
- `style` — formatação, missing semicolons, etc
- `refactor` — mudança de código sem alterar funcionalidade
- `perf` — melhoria de performance
- `test` — adição/atualização de testes
- `chore` — manutenção, dependências

**Exemplos:**

```
feat(carrinho): adicionar validação de estoque

Validar quantidade disponível antes de adicionar
item ao carrinho. Se estoque insuficiente, exibir
mensagem de erro e não adicionar.

Fecha #45

---

fix(pedidos): corrigir cálculo de frete

O frete estava sendo calculado 2x no checkout.
Movido para OrderService.

Fecha #78

---

docs(readme): atualizar instruções de setup

Adicionar seção WSL2 com performance tips.
```

### Pull Request

Use o template em `.github/PULL_REQUEST_TEMPLATE.md`:

```markdown
## Descrição

Qual problema resolve? Qual feature adiciona?

[Descrição clara e concisa]

## Tipo de Mudança

- [x] Bug fix (correção que não quebra uso)
- [ ] Nova feature (funcionalidade nova)
- [ ] Breaking change (quebra compatibilidade)
- [ ] Documentação

## Issue Relacionada

Fecha #123

## Fase do ROADMAP

Qual fase afeta? (Fase 1, 2, 3, etc)

## Testes

- [x] Testes novos/atualizados incluídos
- [x] Testes passam localmente (`composer test`)
- [x] Sem warnings do linter

## Checklist

- [x] Código segue PSR-12
- [x] Documentação atualizada
- [x] Migrations reversíveis
- [x] Sem quebra de compatibilidade
- [x] ROADMAP.md atualizado se necessário
```

---

## 🐛 Criando Issues

### Bug Report

```markdown
## Descrição
Breve descrição do bug

## Passos para Reproduzir
1. Acesse http://localhost/produtos
2. Clique em "Adicionar ao Carrinho"
3. Veja o erro

## Comportamento Esperado
Produto deve ser adicionado ao carrinho

## Comportamento Atual
Exibe erro "OutOfStockException"

## Ambiente
- OS: Windows 11
- Docker version: 4.30
- PHP: 8.3
- Laravel: 12

## Screenshots (se aplicável)
[Paste screenshot here]

## Fase Relacionada
Fase 5 — Carrinho e Checkout
```

### Feature Request

```markdown
## Descrição
Descreva a feature desejada

## Caso de Uso
Por que isso seria útil? Qual problema resolve?

## Possível Solução
Como você implementaria?

## Alternativas Consideradas
Outras abordagens?

## Fase Relacionada
Qual fase seria afetada? (Fase X)

## Prioridade
Alta / Média / Baixa
```

---

## 📊 Checklist Antes de Submeter

Antes de fazer push, verifique:

- [ ] Código segue PSR-12
- [ ] Testes passam (`composer test`)
- [ ] Código formatado (`vendor/bin/pint`)
- [ ] Migrations são reversíveis
- [ ] `.env.example` atualizado (se necessário)
- [ ] Documentação atualizada
- [ ] ROADMAP.md atualizado (se completou fase)
- [ ] Commit messages seguem padrão
- [ ] Branch criada de `main` atualizada
- [ ] PR abre sem conflitos

---

## 🙏 Feedback

### Recebendo Feedback

- Leia com mente aberta
- Não leve como crítica pessoal
- Pergunte se não entender
- Agradeça o tempo do revisor

### Dando Feedback

- Seja respeitoso e construtivo
- Sugira melhorias, não critique
- Use "nós" em vez de "você"
- Cite exemplos específicos

---

## 💰 Compensação

Este é um projeto **open-source não-remunerado**. Contribuições são voluntárias e valorizadas pelo reconhecimento na comunidade.

Contribuidores ativos podem ser adicionados como **maintainers** do projeto.

---

## ❓ Questões?

- 📖 Consulte [README.md](./README.md)
- 🏛️ Veja [docs/ARQUITETURA.md](./docs/ARQUITETURA.md)
- 💬 Abra uma issue ou discussão
- 📧 Email: [seu-email@example.com]

---

## 🎉 Obrigado!

Suas contribuições fazem este projeto melhor. Agradecemos por colaborar!

---

**Última atualização:** 2026-09-04
