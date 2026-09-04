# 🗺️ Roadmap — Loja Online

## Status Geral

- **Fase Atual:** Fase 1 — Infraestrutura Docker & Setup Base ✅
- **Data de Início:** 2026-09-04
- **Data Estimada de MVP Completo:** 2026-09-30
- **Data Estimada de v1.0 Production:** 2026-10-30
- **Progresso Geral:** 11% (Fase 1/9)

---

## 📊 Fases do Projeto

### Fase 1 — Infraestrutura Docker & Setup Base ✅ CONCLUÍDA

**Status:** ✅ **Concluída**  
**Duração Estimada:** Semana 1 (2026-09-04 → 2026-09-10)  
**Responsável:** Arquitetura  

#### Entregáveis
- [x] Docker Compose com todos os serviços configurados
  - PHP-FPM 8.3
  - Nginx 1.27
  - MySQL 8.4
  - Redis 7.x
  - Node.js 20 LTS
  - MailPit (SMTP fake)
  - phpMyAdmin (admin de BD)
- [x] Laravel 12 base instalado e funcionando
- [x] Database schema estruturada
- [x] Migrations base para usuários, roles, permissions
- [x] Seeders base (roles, admin user)
- [x] Vite 7 + TailwindCSS 4 configurado
- [x] Livewire 4 integrado
- [x] AlpineJS 3 pronto
- [x] Health checks em serviços críticos
- [x] .env.example com todas as variáveis
- [x] README.md completo
- [x] ROADMAP.md
- [x] Documentação inicial (docs/)

#### Notas Técnicas
- Volumes nomeados para vendor e node_modules (performance)
- WSL2 compatibility verificado
- Git configurado com .gitignore
- Composer e npm já instalados nos containers
- Artisan tinker acessível

#### Próximo Passo
→ **Fase 2** (CMS e Configurações Admin)

---

### Fase 2 — CMS e Configurações Admin ⏳ EM DESENVOLVIMENTO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 2 (2026-09-10 → 2026-09-17)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **SiteSetting Model**
  - [x] Migration para site_settings (chave => valor JSON)
  - [ ] Model com cache Redis
  - [ ] Setter/getter helpers

- [ ] **Painel Admin Base**
  - [ ] Middleware de autenticação admin
  - [ ] Layout admin com sidebar
  - [ ] Dashboard inicial
  - [ ] Menu de navegação

- [ ] **Configurações Globais**
  - [ ] CRUD de site_settings
  - [ ] Editor de cores (primária, secundária, destaque)
  - [ ] CSS variables gerado dinamicamente
  - [ ] Preview em tempo real
  - [ ] Gerenciar: logo, favicon, email suporte, telefone, endereço

- [ ] **Gerenciar Páginas Estáticas**
  - [ ] Model Page
  - [ ] CRUD de páginas (criar, editar, deletar, publicar)
  - [ ] Slug automático
  - [ ] Meta title e meta description (SEO)
  - [ ] Editor WYSIWYG (Summernote ou similar)
  - [ ] Preview antes de publicar

- [ ] **Gerenciar Banners**
  - [ ] Model Banner
  - [ ] CRUD de banners
  - [ ] Upload de imagens
  - [ ] Ordenação (order field)
  - [ ] Posição (hero, sidebar, footer)
  - [ ] Status ativo/inativo

- [ ] **Gerenciar Menus**
  - [ ] Model Menu + MenuItem
  - [ ] CRUD de menus
  - [ ] Itens com parent/child (hierarquia)
  - [ ] Drag-and-drop de reordenação
  - [ ] URLs customizáveis ou links para pages

- [ ] **Galeria de Mídia**
  - [ ] Model Media
  - [ ] Upload de arquivos (imagens, documentos)
  - [ ] Visualização em grid
  - [ ] Delete com validação (avisar se está em uso)
  - [ ] Compressão de imagens (Intervention/Image)

#### Notas Técnicas
- SiteSetting com cache Redis (5min TTL)
- Intervention/Image para processamento de imagens
- Blade component para color picker
- Middleware CheckIfAdmin para proteger rotas
- Logs de alterações em site_settings

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1 (done)

#### Próximo Passo
→ **Fase 3** (Autenticação, Roles e Permissions)

---

### Fase 3 — Autenticação, Roles e Permissions ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 2-3 (2026-09-17 → 2026-09-24)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Laravel Fortify Setup** (autenticação nativa)
  - [ ] Login com email/password
  - [ ] Registro de clientes
  - [ ] Recuperação de senha
  - [ ] Confirmação de email

- [ ] **Spatie/laravel-permission Configurado**
  - [ ] Migration para roles e permissions
  - [ ] Seeders para roles padrão (admin, gerente, editor, lojista, customer)
  - [ ] 30+ permissions granulares (ver roles-permissions.json)

- [ ] **CRUD de Usuários Admin**
  - [ ] Listar usuários
  - [ ] Criar novo usuário
  - [ ] Editar usuário (dados, role)
  - [ ] Deletar usuário
  - [ ] Atribuir múltiplas roles
  - [ ] Status ativo/inativo

- [ ] **CRUD de Roles**
  - [ ] Listar roles
  - [ ] Criar novo role
  - [ ] Editar permissões de um role
  - [ ] Deletar role (se não tiver usuários)
  - [ ] Atribuir permissions com checkbox

- [ ] **Policies de Autorização**
  - [ ] UserPolicy (quem pode editar quem)
  - [ ] ProductPolicy (quem pode gerenciar)
  - [ ] OrderPolicy (quem pode visualizar)
  - [ ] PagePolicy (quem pode publicar)
  - [ ] Middleware checkAbility em controllers

- [ ] **Middlewares Customizados**
  - [ ] CheckAdmin (se é admin)
  - [ ] CheckRole (se tem role específica)
  - [ ] CheckPermission (se tem permission específica)

- [ ] **Views de Autenticação**
  - [ ] Login (com validação)
  - [ ] Registro (com validação CPF, email)
  - [ ] Reset de senha
  - [ ] Confirmação de email

#### Notas Técnicas
- User model com phone, cpf, status, role_id
- Spatie caching de permissions
- Validação de CPF (Laravel validation rule customizado)
- Email verificado obrigatório para clientes
- Hash de senhas com bcrypt

#### Roles Definidos
| Role | Descrição | Acesso |
|------|-----------|--------|
| admin | Administrador completo | Tudo |
| gerente | Supervisão de vendas | Pedidos, relatórios, usuários (view) |
| editor | Editor de conteúdo | Páginas, banners, menus, mídia |
| lojista | Gestor de produtos | Produtos, categorias, estoque |
| customer | Cliente final | Comprar, perfil, pedidos |

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1 (done)
- ✅ Fase 2 (deve estar concluída)

#### Próximo Passo
→ **Fase 4** (Produtos e Categorias)

---

### Fase 4 — Produtos e Categorias ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 3 (2026-09-24 → 2026-10-01)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Model Category**
  - [ ] Migration (id, name, slug, description, image, is_active, order)
  - [ ] Relacionamento com Products (hasMany)
  - [ ] Scope active() para listar ativas
  - [ ] Slug automático

- [ ] **Model Product**
  - [ ] Migration (id, name, slug, description, short_description, price, cost, sku, image, category_id, is_active, stock, created_by, created_at, updated_at)
  - [ ] Relacionamentos:
    - belongsTo(User, 'created_by')
    - belongsTo(Category)
    - hasMany(ProductImage)
  - [ ] Scopes: active(), byCategory(), featured()
  - [ ] Mutators: formatar preço em R$, datas

- [ ] **Model ProductImage**
  - [ ] Migration (id, product_id, image, order)
  - [ ] Galeria de múltiplas imagens
  - [ ] Reordenação (order field)

- [ ] **CRUD Admin de Categorias**
  - [ ] Listar categorias
  - [ ] Criar categoria (form)
  - [ ] Upload de imagem para categoria
  - [ ] Editar categoria
  - [ ] Deletar categoria (verificar se tem produtos)
  - [ ] Reordenar categorias (drag-drop)
  - [ ] Status ativo/inativo

- [ ] **CRUD Admin de Produtos**
  - [ ] Listar produtos (filtrar por categoria, status)
  - [ ] Criar produto (form com validação)
  - [ ] Upload de múltiplas imagens
  - [ ] Crop de imagens (opcionalmente)
  - [ ] Reordenar imagens
  - [ ] Deletar imagens
  - [ ] Editar produto
  - [ ] Deletar produto
  - [ ] Clonar produto
  - [ ] Importar/exportar (CSV - futuro)
  - [ ] Bulk edit de status/preço

- [ ] **Listagem Pública de Produtos**
  - [ ] GET /produtos (com paginação, filtros)
  - [ ] Filtros: categoria, preço (min-max), ordenação (novo, popular, preço)
  - [ ] Busca por nome/descrição
  - [ ] Componente ProductCard (grid responsivo)
  - [ ] Listar apenas produtos ativos

- [ ] **Página de Detalhe do Produto**
  - [ ] GET /produtos/{slug}
  - [ ] Galeria de imagens com zoom
  - [ ] Informações: nome, preço, descrição, estoque
  - [ ] Botão "Adicionar ao Carrinho"
  - [ ] Avaliação média (futuro)
  - [ ] Produtos relacionados (mesma categoria)
  - [ ] Breadcrumb (Home > Categoria > Produto)

- [ ] **Componentes Livewire**
  - [ ] ProductCard (exibição em grid)
  - [ ] ProductGallery (galeria com zoom)
  - [ ] PaginatedProducts (com filtros)
  - [ ] FilterProducts (sidebar com filtros)

#### Notas Técnicas
- Usar Intervention/Image para processar imagens (redimensionar, thumbnail)
- Slug automático via mutator ou seeder
- Estoque não pode ser negativo (validação)
- Preço em centavos no banco (integer) para evitar float issues
- SKU único por produto

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1, 2, 3

#### Próximo Passo
→ **Fase 5** (Carrinho e Checkout)

---

### Fase 5 — Carrinho e Checkout ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 4 (2026-10-01 → 2026-10-08)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Model Cart**
  - [ ] Migration (id, user_id, session_id, created_at, updated_at)
  - [ ] hasMany CartItem
  - [ ] Sincronizar sessão/login (ao fazer login, copiar carrinho da sessão para usuário)

- [ ] **Model CartItem**
  - [ ] Migration (id, cart_id, product_id, quantity, created_at)
  - [ ] Validação de estoque
  - [ ] Não permitir quantity > estoque disponível

- [ ] **CartService** (lógica complexa)
  - [ ] addItem(productId, quantity)
  - [ ] removeItem(cartItemId)
  - [ ] updateQuantity(cartItemId, newQuantity)
  - [ ] clear()
  - [ ] getTotal() retorna subtotal
  - [ ] getTaxAmount() retorna imposto (futura integração)
  - [ ] getShippingCost() retorna frete (futura integração)

- [ ] **Componente Livewire CartSummary**
  - [ ] Exibir resumo no header
  - [ ] Quantidade de itens
  - [ ] Total em R$
  - [ ] Link para /carrinho

- [ ] **Página de Visualização do Carrinho**
  - [ ] GET /carrinho
  - [ ] Listar todos os itens com imagem, nome, preço
  - [ ] Componente CartManager (Livewire)
    - [ ] Aumentar/diminuir quantidade (AJAX)
    - [ ] Remover item (AJAX com confirmação)
  - [ ] Subtotal, impostos (estimado), frete (estimado), total
  - [ ] Botões: "Continuar Comprando", "Ir para Checkout"

- [ ] **Página de Checkout**
  - [ ] GET /checkout (autenticação obrigatória, redirecionar se not logged)
  - [ ] Componente CheckoutForm (Livewire)
    - [ ] Dados de fatura (nome, email, phone, CPF)
    - [ ] Endereço de entrega (CEP, rua, número, complemento, cidade, estado)
    - [ ] Método de entrega (opções: correios, moto-boy, retire na loja)
    - [ ] Resumo do carrinho (não editável aqui)
    - [ ] Método de pagamento (opções: cartão, boleto, Pix - sem integração agora)
    - [ ] Aceitar termos (checkbox)
  - [ ] Validações em tempo real (Livewire)
  - [ ] Botão "Finalizar Pedido"
  - [ ] Toast de sucesso/erro

- [ ] **POST /pedido** (create order)
  - [ ] Criar Order com items, endereço, pagamento
  - [ ] Snapshot de preços (no order_items, salvar preço praticado)
  - [ ] Diminuir estoque de produtos
  - [ ] Limpar carrinho do usuário
  - [ ] Gerar número de pedido único
  - [ ] Email de confirmação
  - [ ] Redirecionar para página de sucesso

- [ ] **Página de Sucesso**
  - [ ] Exibir número do pedido
  - [ ] Email de confirmação
  - [ ] Link para acompanhar pedido
  - [ ] Opção de imprimir recibo

#### Notas Técnicas
- CartService injetado em controllers via dependency injection
- AJAX via Livewire (não fetch manual)
- Validação de estoque antes de criar order
- Transação de banco no checkout (rollback se falhar)
- Session ID do carrinho do visitante (antes de login)

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1, 2, 3, 4

#### Próximo Passo
→ **Fase 6** (Pedidos e Rastreamento)

---

### Fase 6 — Pedidos e Rastreamento ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 4-5 (2026-10-08 → 2026-10-15)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Model Order**
  - [ ] Migration (id, order_number, user_id, status, subtotal, shipping_cost, discount, total, shipping_address (JSON), notes, created_at)
  - [ ] Order number único, sequencial (ex: 2026000001, 2026000002...)
  - [ ] Relacionamentos: belongsTo(User), hasMany(OrderItem), hasMany(OrderPayment), hasMany(OrderTracking)
  - [ ] Scopes: pending(), processing(), shipped(), delivered(), cancelled()
  - [ ] Mutator para formatação de datas/valores

- [ ] **Model OrderItem**
  - [ ] Migration (id, order_id, product_id, quantity, price, subtotal)
  - [ ] Snapshot do preço (não atualiza se produto mudar de preço)
  - [ ] belongsTo(Order), belongsTo(Product)

- [ ] **Model OrderPayment**
  - [ ] Migration (id, order_id, status, method, transaction_id, paid_at)
  - [ ] Status: pending, approved, declined, refunded
  - [ ] Method: credit_card, boleto, pix, bank_transfer
  - [ ] Transaction ID para rastreamento com gateway (futura)

- [ ] **Model OrderTracking**
  - [ ] Migration (id, order_id, status, message, updated_at)
  - [ ] Timeline de status do pedido
  - [ ] Status: created, confirmed, processing, shipped, in_delivery, delivered, cancelled
  - [ ] Message customizável (ex: "Seu pedido foi despachado!")

- [ ] **OrderService** (lógica de negócio)
  - [ ] createOrder(user, cartItems, shippingAddress, notes)
  - [ ] updateOrderStatus(orderId, newStatus, message)
  - [ ] cancelOrder(orderId)
  - [ ] getOrderTimeline(orderId)
  - [ ] notifyCustomer(orderId, event) → enfileira email

- [ ] **Admin: Listagem de Pedidos**
  - [ ] GET /admin/pedidos
  - [ ] Listar todos os pedidos com status
  - [ ] Filtros: status, data, cliente
  - [ ] Buscar por número de pedido ou email
  - [ ] Ações rápidas: visualizar, atualizar status

- [ ] **Admin: Detalhe do Pedido**
  - [ ] GET /admin/pedidos/{id}
  - [ ] Exibir dados completos: cliente, items, endereço, pagamento, timeline
  - [ ] Atualizar status (dropdown com validação de fluxo)
  - [ ] Adicionar nota/rastreamento
  - [ ] Imprimir recibo/invoice
  - [ ] Cancelar pedido (com motivo)
  - [ ] Histórico de alterações

- [ ] **Público: Acompanhamento de Pedido**
  - [ ] GET /pedido/{number} (público, sem login)
    - [ ] Inserir número do pedido
    - [ ] Inserir email de cadastro
    - [ ] Validar (order_number + email do customer)
  - [ ] Exibir:
    - [ ] Status atual
    - [ ] Data do pedido
    - [ ] Items (produto, quantidade, preço)
    - [ ] Timeline completa de mudanças de status
    - [ ] Endereço de entrega
    - [ ] Método de pagamento

- [ ] **Cliente: Meus Pedidos**
  - [ ] GET /minha-conta/pedidos (autenticado)
  - [ ] Listar pedidos do cliente (pagados e pendentes)
  - [ ] Status badge por pedido
  - [ ] Link para acompanhamento
  - [ ] Paginação

- [ ] **Notificações por Email**
  - [ ] Pedido criado → email com resumo e número
  - [ ] Pagamento confirmado → aviso
  - [ ] Pedido despachado → aviso + rastreamento
  - [ ] Pedido entregue → agradecimento
  - [ ] Templates customizáveis (editável no admin)

- [ ] **Queues** (background jobs)
  - [ ] SendOrderNotificationJob
  - [ ] UpdateOrderStatusJob
  - [ ] Rodar com `php artisan queue:listen` em dev

#### Notas Técnicas
- Order number gerado com data (2026) + sequencial (000001)
- Shipping address salvo como JSON (flexível para país/cidade futura)
- OrderTracking é append-only (não editar, só adicionar)
- Email async via queues (não bloqueia request)
- Transação de banco ao criar order

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1, 2, 3, 4, 5

#### Próximo Passo
→ **Fase 7** (API Mobile - Sanctum)

---

### Fase 7 — API Mobile (REST + Sanctum) ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 5 (2026-10-15 → 2026-10-22)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Laravel Sanctum Configurado**
  - [ ] Token-based authentication
  - [ ] Token expiry em 30 dias
  - [ ] Refresh tokens

- [ ] **API Resources** (transformar models em JSON)
  - [ ] ProductResource (retorna product com imagens)
  - [ ] OrderResource (retorna order com items)
  - [ ] UserResource (retorna usuário sem password)

- [ ] **Endpoints de Autenticação** (/api/v1/auth)
  - [ ] POST /register → criar novo cliente
  - [ ] POST /login → retorna token
  - [ ] POST /logout → invalidar token
  - [ ] GET /me → dados do usuário autenticado
  - [ ] POST /refresh → renovar token

- [ ] **Endpoints de Produtos** (/api/v1)
  - [ ] GET /produtos → listar com filtros (category, page, limit)
  - [ ] GET /produtos/{id} → detalhe
  - [ ] Includes: images, category

- [ ] **Endpoints de Categorias** (/api/v1)
  - [ ] GET /categorias → listar todas

- [ ] **Endpoints de Carrinho** (/api/v1, autenticado)
  - [ ] GET /carrinho → ver carrinho do usuário
  - [ ] POST /carrinho/adicionar → add item (product_id, quantity)
  - [ ] POST /carrinho/remover → remove item (cart_item_id)
  - [ ] POST /carrinho/limpar → clear all
  - [ ] PATCH /carrinho/atualizar → update quantity

- [ ] **Endpoints de Checkout** (/api/v1, autenticado)
  - [ ] POST /checkout → criar order (address, payment_method, notes)
  - [ ] Retorna order_id, order_number

- [ ] **Endpoints de Pedidos** (/api/v1, autenticado)
  - [ ] GET /meus-pedidos → listar pedidos do cliente
  - [ ] GET /pedidos/{id} → detalhe do pedido
  - [ ] GET /pedidos/{id}/rastreamento → timeline

- [ ] **Endpoints de Endereços** (/api/v1, autenticado)
  - [ ] GET /enderecos → listar endereços salvos
  - [ ] POST /enderecos → criar novo
  - [ ] PATCH /enderecos/{id} → editar
  - [ ] DELETE /enderecos/{id} → deletar

- [ ] **Rate Limiting**
  - [ ] 100 requests/minuto por IP (público)
  - [ ] 1000 requests/minuto por token (autenticado)

- [ ] **Validação e Erros**
  - [ ] 400 Bad Request com detalhes de validação
  - [ ] 401 Unauthorized (token inválido/expirado)
  - [ ] 403 Forbidden (não autorizado)
  - [ ] 404 Not Found
  - [ ] 500 Internal Server Error

- [ ] **Documentação OpenAPI/Swagger**
  - [ ] Swagger UI em /api/docs
  - [ ] Especificação OpenAPI 3.0
  - [ ] Testar endpoints pelo Swagger

#### Notas Técnicas
- API versioning (/api/v1) para manutenção futura
- Bearer token authentication via Sanctum
- JSON responses estruturadas (com meta, data, errors)
- Paginação com cursor (não offset, mais escalável)
- Rate limiting via middleware

#### Exemplo de Response
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "Produto",
    "price": 9999,
    "images": [...]
  },
  "meta": {
    "page": 1,
    "total": 100,
    "per_page": 20
  }
}
```

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1-6

#### Próximo Passo
→ **Fase 8** (Testes e QA)

---

### Fase 8 — Testes e Qualidade ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 5-6 (2026-10-22 → 2026-10-29)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Testes de Autenticação**
  - [ ] Login com credenciais válidas
  - [ ] Login com credenciais inválidas
  - [ ] Logout e invalidação de token
  - [ ] Password reset flow

- [ ] **Testes CRUD de Produtos**
  - [ ] Criar produto (validação, upload de imagens)
  - [ ] Listar produtos (filtros, paginação)
  - [ ] Editar produto
  - [ ] Deletar produto
  - [ ] Listar apenas ativos (scope)

- [ ] **Testes de Carrinho**
  - [ ] Adicionar item ao carrinho
  - [ ] Remover item
  - [ ] Atualizar quantidade
  - [ ] Validar estoque
  - [ ] Sincronizar sessão ao login

- [ ] **Testes de Checkout**
  - [ ] Criar order com dados válidos
  - [ ] Validação de dados
  - [ ] Snapshot de preço (não mudar se produto mudar)
  - [ ] Diminuir estoque após order
  - [ ] Gerar número único

- [ ] **Testes de Permissões**
  - [ ] Admin pode acessar /admin
  - [ ] Customer não pode acessar /admin
  - [ ] Editor não pode deletar produtos
  - [ ] Policy validation

- [ ] **Testes de API**
  - [ ] Endpoints de autenticação
  - [ ] Endpoints de produtos (com e sem auth)
  - [ ] Rate limiting funciona
  - [ ] JWT token expiry

- [ ] **Testes de Banco de Dados**
  - [ ] Migrations up/down funcionam
  - [ ] Seeders populam corretamente
  - [ ] Constraints de integridade funcionam

- [ ] **Code Coverage**
  - [ ] Mínimo 70% cobertura de linhas
  - [ ] Relatório HTML gerado
  - [ ] Critérios: >= 60% controllers, >= 80% services

- [ ] **Linting e Formatação**
  - [ ] PHPStan level 5 (static analysis)
  - [ ] PHP-CS-Fixer (code formatting)
  - [ ] Psalm (type checking)

- [ ] **Testes de Segurança**
  - [ ] SQL Injection (usar Eloquent, prepared statements)
  - [ ] XSS (Blade auto-escape)
  - [ ] CSRF (tokens válidos)
  - [ ] Authorization bypass (policies)

- [ ] **Performance**
  - [ ] N+1 queries test (eager loading)
  - [ ] Cache funcionando
  - [ ] Assets minificados (Vite)

#### Notas Técnicas
- PHPUnit com SQLite em memória (testes rápidos)
- Factories para criar dados de teste
- Seeding dentro de testes
- Rollback automático após cada teste
- Testes Feature usam HTTP client
- Testes Unit usam mocking

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1-7

#### Próximo Passo
→ **Fase 9** (Deploy e Produção)

---

### Fase 9 — Deploy e Produção ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 6+ (2026-10-29 →)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **GitHub Actions CI/CD**
  - [ ] Workflow para testes (PHPUnit + coverage)
  - [ ] Workflow para linting (PHPStan, PHP-CS-Fixer)
  - [ ] Workflow para deploy (manual)
  - [ ] Status badges no README

- [ ] **Staging Environment**
  - [ ] Setup em servidor staging
  - [ ] Environment específico (.env.staging)
  - [ ] Database separada
  - [ ] Testes pré-deploy

- [ ] **Database Migrations Seguro**
  - [ ] Migrations reversíveis
  - [ ] Backup antes de migrate em prod
  - [ ] Rollback strategy

- [ ] **Backup Automático**
  - [ ] Daily backup do database
  - [ ] Armazenamento em S3 (futuro)
  - [ ] Retenção de 30 dias

- [ ] **Monitoramento**
  - [ ] Uptime monitoring (Pingdom/Uptime Robot)
  - [ ] Error tracking (Sentry)
  - [ ] Performance monitoring (New Relic - futuro)

- [ ] **Logging Centralizado**
  - [ ] Logs estruturados (JSON)
  - [ ] Stack trace completo
  - [ ] Request/response logging (produção: apenas erros)

- [ ] **HTTPS/SSL**
  - [ ] Certificado Let's Encrypt
  - [ ] Auto-renewal
  - [ ] HSTS headers

- [ ] **Performance Tuning**
  - [ ] Database query optimization
  - [ ] CDN setup para assets
  - [ ] Gzip compression
  - [ ] HTTP/2 enabled
  - [ ] Caching headers otimizados

- [ ] **Security Hardening**
  - [ ] Remover debug info em produção
  - [ ] Configurar .env para prod
  - [ ] Rotate app key
  - [ ] Disable artisan commands em prod (via app.php config)
  - [ ] WAF rules (Cloudflare ou similar)

- [ ] **Email Production**
  - [ ] Configurar SMTP real (SendGrid, AWS SES)
  - [ ] SPF, DKIM, DMARC
  - [ ] Email templates testadas

- [ ] **Documentation**
  - [ ] Deployment guide (deployment.md)
  - [ ] Post-deploy checklist
  - [ ] Rollback procedures
  - [ ] Runbook de incidentes

#### Notas Técnicas
- GitHub Actions grátis para repositório público
- Environment secrets para API keys
- Database backup via mysqldump
- Monitoring uptime com webhook alerts

#### Bloqueadores
- ❌ Nenhum

#### Dependências
- ✅ Fase 1-8

#### Próximo Passo
→ **Pós-MVP** (Melhorias e integrações)

---

## 🚀 Pós-MVP — Roadmap Futuro

### Sprint 2.1 — Integrações de Pagamento
**Status:** 📋 Planejado (após v1.0)

- [ ] Mercado Pago integration
  - [ ] Webhook para confirmação de pagamento
  - [ ] Atualizar order status automaticamente
  - [ ] Suporte para boleto e cartão

- [ ] Stripe integration (futuro)
  - [ ] Checkout via Stripe
  - [ ] Webhooks

- [ ] Pix (Bradesco/Banco24h - futuro)

- [ ] Cupons de Desconto
  - [ ] Model Coupon (code, discount_amount, usage_limit)
  - [ ] Validar cupom no checkout
  - [ ] Aplicar desconto ao order total

### Sprint 2.2 — Inteligência de Cliente
**Status:** 📋 Planejado (após v1.0)

- [ ] Analytics Básico
  - [ ] Dashboard de vendas (últimos 7/30 dias)
  - [ ] Produto mais vendido
  - [ ] Receita total
  - [ ] Número de pedidos

- [ ] Relatórios Administrativos
  - [ ] Vendas por período (CSV export)
  - [ ] Produtos mais vendidos
  - [ ] Clientes com mais compras
  - [ ] Taxa de conversão

- [ ] Recommendations
  - [ ] Produtos relacionados (algoritmo simples)
  - [ ] "Você pode gostar" (baseado em compras similares)

### Sprint 2.3 — Otimizações
**Status:** 📋 Planejado (após v1.0)

- [ ] Cache L2
  - [ ] Query cache com Redis
  - [ ] Fragment cache em views

- [ ] CDN de Imagens
  - [ ] Cloudinary ou Imgix integration
  - [ ] Lazy loading automático

- [ ] Worker de Filas
  - [ ] Redis queue em produção
  - [ ] Supervisor para manter processo vivo

### Sprint 3.1 — Wishlist & Avaliações
**Status:** 📋 Planejado

- [ ] Wishlist (favoritos)
  - [ ] Model Wishlist
  - [ ] Compartilhável via link
  
- [ ] Avaliações de Produtos
  - [ ] Rating (1-5 estrelas)
  - [ ] Comentários
  - [ ] Moderação

---

## 📊 Métricas de Sucesso

| Métrica | Target | Atual | Status |
|---------|--------|-------|--------|
| Cobertura de Testes | 70%+ | - | 🔄 Em andamento |
| Performance (FCP) | < 1.5s | - | 📋 Planejado |
| Performance (LCP) | < 2.5s | - | 📋 Planejado |
| Uptime | 99%+ | - | 📋 Planejado |
| Tempo de Checkout | < 2 min | - | 📋 Planejado |
| Mobile Score (Lighthouse) | 85+ | - | 📋 Planejado |
| SEO Score (Lighthouse) | 90+ | - | 📋 Planejado |

---

## 📅 Timeline Completa

```
Setembro 2026
├── 04-10: Fase 1 — Setup ✅ [CONCLUÍDA]
├── 10-17: Fase 2 — CMS & Admin ⏳
├── 17-24: Fase 3 — Auth & Roles ⏳
└── 24-30: Fase 4 — Produtos ⏳

Outubro 2026
├── 01-08: Fase 5 — Carrinho ⏳
├── 08-15: Fase 6 — Pedidos ⏳
├── 15-22: Fase 7 — API Mobile ⏳
├── 22-29: Fase 8 — Testes ⏳
└── 29+  : Fase 9 — Deploy ⏳

Pós-MVP (Novembro+)
├── Sprint 2.1 — Pagamentos
├── Sprint 2.2 — Analytics
└── Sprint 2.3 — Otimizações
```

---

## 🔗 Como Acompanhar

### 1. **GitHub Issues**
Cada tarefa da roadmap tem uma issue correspondente tagada com `fase-X` (fase-1, fase-2, etc) e tipo (feature, bug, docs).

### 2. **GitHub Projects**
Board com colunas: Backlog → Todo → In Progress → In Review → Done

### 3. **Este Arquivo**
Atualizado toda segunda-feira com progresso real.

### 4. **Releases**
- `v0.1` → Fase 1 concluída
- `v0.2` → Fase 2 concluída
- ...
- `v1.0` → MVP completo (Fase 9)

---

## ⚠️ Bloqueadores Conhecidos

- [ ] Nenhum no momento

---

## 📝 Últimas Atualizações

- **2026-09-04:** Criação inicial do ROADMAP
- **2026-09-04:** Fase 1 — Infraestrutura completa ✅
- *Próxima revisão: 2026-09-11*

---

## 🙋 Questões Frequentes

**P: Quanto tempo para MVP completo?**  
R: Aproximadamente 30 dias (4 semanas) até Fase 8 concluída.

**P: Posso usar em produção agora?**  
R: Fase 1 está pronta para desenvolvimento. Espere Fase 9 para produção.

**P: Preciso implementar tudo?**  
R: Não. Priorize as fases 1-6 para MVP. Fases 7-9 são para release.

**P: Como colaborar?**  
R: Veja [CONTRIBUTING.md](./CONTRIBUTING.md)

---

**Última atualização:** 2026-09-04  
**Próxima revisão:** 2026-09-11
