# 🗺️ Roadmap — Loja Online

## Status Geral

- **Fase Atual:** Fase 2 — CMS e Configurações Admin ⏳ (iniciada em 2026-09-04)
  - F2.1 — Fundação do CMS: ✅ concluída
  - F2.2 — Fundação do Admin: ✅ concluída
  - F2.3-A — Configurações gerais: ✅ concluída
  - F2.3-B — Tema e cores: ✅ concluída
  - F2.3 permanece parcialmente aberta: a F2.3-C aguarda a F2.7
  - Próxima subfase planejada: **F2.4 — Páginas Estáticas** (não iniciada)
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
| F2.4 — Páginas Estáticas | 📋 Planejado | CRUD de páginas com SEO e publicação | F2.2 |
| F2.7 — Biblioteca de Mídia | 📋 Planejado | Upload, processamento e consulta de mídia | F2.2 |
| F2.5 — Banners | 📋 Planejado | CRUD de banners com ordenação, sobre a mídia da F2.7 | F2.2, **F2.7** |
| F2.6 — Menus | 📋 Planejado | Menus hierárquicos e itens | F2.2, F2.4 |

> A tabela segue a **ordem de execução**, não a numeração: a F2.7 precede a
> F2.5 porque os banners dependem da biblioteca de mídia.

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

#### F2.4 — Páginas Estáticas 📋 Planejado

**Objetivo:** permitir a criação e publicação de páginas de conteúdo estático.

**Entregáveis**

- [ ] Model `Page`
- [ ] Migration
- [ ] CRUD (criar, editar, deletar)
- [ ] Slug
- [ ] Publicação (rascunho/publicado)
- [ ] SEO: `meta_title` e `meta_description`
- [ ] Editor de conteúdo
- [ ] Preview antes de publicar

**Testes / critério de aceite**

- [ ] CRUD completo exercitado por testes
- [ ] Slug gerado e único
- [ ] Página não publicada não é acessível publicamente
- [ ] Campos de SEO persistem e são renderizados

**Dependências:** F2.2 (layout e rotas admin).

**Bloqueadores / decisões pendentes:** nenhum. A dependência da F2.2 está
satisfeita.

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
para páginas).

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
→ **F2.4 — Páginas Estáticas** — 📋 planejada, não iniciada. Com F2.3-A e
F2.3-B concluídas, a F2.4 é a próxima na ordem de execução; a F2.3-C só é
retomada depois da F2.7. A Fase 3 permanece após a conclusão da Fase 2.

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

**Última atualização:** 2026-09-04  
**Próxima revisão:** 2026-09-11
