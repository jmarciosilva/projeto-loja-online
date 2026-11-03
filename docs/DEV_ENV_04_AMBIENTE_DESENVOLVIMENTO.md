# DEV-ENV-04 — Ambiente de desenvolvimento local

Guia para inicializar a UAU NetShopping nesta máquina com banco local próprio e dados fictícios de demonstração. Não contém credenciais.

## 1. Pré-requisitos

- Docker Desktop com Docker Compose.
- Serviços do `compose.yaml`: `app`, `nginx`, `mysql`, `redis`, `node`, `phpmyadmin`, `mailpit`.

## 2. Configuração do `.env`

Copie `.env.example` para `.env` e ajuste:

| Variável | Valor local |
|---|---|
| `APP_ENV` | `local` |
| `APP_DEBUG` | `true` |
| `APP_URL` | `http://localhost` |
| `APP_KEY` | gerada localmente com `php artisan key:generate` |
| `DB_HOST` | `mysql` |
| `DB_DATABASE` | `loja_online` |
| `REDIS_HOST` | `redis` |
| `REDIS_PASSWORD` | `null` (o Redis local não tem senha) |
| `MAIL_MAILER` | `smtp` (host `mailpit`, porta `1025`) |

**Não versione o `.env`.** Ele já está no `.gitignore`.

A `APP_KEY` desta máquina é **própria**. Ela não deve ser copiada de outro ambiente, a menos que você esteja restaurando um banco que já tenha dados criptografados.

## 3. Inicialização

```bash
docker compose up -d
docker compose exec -T app composer install      # somente se vendor/ estiver vazio
docker compose exec -T node npm ci
docker compose exec -T node npm run build
docker compose exec -T app php artisan storage:link
docker compose exec -T app php artisan optimize:clear
```

Se `bootstrap/cache/` não existir, crie o diretório (o Git não versiona diretórios vazios) e ajuste o dono no container:

```bash
mkdir -p bootstrap/cache
docker compose exec -T app chown -R www-data:www-data /app/bootstrap/cache
```

## 4. Migrations

Verifique antes:

```bash
docker compose exec -T app php artisan migrate:status
```

Aplique:

```bash
docker compose exec -T app php artisan migrate --force
```

Nunca use `migrate:fresh`, `migrate:refresh` ou `db:wipe` neste banco. Esses comandos apagam os dados.

## 5. Backup antes de alterações

Antes de migrations ou seeders, gere um dump fora do repositório:

```bash
mkdir -p "$HOME/backups-loja"
docker compose exec -T mysql sh -c 'mysqldump -uloja_user -p"$MYSQL_PASSWORD" --single-transaction --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 loja_online' > "$HOME/backups-loja/loja_online_$(date +%Y%m%d_%H%M%S).sql"
```

Valide que a última linha é `-- Dump completed` e gere o SHA-256 do arquivo.

## 6. Seeder de demonstração

```bash
docker compose exec -T app php artisan db:seed --class=StorefrontDevelopmentSeeder
```

O seeder cria:

- 7 categorias: `Livros` (com 4 filhas) e `Artesanato` (com `EVA para Artesanato`).
- 21 produtos fictícios, com SKUs `DEMO-*`. Os livros usados têm saldo de uma unidade. Alguns itens estão esgotados e 4 estão destacados.
- Saldo inicial via `StockService`, com movimentos de tipo `initial_balance`. Não há imagens: os produtos usam o placeholder padrão da vitrine.

Características:

- Só roda com `APP_ENV=local` e o banco `loja_online`.
- É **idempotente**. Categorias são identificadas por slug e produtos por SKU. Reexecutar não duplica nada.
- Não apaga nem altera registros existentes.
- Exige um usuário local com permissões de catálogo e estoque.

Para identificar os dados de demonstração, filtre pelo prefixo do SKU:

```sql
SELECT id, sku, name FROM products WHERE sku LIKE 'DEMO-%';
```

## 7. Acesso

| Área | Endereço |
|---|---|
| Vitrine | `http://localhost` |
| Catálogo | `http://localhost/produtos` |
| Categorias | `http://localhost/categorias/{slug}` |
| Login administrativo | `http://localhost/login` (`/admin` redireciona para cá) |
| Mailpit (e-mails locais) | `http://localhost:8025` |
| phpMyAdmin | `http://localhost:8081` |

A senha do usuário administrador não está documentada aqui. Defina-a localmente, por meio do fluxo de recuperação ou de um comando dedicado.

## 8. Testes

Os testes usam SQLite em memória, configurado em `phpunit.xml`. O banco de desenvolvimento não é tocado.

```bash
docker compose exec -T app php artisan test
docker compose exec -T app php vendor/bin/pint --test
npm run test:js
npm run build
```

## 9. Proteção dos dados

- **Não execute** `CreateDemoBooksCatalog` (`loja:catalogo-demo-livros`). Ele faz exclusão lógica de uma camiseta e categoria de demonstração, e o escopo não corresponde a este catálogo.
- **Não execute** `DemoStorefrontSeeder` sem revisar: ele cria páginas, menu e banner de demonstração.
- Para sincronizar dados com outra máquina, use dump e pacote de mídias, sempre com backup prévio do banco local. Não use o Git para transportar banco, `.env` ou mídias.
- Antes de qualquer restauração, confirme que a `APP_KEY` é a mesma do banco de origem, se ele tiver dados criptografados (por exemplo, credenciais de frete).

## 10. Troubleshooting

| Sintoma | Causa provável | Ação |
|---|---|---|
| HTTP 500 na vitrine | `APP_KEY` ausente | `php artisan key:generate` e `optimize:clear` |
| `bootstrap/cache must be writable` | diretório ausente | criar `bootstrap/cache` e ajustar o dono |
| `/paginas/...` retorna 404 | páginas de demonstração não criadas | rodar `DemoStorefrontSeeder` após revisão |
| Assets 404 | build não gerado | `npm run build` no container `node` |

## 11. Sessão

- `SESSION_DRIVER` e `SESSION_LIFETIME` são lidos do `.env`. O `compose.yaml` repassa `SESSION_DRIVER` com padrão `database`.
- O driver `database` usa a tabela `sessions`, criada pelas migrations. O carrinho de visitantes depende dela.
- Valor de desenvolvimento: `SESSION_LIFETIME=10080` (7 dias). O `.env.example` traz esse valor.
- Verifique: `docker compose exec -T app sh -c 'echo $SESSION_DRIVER'` deve imprimir `database`.

## 12. Administrador de desenvolvimento

- Configure no `.env` local: `DEV_ADMIN_EMAIL` e `DEV_ADMIN_PASSWORD`. Os campos estão vazios no `.env.example`.
- Execute: `docker compose exec -T app php artisan db:seed --class=DevelopmentAdminSeeder`.
- O seeder só roda em `local` e `testing`. Fora deles, lança exceção.
- Se o usuário já existir, a senha não é alterada. O seeder só acrescenta as permissões que faltam.
- Permissões concedidas: catálogo, estoque e movimentos de estoque. Integrações de frete não são concedidas.

## 13. Seeders de demonstração

| Seeder | Conteúdo | Proteção |
|---|---|---|
| `StorefrontDevelopmentSeeder` | 7 categorias e 21 produtos fictícios (`DEMO-*`) com saldo inicial | Só `local` com banco `loja_online`; lança exceção fora disso |
| `DemoStorefrontSeeder` | 3 páginas, menu, banner hero e tema | Só `local`; não faz nada fora de `local` |
| `DevelopmentAdminSeeder` | Usuário administrador local | Só `local` e `testing` |

- Todos são idempotentes: reexecutar não duplica registros.
- Nenhum deles apaga dados ou altera integrações comerciais.
- Os produtos de demonstração não têm imagem.
- `CreateDemoBooksCatalog` (`loja:catalogo-demo-livros`) **não** deve ser usado. Ele faz exclusão lógica de produto e categoria de demonstração.

## 14. Carrinho de visitantes

- Não exige login. Fica na sessão, guardando apenas `produto_id => quantidade`.
- Preço, nome, disponibilidade e subtotal são recalculados a cada leitura. Nenhum valor vindo do navegador é aceito.
- Limite por produto: `min(estoque disponível, 10)`.
- Produtos sem controle de estoque ativo não podem ser adicionados.
- Adicionar ao carrinho não reserva nem baixa estoque.
- Itens indisponíveis (despublicados, em categoria oculta, esgotados ou com estoque reduzido) continuam visíveis com aviso e ficam fora do subtotal.
- Rotas: `/carrinho` (página) e o botão "Adicionar ao carrinho" na página de produto.
- Checkout ainda não existe. O botão aparece desabilitado.
- Mesclagem com carrinho de cliente autenticado fica para uma etapa futura.

## 15. Testes

```bash
# Carrinho
docker compose exec -T app php vendor/bin/phpunit --configuration=phpunit.xml tests/Feature/CartServiceTest.php
docker compose exec -T app php vendor/bin/phpunit --configuration=phpunit.xml tests/Feature/CartRevalidationTest.php
docker compose exec -T app php vendor/bin/phpunit --configuration=phpunit.xml tests/Feature/CartComponentsTest.php
# Autenticação administrativa
docker compose exec -T app php vendor/bin/phpunit --configuration=phpunit.xml tests/Feature/AdminAuthenticationTest.php
# JavaScript, build e estilo
docker compose exec -T node sh -c 'cd /app && npm run test:js && npm run build'
docker compose exec -T app php vendor/bin/pint --test
git diff --check
```

- Rode por arquivo ou em blocos pequenos. Com o bind mount do Docker Desktop no Windows, a suíte completa de 946 testes é muito lenta.
- Os testes PHP usam SQLite em memória (`phpunit.xml`). O banco de desenvolvimento não é tocado.

## 16. Inicializar em outra máquina

1. Clone o repositório e copie `.env.example` para `.env`.
2. Gere `APP_KEY` com `php artisan key:generate`, ou copie a chave original se houver dados criptografados (credenciais de frete).
3. Defina `DB_*`, `REDIS_PASSWORD=null` (padrão local), `DEV_ADMIN_EMAIL` e `DEV_ADMIN_PASSWORD`.
4. Suba os serviços, instale dependências, rode `npm run build` e `php artisan storage:link`.
5. Rode as migrations (`php artisan migrate`, sem `--fresh`) e, se quiser dados de demonstração, os seeders da seção 13.

Nunca versione `.env`, dumps, chaves ou backups.
