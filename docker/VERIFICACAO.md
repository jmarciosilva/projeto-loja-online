# ✅ Verificação Docker — Resultado

Stack validada de ponta a ponta em 2026-09-04 (Windows + Docker Desktop).

## Arquivos

| Arquivo | Papel |
| --- | --- |
| `compose.yaml` | Orquestra os 7 serviços |
| `docker/Dockerfile` | Imagem PHP-FPM 8.3 + extensões |
| `docker/Dockerfile.mysql` | MySQL 8.4 com `mysql.cnf` embutido |
| `docker/nginx.conf` | Reverse proxy → php-fpm |
| `docker/php.ini` | Overrides de PHP |
| `docker/php-fpm.conf` | Pool php-fpm + endpoint `/ping` |
| `docker/mysql.cnf` | Tuning do MySQL |
| `public/health.php` | Endpoint de liveness |
| `.dockerignore` | Reduz o contexto de build |

## Resultado dos testes

```
NAME              STATUS
loja-app          Up (healthy)
loja-mailpit      Up (healthy)
loja-mysql        Up (healthy)
loja-nginx        Up (healthy)
loja-node         Up
loja-phpmyadmin   Up
loja-redis        Up (healthy)
```

| Verificação | Resultado |
| --- | --- |
| nginx → php-fpm (`/health.php`) | HTTP 200 |
| php-fpm ping (`/health`) | HTTP 200 |
| phpMyAdmin `:8081` | HTTP 200 |
| MailPit `:8025` | HTTP 200 |
| MySQL a partir do app | OK — 8.4.11 |
| Redis a partir do app | OK — pong |
| Composer no container | 2.10.3 |
| Node / npm | v20.20.2 / 10.8.2 |

**Extensões PHP carregadas sem warnings:** bcmath, gd, mbstring, pdo_mysql,
pcntl, sockets, zip, redis, xdebug.

**`mysql.cnf` aplicado de fato** (não apenas presente):

```
innodb_buffer_pool_size=268435456   (256M)
slow_query_log=1
long_query_time=2
max_connections=100
charset=utf8mb4
```

## Comportamentos esperados

**`GET /` retorna 403.** O Laravel ainda não foi instalado, então `public/` não
tem `index.php` e a listagem de diretório está desligada. Use `/health.php` para
verificar a stack enquanto isso. Depois de `composer create-project`, `/` passa
a responder normalmente.

**Xdebug está instalado mas desligado** (`xdebug.mode = off`). Ligar
`start_with_request` faz toda requisição tentar abrir conexão com o depurador,
o que deixa o dia a dia lento. Para depurar, edite `docker/php.ini`.

## Como reproduzir

```bash
cp .env.example .env
docker compose up -d --build

# aguarde os healthchecks
docker compose ps

# smoke test
curl -i http://localhost/health.php
docker compose exec app php -m
```

## Próximos passos

Instalar o Laravel dentro do container:

```bash
docker compose exec app composer create-project laravel/laravel:^12 .
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec node npm install
```
