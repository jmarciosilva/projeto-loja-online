# 🗺️ Roadmap — Loja Online

## Status Geral

- **Fase Atual:** Fase 2 — CMS e Configurações Admin ⏳ (iniciada em 2026-09-04)
  - F2.1 — Fundação do CMS: ✅ concluída
  - F2.2 — Fundação do Admin: ✅ concluída
  - F2.3-A — Configurações gerais: ✅ concluída
  - F2.3-B — Tema e cores: ✅ concluída
  - F2.3 permanece parcialmente aberta: a F2.3-C aguarda a F2.7
  - **F2.4 — Páginas Estáticas: ⏳ em desenvolvimento**
    - F2.4-A — Fundação de domínio e persistência: ✅ concluída
    - F2.4-B — Administração e CRUD: 📋 planejada
    - F2.4-C — Publicação, Markdown, preview e SEO: 📋 planejada
  - Próxima etapa planejada: **F2.4-B — Administração e CRUD** (não iniciada)
- **Fase 1:** ✅ Concluída em 2026-09-04
- **Data de Início:** 2026-09-04
- **Data Estimada de MVP Completo:** 2026-09-30
- **Data Estimada de v1.0 Production:** 2026-10-30
- **Progresso Geral:** ~11% (Fase 1 de 9 concluída)

> **Como ler este arquivo:** um item só é marcado `[x]` depois de existir no
> repositório e ter sido executado com sucesso. Itens `[ ]` são planejamento —
> inclusive quando a tecnologia já aparece na Stack Técnica do README.

---

## 📊 Fases do Projeto

### Fase 1 — Infraestrutura Docker & Setup Base ✅ CONCLUÍDA

**Status:** ✅ **Concluída** — infraestrutura Docker e bootstrap do Laravel
**Data de Conclusão:** 2026-09-04
**Duração Estimada:** Semana 1 (2026-09-04 → 2026-09-10)
**Responsável:** Arquitetura

**Resultado final:** 7 containers rodando, 4 endpoints de health em 200,
`composer test` passando.

#### 1a. Infraestrutura Docker ✅ Concluída

- [x] Docker Compose com os 7 serviços
  - [x] PHP-FPM 8.3 (imagem própria, `docker/Dockerfile`)
  - [x] Nginx 1.27 (`docker/nginx.conf`)
  - [x] MySQL 8.4 (imagem própria, `docker/Dockerfile.mysql`)
  - [x] Redis 7
  - [x] Node.js 20 LTS
  - [x] MailPit (SMTP fake)
  - [x] phpMyAdmin
- [x] Extensões PHP compiladas e carregando: bcmath, gd, mbstring, pdo_mysql,
      pcntl, sockets, zip, redis, xdebug
- [x] Health checks em app, nginx, mysql e redis (todos `healthy`)
- [x] Endpoint de liveness (`public/health.php`) respondendo HTTP 200
- [x] Conectividade app → MySQL 8.4.11 e app → Redis verificada
- [x] Tuning do `mysql.cnf` confirmado ativo (não apenas presente)
- [x] `.env.example` com todas as variáveis
- [x] `.dockerignore` e `.gitignore`
- [x] Documentação: README, ROADMAP, ARQUITETURA, INSTALACAO_RAPIDA,
      DOCKER_DEVELOPMENT, CONTRIBUTING, template de PR
- [x] Reprodutível a partir de `docker compose down -v && docker compose up -d --build`

Resultados completos em [`docker/VERIFICACAO.md`](./docker/VERIFICACAO.md).

#### 1b. Bootstrap do Laravel ✅ Concluída

- [x] Laravel 12.69.1 instalado no container
- [x] `APP_KEY` gerada
- [x] Migrations iniciais aplicadas contra o MySQL do compose
      (`users`, `cache`, `jobs` — 3 migrations, schema padrão do framework)
- [x] Vite 7.3.6 + TailwindCSS 4 compilando — configuração CSS-first via
      `@theme` em `resources/css/app.css` (sem `tailwind.config.js`)
- [x] Livewire 4.4.3 instalado (traz o AlpineJS embutido)
- [x] `npm install` funcionando no container `node`
- [x] `composer test` passando (2 testes do esqueleto)
- [x] Laravel Pint disponível para formatação (`vendor/bin/pint`)
- [x] `GET /` serve a aplicação (HTTP 200) com os assets do Vite
- [x] Cache confirmado no Redis (database 1, via `CACHE_STORE=redis`)
- [x] Extensões PHP completas: pdo_mysql, gd, bcmath, intl, exif, opcache,
      zip, redis, sockets, mbstring, pcntl
- [x] Pacotes do projeto instalados: spatie/laravel-permission 8.3.0,
      laravel/sanctum 4.3.3, intervention/image 4.3.2,
      barryvdh/laravel-dompdf 3.1.2
- [x] `routes/api.php` criado via `artisan install:api`
- [x] Layout base (`layouts/app.blade.php`) e home placeholder
- [x] Health checks: `/health` (app + MySQL + Redis), `/api/health`,
      `/up` (nativo do Laravel) e `/health.php` (estático, sem Laravel)

Pendente, mas não bloqueante:

- [ ] Publicar a porta 5173 no `compose.yaml` para usar o dev server do Vite
      (`npm run build` funciona; só o hot reload a partir do host depende disso)

Decisões tomadas contra a especificação original, por serem contraproducentes:

- **`alpinejs` não foi adicionado ao `package.json`.** O Livewire 4 já embute o
  Alpine; instalar em separado cria duas instâncias e o console acusa
  `Alpine has already been initialized`.
- **`tailwind.config.js` não foi criado.** O TailwindCSS 4 é CSS-first — a
  configuração vive no `@theme` do `resources/css/app.css`. Um config JS seria
  peso morto.
- **Não há migration separada de `password_reset_tokens`.** No Laravel 12 essa
  tabela já é criada dentro de `create_users_table`; uma segunda quebraria o
  `migrate`.
- **As migrations do spatie/laravel-permission não foram publicadas.** Isso é
  escopo da Fase 3, junto com os papéis e as policies.

#### Notas Técnicas

Cada decisão abaixo veio de uma falha real ao montar o ambiente — estão
detalhadas em [`docs/DOCKER_DEVELOPMENT.md`](./docs/DOCKER_DEVELOPMENT.md):

- `ext/sockets` exige `linux-headers`; sem ele o build quebra.
- As libs de runtime (libpng, libjpeg-turbo, freetype, libzip, oniguruma) ficam
  permanentes na imagem — descartá-las com as build deps faz `gd` e `zip`
  compilarem mas não carregarem.
- `mysql.cnf` é embutido na imagem, não bind-mountado: no Windows/WSL o bind
  mount o expõe como world-writable e o MySQL o ignora silenciosamente.
- Healthchecks usam `127.0.0.1`, não `localhost` — o wget do BusyBox resolve
  `localhost` como `::1` e o nginx só escuta IPv4.
- `public/health.php` vive no repositório porque o bind mount `./:/app`
  sombreia qualquer arquivo criado no build.
- Volumes nomeados para `vendor` e `node_modules` (performance no Windows).
  Efeito colateral: essas pastas não ficam visíveis no host.
- As imagens são Alpine — use `sh`, não `bash`, no `docker compose exec`.
- `storage/` e `bootstrap/cache` chegam pelo bind mount com dono do host
  (root), mas os workers do php-fpm rodam como `www-data`. Sem correção o
  Laravel devolve 500 com `tempnam(): file created in the system's temporary
  directory` — o PHP caindo no /tmp após falhar a escrita. Resolvido por
  `docker/entrypoint.sh`, que ajusta o dono a cada boot; fazer isso no
  Dockerfile não adianta, porque o bind mount substitui o que a imagem tiver
  nesses caminhos.
- O Laravel 11+ lê `CACHE_STORE`; a antiga `CACHE_DRIVER` é ignorada em
  silêncio. O `.env.example` já vinha com a errada e o cache não estaria indo
  para o Redis.
- O cache do Laravel usa o **database 1** do Redis (`REDIS_CACHE_DB`), então
  `redis-cli KEYS` no db 0 não mostra nada — use `redis-cli -n 1`.
- O bloco `location = /health` do nginx foi removido: ele interceptava a rota
  antes do Laravel, e qualquer rota `/health` da aplicação nasceria morta.
- Editar `docker/nginx.conf` não basta — o arquivo é bind-mount read-only e o
  nginx segue com a config carregada no boot. Exige
  `docker compose restart nginx`.
- `opcache` já vem ativo na imagem oficial e aparece como `Zend OPcache` no
  `php -m` — procurar por "opcache" dá falso negativo.
- Testado em Windows 11 + Docker Desktop. **Não** testado em WSL2 nem Linux.

#### Próximo Passo
→ **Fase 2** (CMS e Configurações Admin)

---

### Fase 2 — CMS e Configurações Admin ⏳ EM DESENVOLVIMENTO

**Status:** ⏳ **Em desenvolvimento**
**Data de Início:** 2026-09-04
**Duração Estimada:** Semana 2 (2026-09-10 → 2026-09-17)
**Responsável:** TBD

A Fase 2 está dividida em sete subfases incrementais. Cada uma é fechada,
testável e entregável por conta própria — o objetivo é evitar um bloco único de
trabalho que só possa ser validado no fim.

**Sequência pretendida**, com F2.1 e F2.2 já concluídas:

```text
F2.3-A → F2.3-B → F2.4 → F2.7 → F2.3-C → F2.5 → F2.6
```

A F2.4 é executada internamente em três etapas, o que **não** altera a
sequência global acima — a divisão é interna à subfase:

```text
F2.3-A → F2.3-B → [F2.4-A → F2.4-B → F2.4-C] → F2.7 → F2.3-C → F2.5 → F2.6
```

Uma subfase posterior não começa automaticamente ao término da anterior. A F2.3
aparece dividida porque suas três partes têm dependências distintas.

Duas decisões arquiteturais explicam essa ordem, e ambas evitam duplicar o
mesmo mecanismo:

- **A F2.7 precede a F2.3-C**, porque logo e favicon usam a biblioteca de mídia
  em vez de upload próprio. Consequência intencional: a F2.3 fica
  **parcialmente executada** após A e B, e só é encerrada quando a F2.7 existir.
- **A F2.7 precede a F2.5**, porque os banners também consomem a biblioteca.

A numeração das subfases é preservada — apenas a ordem de execução muda. A F2.7
não é movida para dentro da F2.3 nem renumerada.

**Modelo de negócio:** e-commerce próprio de operação única (single-store),
sem marketplace, lojistas ou vendedores terceiros. Produtos, estoque, pedidos
e pagamentos pertencem à operação comercial da própria loja.

#### Vocabulário de status

| Marcador | Significado |
| --- | --- |
| ✅ Concluída | Existe no repositório e foi executada/validada com sucesso |
| ⏳ Em desenvolvimento | Trabalho em andamento |
| 📋 Planejado | Escopo definido, sem trabalho iniciado |
| 🚧 Aguardando decisão | Há uma decisão arquitetural pendente que precede a implementação |

Vale o princípio já adotado no projeto: **um item só recebe `[x]` quando existe
no repositório e foi executado/validado com sucesso.**

#### Panorama das subfases

| Subfase | Status | Entregável | Depende de |
| --- | --- | --- | --- |
| F2.1 — Fundação do CMS | ✅ Concluída | Domínio e infraestrutura de configuração | Fase 1 |
| F2.2 — Fundação do Admin | ✅ Concluída | Autenticação, rotas, layout e navegação de `/admin` | Fase 1 |
| F2.3 — Configurações Globais | ⏳ Em desenvolvimento | Configurações gerais, tema e identidade visual integrada à F2.7 | F2.1, F2.2 (C também da F2.7) |
| F2.4 — Páginas Estáticas | ⏳ Em desenvolvimento | CRUD de páginas com SEO e publicação | F2.2 |
| F2.7 — Biblioteca de Mídia | 📋 Planejado | Upload, processamento e consulta de mídia | F2.2 |
| F2.5 — Banners | 📋 Planejado | CRUD de banners com ordenação, sobre a mídia da F2.7 | F2.2, **F2.7** |
| F2.6 — Menus | 📋 Planejado | Menus hierárquicos e itens | F2.2, F2.4 |

> A tabela segue a **ordem de execução**, não a numeração: a F2.7 precede a
> F2.5 porque os banners dependem da biblioteca de mídia.

> A F2.3 e a F2.4 têm subfases internas — `F2.3-A/B/C` e `F2.4-A/B/C`. Elas
> continuam sendo **uma** subfase da Fase 2 cada, e a F2.6 depende da **F2.4
> completa**, não apenas da F2.4-A.

---

#### F2.1 — Fundação do CMS ✅ Concluída

**Objetivo:** estabelecer o domínio e a infraestrutura de configuração do CMS
antes de qualquer interface, para que as subfases seguintes consumam uma base
já testada.

**Progresso interno**

- [x] **F2.1-A — Persistência e contrato do domínio**
  - Commit: `5c9194a9e50a6cbaa13df950de0beee3a4d936d3`
- [x] **F2.1-B — Service Layer, Redis, TTL e invalidação**
  - Commit: `db113a0255791b35f2a8fb88081b6afe95c2f663`
- [x] **F2.1-C — Hardening e fechamento da fundação do CMS**
  - Commit: `d79f83c77b20947b4565eb52f99f2ae3273ebb2b`

**Escopo:** apenas domínio, persistência e cache. **Sem** interface
administrativa, componentes Livewire administrativos ou páginas de
configuração — isso é escopo da F2.3.

**Entregáveis**

- [x] Migration `site_settings`
- [x] Model `SiteSetting`
- [x] Definição do formato `key`/`value`, incluindo a estratégia para valores
      JSON (como serializar, quando desserializar, e como tipar a leitura)
- [x] Service Layer responsável pelas operações de configuração
- [x] Integração com Redis para cache
- [x] Estratégia explícita de invalidação do cache após alterações
- [ ] Helpers/getters/setters — **somente se realmente necessários**; não criar
      açúcar sintático sem uso comprovado

**Testes / critério de aceite**

- [x] Persistência: gravar uma configuração e recuperá-la do banco
- [x] Leitura: ler valores escalares e valores JSON, com o tipo correto
- [x] Atualização: sobrescrever um valor existente
- [x] Cache: leitura subsequente vem do Redis, não do banco
- [x] Invalidação: após alteração, a leitura reflete o novo valor — este é o
      teste que pega o defeito mais provável desta subfase

**Dependências:** Fase 1 (concluída).

**Bloqueadores / decisões pendentes:** nenhum.

---

#### F2.2 — Fundação do Admin ✅ Concluída

**Objetivo:** entregar incrementalmente a fundação administrativa, sem
antecipar a autorização definitiva da Fase 3.

**Concluída em 2026-09-04**, com as três subfases implementadas e validadas:

| Subfase | Entregou |
| --- | --- |
| F2.2-A | Fortify mínimo, login, logout e proteção de `/admin` com `auth` |
| F2.2-B | Layout administrativo único, sidebar, topbar, breadcrumbs e responsividade básica |
| F2.2-C | Dashboard estrutural, navegação ativa, testes de integração e regressões de autenticação e layout |

Detalhes e evidências de cada uma nas seções abaixo.

**Sequência interna:** F2.2-A → F2.2-B → F2.2-C. A F2.2 só seria concluída
quando as três subfases estivessem concluídas e validadas — condição
satisfeita.

**Decisão arquitetural registrada — Opção 1: autenticação mínima na F2.2**

Visitantes não autenticados não acessam `/admin`; qualquer usuário autenticado
acessa provisoriamente a infraestrutura administrativa. Essa barreira de
autenticação não representa a política definitiva de autorização administrativa.

Continuam exclusivamente na **Fase 3**: roles, permissions, Spatie Permission,
autorização granular, policies, middleware baseado em role ou permission, CRUD
de usuários e roles, a definição definitiva dos perfis que acessam `/admin` e
as regras de `admin`, `gerente`, `editor`, `operador` e `customer`.

##### F2.2-A — Fortify mínimo + autenticação ✅ Concluída

**Objetivo:** estabelecer a autenticação web mínima para proteger `/admin`,
usando Laravel Fortify como infraestrutura a ser expandida na Fase 3.

**Concluída e validada em 2026-09-04.**
Commit: `bf175ec9a1e3068bf3d67ee1f051c8a07ad3434a`
(`feat(fase-2): adiciona autenticacao minima com Fortify`)

Validação: 7 testes / 34 assertions na suíte da subfase; 31 testes /
87 assertions na suíte completa. `laravel/fortify v1.39.0` com
`features => []`, guard `web` e redirect pós-login para `/admin`.

**Escopo**

- [x] Instalação e configuração mínima do Laravel Fortify
- [x] Login por email/password, sessão autenticada e logout
- [x] View de login e redirect após login para `/admin`
- [x] Middleware `auth` e proteção de `/admin`
- [x] View administrativa provisória mínima e testes da autenticação

**Fora do escopo:** roles, permissions, Spatie Permission, policies,
autorização granular, `is_admin`, cadastro de clientes, recuperação/reset de
senha, email verification, 2FA, Sanctum tokens, layout administrativo,
sidebar, topbar, breadcrumbs e dashboard real.

##### F2.2-B — Layout administrativo e navegação ✅ Concluída

**Objetivo:** construir sobre a F2.2-A a infraestrutura visual reutilizável do
painel administrativo.

**Concluída e validada em 2026-09-04.**
Commit: `a178dd8d6fa865433f6fe407b223ca98d276a96d`
(`feat(fase-2): adiciona layout administrativo e navegacao`)

Validação: 7 testes / 34 assertions na suíte de autenticação; 31 testes /
87 assertions na suíte completa; Pint sem violações; `git diff --check` limpo.

**Escopo entregue**

- [x] Layout administrativo único (`resources/views/layouts/admin.blade.php`)
- [x] Sidebar (`admin/partials/sidebar.blade.php`)
- [x] Topbar (`admin/partials/topbar.blade.php`)
- [x] Breadcrumbs (`admin/partials/breadcrumbs.blade.php`)
- [x] Migração de `/admin` para o layout — a view deixou de declarar documento
      HTML próprio e passou a usar `@extends('layouts.admin')`
- [x] Responsividade básica sem JavaScript — abaixo de `lg` a sidebar vira
      faixa no topo

**Decisões registradas**

- A sidebar traz apenas o link de Dashboard. Nenhuma seção futura ganhou link:
  apontar para página inexistente é caminho quebrado, não prévia.
- Os breadcrumbs são estáticos de propósito. Uma trilha dinâmica exige mais de
  um nível de navegação, que ainda não existe; aqui apenas se fixa a posição
  visual onde ela aparecerá.
- Nenhum componente Blade, Livewire ou Alpine foi criado — os partials simples
  bastaram para o escopo.

**Fora do escopo:** dashboard funcional, indicadores, métricas, estado ativo
genérico de menu (F2.2-C), roles, permissions, policies e autorização granular
(Fase 3).

**Dependência:** F2.2-A concluída.

##### F2.2-C — Dashboard, navegação ativa e hardening ✅ Concluída

**Objetivo:** completar e endurecer a Fundação do Admin antes das subfases
administrativas seguintes.

**Concluída e validada em 2026-09-04.**
Commit: `359bdf16afe4d97ff7982e49de85c866491f85d7`
(`feat(fase-2): adiciona dashboard e hardening do admin`)

Validação: `AdminAuthenticationTest` com 8 testes / 40 assertions;
`AdminLayoutTest` com 10 testes / 31 assertions; suíte completa com 42 testes /
124 assertions; Pint sem violações; `git diff --check` limpo.

**Escopo entregue**

- [x] Dashboard estrutural inicial
- [x] Identificação do usuário autenticado
- [x] Estado ativo do Dashboard na sidebar, derivado da rota atual
- [x] `aria-current="page"` no item ativo
- [x] Ausência de links para páginas administrativas inexistentes
- [x] Regressão de proteção de `/admin` após logout
- [x] Testes estruturais de layout e navegação
- [x] Validação de que não existe autorização granular na F2.2
- [x] Validação de layout administrativo único

**O dashboard é estrutural, não analítico.** Ele descreve o que a fundação
oferece e cita as próximas áreas em texto corrido, sem link — enquanto as
páginas não existirem, transformá-las em `href` produziria caminhos quebrados.
Nenhuma consulta a banco foi introduzida: o único acesso a dados é o nome do
usuário já carregado da sessão.

**Não foram implementados:** métricas, KPIs, gráficos, consultas de negócio,
services ou repositories de dashboard, menu dinâmico, breadcrumbs dinâmicos,
Livewire ou Alpine para navegação, e autorização granular.

**Nota sobre os testes:** a primeira versão do teste de estado ativo procurava
`aria-current="page"` na página e teria passado mesmo sem o estado ativo — os
breadcrumbs também usam esse atributo. Foi reescrita para casar contra o
próprio `<a>` da sidebar e confirmada removendo o atributo do link e observando
o teste falhar.

**Dependência:** F2.2-B concluída.

**Dependências posteriores:** com a F2.2 concluída, a dependência que
bloqueava F2.3, F2.4 e F2.7 está satisfeita — as três ficam **liberadas para
início**, mantendo o status de planejadas até que o trabalho comece.

As dependências adicionais continuam valendo: a **F2.5 depende da F2.7** (os
banners consomem a biblioteca de mídia), a **F2.6 depende da F2.4** (itens de
menu que apontam para páginas) e a **F2.3-C depende da F2.3-A + F2.7** (logo e
favicon usam a biblioteca de mídia). A ordem de execução detalhada segue
F2.3-A → F2.3-B → F2.4 → F2.7 → F2.3-C → F2.5 → F2.6.

---

#### F2.3 — Configurações Globais ⏳ Em desenvolvimento

**Objetivo:** fornecer a interface administrativa de configurações globais
consumindo o `SiteSettingService`, sem reimplementar persistência ou cache e
sem criar fluxo paralelo de mídia.

A F2.1 já entregou o model `SiteSetting`, o contrato tipado, a persistência, o
`SiteSettingService`, o cache e a invalidação. **A F2.3 consome essa fundação —
não a duplica.**

**Fronteira arquitetural**

A interface desta subfase trabalha sobre um **conjunto explícito de chaves
suportadas pela aplicação**, persistidas por `SiteSettingService`. Ela não:

- reimplementa persistência ou cache;
- acessa `site_settings` diretamente para contornar o Service Layer;
- oferece um CRUD genérico da tabela;
- permite ao administrador criar chaves ou tipos arbitrários;
- cria infraestrutura própria de mídia.

> `site_settings` permanece um mecanismo genérico de armazenamento interno; a
> interface administrativa da F2.3 não é um editor genérico dessa tabela.

Persistência genérica no banco não implica interface administrativa genérica.
A aplicação trabalha com um conjunto conhecido e versionado de configurações.

**Auditoria — fora do escopo**

> Auditoria persistente de alterações administrativas não faz parte da F2.3.
> Caso seja necessária, deverá receber contrato próprio em etapa futura,
> especialmente após a consolidação de usuários, papéis e permissões na Fase 3.

Não serão criados nesta fase: tabela de auditoria, model de audit, activity
log, pacote externo, histórico before/after ou eventos de auditoria.

**Subfases**

| Subfase | Status | Depende de |
| --- | --- | --- |
| F2.3-A — Configurações gerais | ✅ Concluída | F2.1, F2.2 |
| F2.3-B — Tema e cores | ✅ Concluída | F2.3-A |
| F2.3-C — Logo e favicon | 📋 Planejada | F2.3-A **+ F2.7** |

**Dependências internas**

```text
F2.3-A → F2.3-B
F2.3-A + F2.7 → F2.3-C
```

A F2.3-B **não** é pré-requisito técnico da F2.3-C. Não há motivo comprovado
para acoplá-las: cores e arquivos de identidade são independentes entre si.

**Encerramento da F2.3**

A F2.3 só pode ser encerrada depois da F2.3-C, que por sua vez aguarda a F2.7.
Na prática, a F2.3 fica **parcialmente executada** após a conclusão de A e B,
retomando quando a biblioteca de mídia existir. Isso é intencional: dividir a
subfase é preferível a duplicar upload de arquivos só para fechá-la antes.

Com a F2.3-A e a F2.3-B concluídas, a F2.3 está **parcialmente aberta**: falta
apenas a F2.3-C, que aguarda a F2.7. A ordem de execução da Fase 2 segue para a
F2.4 antes de retomar aqui.

**Dependências:** F2.1 (fundação e cache), F2.2 (layout e rotas admin) — ambas
concluídas.

---

##### F2.3-A — Configurações gerais ✅ Concluída

**Objetivo:** entregar a primeira interface administrativa funcional para
edição das configurações textuais globais da loja.

**Concluída e validada em 2026-09-04.**
Commit: `07ce5221669af5b150393c2d57525057f97e1e4e`
(`feat(fase-2): adiciona configuracoes gerais do CMS`)

Validação: 64 testes / 197 assertions na suíte completa; Pint sem violações;
`composer validate` válido; `git diff --check` limpo.

**Entregue:** página `/admin/configuracoes` com `GET` e `PUT` protegidos por
`auth`, em Controller + Blade + Form Request. As quatro chaves — `site.name`,
`site.support_email`, `site.phone` e `site.address` — são persistidas como
`string` exclusivamente via `SiteSettingService`; a leitura aplica defaults sem
persistir, opcionais vazios são normalizados para `''`, e a interface fica
restrita ao conjunto suportado, sem CRUD genérico de `site_settings`. Sidebar e
breadcrumbs integrados. Nada da F2.3-B ou F2.3-C foi antecipado.

**Extensão controlada da fundação F2.1**

A F2.3-A introduziu `SiteSettingService::setMany()` para permitir persistência
atômica de múltiplas configurações, com rollback integral e invalidação
granular de cache somente após o commit. É extensão compatível: `set()` manteve
assinatura e comportamento, e a **F2.1 permanece concluída**, sem reabertura.

**Escopo**

- [x] Nome da loja/site
- [x] Email de suporte
- [x] Telefone
- [x] Endereço
- [x] Formulário administrativo
- [x] Carregamento dos valores já persistidos
- [x] Persistência via `SiteSettingService`
- [x] Validações
- [x] Feedback de sucesso e de erro
- [x] Integração com o layout administrativo da F2.2
- [x] Integração com a sidebar — **somente quando a rota realmente existir**
- [x] Estado ativo da navegação, no padrão iniciado na F2.2-C
- [x] Testes de integração

**Chaves implementadas**

```text
site.name
site.support_email
site.phone
site.address
```

Os valores usam o tipo `string`, respeitando o contrato já fornecido pela
F2.1. Estas chaves **não** viram tabela ou configuração dinâmica de formulário
— são um conjunto conhecido, definido em código.

**Critérios de aceite**

- [x] Guest não acessa a página administrativa
- [x] Usuário autenticado acessa a página
- [x] O formulário carrega os valores já persistidos
- [x] Ausência de configuração usa comportamento/default explicitamente definido
- [x] Submissão válida persiste via `SiteSettingService`
- [x] A alteração reflete na leitura posterior
- [x] Email inválido é rejeitado
- [x] Valores inválidos não sobrescrevem configuração válida
- [x] Sucesso gera feedback ao usuário
- [x] A interface não permite editar chaves arbitrárias
- [x] A sidebar só ganha o link quando a rota existir
- [x] Suíte completa permanece verde
- [x] Pint passa
- [x] `git diff --check` passa

**Fora do escopo:** logo, favicon, editor de cores, CSS variables, preview de
tema, upload, mídia, auditoria persistente e CRUD genérico de `site_settings`.

**Dependências:** F2.1, F2.2 (ambas concluídas).

**Bloqueadores / decisões pendentes:** nenhum bloqueador arquitetural conhecido.

---

##### F2.3-B — Tema e cores ✅ Concluída

**Objetivo:** adicionar as configurações visuais básicas do tema sobre a mesma
fundação do `SiteSettingService`.

**Concluída e validada em 2026-09-04.**
Commit: `98244c3028d9e087669c8c8826ea1ba13bed29ef`
(`feat(fase-2): adiciona tema e cores configuraveis`)

Validação: 96 testes / 307 assertions na suíte completa; Pint sem violações;
`composer validate` válido; `git diff --check` limpo.

**Arquitetura entregue**

`ThemeSettingController` + `UpdateThemeSettingsRequest` + Blade, com escrita por
`SiteSettingService::setMany()` e leitura por um `ThemeService` que concentra
chaves, defaults e acesso. O `ThemeService` não tem persistência própria e não
acessa Model, DB ou Cache diretamente. A fundação da F2.1 e da F2.3-A foi
reutilizada **sem alteração** — nenhuma das duas foi redesenhada.

As cores chegam ao layout público por um **View Composer específico de
`layouts.app`**, e daí para o HTML como CSS variables de runtime.

**Rotas** — `GET` e `PUT` em `/admin/configuracoes/tema`, nomeadas
`admin.settings.theme.edit` e `admin.settings.theme.update`, protegidas por
`auth`. As rotas de configurações gerais permanecem intactas.

**Navegação** — navegação local de Configurações com **Gerais** e **Tema e
cores**. "Identidade visual" não aparece: a F2.3-C ainda não existe. Breadcrumb
da tela de tema: `Dashboard / Configurações / Tema e cores`.

**Escopo entregue**

- [x] Cor primária
- [x] Cor secundária
- [x] Cor de destaque
- [x] Campos administrativos para as três
- [x] Validação de formato de cor
- [x] Persistência via `SiteSettingService`
- [x] Leitura das cores configuradas
- [x] CSS variables
- [x] Aplicação das CSS variables na interface
- [x] Preview mínimo
- [x] Testes

**Chaves implementadas**

```text
theme.primary_color
theme.secondary_color
theme.accent_color
```

Todas com `type = string`, no contrato `#RRGGBB`, **normalizadas para
maiúsculas antes de persistir** — `#abcdef` e `#ABCDEF` são a mesma cor, e
gravar as duas formas faria comparações divergirem.

Defaults, centralizados no `ThemeService`:

```text
theme.primary_color   → #111827
theme.secondary_color → #4B5563
theme.accent_color    → #2563EB
```

A leitura aplica os defaults **sem materializar registros**: abrir a página não
cria configurações que o administrador nunca salvou.

**Validação**

O contrato é estreito de propósito: formas abreviadas de três dígitos, `rgb()`,
`rgba()`, `hsl()`, nomes de cor, `var()` e `url()` são rejeitados. Como os
valores são interpolados dentro de um bloco `<style>`, é esse contrato que
impede conteúdo arbitrário de chegar lá.

**CSS variables — decisão arquitetural**

As cores são configurações de **runtime** e são expostas no HTML renderizado:

```text
--color-primary
--color-secondary
--color-accent
```

Elas **não** são gravadas no bloco `@theme` do Tailwind, que o Vite compila.
Como consequência, alterar as cores **não** modifica `resources/css/app.css`,
**não** exige `npm run build` e **não** exige rebuild ou deploy do frontend — a
mudança aparece nas requisições seguintes, a partir do valor persistido e
cacheado. O `app.css` contém apenas classes semânticas que consomem
`var(--color-*)`.

**Frontend e admin**

As variáveis são aplicadas ao layout público, com consumo real mínimo — não
ficam apenas declaradas. O **painel administrativo permanece visualmente
neutro**: as cores da loja não tematizam sidebar, topbar ou botões do admin, e
aparecem lá somente no contexto da própria configuração e do preview. Decisão
deliberada.

**Preview mínimo**

Visualização simples das cores configuradas, **server-side**: reflete o que
está persistido depois de salvar e recarregar, não acompanha a digitação. Sem
JavaScript, sem Livewire e sem Alpine. Não é um theme builder nem tem autosave.

**Critérios de aceite**

- [x] As três cores podem ser configuradas
- [x] Valores válidos são persistidos
- [x] Valores inválidos são rejeitados
- [x] As CSS variables refletem os valores configurados
- [x] O preview mínimo reflete as cores
- [x] Defaults definidos para configurações ausentes
- [x] Suíte completa permanece verde
- [x] Pint passa
- [x] `git diff --check` passa

**Fora do escopo:** seleção de fontes, construtor visual completo, presets de
tema, dark mode configurável, drag-and-drop, dezenas de propriedades CSS, logo,
favicon, upload e biblioteca de mídia.

**Bloqueadores / decisões pendentes:** nenhum. A F2.3-A, sua dependência,
estava concluída.

---

##### F2.3-C — Logo e favicon 📋 Planejada

**Objetivo:** permitir a configuração da identidade visual baseada em arquivos,
usando a Biblioteca de Mídia criada pela F2.7.

**Dependências:** **F2.3-A + F2.7.** A F2.3-C não começa antes da F2.7.

**Decisão arquitetural**

> A F2.3-C não cria upload próprio de logo ou favicon. Os arquivos usam a
> infraestrutura centralizada de mídia da F2.7.

A razão: evitar dois mecanismos de upload, dois padrões de armazenamento e
processamento duplicado de imagens; permitir reuso dos arquivos; e permitir a
proteção contra exclusão de mídia em uso, que já é contrato da F2.7.

**Escopo**

- [ ] Integração com a F2.7
- [ ] Seleção da logo a partir da biblioteca de mídia
- [ ] Seleção do favicon a partir da biblioteca de mídia
- [ ] Associação da mídia às configurações globais
- [ ] Leitura das referências
- [ ] Renderização da logo
- [ ] Renderização do favicon
- [ ] Proteção das referências
- [ ] Testes

**Referência de mídia**

> `SiteSetting` deverá armazenar uma referência estável à mídia gerenciada pela
> F2.7, conforme o contrato que estiver definido nessa subfase.

Se a referência será ID, UUID, caminho ou outra forma é decisão **interna da
F2.7** — não antecipada aqui.

**Proteção de referências**

Logo e favicon configurados contam como mídia **em uso**. A F2.3-C integra-se
ao contrato de proteção já previsto na F2.7, em vez de criar proteção paralela.

**Critérios de aceite planejados**

- [ ] O administrador seleciona uma logo existente na biblioteca
- [ ] O administrador seleciona um favicon existente na biblioteca
- [ ] A configuração guarda a referência à mídia
- [ ] A leitura resolve corretamente a referência
- [ ] A logo configurada é renderizada no ponto definido
- [ ] O favicon configurado é renderizado
- [ ] Mídia configurada não pode ser removida ignorando a proteção de "em uso"
- [ ] Nenhum fluxo paralelo de upload é criado
- [ ] Suíte completa permanece verde
- [ ] Pint passa
- [ ] `git diff --check` passa

**Fora do escopo:** upload próprio, armazenamento próprio e qualquer
processamento de imagem fora da F2.7.

**Bloqueadores / decisões pendentes:** depende da F2.3-A e da F2.7.

---

#### F2.4 — Páginas Estáticas ⏳ Em desenvolvimento

> **Contrato arquitetural definido antes da implementação.** As decisões abaixo
> foram fechadas e revisadas antes de qualquer código, e permanecem válidas.
>
> A **F2.4-A está concluída**; F2.4-B e F2.4-C continuam planejadas, com todos
> os seus itens em `[ ]`. A F2.4 só é encerrada após a F2.4-C.

**Objetivo:** permitir a criação e publicação de páginas de conteúdo estático
institucional ou editorial, com identidade estável, URL pública por slug, ciclo
simples rascunho/publicado, conteúdo Markdown renderizado com segurança, SEO
mínimo e preview administrativo.

Não é um CMS genérico: o escopo é a página estática, não um construtor de
páginas.

---

##### Divisão interna — F2.4-A → F2.4-B → F2.4-C

A F2.4 é executada em três subfases internas, incrementais e testáveis
isoladamente. A divisão é **documental e organizacional**: ela não altera o
contrato arquitetural já definido, não antecipa implementação e não promove
A/B/C a subfases independentes da Fase 2. A F2.4 continua sendo **uma** subfase
da Fase 2.

```text
F2.4-A → F2.4-B → F2.4-C
```

| Subfase | Status | Entrega principal | Depende de |
| --- | --- | --- | --- |
| F2.4-A — Fundação de domínio e persistência | ✅ Concluída | Entidade `Page`, persistência, `PageStatus`, `SoftDeletes`, **núcleo do `PageService`** e invariantes de slug | F2.2 |
| F2.4-B — Administração e CRUD | 📋 Planejada | Controller, Form Requests, Blades, rotas e integração administrativa sobre o serviço já fundado | F2.4-A |
| F2.4-C — Publicação, Markdown, preview e SEO | 📋 Planejada | Renderização segura, publicação pública, preview e SEO mínimo | F2.4-B |

**Por que dividir:** a F2.4 concentra três preocupações com riscos distintos —
invariantes de domínio, operação administrativa e exposição pública segura.
Validar as três de uma vez só no fim adiaria a descoberta de defeitos para o
ponto mais caro. Cada subfase fecha com suíte verde, Pint e `git diff --check`.

**A F2.4 só é encerrada após a F2.4-C.** Concluir apenas A, ou apenas A e B,
mantém a F2.4 em desenvolvimento — e mantém a F2.6 bloqueada, porque ela
depende da F2.4 **completa**, não somente da identidade entregue pela F2.4-A.

Uma subfase posterior não começa automaticamente ao término da anterior.

---

##### Contratos arquiteturais compartilhados

As decisões desta seção valem para as três subfases internas e não são
redefinidas dentro de A, B ou C.

**Identidade — decisão fundamental**

> **`Page.id` é a identidade interna estável da página; o `slug` é seu endereço
> público e pode mudar.**

Essa separação existe principalmente para preservar a integração futura com a
F2.6. Quando os menus forem implementados, um item interno deverá relacionar-se
à **identidade** da página — conceitualmente `page_id → pages.id` — e **nunca**
usar o slug como chave estrangeira.

Assim, uma página com `id = 17` e `slug = quem-somos` pode passar a
`slug = sobre-a-empresa` sem que o menu perca o vínculo.

A F2.4 **não** implementa `MenuItem`, não define a migration da F2.6 e não
escolhe a modelagem de destinos de menu. Ela apenas fornece a identidade
estável que a F2.6 poderá consumir.

**Conteúdo — Markdown**

> `Page.content` armazena **Markdown**, não HTML arbitrário.

O editor administrativo será inicialmente `textarea` + Markdown. **Não** haverá
WYSIWYG nesta subfase, nem dependência de editor visual — TinyMCE, CKEditor,
TipTap, Quill ou equivalente.

O Markdown é **persistido** pela F2.4-B, mas a responsabilidade de
**renderização pública segura** pertence integralmente à F2.4-C. Nenhum HTML
renderizado é persistido em `pages`.

**Camadas**

`PageService` é o Service Layer da subfase, responsável por criação,
atualização, exclusão lógica, geração e resolução de slug, consulta pública de
página publicada e demais regras da entidade.

Ele é **fundado pela F2.4-A**, junto das invariantes de domínio que implementa,
e apenas **consumido e estendido** por B e C. Não existe uma segunda camada de
regras: Controller, Form Request, Observer e Model não hospedam lógica de slug
ou de ciclo de vida da página.

```text
Admin:    Controller → Form Request → PageService → Page (Eloquent)
Público:  PageController → PageService → Page publicada
                        → PageContentRenderer → Blade
```

> **Repository não é necessário para a F2.4.**

Não serão criados `PageRepository`, `PageRepositoryInterface` nem
`EloquentPageRepository`. As consultas previstas são simples e o Eloquent
atende ao contrato; um repositório aqui seria abstração prematura. A diretriz
geral do projeto mantém o Repository como opcional.

**Autorização durante a Fase 2**

Todas as rotas administrativas da F2.4 são protegidas **somente por `auth`**.
Papéis, policies e permissões permanecem na Fase 3 — mesmo que `editor` venha a
ser o papel natural para páginas, ele não é antecipado aqui, em nenhuma das
três subfases internas.

**A home permanece fora do CMS**

> A rota `/` e a home atual **não** são convertidas em `Page` pela F2.4.

A home comercial é uma preocupação distinta e poderá futuramente combinar
banners, produtos, categorias e outros componentes do e-commerce. A F2.4 trata
apenas de páginas estáticas institucionais e editoriais.

---

##### F2.4-A — Fundação de domínio e persistência ✅ Concluída

**Concluída e validada em 2026-09-05.**
Commit: `253315872d6eda3d6b45e2ce3db9d319e2adcce0`
(`feat(fase-2): adiciona fundacao de paginas estaticas`)

Validação: 47 testes focados / 93 assertions; 143 testes / 400 assertions na
suíte completa; Pint sem violações (52 arquivos); `composer validate` válido;
`git diff --check` limpo.

**Objetivo:** estabelecer a entidade `Page` e suas invariantes **antes** de
construir interface administrativa ou publicação pública. Slug, unicidade,
status e exclusão lógica são regras de domínio — se estiverem erradas aqui,
nenhuma tela ou rota pública corrige.

Por isso a F2.4-A entrega também o **núcleo do `PageService`**: as invariantes
desta subfase precisam existir na camada que a arquitetura já designou para
elas, e precisam ser testáveis sem interface. A alternativa — espalhar geração
de slug por Controller, Observer ou Model só para respeitar a divisão — criaria
exatamente a duplicação de regras que o Service Layer existe para evitar.

**Dependências:** F2.2 (satisfeita).

**Model `Page` — contrato**

```text
pages

id                BIGINT autoincremental — identidade interna estável
title             VARCHAR(255)
slug              VARCHAR(255), UNIQUE
content           LONGTEXT — Markdown
status            VARCHAR, indexado
meta_title        VARCHAR(255), nullable
meta_description  VARCHAR(320), nullable
created_at        timestamp
updated_at        timestamp
deleted_at        timestamp, nullable — SoftDeletes
```

O `id` segue a convenção já usada nas migrations do projeto (`$table->id()`).
**Não** serão introduzidos UUID, ULID ou identificador distribuído: nenhum
requisito atual justifica divergir da convenção existente.

Os campos `meta_title` e `meta_description` são **persistidos** aqui; sua
emissão no HTML público pertence à F2.4-C.

**Fora do contrato desta subfase:** `author_id`, `user_id`, `updated_by`,
`published_by`, `published_at`, `image_id`, `featured_image_id` e `menu_id`.
Autoria depende da Fase 3, imagem depende da F2.7 e vínculo com menu é da F2.6.
Nenhum campo é acrescentado ao schema acima.

**Status — enum PHP**

Dois estados, em um enum PHP `PageStatus` com armazenamento em coluna string:

```text
draft
published
```

**Não** será usado `ENUM` nativo do MySQL. Não entram `scheduled`, `archived`
nem `pending_review`, e não há agendamento de publicação nesta subfase. A
F2.4-A entrega o enum, o cast e a persistência do estado; a **regra de
exposição pública** derivada dele é da F2.4-C.

**Slug — contrato**

O slug é **único, normalizado, editável e público** — e não é a identidade da
entidade.

**Formato**, conceitualmente equivalente a:

```text
^[a-z0-9]+(?:-[a-z0-9]+)*$
```

Não são aceitos espaços, `/`, `..`, query string, fragmento `#`, HTML ou
caracteres arbitrários de URL.

**Na criação**, se o administrador não informar slug, ele é derivado do título:

```text
Política de Privacidade  →  politica-de-privacidade
```

**Colisão de slug gerado automaticamente** resolve-se por sufixo
determinístico, até encontrar disponibilidade:

```text
quem-somos
quem-somos-2
quem-somos-3
```

**Slug informado explicitamente e já ocupado é rejeitado pela validação.** Não
é alterado em silêncio para `-2`: o administrador pediu um endereço específico,
e trocá-lo sem avisar produziria uma URL que ele não escolheu.

**Na atualização**, alterar apenas o título **não** regenera o slug existente —
isso preserva URLs já divulgadas. O slug continua editável explicitamente, e
alterá-lo **não** altera `Page.id`.

**SoftDeletes**

`Page` usa `SoftDeletes`; o `DELETE` administrativo é exclusão lógica. **Não**
haverá lixeira, tela de excluídos, restore ou force delete nesta subfase.

> A unicidade do slug considera também páginas soft-deleted.

Um slug de página excluída logicamente **não** é reutilizado automaticamente:
caso contrário, uma URL antiga passaria a servir conteúdo semanticamente
diferente para quem a tivesse guardado.

**Núcleo do `PageService`**

A F2.4-A funda o `PageService` com as regras que precisam existir e ser
testadas **antes** de qualquer interface:

- criação da `Page`;
- atualização da `Page`;
- exclusão lógica;
- normalização do slug;
- geração automática do slug a partir do título;
- busca de disponibilidade do slug;
- colisão automática por sufixo determinístico (`-2`, `-3`, e seguintes);
- consideração de registros soft-deleted na disponibilidade;
- preservação do slug quando somente o título muda;
- alteração explícita do slug.

> A verificação de disponibilidade do slug considera registros soft-deleted —
> conceitualmente, uma consulta equivalente a `withTrashed()`.

Como isso é implementado internamente não é decidido aqui; o contrato é o
comportamento, não a mecânica.

Essas regras **não** ficam no Model, no Controller nem em um Observer. E a
F2.4-A entrega apenas o núcleo: nada de Controller, Blade, Form Request ou
rotas — a interface administrativa é da F2.4-B.

**Entregáveis planejados**

- [x] Model `Page`
- [x] Migration `pages` conforme o schema acima
- [x] Enum PHP `PageStatus` (`draft`, `published`), persistido em coluna string
- [x] Casts e configuração do model necessários ao contrato
- [x] `SoftDeletes` no model e `deleted_at` na migration
- [x] Geração automática de slug a partir do título
- [x] Normalização do slug conforme o formato definido
- [x] Sufixo determinístico para colisão de slug gerado automaticamente
- [x] Unicidade de slug considerando registros soft-deleted
- [x] Núcleo do `PageService` responsável pelas invariantes da entidade e do slug

**Testes / critério de aceite planejados**

- [x] Criação sem slug gera slug a partir do título
- [x] Slug gerado é normalizado
- [x] Slug é único
- [x] Colisão de slug automático recebe sufixo determinístico
- [x] Slug explícito duplicado é rejeitado
- [x] Alterar apenas o título não altera automaticamente o slug existente
- [x] Alteração explícita do slug é persistida
- [x] Alterar o slug não altera `Page.id`
- [x] Status `draft` e `published` persistem e são lidos pelo enum
- [x] Exclusão usa soft delete
- [x] Slug de página soft-deleted não é reutilizado automaticamente
- [x] Campos não suportados não são persistidos
- [x] As invariantes de slug são exercitadas pelo `PageService`, sem interface
- [x] Nenhuma regra de slug reside no Model, no Controller ou em Observer
- [x] Testes da fundação de domínio e persistência
- [x] Suíte completa permanece verde
- [x] Pint passa
- [x] `git diff --check` passa

**Resultado entregue**

Arquivos adicionados pelo commit técnico:

```text
app/Enums/PageStatus.php
app/Models/Page.php
app/Services/PageService.php
database/migrations/2026_09_05_120000_create_pages_table.php
tests/Feature/PageTest.php
tests/Feature/PageServiceTest.php
```

Schema confirmado no MySQL do ambiente Docker, com `up()`, rollback e
reaplicação executados:

```text
id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
title             VARCHAR(255) NOT NULL
slug              VARCHAR(255) NOT NULL UNIQUE
content           LONGTEXT NOT NULL
status            VARCHAR(20) NOT NULL INDEX
meta_title        VARCHAR(255) NULL
meta_description  VARCHAR(320) NULL
created_at
updated_at
deleted_at
```

Comportamento validado:

- `Page.id` permanece identidade estável; alterar o slug não o altera;
- slug ausente, `null`, vazio ou só com whitespace pede geração automática;
- slug explícito é validado pelo `PageService` — formato canônico e limite;
- slug explícito com tipo inválido é rejeitado, e não tratado como ausente;
- slug explícito duplicado é rejeitado, sem virar `-2` em silêncio;
- slug automático resolve colisão por sufixo determinístico `-2`, `-3`, …;
- o slug final nunca passa de 255 caracteres, e a geração longa reserva o
  espaço exato do sufixo antes de encurtar a base;
- páginas soft-deleted continuam reservando seus slugs;
- alterar apenas o título preserva o slug já publicado;
- o `PageService` continua autoritativo fora do HTTP, sem Form Request;
- a constraint `UNIQUE(slug)` permanece como proteção final contra corrida.

**Decisões da implementação**

- `status` ausente na criação assume `PageStatus::Draft`; `content` ausente
  assume `''`, já que a coluna é `NOT NULL`;
- título vazio ou ausente é rejeitado, assim como um título que não produza
  slug canônico — o slug é derivado dele;
- `InvalidArgumentException` é a exceção usada para entrada que viola o
  contrato, seguindo o que `SiteSetting` já fazia; não há hierarquia própria;
- `slugIsAvailable()` é público e consulta `withTrashed()`, para que a F2.4-B
  consiga dar feedback consultando a regra em vez de reimplementá-la.

> A validação de **tamanho** dos campos de entrada (`title`, `meta_title`,
> `meta_description`) é da camada administrativa da F2.4-B. O `PageService`
> garante o limite apenas do `slug`, que é o campo cuja unicidade e formato ele
> resolve sozinho.

**Fora do escopo da F2.4-A:** Controller, Blade, Form Request, rotas, CRUD
administrativo, rota pública, renderização de Markdown, preview e emissão de
SEO no HTML. O `PageService` entra aqui apenas com seu núcleo de domínio — as
necessidades administrativas e a consulta pública chegam em B e C.

**Bloqueadores / decisões pendentes:** nenhum. A F2.4 segue para a F2.4-B.

---

##### F2.4-B — Administração e CRUD 📋 Planejada

**Objetivo:** construir a operação administrativa das páginas sobre o domínio e
o `PageService` já estabilizados pela F2.4-A, sem ainda expor nada
publicamente.

**Dependências:** **F2.4-A.**

**Consumo do Service Layer**

```text
Controller → Form Request → PageService → Page (Eloquent)
```

> O `PageService` **não é criado nesta subfase** — ele já foi fundado pela
> F2.4-A, com criação, atualização, exclusão lógica e todas as regras de slug.
> A F2.4-B o **consome**.

O Controller não acessa o Eloquent diretamente para contornar o Service, e
**não reimplementa nem duplica as regras de slug**. O Form Request valida
entrada — formato, obrigatoriedade, tamanho e, inclusive, slug explícito já
ocupado — para devolver erro amigável ao formulário.

> Essa validação é uma **barreira antecipada de entrada e de experiência do
> usuário**. A invariante de disponibilidade e unicidade do slug continua
> garantida pelo `PageService`, inclusive para chamadas que não passem por
> HTTP — comando de console, seeder, teste ou qualquer outro consumidor.

O Form Request, portanto, **não é a fonte autoritativa da regra**, e não
reimplementa o algoritmo de geração ou de resolução de slug. Geração,
normalização, disponibilidade, colisão automática, consideração de registros
soft-deleted e preservação/alteração do slug permanecem no `PageService`,
conforme fundado pela F2.4-A.

Se a listagem administrativa exigir consultas que ainda não existam, o
`PageService` pode ganhar métodos de consulta. Isso é **extensão compatível**
do serviço fundado na F2.4-A — no mesmo espírito do `setMany()` acrescentado ao
`SiteSettingService` pela F2.3-A —, e não uma segunda camada nem
reimplementação das invariantes. As assinaturas e o comportamento entregues
pela F2.4-A permanecem intactos.

> `PageRepository` **não faz parte da F2.4** — nem desta subfase, nem de
> nenhuma outra. Ver "Camadas" nos contratos compartilhados.

**Rotas administrativas**

```text
GET    /admin/paginas                    admin.pages.index
GET    /admin/paginas/criar              admin.pages.create
POST   /admin/paginas                    admin.pages.store
GET    /admin/paginas/{page}/editar      admin.pages.edit
PUT    /admin/paginas/{page}             admin.pages.update
DELETE /admin/paginas/{page}             admin.pages.destroy
```

Todas protegidas **somente por `auth`**. A rota `admin.pages.preview` pertence
à F2.4-C e não é criada aqui.

**Formulário administrativo**

```text
title
slug
status
content
meta_title
meta_description
```

O slug pode ficar vazio na criação, para geração automática. O `content` é
editado como Markdown em `textarea` — sem WYSIWYG. **A renderização pública
segura desse Markdown é responsabilidade da F2.4-C**; a F2.4-B apenas persiste
o conteúdo.

Não entram imagem destacada, autor, mídia, template, menu, posição, categoria,
tags nem editor visual.

**Navegação administrativa**

Integração com o layout administrativo da F2.2, incluindo sidebar e
breadcrumbs, no padrão iniciado na F2.2-C. Como já vale para as demais
subfases, **o link só é adicionado à navegação quando a rota realmente
existir**.

**Entregáveis planejados**

- [ ] Integração administrativa com o `PageService` fundado na F2.4-A
- [ ] Extensões compatíveis de consulta do `PageService`, somente se a listagem exigir
- [ ] Form Requests de criação e de atualização
- [ ] Controller administrativo de páginas
- [ ] Blade de listagem
- [ ] Blade de criação
- [ ] Blade de edição
- [ ] Rotas administrativas com os nomes definidos acima
- [ ] Feedback de sucesso e de erro
- [ ] Integração com o layout administrativo da F2.2
- [ ] Sidebar/navegação — somente quando as rotas existirem
- [ ] Breadcrumbs

**Testes / critério de aceite planejados**

- [ ] Guest não acessa o CRUD administrativo
- [ ] Usuário autenticado acessa o CRUD administrativo
- [ ] Criação de página persiste corretamente
- [ ] Listagem exibe as páginas existentes
- [ ] Edição carrega os valores persistidos
- [ ] Atualização persiste as alterações
- [ ] Exclusão administrativa é exclusão lógica
- [ ] Validações rejeitam entradas inválidas
- [ ] Os limites de tamanho da coluna são validados na entrada: `title` até 255,
      `slug` até 255, `meta_title` até 255 e `meta_description` até 320
- [ ] Somente campos suportados podem ser persistidos
- [ ] O CRUD administrativo opera através do `PageService`, sem acesso direto ao Eloquent
- [ ] O `PageService` continua autoritativo sobre a disponibilidade do slug,
      inclusive fora do HTTP — validar no Form Request para dar feedback ao
      formulário não equivale a duplicar a invariante, desde que o algoritmo
      de geração e resolução não seja reimplementado ali
- [ ] O comportamento entregue pela F2.4-A permanece inalterado
- [ ] Integração com o layout administrativo verificada
- [ ] Navegação e breadcrumbs verificados
- [ ] Nenhuma autorização da Fase 3 é antecipada
- [ ] Nenhuma funcionalidade da F2.6 é antecipada
- [ ] Nenhuma funcionalidade da F2.7 é antecipada
- [ ] Suíte completa permanece verde
- [ ] Pint passa
- [ ] `git diff --check` passa

**Fora do escopo da F2.4-B:** rota pública, regra de exposição
`draft`/`published`, `PageContentRenderer`, dependência direta de
`league/commonmark`, preview administrativo e emissão de SEO no HTML.

**Bloqueadores / decisões pendentes:** nenhum. **Dependência F2.4-A
satisfeita** — o que libera o início, sem que trabalho algum tenha começado.

---

##### F2.4-C — Publicação, Markdown, preview e SEO 📋 Planejada

**Objetivo:** expor páginas publicadas com segurança e completar a experiência
editorial da F2.4.

**Dependências:** **F2.4-B.**

**Rota pública**

```text
GET /paginas/{slug}     →  pages.show
```

**Não** será usado um catch-all `/{slug}`. O projeto ainda receberá rotas
próprias de e-commerce — catálogo, produtos, carrinho, checkout, conta do
cliente — e um catch-all colocaria slugs de CMS competindo com elas.

Como a rota é namespaced, **não** será criada lista antecipada de slugs
reservados (`admin`, `login`, `checkout`, `produto`…). O namespace já resolve a
colisão; a validação protege o **formato** do slug, sem tentar adivinhar as
rotas futuras da plataforma.

**Regra de publicação**

> Somente páginas com status `published` são resolvidas pela rota pública.

Uma página `draft` resulta em **404**, não 403 — a rota pública não deve
revelar que o rascunho existe. **Estar autenticado não libera drafts pela rota
pública**: o preview acontece exclusivamente pela rota administrativa. Página
soft-deleted também não é acessível publicamente.

**Preview**

```text
GET /admin/paginas/{page}/preview   →  admin.pages.preview   (auth)
```

Exibe tanto `draft` quanto `published`, usando **o mesmo conteúdo, o mesmo
renderer e preferencialmente o mesmo layout público** da página publicada. O
objetivo é impedir que preview e resultado público divirjam.

Contrato de uso: **salvar → abrir preview**. Não haverá preview público por
token, URL compartilhável, iframe obrigatório, live preview, autosave nem
JavaScript de preview.

**Dependência direta do CommonMark**

> Embora `league/commonmark` já esteja presente transitivamente no
> `composer.lock`, a F2.4 utilizará essa biblioteca **diretamente no código da
> aplicação**. Portanto, durante a implementação técnica desta subfase,
> `league/commonmark` deverá ser declarado como **dependência direta** do
> projeto no `composer.json`, **antes** de sua API ser utilizada.

Depender implicitamente de uma dependência transitiva de outro pacote é frágil:
o pacote que hoje a traz pode removê-la ou trocar de versão numa atualização
qualquer, e o build quebraria por um motivo que não aparece no `composer.json`.

**Segurança do conteúdo**

> HTML arbitrário embutido no Markdown não deve ser renderizado como HTML
> executável.

O renderer deverá usar configuração segura equivalente a:

```text
html_input = strip
allow_unsafe_links = false
max_nesting_level = 100
```

- `html_input = strip` impede que HTML arbitrário escrito no editor chegue à
  página como marcação executável;
- `allow_unsafe_links = false` bloqueia protocolos inseguros em links;
- `max_nesting_level = 100` limita profundidade patológica de Markdown,
  reduzindo o risco de consumo excessivo de recursos ao renderizar.

Sem isso, o conteúdo administrativo viraria um vetor de script na página
pública.

**`PageContentRenderer`** — componente pequeno, de responsabilidade única:

```text
Markdown persistido  →  HTML seguro para renderização
```

Preview e página pública **compartilham** esse renderer. Ele não faz
persistência, e o HTML renderizado **não** é gravado em `pages`.

**SEO mínimo**

```text
meta_title        nullable, até 255 caracteres
meta_description  nullable, até 320 caracteres
```

Na página pública, `<title>` usa `meta_title ?? title`. Havendo
`meta_description`, é renderizada como `<meta name="description">`; não havendo,
nenhuma tag é emitida.

Ficam fora: meta keywords, OpenGraph, Twitter Cards, JSON-LD, canonical
configurável e robots configurável.

**Entregáveis planejados**

- [ ] `league/commonmark` declarado como dependência direta no `composer.json`
- [ ] `PageContentRenderer` com a configuração segura definida acima
- [ ] Consulta pública de página publicada no `PageService`
- [ ] Rota pública `GET /paginas/{slug}` → `pages.show`
- [ ] Controller e Blade públicos da página
- [ ] Integração com o layout público
- [ ] Rota de preview `admin.pages.preview`, protegida por `auth`
- [ ] Emissão de `<title>` e `<meta name="description">` conforme o contrato

**Testes / critério de aceite planejados**

- [ ] `league/commonmark` é dependência direta do projeto antes de ser utilizado pelo `PageContentRenderer`
- [ ] Markdown é renderizado corretamente
- [ ] HTML inseguro não é renderizado como HTML executável
- [ ] Links inseguros são bloqueados conforme a configuração do renderer
- [ ] O renderer limita profundidade de Markdown conforme o contrato de segurança
- [ ] HTML renderizado não é persistido
- [ ] `published` é acessível pela rota pública
- [ ] `draft` não é acessível pela rota pública (404)
- [ ] Usuário autenticado não obtém draft pela rota pública apenas por estar autenticado
- [ ] Página soft-deleted não é acessível publicamente
- [ ] Preview administrativo exige autenticação
- [ ] Preview consegue exibir draft
- [ ] Preview consegue exibir published
- [ ] Preview e público compartilham o mesmo renderer
- [ ] `meta_title` substitui o título HTML quando preenchido
- [ ] Ausência de `meta_title` usa `Page.title`
- [ ] `meta_description` é renderizada quando existir
- [ ] Ausência de `meta_description` não emite a tag
- [ ] A home `/` permanece independente do CMS
- [ ] Nenhuma funcionalidade da F2.6 é antecipada
- [ ] Nenhuma funcionalidade da F2.7 é antecipada
- [ ] Suíte completa permanece verde
- [ ] Pint passa
- [ ] `git diff --check` passa

**Bloqueadores / decisões pendentes:** depende da F2.4-B.

---

##### Fora do escopo

Vale para a F2.4 inteira — A, B e C.

**Outras subfases:** menus e `MenuItem` (F2.6), biblioteca de mídia, upload e
imagem destacada (F2.7), banners (F2.5).

**Editor e frontend:** WYSIWYG e qualquer dependência de editor visual
(TinyMCE, CKEditor, TipTap, Quill), Livewire para o CRUD, JavaScript complexo,
autosave.

**Ciclo de conteúdo:** histórico e versionamento, agendamento de publicação,
workflow de aprovação, restore, lixeira, force delete, redirect manager.

**Fase 3:** papéis, permissões, policies, autor/editor por usuário e auditoria
persistente.

**SEO avançado:** OpenGraph, Twitter Cards, JSON-LD.

**Arquitetura:** `PageRepository` e suas variações.

---

##### Contrato futuro com a F2.6

> A F2.4 fornece `Page.id` como identidade estável. Quando a F2.6 for
> implementada, itens de menu internos deverão relacionar-se à página por sua
> **identidade**, e a URL deverá ser resolvida a partir da `Page` atual.

Não fica decidido agora se a F2.6 usará enum de destino, relacionamento
polimórfico, `page_id` nullable, URL externa combinada com `page_id` ou outra
modelagem — isso pertence à auditoria arquitetural da F2.6. O único contrato
que a F2.4 fecha é:

```text
slug != identidade
Page.id = identidade estável
```

A F2.6 depende da **F2.4 concluída** — isto é, após a F2.4-C — e não apenas da
identidade entregue pela F2.4-A.

---

**Dependências:** F2.2 (layout e rotas admin) — satisfeita. A F2.4 **não**
depende de F2.5, F2.6, F2.7, F2.3-C nem da Fase 3.

Internamente: `F2.4-A → F2.4-B → F2.4-C`.

**Bloqueadores / decisões pendentes:** nenhum. Com a F2.4-A concluída, a
próxima etapa é a **F2.4-B — Administração e CRUD**, ainda não iniciada.

---

#### F2.5 — Banners 📋 Planejado

**Objetivo:** gerenciar banners posicionáveis no site.

**Entregáveis**

- [ ] Model e migration `Banner`
- [ ] CRUD
- [ ] Seleção de imagem a partir da Biblioteca de Mídia (F2.7) — sem upload
      próprio
- [ ] Ordenação
- [ ] Posições (ex.: hero, sidebar, footer)
- [ ] Status ativo/inativo

**Testes / critério de aceite**

- [ ] CRUD completo exercitado por testes
- [ ] A imagem do banner é referenciada a partir da Biblioteca de Mídia
- [ ] Excluir uma mídia em uso por um banner é impedido (integra com a F2.7)
- [ ] A ordenação é respeitada na consulta
- [ ] Banner inativo não aparece na listagem pública

**Dependências:** F2.2 (layout e rotas admin) e **F2.7 (obrigatória)** — a
biblioteca de mídia precisa existir antes desta subfase.

**Decisão arquitetural (resolvida):** os banners **utilizam a Biblioteca de
Mídia centralizada da F2.7**. Não criar subsistema independente de
armazenamento/upload para banners. Isso evita duas rotas de upload, dois
formatos de armazenamento e a duplicação do processamento de imagens — e é o
motivo de a F2.7 ser executada antes da F2.5.

**Bloqueadores / decisões pendentes:** nenhum. A dependência da F2.2 está
satisfeita.

---

#### F2.6 — Menus 📋 Planejado

**Objetivo:** montar a navegação do site a partir de menus hierárquicos.

**Entregáveis**

- [ ] Model `Menu`
- [ ] Model `MenuItem`
- [ ] Migrations
- [ ] Hierarquia parent/child
- [ ] Ordenação
- [ ] URLs customizadas
- [ ] Relacionamento com páginas, quando aplicável
- [ ] Drag-and-drop — **somente na camada de interface**; a ordenação em si é
      persistida por um campo de ordem, não pelo componente visual

**Testes / critério de aceite**

- [ ] CRUD de menus e itens exercitado por testes
- [ ] Hierarquia parent/child persiste e é lida corretamente
- [ ] A ordenação é respeitada na renderização
- [ ] Item apontando para uma página resolve a URL correta

**Dependências:** F2.2 (layout e rotas admin), F2.4 (para itens que apontam
para páginas) — a **F2.4 completa**, isto é, após a F2.4-C, e não apenas a
identidade entregue pela F2.4-A.

> **Contrato herdado da F2.4:** itens internos devem se relacionar à página por
> `Page.id`, não pelo slug — o slug é endereço público e pode mudar. A URL é
> resolvida a partir da `Page` atual. A modelagem dos destinos de menu (enum,
> polimórfico, `page_id` nullable ou outra) fica para a auditoria arquitetural
> desta subfase.

**Bloqueadores / decisões pendentes:** nenhum. A dependência da F2.2 está
satisfeita.

---

#### F2.7 — Biblioteca de Mídia 📋 Planejado

**Objetivo:** centralizar upload, processamento e reuso de arquivos de mídia.

**Entregáveis**

- [ ] Model e migration `Media`
- [ ] Upload
- [ ] Armazenamento
- [ ] Processamento/compressão de imagens com Intervention Image
- [ ] Grid/consulta da biblioteca
- [ ] Exclusão (delete) de itens da biblioteca
- [ ] Proteção contra exclusão de mídia em uso

**Testes / critério de aceite**

- [ ] Upload persiste o arquivo e o registro correspondente
- [ ] Imagem é processada/comprimida conforme configurado
- [ ] Consulta da biblioteca retorna os itens esperados
- [ ] **Excluir mídia em uso é impedido** — este é o critério que justifica a
      subfase existir separada

**Dependências:** F2.2 (layout e rotas admin). O `intervention/image 4.3.2` já
foi instalado na Fase 1.

**Consumidores:** a **F2.5 (Banners)** e a **F2.3-C (Logo e favicon)** dependem
desta subfase — por isso a F2.7 é executada antes de ambas.

> **A F2.7 não depende da F2.3-C.** A direção é `F2.7 → F2.3-C`, nunca o
> inverso: a biblioteca de mídia pode ser implementada e validada de forma
> independente, sem que logo ou favicon existam.

**Bloqueadores / decisões pendentes:** nenhum. A dependência da F2.2 está
satisfeita.

---

#### Notas Técnicas da Fase 2

Preservadas da versão anterior deste Roadmap:

- `SiteSetting` com cache Redis (TTL de 5 minutos) — F2.1
- Intervention/Image para processamento de imagens — F2.7
- Campos nativos de cor no formulário Blade, com preview server-side — F2.3-B
- Middleware `auth` para proteger as rotas administrativas — F2.2; autorização
  granular permanece na Fase 3
- Auditoria persistente de alterações administrativas **saiu do escopo
  obrigatório da F2.3**. Se vier a ser necessária, receberá contrato próprio em
  etapa futura, preferencialmente após a consolidação de usuários, papéis e
  permissões na Fase 3.

#### Bloqueadores da Fase 2

- Nenhum bloqueador arquitetural pendente. A **F2.2 — Fundação do Admin está
  concluída** (A, B e C), com autenticação mínima, layout e navegação do painel
  entregues e validados. F2.3, F2.4 e F2.7 ficam liberadas para início.
- A autorização granular permanece na Fase 3. Durante toda a Fase 2, qualquer
  usuário autenticado acessa `/admin`.

#### Dependências

- ✅ Fase 1 (concluída)

#### Próximo Passo
→ **F2.4-B — Administração e CRUD** — 📋 planejada, não iniciada. A F2.4-A
está concluída e a F2.4 segue **em desenvolvimento**; a F2.3-C só é retomada
depois da F2.7. A Fase 3 permanece após a conclusão da Fase 2.

A F2.4 só é encerrada após a F2.4-C — e é só então que a F2.6 fica liberada.

---

### Fase 3 — Autenticação, Roles e Permissions ⏳ PLANEJADO

**Status:** ⏳ **Planejado**  
**Duração Estimada:** Semana 2-3 (2026-09-17 → 2026-09-24)  
**Responsável:** TBD  

#### Entregáveis
- [ ] **Expansão e consolidação do Laravel Fortify iniciado na F2.2-A**
  - [ ] Registro de clientes
  - [ ] Recuperação de senha
  - [ ] Confirmação de email

- [ ] **Spatie/laravel-permission Configurado**
  - [ ] Migration para roles e permissions
  - [ ] Seeders para roles padrão (admin, gerente, editor, operador, customer)
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
| admin | Administrador completo da plataforma | Usuários, roles, permissions, configurações e operação administrativa |
| gerente | Gestão da operação do e-commerce | Produtos, estoque, pedidos, clientes, relatórios e supervisão operacional |
| editor | Conteúdo editorial do site | Páginas, banners, menus, mídia e conteúdo editorial |
| operador | Operação cotidiana sem poderes administrativos sensíveis | Pedidos, atualização de status, estoque operacional e atendimento |
| customer | Cliente final do e-commerce | Conta, endereços, compras, carrinho, checkout e próprios pedidos |

#### Bloqueadores
- Nenhum bloqueador arquitetural pendente. A F2.2 entrega autenticação mínima
  para `/admin`; esta fase preserva a autorização definitiva, sem antecipação
  de escopo.

#### Dependências
- ✅ Fase 1 (concluída)
- ⏳ Fase 2 (deve estar concluída)

> **Fronteira definida com a F2.2.** A F2.2 entrega somente autenticação mínima
> para proteger `/admin`. Esta Fase 3 preserva o modelo definitivo de
> identidade e autorização: papéis, permissões, policies, controles granulares
> e administração de usuários e roles. Nenhum desses itens foi antecipado.

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
├── 04-10: Fase 1 — Docker + Laravel ✅ [CONCLUÍDA]
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

- ✅ **F2.1 — Fundação do CMS:** concluída.
- ✅ **F2.2 — Fundação do Admin:** concluída (A, B e C).
- Nenhum bloqueador arquitetural conhecido. F2.3, F2.4 e F2.7 estão liberadas
  para início; F2.5 ainda depende da F2.7 e F2.6 da F2.4. A autorização
  granular permanece na Fase 3.

---

## 📝 Últimas Atualizações

- **2026-09-04:** Criação inicial do ROADMAP
- **2026-09-04:** Fase 1a — infraestrutura Docker concluída e validada ✅
- **2026-09-04:** Varredura de fidelidade na documentação: itens que estavam
  marcados como prontos sem existir no repositório (Laravel, migrations,
  seeders, Vite, Livewire) foram revertidos para pendente
- **2026-09-04:** Fase 1b — Laravel 12.69.1, Livewire 4.4.3, Vite 7 e
  TailwindCSS 4 instalados; migrations iniciais aplicadas; `composer test`
  passando. Fase 1 concluída ✅
- **2026-09-04:** F2.1-A concluída — persistência e contrato de SiteSetting.
  Commit: `5c9194a9e50a6cbaa13df950de0beee3a4d936d3`
- **2026-09-04:** F2.1-B concluída — SiteSettingService, cache Redis com TTL
  de 5 minutos e invalidação explícita. Commit: `db113a0255791b35f2a8fb88081b6afe95c2f663`
- **2026-09-04:** F2.1-C concluída — hardening e testes de regressão da
  fundação de SiteSetting. Commit: `d79f83c77b20947b4565eb52f99f2ae3273ebb2b`
- **2026-09-04:** Decisão arquitetural da F2.2 resolvida — autenticação mínima
  para `/admin` na F2.2; autorização granular permanece na Fase 3. F2.2 pronta
  para implementação.
- **2026-09-04:** Modelo de negócio explicitado como e-commerce próprio
  single-store; marketplace e lojistas terceiros estão fora do escopo. A role
  `lojista` foi substituída por `operador` no planejamento da Fase 3.
- **2026-09-04:** F2.2 decomposta em F2.2-A (Fortify mínimo + autenticação),
  F2.2-B (layout administrativo e navegação) e F2.2-C (dashboard, navegação
  ativa e hardening), permitindo evolução incremental sem antecipar a Fase 3.
- **2026-09-04:** F2.2-A concluída e validada — Fortify mínimo, login/logout e
  proteção de `/admin` por middleware `auth`. Commit:
  `bf175ec9a1e3068bf3d67ee1f051c8a07ad3434a`. A F2.2 segue em desenvolvimento
  até a conclusão de F2.2-B e F2.2-C.
- **2026-09-04:** F2.2-B concluída e validada — layout administrativo único,
  sidebar, topbar, breadcrumbs e migração de `/admin` para o layout, com
  responsividade básica sem JavaScript. Commit:
  `a178dd8d6fa865433f6fe407b223ca98d276a96d`. A F2.2 segue em desenvolvimento
  até a conclusão da F2.2-C.
- **2026-09-04:** F2.2-C concluída e validada — dashboard estrutural,
  navegação ativa e testes de hardening de autenticação, layout e navegação.
  Commit: `359bdf16afe4d97ff7982e49de85c866491f85d7`.
- **2026-09-04:** **F2.2 — Fundação do Admin encerrada** com as três subfases
  concluídas e validadas. A dependência que bloqueava F2.3, F2.4 e F2.7 está
  satisfeita; as três ficam liberadas para início. Próxima subfase: F2.3 —
  Configurações Globais.
- **2026-09-04:** F2.3-A concluída e validada — configurações gerais do CMS
  (nome, email de suporte, telefone e endereço) sobre `SiteSettingService`, que
  ganhou `setMany()` transacional. Commit:
  `07ce5221669af5b150393c2d57525057f97e1e4e`. 64 testes / 197 assertions.
  Próxima subfase: F2.3-B — Tema e cores.
- **2026-09-04:** F2.3-B — Tema e cores concluída. Commit técnico:
  `98244c3028d9e087669c8c8826ea1ba13bed29ef`. Três cores configuráveis em
  `#RRGGBB` sobre o `ThemeService`, persistidas por
  `SiteSettingService::setMany()`, expostas como CSS variables de runtime, com
  preview server-side e integração mínima ao frontend público. Validação: 96
  testes / 307 assertions. Próxima subfase planejada: F2.4 — Páginas Estáticas.
- **2026-09-04:** F2.4 — contrato arquitetural definido antes da
  implementação: `Page` com identidade `BIGINT` estável; slug único, editável e
  mutável; rota pública `/paginas/{slug}`; estados `draft`/`published`;
  conteúdo Markdown com renderização segura; `SoftDeletes`; SEO mínimo; preview
  administrativo compartilhando o renderer; `PageService` sem Repository; e
  integração futura com a F2.6 pela identidade da página. **A F2.4 permanece
  planejada e não iniciada** — nenhum código foi escrito.
- **2026-09-05:** F2.4 reorganizada documentalmente em três subfases
  internas — F2.4-A (fundação de domínio e persistência), F2.4-B
  (administração e CRUD) e F2.4-C (publicação, Markdown, preview e SEO) —, com
  contratos arquiteturais compartilhados extraídos para uma seção única. Na
  revisão da divisão, a responsabilidade do `PageService` foi corrigida: seu
  **núcleo** pertence à F2.4-A, onde ficam as invariantes de entidade e de
  slug que precisam ser testadas antes de existir interface; a F2.4-B consome
  esse serviço e pode estendê-lo com consultas administrativas compatíveis, sem
  duplicar regras no Controller, no Form Request, em Observer ou no Model.
  Nenhuma decisão arquitetural nova foi introduzida e **nenhuma implementação
  foi iniciada**: a F2.4 e as três subfases permanecem 📋 planejadas, com todos
  os itens em `[ ]`. A sequência global da Fase 2 permanece inalterada e a F2.6
  continua dependendo da F2.4 completa.
- **2026-09-05:** F2.4-A — Fundação de domínio e persistência concluída.
  Commit técnico: `253315872d6eda3d6b45e2ce3db9d319e2adcce0`. Entrega o model
  `Page`, a migration `pages`, o enum `PageStatus`, `SoftDeletes` e o núcleo do
  `PageService` com as invariantes de slug — geração, normalização, colisão
  determinística, limite de 255 e reserva do slug de páginas soft-deleted —,
  tudo exercitado sem HTTP. Validação: 47 testes focados / 93 assertions e 143
  testes / 400 assertions na suíte completa. **A F2.4 passa a ⏳ em
  desenvolvimento**: F2.4-B e F2.4-C continuam planejadas, e a F2.6 segue
  bloqueada até a F2.4 completa. Próxima etapa: F2.4-B — Administração e CRUD.
- *Próxima revisão: 2026-09-11*

---

## 🙋 Questões Frequentes

**P: Quanto tempo para MVP completo?**  
R: Aproximadamente 30 dias (4 semanas) até Fase 8 concluída.

**P: Posso usar em produção agora?**  
R: Não. O ambiente de desenvolvimento já roda a aplicação e as fundações do
CMS (F2.1) e do painel administrativo (F2.2) estão concluídas, mas a Fase 2
segue em desenvolvimento a partir da F2.3. As funcionalidades de e-commerce e a
preparação para produção continuam previstas para as próximas fases.

**P: Preciso implementar tudo?**  
R: Não. Priorize as fases 1-6 para MVP. Fases 7-9 são para release.

**P: Como colaborar?**  
R: Veja [CONTRIBUTING.md](./CONTRIBUTING.md)

---

**Última atualização:** 2026-09-05

**Próxima revisão:** 2026-09-11
