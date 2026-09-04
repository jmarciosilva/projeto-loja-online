# ⚡ Instalação Rápida — 5 Minutos

Guia mínimo para começar a desenvolver **agora mesmo**.

---

## 📋 Pré-requisitos

- **Docker Desktop** instalado e rodando
- **Git** instalado
- **Editor de código** (VS Code recomendado)

---

## 🚀 Passo 1: Clonar e Setup (1 minuto)

```bash
# Clone o repositório
git clone https://github.com/jmarciosilva/loja-online.git
cd loja-online

# Copie o arquivo de ambiente
cp .env.example .env
```

---

## 🐳 Passo 2: Docker Compose Up (2 minutos)

```bash
# Build e inicie os containers
docker compose up -d

# Aguarde ~30 segundos para tudo estar pronto
docker compose logs -f
# Ctrl+C para sair dos logs
```

> Se tiver erro de porta já em uso, edite `.env` alterando `DOCKER_HTTP_PORT=80` para outra porta (ex: 8080)

---

## 🔧 Passo 3: Instalar Dependências (1 minuto)

```bash
# Composer install (PHP)
docker compose exec app composer install

# Gerar chave de aplicação
docker compose exec app php artisan key:generate

# Rodas migrations e seeders
docker compose exec app php artisan migrate --seed

# NPM install (Node)
docker compose exec node npm install
```

---

## ✅ Passo 4: Verificar e Acessar (1 minuto)

Abra no browser:

| O quê | URL |
|-------|-----|
| **Aplicação** | http://localhost |
| **phpMyAdmin** | http://localhost:8081 |
| **MailPit (Emails)** | http://localhost:8025 |

---

## 👤 Login com Credenciais Padrão

**Admin:**
- Email: `admin@loja.com`
- Senha: `password`

**Cliente:**
- Email: `customer@teste.com`
- Senha: `password`

> ⚠️ Altere essas senhas em produção!

---

## 📝 Comandos Diários

### Iniciar servidor
```bash
docker compose up -d
```

### Parar servidor
```bash
docker compose down
```

### Ver logs
```bash
docker compose logs -f app        # Apenas app
docker compose logs -f            # Todos os serviços
```

### Executar Artisan
```bash
docker compose exec app php artisan <comando>

# Exemplos
docker compose exec app php artisan tinker
docker compose exec app php artisan make:model Post -m
```

### Executar npm
```bash
docker compose exec node npm run dev    # Watch mode
docker compose exec node npm run build  # Build production
```

### Acessar shell do container
```bash
docker compose exec app bash
docker compose exec node bash
```

---

## 🧪 Rodar Testes

```bash
docker compose exec app composer test
# ou com coverage
docker compose exec app composer test:coverage
```

---

## 🆘 Troubleshooting

### "Port 80 already in use"
Altere em `.env`:
```env
DOCKER_HTTP_PORT=8080
```
Depois acesse: http://localhost:8080

### "mysql connection refused"
Aguarde o MySQL iniciar (30-60 segundos) e tente novamente.

### "Permission denied" em Linux
```bash
sudo usermod -aG docker $USER
# Logout e login depois
```

### Limpar tudo e recomeçar
```bash
docker compose down -v     # Remove tudo
docker compose up -d       # Recria
docker compose exec app php artisan migrate --seed
```

---

## 📚 Próximos Passos

1. Leia [README.md](../README.md) para visão geral
2. Consulte [ROADMAP.md](../ROADMAP.md) para saber o que fazer
3. Veja [docs/ARQUITETURA.md](./ARQUITETURA.md) para entender a estrutura
4. Se tiver dúvidas sobre Docker: [docs/DOCKER_DEVELOPMENT.md](./DOCKER_DEVELOPMENT.md)

---

## 🎉 Pronto!

Você está com a aplicação rodando. Agora comece a codar!

Quer entender melhor como o Docker funciona aqui? Veja [DOCKER_DEVELOPMENT.md](./DOCKER_DEVELOPMENT.md).

---

**Última atualização:** 2026-09-04
