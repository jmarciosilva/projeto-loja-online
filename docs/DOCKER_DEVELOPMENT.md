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
- MySQL 8.4
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
mysql_data:     # Banco de dados (persiste entre restart)
redis_data:     # Cache data
```

### Volumes com Bind Mount (Código)
```yaml
./ → /app           # Seu código sincronizado em tempo real
./docker → /docker  # Configurações Docker
```

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

## 🔄 Hot Reload (Desenvolviment Automático)

### PHP (Livewire + AlpineJS)
Alterações em `.php` e `.blade.php` recarregam **automaticamente** no browser (via Livewire).

Não precisa reiniciar container!

### CSS/JS (Vite)
Alterações em `resources/css` e `resources/js` recompilam **automaticamente**.

```bash
# Deixe rodando durante desenvolvimento
docker compose exec node npm run dev
```

Acesse http://localhost:5173 para ver Vite dev server (ou http://localhost se tudo integrado).

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

Xdebug já está instalado em `docker/Dockerfile`. Configure no VS Code:

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

# Output:
# NAME      IMAGE      STATUS
# app       php:8.3    Up 5 minutes
# mysql     mysql:8.4  Up 5 minutes
# ...
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
    image: mysql:8.4
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
