# ✅ Verificação do Ambiente — Resultado

Validado de ponta a ponta em 2026-09-04 (Windows 11 + Docker Desktop).

## Serviços

```
NAME              STATUS
loja-app          Up (healthy)
loja-nginx        Up (healthy)
loja-mysql        Up (healthy)
loja-redis        Up (healthy)
loja-mailpit      Up (healthy)
loja-node         Up
loja-phpmyadmin   Up
```

## Infraestrutura

| Verificação | Resultado |
| --- | --- |
| nginx → php-fpm (`/health.php`) | HTTP 200 |
| php-fpm ping (`/health`) | HTTP 200 |
| phpMyAdmin `:8081` | HTTP 200 |
| MailPit `:8025` | HTTP 200 |
| MySQL a partir do app | OK — 8.4.11 |
| Redis a partir do app | OK — pong |
| `mysql.cnf` efetivamente aplicado | `innodb_buffer_pool_size=256M`, `max_connections=100`, `slow_query_log=1` |

**Extensões PHP carregadas sem warnings:** bcmath, gd, mbstring, pdo_mysql,
pcntl, sockets, zip, redis, xdebug.

## Aplicação

| Verificação | Resultado |
| --- | --- |
| `GET /` | HTTP 200 — `<title>Loja Online</title>` |
| Assets do Vite (`/build/...`) | HTTP 200 |
| Laravel | 12.69.1 |
| Livewire | 4.4.3 |
| Vite / TailwindCSS | 7.3.6 / 4 |
| Composer / Node / npm | 2.10.3 / v20.20.2 / 10.8.2 |
| Migrations aplicadas | 3 (`users`, `cache`, `jobs`) |
| `composer test` | 2 testes, 2 asserções — passa |
| Cache | Redis, database 1 (`CACHE_STORE=redis`) |
| Queue / Session / Mail | redis / cookie / log |

## Comportamentos esperados

**`/health.php` é independente do Laravel.** Responde mesmo antes de
`composer install`, o que separa "a stack Docker está de pé" de "a aplicação
está funcionando" no diagnóstico.

**Xdebug está instalado mas desligado** (`xdebug.mode = off`). Ligar
`start_with_request` faz toda requisição tentar abrir conexão com o depurador.
Para depurar, edite `docker/php.ini` e recrie o container.

**A porta 5173 do Vite não está publicada.** `npm run build` funciona; usar o
dev server a partir do host exige publicar a porta no `compose.yaml`.

## Como reproduzir

```bash
cp .env.example .env
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec node npm install
docker compose exec node npm run build

curl -i http://localhost/
docker compose exec app composer test
```

## Próximo passo

Fase 2 — CMS e Configurações Admin. Ver [ROADMAP](../ROADMAP.md).
