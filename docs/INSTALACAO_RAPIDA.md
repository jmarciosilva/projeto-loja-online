# ⚡ Instalação Rápida

Setup do ambiente de desenvolvimento.

> **Estado atual:** a infraestrutura Docker está pronta; o Laravel ainda não
> foi instalado. Os passos 1 a 3 funcionam hoje. O passo 4 é o bootstrap da
> aplicação, que ainda não foi executado por ninguém (Fase 1b do
> [ROADMAP](../ROADMAP.md)).

---

## Pré-requisitos

- **Docker Desktop** rodando
- **Git**

Não é preciso PHP, MySQL nem Node.js instalados no host.

---

## 1. Clonar e configurar

```bash
git clone git@github.com:jmarciosilva/projeto-loja-online.git
cd projeto-loja-online
cp .env.example .env
```

Se as portas 80, 3306, 8081 ou 8025 estiverem ocupadas, ajuste no `.env`:

```env
DOCKER_HTTP_PORT=8080
DOCKER_MYSQL_PORT=3307
```

---

## 2. Subir os containers

```bash
docker compose up -d --build
```

O primeiro build leva alguns minutos (compila as extensões PHP). Nos
seguintes, o cache resolve em segundos.

---

## 3. Verificar

```bash
docker compose ps
```

Esperado — os 5 serviços com health check devem estar `healthy` (node e phpMyAdmin não têm um):

```
loja-app          Up (healthy)
loja-nginx        Up (healthy)
loja-mysql        Up (healthy)
loja-redis        Up (healthy)
loja-node         Up
loja-phpmyadmin   Up
loja-mailpit      Up (healthy)
```

Smoke test:

```bash
curl http://localhost/health.php     # OK
```

| Serviço | URL |
| --- | --- |
| Aplicação | http://localhost |
| phpMyAdmin | http://localhost:8081 |
| MailPit | http://localhost:8025 |

**`http://localhost` retorna 403 neste momento** — é o esperado enquanto o
Laravel não estiver instalado: `public/` não tem `index.php`. Use
`/health.php` para confirmar que a stack está de pé.

---

## 4. Instalar o Laravel (ainda não feito)

```bash
docker compose exec app composer create-project laravel/laravel:^12 .
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec node npm install
docker compose exec node npm run build
```

É `create-project` e não `composer install`: o repositório ainda não tem
`composer.json`. Depois que o Laravel existir e for commitado, novos clones
usam `composer install`.

Feito isso, `http://localhost` passa a servir a aplicação.

---

## Comandos do dia a dia

```bash
docker compose up -d          # iniciar
docker compose down           # parar
docker compose logs -f app    # acompanhar logs
docker compose exec app sh    # shell no container
```

> As imagens são Alpine: use `sh`, não `bash`. `docker compose exec app bash`
> falha com `executable file not found`.

Após o Laravel instalado:

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate
docker compose exec node npm run dev
```

---

## Problemas comuns

**Porta já em uso** — ajuste `DOCKER_HTTP_PORT` no `.env` e recrie:
`docker compose down && docker compose up -d`.

**MySQL recusando conexão** — aguarde o health check (~20s). Confirme que o
`.env` usa `DB_HOST=mysql`, não `localhost`.

**Recomeçar do zero** (apaga o banco):

```bash
docker compose down -v
docker compose up -d --build
```

Mais casos em [DOCKER_DEVELOPMENT.md](./DOCKER_DEVELOPMENT.md).

---

## Próximos passos

- [README.md](../README.md) — visão geral e estado atual
- [ROADMAP.md](../ROADMAP.md) — o que fazer em seguida
- [docs/ARQUITETURA.md](./ARQUITETURA.md) — desenho técnico alvo
- [docker/VERIFICACAO.md](../docker/VERIFICACAO.md) — como a stack foi validada
