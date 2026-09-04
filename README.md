# Loja Online - E-commerce Moderno

![Status](https://img.shields.io/badge/status-EM%20DESENVOLVIMENTO-orange?style=for-the-badge)
![Laravel](https://img.shields.io/badge/Laravel-12-red?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.3-blue?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-✓-blue?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## 📋 Visão Geral

**Loja Online** é uma plataforma de e-commerce moderna, completa e escalável, construída com Laravel 12, Livewire 4 e Docker. Oferece um painel administrativo intuitivo (CMS) com gerenciamento de cores, menus, páginas estáticas e um catálogo de produtos com carrinho e checkout totalmente funcional.

### Para Quem?
- Lojistas que precisam de uma solução de e-commerce profissional e flexível
- Desenvolvedoras/es que querem estudar arquitetura Laravel com padrões modernos
- Startups que precisam escalar um e-commerce rapidamente

### Diferencial Competitivo
✨ **CMS completo** integrado ao Laravel (sem dependências externas pesadas)
✨ **Docker pronto para produção** com health checks e otimizações WSL2
✨ **API REST autenticada** com Sanctum para apps mobile
✨ **Permissões granulares** com Spatie/laravel-permission
✨ **Mobile-first** - design responsivo desde o início
✨ **Performance** - lazy loading, cache, otimizações de imagem
✨ **LGPD compliant** - pronto para regulamentações brasileiras

---

## ✨ MVP Demonstrável

Este projeto implementa as seguintes funcionalidades (atualizado: 2026-09-04):

### Fase 1 — Infraestrutura Docker & Setup Base ✅
- [x] Docker Compose com todos os serviços (PHP-FPM, Nginx, MySQL, Redis, Node, MailPit, phpMyAdmin)
- [x] Laravel 12 base completo
- [x] Database schema estruturada com migrations
- [x] Vite + TailwindCSS v4 configurado
- [x] Livewire 4 integrado
- [x] Health checks em serviços críticos

### Fases 2-9 — Em Planejamento
Veja o **[📊 Status do Projeto](#-status-do-projeto)** abaixo e consulte o [ROADMAP.md](./ROADMAP.md) para detalhes completos.

---

## 🛠️ Stack Técnica

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| **Backend** | PHP | 8.3 |
| | Laravel | 12 |
| | Laravel Livewire | 4 |
| | Spatie Permission | 6.x |
| | Laravel Sanctum | API tokens |
| **Frontend** | AlpineJS | 3 |
| | TailwindCSS | 4 |
| | Vite | 7 |
| **Database** | MySQL | 8.4 |
| | Redis | 7.x |
| **Infraestrutura** | Docker | Latest |
| | Nginx | 1.27 |
| | Node.js | 20 LTS |
| **Ferramentas** | MailPit | SMTP fake |
| | phpMyAdmin | Admin DB |
| | Composer | 2.x |

---

## 📦 Pré-requisitos

- **Docker Desktop** (Windows 10/11, Mac ou Linux)
- **Git**
- **WSL2** (recomendado para Windows para melhor performance)
- Mínimo 2 GB RAM livre para Docker

> ℹ️ Não precisa de PHP, MySQL, Node.js locais — tudo roda em containers!

---

## 🚀 Como Rodar

### Opção A: Docker (Recomendado ⭐)

```bash
# 1. Clone o repositório
git clone https://github.com/jmarciosilva/loja-online.git
cd loja-online

# 2. Copie o arquivo de ambiente
cp .env.example .env

# 3. Build e inicie os containers
docker compose up -d

# 4. Instale dependências PHP
docker compose exec app composer install

# 5. Gere a chave da aplicação
docker compose exec app php artisan key:generate

# 6. Execute as migrations e seeders
docker compose exec app php artisan migrate --seed

# 7. Instale dependências frontend
docker compose exec node npm install

# 8. Compile assets (Vite)
docker compose exec node npm run build
```

**Pronto!** Acesse http://localhost

### Opção B: Local (sem Docker)

Se preferir instalar localmente (não recomendado):

```bash
# Requisitos: PHP 8.3, Composer, MySQL 8.4, Node.js 20+
git clone ...
cd loja-online
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

---

## 🐳 Serviços e Portas

| Serviço | Porta | URL | Descrição |
|---------|-------|-----|-----------|
| **Aplicação Web** | 80 | http://localhost | Frontend + Backend (Nginx) |
| **MySQL** | 3306 | localhost:3306 | Banco de dados (conexão interna) |
| **phpMyAdmin** | 8081 | http://localhost:8081 | Gerenciador visual de BD |
| **MailPit** | 8025 | http://localhost:8025 | Interceptar emails em desenvolvimento |
| **Vite Dev Server** | 5173 | (interno ao container) | Hot reload para assets |
| **Redis** | 6379 | (interno ao container) | Cache e sessions |

---

## 📝 Variáveis de Ambiente Importantes

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `APP_NAME` | Loja Online | Nome da aplicação |
| `APP_ENV` | local | Environment (local, staging, production) |
| `APP_DEBUG` | true | Debug mode (false em produção) |
| `APP_URL` | http://localhost | URL da aplicação |
| `DB_HOST` | mysql | Host do banco (interno Docker) |
| `DB_PORT` | 3306 | Porta MySQL |
| `DB_DATABASE` | loja_online | Nome do banco |
| `DB_USERNAME` | loja_user | Usuário MySQL |
| `DB_PASSWORD` | senha_segura | Senha MySQL |
| `MAIL_MAILER` | log | Mailer (log para dev, smtp para prod) |
| `DOCKER_HTTP_PORT` | 80 | Porta HTTP do container |
| `DOCKER_MYSQL_PORT` | 3306 | Porta MySQL mapeada |
| `DOCKER_PHPMYADMIN_PORT` | 8081 | Porta phpMyAdmin |
| `DOCKER_MAILPIT_PORT` | 8025 | Porta MailPit |

> Edite o arquivo `.env` para customizar. Copie `.env.example` como base.

---

## 👥 Usuários de Teste

Após executar `php artisan migrate --seed`, os seguintes usuários estão disponíveis:

| Email | Senha | Papel | Acesso |
|-------|-------|-------|--------|
| admin@loja.com | password | Admin | /admin (painel completo) |
| gerente@loja.com | password | Gerente | /admin (funcionalidades limitadas) |
| editor@loja.com | password | Editor | /admin (edição de conteúdo) |
| lojista@loja.com | password | Lojista | /admin (gerenciar produtos) |
| customer@teste.com | password | Cliente | Comprar, perfil pessoal |

> ⚠️ **Não use essas credenciais em produção!** Altere as senhas após setup.

---

## 📝 Uso Diário

### Iniciar
```bash
docker compose up -d
```

### Parar
```bash
docker compose down
```

### Ver logs
```bash
# Todos os serviços
docker compose logs -f

# Apenas aplicação
docker compose logs -f app

# Apenas banco de dados
docker compose logs -f mysql
```

### Executar comandos Artisan
```bash
docker compose exec app php artisan <comando>

# Exemplos
docker compose exec app php artisan tinker
docker compose exec app php artisan make:model Product -m
docker compose exec app php artisan migrate:rollback
```

### Executar npm/node
```bash
docker compose exec node npm run dev
docker compose exec node npm run build
```

### Acessar bash do container
```bash
docker compose exec app bash
docker compose exec node bash
```

---

## 📂 Estrutura de Diretórios

```
loja-online/
├── .github/                          # GitHub templates e workflows
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug.md
│   │   ├── feature.md
│   │   └── documentation.md
│   ├── PULL_REQUEST_TEMPLATE.md
│   └── workflows/
│       ├── tests.yml
│       └── lint.yml
├── docs/                             # Documentação técnica
│   ├── ARQUITETURA.md               # Visão técnica detalhada
│   ├── DOCKER_DEVELOPMENT.md        # Desenvolvimento com Docker
│   ├── INSTALACAO_RAPIDA.md         # Setup em 5 minutos
│   ├── API.md                       # Documentação da API
│   └── INTEGRACAO_FRETE.md          # Integrações (futuro)
├── docker/                           # Configurações Docker
│   ├── Dockerfile                   # Imagem PHP-FPM
│   ├── nginx.conf                   # Configuração Nginx
│   ├── php.ini                      # Configurações PHP
│   └── mysql.cnf                    # Configurações MySQL
├── app/
│   ├── Models/                      # Eloquent models
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/              # Controllers do painel
│   │   │   ├── Api/                # Controllers da API
│   │   │   └── (públicos)          # Frontend controllers
│   │   ├── Requests/               # Form requests e validação
│   │   ├── Middleware/             # Middlewares customizados
│   │   └── Resources/              # API resources
│   ├── Services/                   # Lógica de negócio
│   ├── Policies/                   # Autorização por modelo
│   ├── Livewire/                   # Componentes Livewire
│   ├── Enums/                      # Enums (OrderStatus, UserRole, etc)
│   └── Exceptions/                 # Exceções customizadas
├── database/
│   ├── migrations/                 # Migrations do banco
│   ├── factories/                  # Model factories para testes
│   └── seeders/                    # Seeders para populate dados
├── resources/
│   ├── views/
│   │   ├── layouts/               # Layouts base
│   │   ├── admin/                 # Views do painel admin
│   │   ├── public/                # Views públicas
│   │   ├── components/            # Componentes Blade reutilizáveis
│   │   └── livewire/              # Views dos componentes Livewire
│   ├── css/                       # Estilos (TailwindCSS)
│   └── js/                        # JavaScripts (AlpineJS)
├── routes/
│   ├── web.php                    # Rotas web (frontend e admin)
│   ├── api.php                    # Rotas API REST
│   └── admin.php                  # Rotas admin (middleware group)
├── storage/                       # Arquivos de armazenamento
├── public/                        # Arquivos públicos (CSS, JS, imagens)
├── bootstrap/                     # Bootstrap da aplicação
├── config/                        # Arquivos de configuração
├── tests/
│   ├── Feature/                   # Testes de funcionalidade
│   └── Unit/                      # Testes unitários
├── compose.yaml                   # Docker Compose
├── README.md                       # Este arquivo
├── ROADMAP.md                     # Fases do projeto
├── CONTRIBUTING.md                # Guia de contribuição
├── .env.example                   # Variáveis de ambiente (template)
├── .dockerignore                  # Arquivos ignorados no Docker build
├── .gitignore                     # Arquivos ignorados pelo Git
├── composer.json                  # Dependências PHP
├── package.json                   # Dependências Node.js
├── vite.config.js                 # Configuração Vite
├── tailwind.config.js             # Configuração TailwindCSS
├── phpunit.xml                    # Configuração PHPUnit
└── CHANGELOG.md                   # Histórico de versões
```

---

## 🧪 Testes

A aplicação usa **PHPUnit** para testes automatizados.

### Rodar testes
```bash
# Testes Feature
docker compose exec app composer test:feature

# Testes Unit
docker compose exec app composer test:unit

# Todos os testes
docker compose exec app composer test

# Com coverage
docker compose exec app composer test:coverage
```

### Escrita de testes
Veja exemplos em `tests/Feature` e `tests/Unit`. Cada feature nova deve ter testes correspondentes.

---

## 📊 Status do Projeto

**Fase Atual:** Fase 1 — Infraestrutura Docker & Setup Base ✅  
**Data de Início:** 2026-09-04  
**Data Estimada de MVP:** 2026-09-30  
**Data Estimada de v1.0:** 2026-10-30  

### Roadmap Simplificado

| Fase | Status | Entregável | Data Estimada |
|------|--------|-----------|---------------|
| Fase 1 — Setup Docker & Base Laravel | ✅ Concluída | Infraestrutura rodando | Semana 1 |
| Fase 2 — CMS e Admin | ⏳ Planejado | Painel admin com cores, menus, páginas | Semana 2 |
| Fase 3 — Autenticação & Roles | ⏳ Planejado | Roles, permissions, CRUD usuários | Semana 2 |
| Fase 4 — Produtos e Categorias | ⏳ Planejado | CRUD produtos com upload de imagens | Semana 3 |
| Fase 5 — Carrinho e Checkout | ⏳ Planejado | Fluxo completo de compra | Semana 4 |
| Fase 6 — Pedidos e Rastreamento | ⏳ Planejado | Gestão de pedidos e status | Semana 4 |
| Fase 7 — API Mobile (Sanctum) | ⏳ Planejado | Endpoints REST autenticados | Semana 5 |
| Fase 8 — Testes e Qualidade | ⏳ Planejado | Testes unitários e feature | Semana 5 |
| Fase 9 — Deploy e Produção | ⏳ Planejado | CI/CD, backup, monitoramento | Semana 6 |

**→ [Ver ROADMAP.md completo](./ROADMAP.md)**

---

## 🔒 Segurança e Permissões

Esta aplicação implementa múltiplas camadas de segurança:

### Roles (Papéis)
- **Admin** — Acesso total ao sistema
- **Gerente** — Supervisão de pedidos e relatórios
- **Editor** — Edição de conteúdo (páginas, banners, menus)
- **Lojista** — Gerenciamento de produtos e categorias
- **Customer** — Usuário final, pode comprar

### Proteções
- ✅ **CSRF Protection** — Tokens em todos os forms
- ✅ **SQL Injection** — Eloquent ORM com prepared statements
- ✅ **XSS** — Blade auto-escaping
- ✅ **Rate Limiting** — Proteção contra brute-force na API
- ✅ **Validação de Entrada** — Form Requests customizados
- ✅ **Autorização** — Policies por modelo
- ✅ **Sanitização** — Inputs limpos antes do banco

---

## 📚 Documentação Adicional

| Documento | Descrição |
|-----------|-----------|
| [ROADMAP.md](./ROADMAP.md) | Fases detalhadas com progresso e bloqueadores |
| [docs/ARQUITETURA.md](./docs/ARQUITETURA.md) | Diagrama técnico, padrões de código, relacionamentos |
| [docs/INSTALACAO_RAPIDA.md](./docs/INSTALACAO_RAPIDA.md) | Setup em 5 minutos para novo dev |
| [docs/DOCKER_DEVELOPMENT.md](./docs/DOCKER_DEVELOPMENT.md) | Troubleshooting e dicas WSL2 |
| [CONTRIBUTING.md](./CONTRIBUTING.md) | Como contribuir, padrões de código |
| [docs/API.md](./docs/API.md) | Documentação OpenAPI da REST API |

---

## 🤝 Como Contribuir

Quer ajudar? Ótimo! Siga estes passos:

1. **Fork** o repositório
2. **Crie uma branch** para sua feature (`git checkout -b feature/minha-feature`)
3. **Faça seus commits** com mensagens claras (veja [Estrutura de Commit](#estrutura-de-commit))
4. **Abra um Pull Request** referenciando a issue relacionada
5. **Aguarde review** e responda aos feedbacks

### Padrões de Código
- ✅ Segua **PSR-12** (PHP coding standards)
- ✅ Use **Blade components** para UI reutilizável
- ✅ Implemente **Service Layer** para lógica complexa
- ✅ Prefira **Enums** para valores fixos (não strings soltas)
- ✅ Escreva **testes** para novas funcionalidades
- ✅ Atualize **documentação** se mudou comportamento

### Estrutura de Commit
```
tipo(escopo): descrição curta

Descrição mais longa explicando o quê e por quê

Fecha #123

tipos: feat, fix, docs, style, refactor, chore, test
```

Exemplo:
```
feat(produtos): adicionar upload de múltiplas imagens

Implementa Product\ProductImage model para permitir
galeria de imagens por produto com reordenação e deleção.

Relacionado com Fase 4 — Produtos e Categorias
Fecha #42
```

### Code of Conduct
Somos uma comunidade amigável e inclusiva. Trate todos com respeito.

---

## 💡 Princípios Transversais

### Mobile-First
- Design responsivo desde o início
- Testes em dispositivos reais (não só desktop)
- Performance otimizada para conexões 3G/4G

### Performance
- Lazy loading de imagens
- Cache de queries (Redis)
- Compressão de assets (Vite)
- Minificação automática

### LGPD Compliant
- Consentimento de cookies
- Direito ao esquecimento (dados de clientes)
- Política de privacidade clara
- Logs de acesso de dados sensíveis

---

## 📄 License

Este projeto está licenciado sob a **MIT License** — veja o arquivo [LICENSE](./LICENSE) para detalhes.

---

## 📞 Suporte

### Dúvidas?
1. Leia o [docs/INSTALACAO_RAPIDA.md](./docs/INSTALACAO_RAPIDA.md)
2. Consulte [docs/DOCKER_DEVELOPMENT.md](./docs/DOCKER_DEVELOPMENT.md)
3. Abra uma [Issue no GitHub](https://github.com/jmarciosilva/loja-online/issues)

### Relatou um bug?
- Use o template de issue: **[BUG]**
- Inclua passos para reproduzir
- Mencione seu ambiente (Docker version, PHP version, etc)

### Sugestão de feature?
- Use o template de issue: **[FEATURE]**
- Explique o caso de uso
- Relacione com uma fase do ROADMAP se possível

---

**Made with ❤️ by [Seu Nome](https://github.com/jmarciosilva)**

Última atualização: 2026-09-04
