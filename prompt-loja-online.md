# Prompt para Gerar Loja Online - Claude CLI

Crie um projeto de loja online completo com Laravel 12, Docker e Livewire, baseado na arquitetura da Feira Esquerda Livre (https://github.com/jmarciosilva/projeto-feira-esquerda-livre), mas focado APENAS em funcionalidades de e-commerce. REMOVA: rede social, notícias, agenda de feiras, comunidade e AVA.

## ⚠️ PRIORIDADE: Documentação Estruturada desde o Início

**Crie 3 arquivos de documentação principais:**

### 1. README.md (Completo e Profissional)
Com as seguintes seções:
- Badge de status do projeto (EM DESENVOLVIMENTO)
- Descrição executiva do projeto
- Visão geral (plataforma de e-commerce com CMS)
- Stack técnica (tabela formatada)
- MVP Demonstrável (funcionalidades já implementadas)
- Requisitos (Docker, Git)
- Instruções de instalação com Docker (passo a passo)
- Serviços, portas e endereços (tabela com localhost:80, 8081, 8025, 5173, etc)
- Uso diário (docker compose up, down)
- Comandos dentro dos containers
- Estrutura de diretórios
- Variáveis de ambiente importantes (tabela)
- Seeders e usuários demonstrativos (tabela)
- Testes (como rodar com PHPUnit)
- Princípios transversais (mobile-first, performance, LGPD)
- Status do Projeto com link para ROADMAP
- Segurança e permissões
- Como contribuir

### 2. ROADMAP.md (Fases de Desenvolvimento)
Com estrutura clara de progresso:
- Fases do projeto com status (Concluída, MVP, Em andamento, Planejado)
- Cada fase deve ter:
  - Número e título (ex: "Fase 1 — CMS e Admin")
  - Status atual (checkbox ✅ ou ⏳)
  - Funcionalidades incluídas (lista com bullets)
  - Data estimada de conclusão
  - Dependências de outras fases
  - Notas técnicas importantes
  
Exemplo de fases:
| Fase | Status | Entregável | Data Estimada |
| --- | --- | --- | --- |
| Fase 1 — Setup Docker & Base Laravel | ⏳ | Infraestrutura rodando | Semana 1 |
| Fase 2 — CMS e Configurações | ⏳ | Painel admin com cores, menus, páginas | Semana 2 |
| Fase 3 — Usuários e Permissões | ⏳ | Roles, permissions, CRUD usuários | Semana 2 |
| Fase 4 — Produtos e Categorias | ⏳ | CRUD produtos com upload de imagens | Semana 3 |
| Fase 5 — Carrinho e Checkout | ⏳ | Fluxo completo de compra | Semana 4 |
| Fase 6 — Pedidos e Rastreamento | ⏳ | Gestão de pedidos e status | Semana 4 |
| Fase 7 — API Mobile (Sanctum) | ⏳ | Endpoints REST autenticados | Semana 5 |
| Fase 8 — Testes e Qualidade | ⏳ | Testes unitários e feature | Semana 5 |
| Fase 9 — Deploy e Produção | Planejado | CI/CD, backup, monitoramento | Semana 6 |

### 3. docs/ARQUITETURA.md (Visão Técnica Detalhada)
Com seções:
- Diagrama de arquitetura (em ASCII ou descrição)
- Padrões de código (Service Layer, Repository, Traits)
- Estrutura de pastas comentada
- Fluxo de requisição (Request → Middleware → Controller → Service → Model → Response)
- Relacionamentos entre Models (ER em texto)
- Camadas de aplicação
- Escolhas arquiteturais importantes
- Performance e otimizações

### 4. docs/INSTALACAO_RAPIDA.md
Passo a passo mínimo para novo desenvolvedor:
- Clone e setup (5 minutos)
- Subir Docker (1 minuto)
- Acessar (URLs e credenciais)
- Testar API com Postman/Insomnia

### 5. docs/DOCKER_DEVELOPMENT.md
Detalhado sobre desenvolvimento com Docker:
- Volumes e sincronização
- Performance no WSL2
- Troubleshooting comum
- Como debugar
- Hot reload com Vite
- Comandos mais usados

### 6. .github/ISSUES_TEMPLATE.md
Template para issues com categorias:
- **[FEATURE]** Novas funcionalidades
- **[BUG]** Correção de bugs
- **[DOCS]** Documentação
- **[REFACTOR]** Melhorias de código
- **[CHORE]** Manutenção

Exemplo de issue:
```
## Descrição
Breve descrição do que precisa ser feito

## Aceitação
- [ ] Tarefa 1
- [ ] Tarefa 2
- [ ] Testes incluídos
- [ ] Documentação atualizada

## Fase Relacionada
Fase X — Nome da Fase

## Prioridade
Alta / Média / Baixa

## Assignee
Nome do desenvolvedor
```

---

## Estrutura de Documentação para Rastreamento

### Como Usar Este Projeto

**Para compreender o status geral:**
1. Leia `README.md` → visão geral e setup
2. Verifique `ROADMAP.md` → progresso das fases
3. Consulte `docs/ARQUITETURA.md` → entenda a estrutura técnica

**Para adicionar funcionalidade:**
1. Crie uma issue com template
2. Mencione qual fase afeta no ROADMAP
3. Faça PR referenciando a issue
4. Update ROADMAP após merge

**Para onboard novo dev:**
1. Faça clone + read README.md
2. Siga `docs/INSTALACAO_RAPIDA.md`
3. Explore `docs/DOCKER_DEVELOPMENT.md` se tiver dúvidas
4. Estude `docs/ARQUITETURA.md` para entender padrões

### Badges para README
Adicione na primeira linha do README:
```
![Status](https://img.shields.io/badge/status-EM%20DESENVOLVIMENTO-green?style=for-the-badge)
![Laravel](https://img.shields.io/badge/Laravel-12-red?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.3-blue?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-✓-blue?style=for-the-badge)
```

---

## Requisitos Técnicos

**Stack:**
- PHP 8.3 (Docker)
- Laravel 12
- MySQL 8.4
- Livewire 4
- AlpineJS 3
- TailwindCSS 4
- Vite 7
- spatie/laravel-permission (permissões)
- Laravel Sanctum (API mobile)
- Intervention/Image (processamento de imagens)
- barryvdh/laravel-dompdf (gerar PDFs)

**Infraestrutura:**
- Docker Compose com serviços: app (PHP-FPM), nginx, mysql, node, phpmyadmin, mailpit, redis
- Variáveis de ambiente para portas configuráveis
- Volume compartilhado para código
- Health checks nos serviços críticos

## Estrutura de Banco de Dados

### Usuários e Permissões
- `users` (id, name, email, password, role, phone, cpf, status, created_at, updated_at)
- `roles` (via spatie/laravel-permission: admin, gerente, editor, lojista, customer)
- `permissions` (permissões granulares por módulo)
- `model_has_roles` e `model_has_permissions`

### CMS e Configurações
- `site_settings` (chave => valor): logo, favicon, cores primária/secundária/destaque, email suporte, telefone, endereço, redes sociais, configurações gerais
- `pages` (id, title, slug, content, meta_title, meta_description, is_published, created_at)
- `banners` (id, title, image, link, position, is_active, order, created_at)
- `menus` (id, name, position) + `menu_items` (id, menu_id, label, url, order, parent_id)
- `media` (id, filename, path, mime_type, size, uploaded_by, created_at)

### Loja e Produtos
- `categories` (id, name, slug, description, image, is_active, order, created_at)
- `products` (id, name, slug, description, short_description, price, cost, sku, image, category_id, is_active, stock, created_by, created_at)
- `product_images` (id, product_id, image, order)
- `product_variants` (id, product_id, name, sku, price, stock - opcional para futuro)

### Pedidos
- `orders` (id, order_number, user_id, status, subtotal, shipping_cost, discount, total, shipping_address, notes, created_at)
- `order_items` (id, order_id, product_id, quantity, price, subtotal)
- `order_payments` (id, order_id, status, method, transaction_id, paid_at)
- `order_tracking` (id, order_id, status, message, updated_at)

### Carrinho
- `carts` (id, user_id, session_id, created_at, updated_at)
- `cart_items` (id, cart_id, product_id, quantity, created_at)

### Configurações de Loja
- `store_config` (chave => valor): taxa de imposto, tipos de entrega, horário funcionamento, etc

## Migrations

Gere migrations para todas as tabelas acima com relacionamentos apropriados, indexes e constraints.

## Models Eloquent

Crie models com:
- Relacionamentos: User hasMany Orders, Product hasMany OrderItems, Order hasMany OrderItems, etc
- Scopes: active, published, featured
- Mutators: formatação de datas, valores monetários
- Appends: atributos computados

**Models obrigatórios:**
- User (com role e relacionamentos)
- Role, Permission (via Spatie)
- SiteSetting
- Category
- Product
- ProductImage
- Order, OrderItem, OrderPayment, OrderTracking
- Cart, CartItem
- Page, Banner, Menu, MenuItem
- Media
- CustomerAddress (endereços salvos do cliente)

## Rotas (routes/web.php)

### Frontend Público
- `GET /` → Homepage com banners, categorias, produtos em destaque
- `GET /produtos` → Listagem de produtos com filtros, paginação
- `GET /produtos/{slug}` → Detalhe do produto
- `GET /categoria/{slug}` → Produtos por categoria
- `GET /carrinho` → Visualizar carrinho
- `POST /carrinho/adicionar` → AJAX para adicionar produto
- `POST /carrinho/remover/{id}` → Remover item
- `POST /carrinho/atualizar` → Atualizar quantidades
- `GET /checkout` → Finalizar compra (com login obrigatório)
- `POST /pedido` → Criar pedido
- `GET /pedido/{number}` → Acompanhar pedido (públido com número+email)
- `GET /minha-conta` → Dashboard cliente (autenticado)
- `GET /minha-conta/pedidos` → Pedidos do cliente
- `GET /minha-conta/enderecos` → Endereços salvos
- `GET /privacidade` → Página de privacidade
- `GET /termos` → Termos de uso
- `GET /contato` → Página de contato
- `POST /contato` → Enviar mensagem

### Painel Administrativo (`/admin`)
- `GET /admin` → Dashboard (admin only)
- `GET /admin/configuracoes` → Configurações gerais, cores, contato
- `GET /admin/usuarios` → CRUD usuários
- `GET /admin/categorias` → CRUD categorias
- `GET /admin/produtos` → CRUD produtos com upload de imagens
- `GET /admin/pedidos` → Listagem de pedidos com status
- `GET /admin/pedidos/{id}` → Detalhe pedido, atualizar status
- `GET /admin/paginas` → CRUD páginas estáticas
- `GET /admin/banners` → CRUD banners
- `GET /admin/menus` → Gerenciar menus e itens
- `GET /admin/midia` → Galeria de mídia

### API Mobile (`/api/v1`, autenticada com Sanctum)
- `POST /api/v1/auth/register` → Cadastro
- `POST /api/v1/auth/login` → Login
- `POST /api/v1/auth/logout` → Logout
- `GET /api/v1/produtos` → Listar produtos (com paginação e filtros)
- `GET /api/v1/produtos/{id}` → Detalhe produto
- `GET /api/v1/categorias` → Listar categorias
- `GET /api/v1/carrinho` → Ver carrinho
- `POST /api/v1/carrinho/adicionar` → Adicionar produto
- `POST /api/v1/carrinho/remover` → Remover item
- `POST /api/v1/checkout` → Criar pedido
- `GET /api/v1/meus-pedidos` → Pedidos do cliente autenticado
- `GET /api/v1/pedido/{id}` → Detalhe do pedido
- `GET /api/v1/enderecos` → Endereços salvos
- `POST /api/v1/enderecos` → Criar/atualizar endereço

## Controllers

**Public:**
- `HomeController` (homepage)
- `ProductController` (listagem e detalhe)
- `CartController` (gerenciar carrinho via AJAX)
- `OrderController` (checkout, criar pedido, acompanhar)
- `AuthController` (login, registro, logout)
- `AccountController` (dados do cliente, endereços)
- `PageController` (páginas estáticas)

**Admin:**
- `Admin\DashboardController`
- `Admin\ConfigurationController` (configurações, cores, textos)
- `Admin\UserController` (CRUD usuários)
- `Admin\CategoryController` (CRUD categorias)
- `Admin\ProductController` (CRUD produtos, upload imagens)
- `Admin\OrderController` (gerenciar pedidos, status)
- `Admin\PageController` (CRUD páginas)
- `Admin\BannerController` (CRUD banners)
- `Admin\MenuController` (CRUD menus)
- `Admin\MediaController` (gerenciar mídia)

**API:**
- `Api\AuthController` (autenticação via Sanctum)
- `Api\ProductController`
- `Api\CartController`
- `Api\OrderController`
- `Api\AddressController`
- `Api\AccountController`

## Livewire Components

- `ProductCard` (exibir produto em grid)
- `CartSummary` (resumo do carrinho no header)
- `CartManager` (gerenciar items do carrinho)
- `CheckoutForm` (formulário checkout)
- `OrderTracking` (rastreamento de pedido)
- `PaginatedProducts` (produtos com paginação)
- `FilterProducts` (filtros: categoria, preço, etc)
- `ProductGallery` (galeria de imagens do produto)

## Seeders

- `RoleAndPermissionSeeder` (criar roles e permissions)
- `AdminUserSeeder` (criar usuário admin padrão)
- `SettingSeeder` (configurações padrão)
- `CategorySeeder` (categorias de exemplo)
- `ProductSeeder` (produtos de exemplo com imagens)
- `PageSeeder` (páginas padrão: privacidade, termos, etc)

## Services

- `CartService` (adicionar, remover, limpar carrinho)
- `OrderService` (criar pedido, atualizar status)
- `PaymentService` (interface para processamento de pagamento)
- `ShippingService` (cálculo de frete - futura integração)
- `ImageService` (processar e redimensionar imagens)

## Views (Blade)

**Layout:**
- `layouts/app.blade.php` (layout principal com navbar e footer)
- `layouts/admin.blade.php` (layout admin com sidebar)
- `layouts/auth.blade.php` (layout para login/registro)

**Frontend:**
- `home.blade.php` (homepage com banners, categorias, destaques)
- `products/index.blade.php` (listagem produtos)
- `products/show.blade.php` (detalhe produto)
- `cart/index.blade.php` (visualizar carrinho)
- `checkout/index.blade.php` (formulário checkout)
- `orders/show.blade.php` (detalhe pedido)
- `account/dashboard.blade.php` (dashboard cliente)
- `account/orders.blade.php` (meus pedidos)
- `account/addresses.blade.php` (meus endereços)
- `pages/show.blade.php` (página estática)
- `auth/login.blade.php`
- `auth/register.blade.php`

**Admin:**
- `admin/dashboard.blade.php`
- `admin/configuration.blade.php` (CMS com editor cores)
- `admin/users/index.blade.php` e CRUD
- `admin/categories/index.blade.php` e CRUD
- `admin/products/index.blade.php` e CRUD com upload
- `admin/orders/index.blade.php` e show
- `admin/pages/index.blade.php` e CRUD
- `admin/banners/index.blade.php` e CRUD
- `admin/media/index.blade.php`

## Componentes Blade

- `navbar.blade.php` (barra de navegação com logo)
- `footer.blade.php` (rodapé com links e contato)
- `breadcrumbs.blade.php` (navegação)
- `alerts.blade.php` (mensagens de sucesso/erro)
- `pagination.blade.php` (paginação customizada)
- `form-errors.blade.php` (exibir erros de validação)

## Funcionalidades CMS

**Configuração de Cores:**
- Salvá-las em `site_settings` (JSON)
- Disponibilizá-las via CSS variables no layout
- Editor visual no painel (color picker)
- Pré-visualização em tempo real

**Editor de Conteúdo:**
- Campos de texto rico (WYSIWYG - Summernote ou similar)
- Upload de imagens com compressão (Intervention/Image)
- Crop de imagens
- Galeria de mídia reutilizável

## Validação e Segurança

- Form requests para validações complexas
- Policies para autorização por modelo
- Middleware de autenticação e papéis
- CSRF protection em todos os forms
- Rate limiting em APIs
- Sanitização de inputs
- Proteção contra SQL injection via Eloquent

## Tests (PHPUnit)

- Testes de autenticação e autorização
- Testes CRUD de produtos
- Testes de carrinho
- Testes de checkout
- Testes de API

## Arquivo .env.example

Com todas as variáveis necessárias:
- APP_NAME, APP_ENV, APP_DEBUG, APP_URL
- DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_ROOT_PASSWORD
- REDIS_HOST, REDIS_PORT
- MAIL_* (SMTP configuration)
- Portas Docker (DOCKER_HTTP_PORT, DOCKER_MYSQL_PORT, etc)

## Estrutura de Diretórios

```
loja-online/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug.md
│   │   ├── feature.md
│   │   └── documentation.md
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── workflows/
│       ├── tests.yml
│       └── lint.yml
├── docs/
│   ├── ARQUITETURA.md
│   ├── DOCKER_DEVELOPMENT.md
│   ├── INSTALACAO_RAPIDA.md
│   ├── INTEGRACAO_FRETE.md (futura)
│   └── API.md (quando implementar)
├── app/
│   ├── Models/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   ├── Api/
│   │   │   └── (públicos)
│   │   ├── Requests/
│   │   ├── Middleware/
│   │   └── Resources/
│   ├── Services/
│   ├── Policies/
│   ├── Livewire/
│   ├── Enums/
│   └── Exceptions/
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── admin/
│   │   ├── public/
│   │   ├── components/
│   │   └── livewire/
│   ├── css/
│   └── js/
├── routes/
│   ├── web.php
│   ├── api.php
│   └── admin.php
├── docker/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── php.ini
│   └── mysql.cnf
├── tests/
│   ├── Feature/
│   └── Unit/
├── storage/
├── public/
├── bootstrap/
├── config/
├── compose.yaml
├── README.md (completo com todas seções)
├── ROADMAP.md (fases e status)
├── .env.example
├── .dockerignore
├── .gitignore
├── composer.json
├── package.json
├── vite.config.js
├── tailwind.config.js
├── phpunit.xml
└── CONTRIBUTING.md (como contribuir)
```

## Arquivos de Documentação que DEVEM Ser Criados

### README.md (Mínimo 500 linhas)
**Seções obrigatórias:**

1. **Cabeçalho com Badges**
   - Status do projeto (Em desenvolvimento)
   - Versões de tech stack
   - License

2. **Índice automático** (GitHub gera automaticamente)

3. **📋 Visão Geral**
   - O que é a plataforma
   - Para quem é
   - Diferencial competitivo

4. **✨ MVP Demonstrável**
   - Funcionalidades já implementadas
   - Capturas de tela/GIFs (opcionais)
   - O que pode ser testado agora

5. **🛠️ Stack Técnica** (em tabela)
   - Backend: PHP 8.3 · Laravel 12
   - Frontend: Livewire 4 · AlpineJS 3 · TailwindCSS
   - Database: MySQL 8.4
   - etc...

6. **📦 Pré-requisitos**
   - Docker Desktop
   - Git

7. **🚀 Como Rodar**
   - Caminho A: Docker (recomendado)
   - Caminho B: Local (sem Docker)

8. **🐳 Serviços e Portas** (tabela)
   - Aplicação (80)
   - MySQL (3306)
   - phpMyAdmin (8081)
   - Mailpit (8025)
   - Vite Dev Server (5173)

9. **📝 Variáveis de Ambiente Importantes** (tabela)
   - APP_ENV, DB_*, MAIL_*, DOCKER_*

10. **👥 Usuários de Teste**
    - Admin: admin@loja.com / password
    - Cliente: customer@teste.com / password

11. **🧪 Testes**
    - Como rodar PHPUnit
    - Cobertura esperada

12. **📊 Status do Projeto**
    - Link direto para ROADMAP.md
    - Fase atual
    - Próximas prioridades

13. **🔒 Segurança e Permissões**
    - Roles utilizados
    - Proteção de rotas

14. **📚 Documentação Adicional**
    - Link para docs/ARQUITETURA.md
    - Link para docs/INSTALACAO_RAPIDA.md
    - Link para docs/DOCKER_DEVELOPMENT.md

15. **🤝 Como Contribuir**
    - Fork e PR
    - Template de issue
    - Código de conduta

16. **📄 License**
    - MIT ou outro

### ROADMAP.md (Rastreamento de Fases)
**Conteúdo mínimo:**

```markdown
# 🗺️ Roadmap - Loja Online

## Status Geral
- Fase Atual: Fase 2 — Setup Base (em desenvolvimento)
- Data de Início: YYYY-MM-DD
- Data Estimada de MVP: YYYY-MM-DD
- Data Estimada de v1.0: YYYY-MM-DD

## Fases do Projeto

### Fase 1 — Infraestrutura Docker & Setup Base
**Status:** ✅ Concluída
**Quando:** Semana 1
**O que inclui:**
- [x] Docker Compose com todos os serviços
- [x] Laravel 12 instalado
- [x] Database migrations estruturada
- [x] Vite + TailwindCSS configurado
- [x] Health checks nos serviços
**Notas técnicas:**
- Volumes nomeados para vendor e node_modules
- WSL2 compatibility
**Próximo passo:** Fase 2

### Fase 2 — CMS e Configurações Admin
**Status:** ⏳ Em desenvolvimento
**Quando:** Semana 2
**O que inclui:**
- [ ] Painel admin funcional
- [ ] CRUD de configurações globais
- [ ] Editor de cores com CSS variables
- [ ] CRUD de páginas estáticas
- [ ] CRUD de banners
- [ ] CRUD de menus
- [ ] Upload e gestão de mídia
**Notas técnicas:**
- SiteSetting model com cache
- Intervention/Image para processamento
**Bloqueadores:** Nenhum
**Próximo passo:** Fase 3

### Fase 3 — Autenticação, Roles e Permissions
**Status:** ⏳ Planejado
**Quando:** Semana 2-3
**O que inclui:**
- [ ] Autenticação com Laravel UI
- [ ] Spatie/laravel-permission configurado
- [ ] CRUD de usuários internos
- [ ] CRUD de perfis (roles)
- [ ] Policies de autorização
- [ ] Middleware de autenticação
**Notas técnicas:**
- Roles: admin, gerente, editor, lojista, customer
- Permissions granulares por módulo
**Bloqueadores:** Nenhum
**Próximo passo:** Fase 4

### Fase 4 — Produtos e Categorias
**Status:** 📋 Planejado
**Quando:** Semana 3
**O que inclui:**
- [ ] Model Product com migrations
- [ ] Model Category
- [ ] CRUD completo de produtos
- [ ] Upload de múltiplas imagens
- [ ] Crop de imagens
- [ ] Listagem pública com filtros
- [ ] Página de detalhe do produto
**Notas técnicas:**
- ProductImage model para galeria
- Slug para URLs amigáveis
- Scopes de filtro (active, by_category)
**Bloqueadores:** Fase 1, 2, 3
**Próximo passo:** Fase 5

### Fase 5 — Carrinho e Checkout
**Status:** 📋 Planejado
**Quando:** Semana 4
**O que inclui:**
- [ ] Cart model com sessão/usuário
- [ ] Adicionar produtos (AJAX)
- [ ] Remover itens
- [ ] Atualizar quantidades
- [ ] Carrinho no header
- [ ] Página de visualização
- [ ] Fluxo de checkout
- [ ] Validação de dados
**Notas técnicas:**
- CartService para lógica
- Livewire component reativo
- Validação de estoque
**Bloqueadores:** Fase 4
**Próximo passo:** Fase 6

### Fase 6 — Pedidos e Rastreamento
**Status:** 📋 Planejado
**Quando:** Semana 4-5
**O que inclui:**
- [ ] Model Order com relacionamentos
- [ ] OrderItem snapshot
- [ ] OrderPayment com status
- [ ] OrderTracking com timeline
- [ ] Admin de gestão de pedidos
- [ ] Atualizar status
- [ ] Notificação ao cliente
- [ ] Página pública de acompanhamento
**Notas técnicas:**
- Order number único
- Transações no checkout
- Queue para notificações
**Bloqueadores:** Fase 5
**Próximo passo:** Fase 7

### Fase 7 — API Mobile (REST + Sanctum)
**Status:** 📋 Planejado
**Quando:** Semana 5
**O que inclui:**
- [ ] Laravel Sanctum configurado
- [ ] Endpoints de autenticação
- [ ] Endpoints de produtos
- [ ] Endpoints de carrinho
- [ ] Endpoints de pedidos
- [ ] Endpoints de endereços
- [ ] Rate limiting
- [ ] Documentação OpenAPI
**Notas técnicas:**
- Token Bearer authentication
- API versioning (/api/v1)
- JSON responses estruturadas
**Bloqueadores:** Fase 6
**Próximo passo:** Fase 8

### Fase 8 — Testes e QA
**Status:** 📋 Planejado
**Quando:** Semana 5-6
**O que inclui:**
- [ ] Testes de autenticação
- [ ] Testes de CRUD
- [ ] Testes de carrinho
- [ ] Testes de checkout
- [ ] Testes de API
- [ ] Cobertura mínima 70%
- [ ] Testes de segurança
**Notas técnicas:**
- PHPUnit com SQLite em memória
- Feature tests com factories
- Refactor baseado em testes
**Bloqueadores:** Todas as fases anteriores
**Próximo passo:** Fase 9

### Fase 9 — Deploy e Produção
**Status:** 📋 Planejado
**Quando:** Semana 6+
**O que inclui:**
- [ ] Setup de CI/CD (GitHub Actions)
- [ ] Deploy em staging
- [ ] Backup automático
- [ ] Monitoramento
- [ ] Logging centralizado
- [ ] HTTPS/SSL
- [ ] Performance tuning
**Notas técnicas:**
- GitHub Actions workflow
- Database migrations seguras
- Rollback strategy
**Bloqueadores:** Fase 8
**Próximo passo:** Pós-MVP

## Pós-MVP (Roadmap Futuro)

### Sprint 2.1 — Integrações de Pagamento
- [ ] Mercado Pago webhook
- [ ] Stripe integration
- [ ] Cupons de desconto

### Sprint 2.2 — Inteligência de Cliente
- [ ] Analytics básico
- [ ] Dashboard de vendas
- [ ] Relatórios administrativos

### Sprint 2.3 — Otimizações
- [ ] Cache L2
- [ ] CDN de imagens
- [ ] Worker de filas em produção

## Métricas de Sucesso

| Métrica | Target | Atual |
|---------|--------|-------|
| Cobertura de testes | 70%+ | - |
| Performance (FCP) | < 1.5s | - |
| Uptime | 99%+ | - |
| Tempo de checkout | < 2 min | - |

## Como Acompanhar

1. **GitHub Issues:** Tags by phase (fase-1, fase-2, etc)
2. **GitHub Projects:** Board com To Do, In Progress, Done
3. **Este arquivo:** Atualizar status toda segunda-feira
4. **Releases:** Tag a cada fase conclusa (v0.1, v0.2, etc)

## Bloqueadores Conhecidos

- [ ] Nenhum no momento

## Últimas Atualizações

- **2026-01-15:** Criação inicial do ROADMAP
- **2026-01-16:** Início Fase 1
- *Próxima revisão: 2026-01-23*
```

### docs/ARQUITETURA.md
Descrever:
- Diagrama de fluxo (em texto ASCII)
- Padrões de código (Service Layer, Policies)
- Relacionamentos entre Models
- Fluxo de requisição
- Camadas da aplicação
- Decisões arquiteturais

### docs/INSTALACAO_RAPIDA.md
Passo a passo mínimo (5 minutos)

### docs/DOCKER_DEVELOPMENT.md
Troubleshooting e dicas de desenvolvimento

### CONTRIBUTING.md
Como contribuir ao projeto

### .github/PULL_REQUEST_TEMPLATE.md
```markdown
## Descrição
Qual problema resolve? Qual feature adiciona?

## Tipo de mudança
- [ ] Bug fix
- [ ] Nova feature
- [ ] Refactor
- [ ] Docs

## Issue relacionada
Fecha #123

## Fase do ROADMAP
Qual fase afeta? (Fase 1, 2, etc)

## Testes
- [ ] Testes novos/atualizados
- [ ] Testes passam localmente
- [ ] Sem warnings do linter

## Checklist
- [ ] Documentação atualizada
- [ ] ROADMAP atualizado se necessário
- [ ] Migrations reversíveis
- [ ] Sem quebra de compatibilidade
```

---

## Instruções de Instalação

No arquivo README.md, incluir:
1. Pré-requisitos (Docker)
2. Passos: `git clone`, `cp .env.example .env`, `docker compose build`, `docker compose up -d`
3. Setup: `docker compose exec app composer install`, `php artisan key:generate`, `php artisan migrate --seed`
4. Acessos: localhost (app), localhost:8081 (phpMyAdmin), localhost:8025 (Mailpit)
5. Usuários de teste (admin e customer)
6. Comandos úteis (artisan, npm, etc)
7. Próximos passos (ver docs/)

## Padrões de Código

- PSR-12 (coding standards)
- Blade components para UI reutilizável
- Service layer para lógica complexa
- Traits para código compartilhado
- Enums para valores fixos (OrderStatus, UserRole, etc)
- Repository pattern para queries complexas (opcional)

## Features Bônus (se houver tempo)

- Carrinho compartilhável via link
- Cupons de desconto
- Wishlist/favoritos
- Avaliações de produtos
- Notificações por email (pedido criado, pagamento confirmado, etc)
- Relatórios admin (vendas, produtos mais vendidos)
- Backup automático do banco

## Sistema de Acompanhamento do Desenvolvimento

### GitHub Issues Strategy
**Categorias de Issue:**
1. **Feature** → Ligada a uma fase do ROADMAP
2. **Bug** → Reportado e precisa ser corrigido
3. **Enhancement** → Melhorias em features existentes
4. **Documentation** → Atualizar docs
5. **Chore** → Manutenção e refator

**Cada issue DEVE ter:**
- Título claro e descritivo
- Label de fase (fase-1, fase-2, etc)
- Label de tipo (feature, bug, docs)
- Descrição detalhada
- Critérios de aceitação (checklist)
- Link para ROADMAP quando aplicável

**Exemplo:**
```
Title: [FEATURE] CRUD de categorias de produtos

Labels: fase-4, feature

Phase: Fase 4 — Produtos e Categorias

Descrição:
Implementar o CRUD completo de categorias de produtos com:

Aceitação:
- [ ] Model Category com migrations
- [ ] Controller Admin\CategoryController
- [ ] Validação de dados
- [ ] Testes unitários
- [ ] Views de lista, create, edit, delete
- [ ] Mensagens flash
- [ ] Integração com produtos

Blocker: Fase 2 e 3 devem estar completas
```

### GitHub Projects Board
Criar board com colunas:
- **Backlog** → Não iniciado
- **Todo** → Pronto para começar
- **In Progress** → Alguém está fazendo
- **In Review** → PR aberto aguardando review
- **Done** → Concluído e mergeado

Atualizar board conforme issues avançam.

### Checklist Pré-Commit
Antes de fazer commit, verificar:
- [ ] Código segue PSR-12
- [ ] Testes passam (`composer test`)
- [ ] Sem warnings do linter (`npm run lint`)
- [ ] Migrations são reversíveis
- [ ] .env.example atualizado se necessário
- [ ] Documentação updated se mudou comportamento
- [ ] ROADMAP.md updated se fase foi concluída

### Checklist Pré-PR
- [ ] Branch atualizada com main
- [ ] 1 feature ou bug fix por PR
- [ ] Título claro (ex: "feat: adiciona CRUD de categorias")
- [ ] Descrição com contexto
- [ ] Referencia issue (#123)
- [ ] Testes incluídos
- [ ] Documentação atualizada
- [ ] Screenshots se for UI

### Checklist Pós-Merge
- [ ] Atualizar ROADMAP.md com progresso
- [ ] Fechar issue correspondente
- [ ] Marcar como Done no GitHub Projects
- [ ] Se fase foi concluída: criar tag (v0.1, v0.2, etc)
- [ ] Atualizar documentação de MUDANÇAS (CHANGELOG.md)

### Releases e Tags
**Versionamento:**
- `v0.1` → Fase 1 concluída
- `v0.2` → Fase 2 concluída
- `v0.3` → Fase 3 concluída
- ... até `v1.0` quando MVP completo

**CHANGELOG.md por release:**
```markdown
# Changelog

## [0.1] - 2026-01-XX

### Added
- Docker Compose setup completo
- Laravel 12 base com Livewire
- Database migrations estructura

### Changed
- N/A

### Fixed
- N/A

### Security
- N/A

[Full Diff](https://github.com/user/loja-online/compare/v0.0...v0.1)
```

---

## ⚠️ Observações Críticas

### 1. Documentação é Código
- **NUNCA** deixar documentação obsoleta
- Atualizar README/ROADMAP quando mudar comportamento
- PR review: se mudou funcionalidade, deve atualizar docs

### 2. Manter Sincronismo
- ROADMAP.md = "verdade" do projeto
- GitHub Issues = tarefas do ROADMAP
- GitHub Projects = visualização de progresso
- Commits = implementação do plano

**Fluxo correto:**
1. Adicionar tarefa ao ROADMAP.md
2. Criar GitHub Issue a partir da tarefa
3. Trabalhar na issue
4. Fazer PR referenciando a issue
5. Atualizar ROADMAP com progresso após merge

### 3. Qualidade de Código
- Use a mesma estrutura e padrões do projeto Feira Esquerda Livre
- Mantenha o código limpo e bem comentado
- Incluir migrations reversíveis
- Health checks nos serviços Docker
- Logs estruturados
- Tratamento de erros apropriado
- Responses JSON bem estruturadas na API
- Documentação inline do código complexo

### 4. Estrutura de Commit
```
tipo(escopo): descrição curta

Descrição mais longa explicando o quê e por quê

Fecha #123
Relacionado com Fase 2

tipos: feat, fix, docs, style, refactor, chore, test
```

### 5. Revisão de Código
- Mínimo 1 aprovação antes de merge
- Verificar: segurança, padrões, testes, docs
- Pedir ajustes se necessário
- Aquém de padrão = rejeitar

---

## Ordem de Criação

**Comece criando NESTA ORDEM:**

1. ✅ **Documentação** (README.md, ROADMAP.md, docs/)
2. ✅ **Docker e Infraestrutura** (Dockerfile, compose.yaml, nginx.conf)
3. ✅ **Laravel Base** (migrations, models, seeders)
4. ✅ **Authentication & Permissions** (roles, policies, middleware)
5. ✅ **Admin Panel** (controllers, views, CMS)
6. ✅ **Products & Categories** (CRUD com upload)
7. ✅ **Shopping Cart & Checkout** (Services, Livewire)
8. ✅ **Orders & Tracking** (Models, notifications)
9. ✅ **API Mobile** (Sanctum, endpoints)
10. ✅ **Tests** (PHPUnit, coverage)
11. ✅ **Deploy** (CI/CD, monitoring)

**⚠️ NÃO pule a documentação!** Ela vai ser seu guia durante todo desenvolvimento.
