# ⚡ Instalação Rápida

Setup do ambiente de desenvolvimento.

> **Estado atual:** ambiente completo — Docker + Laravel 12 + Livewire + Vite.
> Todos os passos abaixo funcionam.

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

`/health.php` responde mesmo antes de instalar as dependências da aplicação —
serve para separar "a stack está de pé" de "o Laravel está funcionando".
Se `/` devolver 403, é porque o passo 4 ainda não foi executado.

---

## 4. Instalar as dependências da aplicação

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan storage:link
docker compose exec node npm install
docker compose exec node npm run build
```

`vendor/` e `node_modules/` ficam em volumes nomeados do Docker, não no host —
por isso esses comandos precisam rodar **dentro** dos containers.

Feito isso, http://localhost serve a aplicação.

### Por que `storage:link`

O disco público do Laravel grava em `storage/app/public`, que fica **fora** da
raiz servida pelo nginx (`public/`). O comando cria o link simbólico:

```text
public/storage  →  storage/app/public
```

É ele que faz um arquivo gravado no disco `public` ser servido em
`http://localhost/storage/<caminho>`. O link está no `.gitignore` e **não é
versionado** — portanto cada instalação precisa rodar o comando. Sem ele, o
arquivo existe no servidor e a URL é montada corretamente, mas a resposta é
404. O comando é idempotente: executado de novo, apenas informa que o link já
existe.

Verificação mínima:

```bash
docker compose exec app ls -l public/storage
# storage -> /app/storage/app/public
```

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

```bash
docker compose exec app php artisan tinker
docker compose exec app php artisan migrate
docker compose exec app composer test
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

**500 com `tempnam(): file created in the system's temporary directory`** —
é `storage/` sem permissão de escrita para o `www-data`. O
`docker/entrypoint.sh` corrige isso a cada boot; se aparecer, recrie o
container: `docker compose up -d --force-recreate app`.

Mais casos em [DOCKER_DEVELOPMENT.md](./DOCKER_DEVELOPMENT.md).

---

## Próximos passos

- [README.md](../README.md) — visão geral e estado atual
- [ROADMAP.md](../ROADMAP.md) — o que fazer em seguida
- [docs/ARQUITETURA.md](./ARQUITETURA.md) — desenho técnico alvo
- [docker/VERIFICACAO.md](../docker/VERIFICACAO.md) — como a stack foi validada
