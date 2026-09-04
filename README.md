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

## ✨ O que já roda hoje

**Estado em 2026-09-04: a infraestrutura Docker está pronta e validada; o
Laravel ainda não foi instalado.**

### ✅ Pronto — Infraestrutura Docker

- Os 7 serviços sobem e ficam `healthy` a partir de `docker compose up -d --build`
- PHP 8.3 com bcmath, gd, mbstring, pdo_mysql, pcntl, sockets, zip, redis, xdebug
- MySQL 8.4 e Redis acessíveis a partir do container da aplicação
- Nginx servindo via php-fpm (`/health.php` → HTTP 200)
- phpMyAdmin e MailPit acessíveis
- Reprodutível do zero — resultados em [`docker/VERIFICACAO.md`](./docker/VERIFICACAO.md)

### ⏳ Ainda não existe

Nada da aplicação foi escrito. Não há `composer.json`, `artisan`, `app/`,
`routes/`, migrations, models nem seeders. **`GET /` responde 403** porque
`public/` não tem `index.php`.

A Stack Técnica abaixo descreve o alvo do projeto, não o que está instalado.
Para o estado real por fase, veja o [ROADMAP.md](./ROADMAP.md).

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

### Passo 1 — Subir a infraestrutura (funciona hoje)

```bash
git clone git@github.com:jmarciosilva/projeto-loja-online.git
cd projeto-loja-online

cp .env.example .env

docker compose up -d --build
```

Aguarde os health checks e confirme:

```bash
docker compose ps                      # todos Up, os com healthcheck healthy
curl http://localhost/health.php        # deve responder OK
```

### Passo 2 — Instalar o Laravel (ainda não feito)

O projeto Laravel ainda não existe no repositório. Para criá-lo:

```bash
docker compose exec app composer create-project laravel/laravel:^12 .
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec node npm install
docker compose exec node npm run build
```

> Note que é `create-project`, não `composer install`: não há `composer.json`
> no repositório ainda. Depois que o Laravel existir, `composer install` passa
> a ser o comando correto para novos clones.

---

## 🐳 Serviços e Portas

| Serviço | Porta | URL | Descrição |
|---------|-------|-----|-----------|
| **Aplicação Web** | 80 | http://localhost | Frontend + Backend (Nginx) |
| **MySQL** | 3306 | localhost:3306 | Banco de dados (conexão interna) |
| **phpMyAdmin** | 8081 | http://localhost:8081 | Gerenciador visual de BD |
| **MailPit** | 8025 | http://localhost:8025 | Interceptar emails em desenvolvimento |
| **Vite Dev Server** | — | não publicado | Publicar a porta no `compose.yaml` se for usar o dev server |
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

⏳ **Ainda não existem.** Dependem dos seeders da Fase 3 (Autenticação e Roles).

Os papéis planejados são admin, gerente, editor, lojista e customer — a
definição está no [ROADMAP.md](./ROADMAP.md#fase-3--autenticação-roles-e-permissions).
Quando os seeders forem escritos, as credenciais de desenvolvimento entram aqui.

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

### Executar comandos Artisan (após instalar o Laravel)
```bash
docker compose exec app php artisan <comando>

# Exemplos
docker compose exec app php artisan tinker
docker compose exec app php artisan make:model Product -m
docker compose exec app php artisan migrate:rollback
```

### Executar npm/node (após instalar o Laravel)
```bash
docker compose exec node npm run dev
docker compose exec node npm run build
```

### Acessar o shell do container
```bash
docker compose exec app sh
docker compose exec node sh
```

> As imagens são Alpine e **não têm `bash`** — `docker compose exec app bash`
> falha com `executable file not found`. Use `sh`.

---

## 📂 Estrutura de Diretórios

### O que existe hoje

```
projeto-loja-online/
├── .github/
│   └── PULL_REQUEST_TEMPLATE.md
├── docker/
│   ├── Dockerfile                # Imagem PHP-FPM 8.3 + extensões
│   ├── Dockerfile.mysql          # MySQL 8.4 com mysql.cnf embutido
│   ├── nginx.conf                # Reverse proxy → php-fpm
│   ├── php.ini                   # Overrides de PHP
│   ├── php-fpm.conf              # Pool php-fpm + endpoint /ping
│   ├── mysql.cnf                 # Tuning do MySQL
│   └── VERIFICACAO.md            # Resultados da validação da stack
├── docs/
│   ├── ARQUITETURA.md
│   ├── DOCKER_DEVELOPMENT.md
│   └── INSTALACAO_RAPIDA.md
├── public/
│   └── health.php                # Endpoint de liveness
├── compose.yaml
├── .env.example
├── .dockerignore
├── .gitignore
├── README.md
├── ROADMAP.md
├── CONTRIBUTING.md
└── prompt-loja-online.md         # Especificação original do projeto
```

### Estrutura planejada (após instalar o Laravel)

O layout abaixo é o alvo descrito em [docs/ARQUITETURA.md](./docs/ARQUITETURA.md).
**Nenhuma dessas pastas existe ainda.**

```
├── app/
│   ├── Models/
│   ├── Http/{Controllers,Requests,Middleware,Resources}/
│   ├── Services/                 # Lógica de negócio
│   ├── Policies/                 # Autorização por modelo
│   ├── Livewire/                 # Componentes reativos
│   └── Enums/
├── database/{migrations,factories,seeders}/
├── resources/{views,css,js}/
├── routes/{web.php,api.php}
├── tests/{Feature,Unit}/
├── config/  bootstrap/  storage/
├── composer.json  package.json
├── vite.config.js  tailwind.config.js
└── phpunit.xml
```

---

## 🧪 Testes

⏳ **Ainda não há testes** — dependem do Laravel instalado (Fase 1b) e são o
foco da Fase 8.

O plano é PHPUnit com SQLite em memória, cobertura mínima de 70%, e scripts
`composer test` / `composer lint` definidos no `composer.json`. Detalhes em
[ROADMAP.md](./ROADMAP.md) e nas convenções do [CONTRIBUTING.md](./CONTRIBUTING.md).

---

## 📊 Status do Projeto

**Fase Atual:** Fase 1 — Infraestrutura Docker & Setup Base ⏳ **parcial**
(Docker concluído, Laravel pendente)
**Data de Início:** 2026-09-04
**Progresso:** ~6% (metade da Fase 1 de 9)

| Fase | Status | Entregável |
|------|--------|-----------|
| 1a — Infraestrutura Docker | ✅ Concluída | 7 serviços healthy, validados |
| 1b — Bootstrap do Laravel | ⏳ Pendente | Laravel 12, Vite, Livewire |
| 2 — CMS e Admin | ⏳ Planejado | Configurações, páginas, banners, menus |
| 3 — Autenticação & Roles | ⏳ Planejado | Roles, permissions, CRUD usuários |
| 4 — Produtos e Categorias | ⏳ Planejado | CRUD com upload de imagens |
| 5 — Carrinho e Checkout | ⏳ Planejado | Fluxo completo de compra |
| 6 — Pedidos e Rastreamento | ⏳ Planejado | Gestão de pedidos e status |
| 7 — API Mobile (Sanctum) | ⏳ Planejado | Endpoints REST autenticados |
| 8 — Testes e Qualidade | ⏳ Planejado | PHPUnit, cobertura 70%+ |
| 9 — Deploy e Produção | ⏳ Planejado | CI/CD, backup, monitoramento |

**→ [Ver ROADMAP.md completo](./ROADMAP.md)**

---

## 🔒 Segurança e Permissões

⏳ **Planejado** — nada abaixo está implementado; depende das Fases 3 em diante.
Descreve as camadas de segurança previstas para a aplicação:

### Roles (Papéis)
- **Admin** — Acesso total ao sistema
- **Gerente** — Supervisão de pedidos e relatórios
- **Editor** — Edição de conteúdo (páginas, banners, menus)
- **Lojista** — Gerenciamento de produtos e categorias
- **Customer** — Usuário final, pode comprar

### Proteções previstas
- **CSRF Protection** — Tokens em todos os forms
- **SQL Injection** — Eloquent ORM com prepared statements
- **XSS** — Blade auto-escaping
- **Rate Limiting** — Proteção contra brute-force na API
- **Validação de Entrada** — Form Requests customizados
- **Autorização** — Policies por modelo
- **Sanitização** — Inputs limpos antes do banco

---

## 📚 Documentação Adicional

| Documento | Descrição |
|-----------|-----------|
| [ROADMAP.md](./ROADMAP.md) | Fases, progresso real e bloqueadores |
| [docker/VERIFICACAO.md](./docker/VERIFICACAO.md) | Resultados da validação da stack Docker |
| [docs/INSTALACAO_RAPIDA.md](./docs/INSTALACAO_RAPIDA.md) | Setup do ambiente |
| [docs/DOCKER_DEVELOPMENT.md](./docs/DOCKER_DEVELOPMENT.md) | Troubleshooting e debugging |
| [docs/ARQUITETURA.md](./docs/ARQUITETURA.md) | Desenho técnico alvo (ainda não implementado) |
| [CONTRIBUTING.md](./CONTRIBUTING.md) | Padrões de código e fluxo de contribuição |

Planejados: `docs/API.md` (Fase 7) e `CHANGELOG.md` (primeira release).

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

**MIT** pretendida. ⏳ O arquivo `LICENSE` ainda não foi adicionado ao repositório.

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
