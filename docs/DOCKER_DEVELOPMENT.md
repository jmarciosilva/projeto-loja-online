# 🐳 Desenvolvimento com Docker

Guia detalhado sobre desenvolvimento local com Docker.

---

## 📦 Serviços Docker

O `compose.yaml` define os seguintes serviços:

### 1. **app** (PHP-FPM 8.3)
- Executa aplicação Laravel
- Volume: `./` → `/app` (código sincronizado)
- Porta interna: 9000 (não exposta, Nginx acessa)

### 2. **nginx** (Reverse Proxy)
- Proxy reverso HTTP
- Porta exposta: `${DOCKER_HTTP_PORT}` (padrão 80)
- Arquivo config: `docker/nginx.conf`

### 3. **mysql** (Database)
- MySQL 8.4 (imagem própria: `docker/Dockerfile.mysql`)
- Porta exposta: `${DOCKER_MYSQL_PORT}` (padrão 3306)
- Volume nomeado: `mysql_data` (persiste dados)
- Credenciais: veja `.env`

### 4. **redis** (Cache)
- Redis 7.x
- Porta interna: 6379 (não exposta, apenas app acessa)
- Volume nomeado: `redis_data`

### 5. **node** (Node.js)
- Node.js 20 LTS
- Para npm e compilação de assets (Vite)
- Acesso ao código via volume

### 6. **phpmyadmin** (Admin de BD)
- Interface web para MySQL
- Porta exposta: `${DOCKER_PHPMYADMIN_PORT}` (padrão 8081)
- Acesso em http://localhost:8081

### 7. **mailpit** (Email Testing)
- SMTP fake para desenvolvimento
- Porta SMTP: 1025 (app se conecta)
- Porta Web: `${DOCKER_MAILPIT_PORT}` (padrão 8025)
- Acesso em http://localhost:8025

---

## 📂 Volumes (Sincronização)

### Volumes Nomeados (Persistidos)
```yaml
mysql_data:          # Banco de dados (persiste entre restart)
redis_data:          # Cache
mailpit_data:        # Emails capturados
app_vendor:          # /app/vendor
app_node_modules:    # /app/node_modules
```

### Volumes com Bind Mount (Código)
```yaml
./ → /app            # Seu código sincronizado em tempo real
```

⚠️ **`vendor/` e `node_modules/` ficam em volumes nomeados**, não no host. Isso
evita a lentidão de sincronizar milhares de arquivos pequenos no Windows, mas
tem um custo: **essas pastas não aparecem no seu editor**, então o autocomplete
da IDE não enxerga as dependências. Se preferir o autocomplete à performance,
remova as duas linhas do serviço `app` no `compose.yaml`.

Consequência prática: `composer install` precisa rodar **dentro** do container
(`docker compose exec app composer install`) — rodar no host preenche um
`vendor/` que o container não usa.

---

## ⚡ Performance no WSL2 (Windows)

Se você usa Windows com WSL2 (recomendado):

### ✅ Boa Performance
- Projeto clonado dentro do WSL2 (ex: `/home/user/projects/loja-online`)
- **NÃO** em `/mnt/c` (Windows mount)

### ❌ Lenta Performance
- Projeto em `C:\Users\...\loja-online`
- File sync muito lento entre Windows e Linux

### Como Verificar
```bash
# Abra o terminal WSL2
cd ~
mkdir projects
cd projects
git clone https://github.com/jmarciosilva/loja-online.git
cd loja-online
docker compose up -d
# Será MUITO mais rápido que em /mnt/c
```

---

## 🔄 Hot Reload

⏳ **Depende do Laravel instalado** (Fase 1b) — nada disso funciona ainda.

### PHP
O bind mount `./:/app` sincroniza o código em tempo real e o OPcache está com
`validate_timestamps=1`, então alterações em `.php` valem na próxima requisição
sem reiniciar o container.

### CSS/JS (Vite)
Após instalar o Laravel:

```bash
docker compose exec node npm run dev
```

⚠️ **A porta 5173 não está publicada no `compose.yaml`.** Para acessar o dev
server do Vite a partir do host, adicione ao serviço `node`:

```yaml
    ports:
      - "5173:5173"
```

e rode o Vite com `--host 0.0.0.0` para que ele aceite conexões de fora do
container.

---

## 🐛 Debugging

### 1. Tinker (Interactive Shell)

```bash
docker compose exec app php artisan tinker

# Exemplos
>>> $user = User::first();
>>> $user->email;
>>> exit
```

### 2. Xdebug (Breakpoints)

Xdebug está instalado, mas **desligado por padrão** (`xdebug.mode = off` em
`docker/php.ini`) — com `start_with_request=yes` toda requisição tenta abrir
conexão com o depurador, o que deixa o dia a dia lento.

Para depurar, edite `docker/php.ini`:

```ini
xdebug.mode = debug
xdebug.start_with_request = yes
```

e recrie o container: `docker compose up -d --build app`. Depois configure o VS Code:

**VS Code extensions necessário:**
- Felixbecker.php-debug

**.vscode/launch.json:**
```json
{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "port": 9003,
            "pathMapping": {
                "/app": "${workspaceFolder}"
            }
        }
    ]
}
```

Depois: Pressione F5 no VS Code para "listen" e acione breakpoints com `xdebug_break()`.

### 3. Logs

```bash
# Ver logs da aplicação
docker compose logs -f app

# Ver logs do MySQL
docker compose logs -f mysql

# Ver logs do Nginx
docker compose logs -f nginx

# Todos os logs
docker compose logs -f
```

---

## 🔧 Troubleshooting Comum

### "Connection refused" ao tentar acessar MySQL

**Sintoma:**
```
SQLSTATE[HY000]: General error: 2002 No such file or directory
```

**Solução:**
1. Aguarde ~30 segundos para MySQL iniciar
2. Verifique em `.env`: `DB_HOST=mysql` (não localhost)
3. Reinicie: `docker compose restart mysql`

---

### "Port already in use"

**Sintoma:**
```
Address already in use
```

**Solução:**

Opção A: Mude a porta em `.env`
```env
DOCKER_HTTP_PORT=8080      # Usar porta 8080 em vez de 80
DOCKER_MYSQL_PORT=3307     # Usar porta 3307 em vez de 3306
```

Opção B: Libere a porta
```bash
# Windows (PowerShell com admin)
netstat -ano | findstr :80
taskkill /PID <PID> /F

# Linux/Mac
lsof -i :80
kill <PID>
```

---

### "docker compose: command not found"

**Solução:** Instale Docker Desktop (inclui docker-compose)
ou use `docker compose` (não `docker-compose`)

---

### Permissões em Linux

**Sintoma:**
```
permission denied
```

**Solução:**
```bash
# Adicione seu usuário ao grupo docker
sudo usermod -aG docker $USER
# Logout e login
exit
```

---

### Container crasheia ao iniciar

**Ver erro:**
```bash
docker compose logs app
```

**Reiniciar:**
```bash
docker compose down
docker compose up -d
```



---

### ❌ mysql.cnf não surte efeito (Windows/WSL)

**Sintoma:** As configurações de `docker/mysql.cnf` são ignoradas. Nos logs:

```
mysqld: [Warning] World-writable config file '/etc/mysql/conf.d/mysql.cnf' is ignored.
```

**Causa:** Bind mounts no Windows expõem arquivos como world-writable (0777).
O MySQL recusa ler arquivos de configuração graváveis por todos, por segurança —
e apenas emite um *warning*, sem falhar. O servidor sobe com os padrões.

**Solução (já aplicada):** o `mysql.cnf` é copiado para dentro da imagem via
`docker/Dockerfile.mysql` (`COPY` resulta em 0644), em vez de montado.

**Conferir se pegou:**
```bash
docker compose exec mysql mysql -uroot -p${DB_ROOT_PASSWORD}   -e "SELECT @@innodb_buffer_pool_size, @@max_connections;"
# esperado: 268435456 (256M) e 100 — não 134217728 e 151
```

---

### ❌ Healthcheck falha com "Connection refused" em localhost

**Causa:** o `wget` do BusyBox resolve `localhost` para `::1` (IPv6) primeiro,
mas o nginx do container escuta apenas em IPv4.

**Solução:** usar `127.0.0.1` explicitamente no healthcheck, nunca `localhost`.

---

### ⚠️ `GET /` retorna 403 antes de instalar o Laravel

Esperado: `public/` ainda não tem `index.php`, e a listagem de diretório está
desligada. Use `/health.php` para verificar a stack antes do Laravel existir.

---

## 🔐 Senhas e Dados Sensíveis

### Não commitar `.env`
```bash
# .env é ignorado (veja .gitignore)
# Use .env.example como template
```

### Em Produção
```bash
# Gere novas senhas
php artisan key:generate      # Nova APP_KEY
php artisan tinker
>>> DB::table('users')->update(['password' => bcrypt('nova-senha-segura')])
```

---

## 📊 Monitoramento

### Ver status dos containers
```bash
docker compose ps

# Output real:
# NAME              STATUS
# loja-app          Up (healthy)
# loja-nginx        Up (healthy)
# loja-mysql        Up (healthy)
# loja-redis        Up (healthy)
# loja-mailpit      Up (healthy)
# loja-node         Up
# loja-phpmyadmin   Up
```

### Ver uso de recursos
```bash
docker stats

# Atualiza em tempo real (Ctrl+C para sair)
```

---

## 🗑️ Limpeza

### Remover containers (mantém volumes)
```bash
docker compose down
```

### Remover tudo (inclusive volumes/BD)
```bash
docker compose down -v
```

### Remover imagens
```bash
docker rmi $(docker images -q)
```

---

## 📝 Arquivo Compose

Localização: `compose.yaml`

### Estrutura básica
```yaml
version: '3.9'

services:
  app:
    build: ./docker
    container_name: loja-app
    working_dir: /app
    volumes:
      - ./:/app
    depends_on:
      - mysql
      - redis

  mysql:
    build:
      context: .
      dockerfile: docker/Dockerfile.mysql
    container_name: loja-mysql
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    ports:
      - "${DOCKER_MYSQL_PORT}:3306"
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
  redis_data:
```

---

## 🚀 Boas Práticas

### ✅ Faça
- Deixe `docker compose up -d` rodando durante o dia
- Revise logs regularmente: `docker compose logs -f`
- Commit `.env.example`, não `.env`
- Use variáveis de ambiente para configuração

### ❌ Evite
- Não altere containers em produção (use code changes)
- Não delete volumes sem backup
- Não ignore erros de container crash
- Não commitar senhas em código

---

## 🔗 Referências

- [Docker Docs](https://docs.docker.com/)
- [Docker Compose Docs](https://docs.docker.com/compose/)
- [Laravel Docker Setup](https://laravel.com/docs/deployment)
- [WSL2 Performance](https://docs.microsoft.com/en-us/windows/wsl/compare-versions)

---

**Última atualização:** 2026-09-04
