# Loja Online - E-commerce Moderno

![Status](https://img.shields.io/badge/status-EM%20DESENVOLVIMENTO-orange?style=for-the-badge)
![Laravel](https://img.shields.io/badge/Laravel-12-red?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.3-blue?style=for-the-badge)
![Docker](https://img.shields.io/badge/Docker-✓-blue?style=for-the-badge)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

## 📋 Visão Geral

**Loja Online** é uma loja virtual em desenvolvimento, construída com Laravel 12, Livewire 4 e Docker. Já oferece CMS para páginas, menus, banners e mídia, além de catálogo público e administração de categorias e produtos, incluindo precificação, promoções e galeria de imagens.

É um **e-commerce próprio de operação única (single-store)**: não é marketplace
e não possui lojistas ou vendedores terceiros. Produtos, estoque, pedidos e
pagamentos pertencem à operação comercial da própria loja.

### Para Quem?
- Operações de loja que precisam de uma solução de e-commerce profissional e flexível
- Desenvolvedoras/es que querem estudar arquitetura Laravel com padrões modernos
- Startups que precisam escalar um e-commerce rapidamente

### Diferencial Competitivo
✨ **CMS e vitrine integrados** ao Laravel
✨ **Ambiente Docker de desenvolvimento** com health checks
✨ **Catálogo mobile-first** com navegação responsiva e galeria de imagens
✨ **Gestão comercial** com SKU, custo, margem e promoções programadas
✨ **Conteúdo protegido** com sanitização de descrições e autorização administrativa

---

## ✨ O que já roda hoje

**Estado em 2026-11-02:** CMS, navegação pública, categorias, produtos e vitrine
foram implementados. A experiência mobile foi validada manualmente pelo
proprietário. O projeto continua em desenvolvimento e não foi validado para
produção.

### ✅ Infraestrutura e funcionalidades disponíveis

- Os 7 serviços Docker sobem e ficam `healthy`
- **Laravel 12.69.1** servindo em http://localhost (HTTP 200)
- **Livewire 4.4.3** instalado (traz o AlpineJS embutido)
- **Vite 7.3.6 + TailwindCSS 4** compilando assets
- MySQL 8.4 com migrations do CMS, catálogo, precificação e galeria
- Cache e filas no Redis (`CACHE_STORE=redis`, database 1)
- 866 testes aprovados; sete testes de concorrência aguardam execução em MySQL de testes
- Laravel Pint e build Vite aprovados
- Catálogo de categorias e produtos, SKU automático, custo, margem e promoções programadas
- Editor de descrição sanitizado e compatibilidade com descrições Markdown existentes
- Galeria de até seis imagens por produto, miniaturas e zoom
- Navegação CMS hierárquica e layouts responsivos para celular, tablet e desktop
- Pacotes do projeto instalados e prontos para as próximas fases:
  spatie/laravel-permission, laravel/sanctum, intervention/image,
  barryvdh/laravel-dompdf

### Endpoints disponíveis

| Rota | O que verifica |
|------|----------------|
| `/` | Vitrine inicial com banner, navegação e conteúdo de demonstração local |
| `/health` | Aplicação **e** dependências (MySQL + Redis), em JSON |
| `/api/health` | API respondendo |
| `/up` | Health nativo do Laravel — só o framework |
| `/health.php` | Estático: responde mesmo sem o Laravel instalado |

Detalhes da validação em [`docker/VERIFICACAO.md`](./docker/VERIFICACAO.md).

### ⏳ Ainda pendente

Carrinho, checkout, integração de pagamentos, pedidos, acompanhamento de pedidos,
controle de estoque e API mobile ainda não foram implementados. Também permanecem
pendentes a autorização granular por papéis e a validação de implantação em
produção. O painel de conteúdo e catálogo já existe; a Fase 3 continua responsável
por papéis, permissões granulares e administração de usuários.

A Stack Técnica abaixo mistura o que já está instalado com o que é alvo do
projeto. Para o estado real por fase, veja o [ROADMAP.md](./ROADMAP.md).

---

## 🛠️ Stack Técnica

| Camada | Tecnologia | Versão |
|--------|-----------|--------|
| **Backend** | PHP | 8.3 |
| | Laravel | 12 |
| | Laravel Livewire | 4 |
| | Spatie Permission | 8.3.x |
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

```bash
git clone git@github.com:jmarciosilva/projeto-loja-online.git
cd projeto-loja-online

cp .env.example .env
# Edite .env: defina ADMIN_EMAIL e ADMIN_PASSWORD para sua conta local

docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
# Opcional, apenas quando quiser criar conteúdo demonstrativo em ambiente local:
docker compose exec app php artisan db:seed --class=DemoStorefrontSeeder
docker compose exec app php artisan storage:link
docker compose exec node npm install
docker compose exec node npm run build
```

Acesse http://localhost. Entre no painel por **Administração** no cabeçalho ou
diretamente em `/login`; depois use as credenciais definidas no `.env`.

> `vendor/` e `node_modules/` ficam em volumes nomeados do Docker, não no
> host — por isso `composer install` e `npm install` precisam rodar **dentro**
> dos containers. Rodar no host preenche pastas que os containers não usam.

> `storage:link` cria o link `public/storage → storage/app/public`, que é o que
> torna os arquivos públicos acessíveis por `/storage/...`. O link **não é
> versionado** (está no `.gitignore`), então cada instalação precisa rodar o
> comando — sem ele, arquivos gravados no disco `public` existem no servidor
> mas respondem 404. O comando é idempotente: rodá-lo de novo apenas informa
> que o link já existe.

### Verificar

```bash
docker compose ps                       # serviços Up, os com healthcheck healthy
curl http://localhost/health.php         # OK — responde mesmo sem o Laravel
docker compose exec app composer test    # suíte de testes
docker compose exec app ls -l public/storage   # deve apontar para storage/app/public
```

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
| `ADMIN_NAME` | Administrador da Loja | Nome da conta administrativa local |
| `ADMIN_EMAIL` | (definir no `.env`) | E-mail da conta criada pelo seeder local |
| `ADMIN_PASSWORD` | (definir no `.env`) | Senha da conta criada pelo seeder local |
| `DOCKER_HTTP_PORT` | 80 | Porta HTTP do container |
| `DOCKER_MYSQL_PORT` | 3306 | Porta MySQL mapeada |
| `DOCKER_PHPMYADMIN_PORT` | 8081 | Porta phpMyAdmin |
| `DOCKER_MAILPIT_PORT` | 8025 | Porta MailPit |

> Edite o arquivo `.env` para customizar. Copie `.env.example` como base.

---

## 👥 Acesso administrativo local

Acesse `/login` ou use o link **Administração** no cabeçalho da loja. Para
inicializar o acesso local ao catálogo, defina `ADMIN_EMAIL` no `.env` e execute:

```bash
docker compose exec app php artisan db:seed
```

Se esse e-mail ainda não existir, também defina `ADMIN_PASSWORD` com pelo menos
12 caracteres para criar a conta. Se já existir, o bootstrap apenas habilita
`can_manage_catalog`; não altera nome, senha ou demais dados da conta. A rotina
é limitada a `APP_ENV=local` e não habilita outros usuários. Sem as configurações
necessárias, nenhuma conta insegura é criada. O painel atual reúne configurações,
identidade visual, páginas, banners, menus e biblioteca de mídia. O controle
granular por papéis e permissões permanece planejado na Fase 3.

O conteúdo demonstrativo não é executado pelo `db:seed` padrão. Para solicitá-lo
explicitamente em ambiente local, execute `php artisan db:seed --class=DemoStorefrontSeeder`.
Ele cria páginas, tema, banner e menu somente
quando estão ausentes; não substitui conteúdo existente nem cria produtos ou
categorias fictícias. A imagem do banner está versionada junto ao seeder; nenhum
arquivo é baixado de serviço externo durante a carga.

Páginas demonstrativas removidas logicamente não são restauradas. Menus e
banners não possuem exclusão lógica nem marca de origem no schema; por isso, se
forem removidos fisicamente, uma execução manual posterior não consegue
distinguir essa remoção de um banco ainda sem esses registros e poderá criá-los
novamente. Evite executar o seeder novamente quando a intenção for manter essas
exclusões.

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

```
projeto-loja-online/
├── app/
│   ├── Http/Controllers/
│   ├── Models/
│   ├── Providers/
│   └── Services/
├── bootstrap/  config/  database/  resources/  routes/  storage/  tests/
├── docker/
│   ├── Dockerfile                # PHP-FPM 8.3 + extensões
│   ├── Dockerfile.mysql          # MySQL 8.4 com mysql.cnf embutido
│   ├── entrypoint.sh             # Corrige permissões de storage/ no boot
│   ├── nginx.conf  php.ini  php-fpm.conf  mysql.cnf
│   └── VERIFICACAO.md            # Resultados da validação da stack
├── docs/
│   ├── ARQUITETURA.md  DOCKER_DEVELOPMENT.md  INSTALACAO_RAPIDA.md
├── public/
│   ├── index.php                 # Front controller do Laravel
│   └── health.php                # Liveness, independente da aplicação
├── compose.yaml
├── composer.json  package.json  vite.config.js  phpunit.xml
├── README.md  ROADMAP.md  CONTRIBUTING.md
└── prompt-loja-online.md         # Especificação original do projeto
```

As pastas do desenho-alvo que **ainda não existem** — `app/Policies/`,
`app/Livewire/`, `app/Enums/` — estão descritas em
[docs/ARQUITETURA.md](./docs/ARQUITETURA.md).

---

## 🧪 Testes

```bash
docker compose exec app composer test
```

Atualmente a suíte contém testes da infraestrutura e da fundação do CMS. A Fase
8 ampliará a cobertura global do e-commerce e consolidará a meta de cobertura
do projeto.

Formatação com Laravel Pint:

```bash
docker compose exec app vendor/bin/pint
```

---

## 📊 Status do Projeto

**Fase Atual:** Fase 2 — CMS e Configurações Admin (em desenvolvimento; F2.6-C concluída; catálogo E02–E02.3 implementado)
**Data de Início:** 2026-09-04
**Progresso:** Fase 1 concluída; Fase 2 em andamento; catálogo da Fase 4 parcialmente entregue pela E02.

| Fase | Status | Entregável |
|------|--------|-----------|
| 1a — Infraestrutura Docker | ✅ Concluída | 7 serviços healthy, validados |
| 1b — Bootstrap do Laravel | ✅ Concluída | Laravel 12.69.1, Livewire 4.4.3, Vite 7 |
| 2 — CMS e Admin | ⏳ Em desenvolvimento | Configurações, páginas, banners e menus; F2.6 concluída |
| 3 — Autenticação & Roles | ⏳ Planejado | Roles, permissions, CRUD usuários |
| 4 — Produtos e Categorias | ⏳ Parcialmente implementada | E02–E02.3: catálogo, precificação, promoções, editor, galeria e responsividade; ver pendências no ROADMAP |
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
- **Operador** — Operação cotidiana de pedidos, estoque e atendimento
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
| [docs/LOGISTICA_L01.2.md](./docs/LOGISTICA_L01.2.md) | Perfis logísticos de produtos e preparação local de cotação |
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
