# Troubleshooting Guide

Common problems and their solutions for the docker-webdev boilerplate.

## Quick Diagnostics

Before diving into specific issues, run these diagnostic commands:

```bash
# Check container status
make status

# Check container health
make check-health

# View logs
make logs

# Check Docker system
docker info
docker compose version
```

## Docker Issues

### Containers Won't Start

#### Symptom

`make up` fails or containers exit immediately.

#### Solutions

1. **Check for existing containers**

   ```bash
   docker ps -a | grep docker-webdev
   make down  # Stop any existing containers
   make up
   ```

2. **Check Docker daemon**

   ```bash
   sudo systemctl status docker
   sudo systemctl restart docker
   ```

3. **Rebuild images**

   ```bash
   make clean
   make build
   make up
   ```

4. **Complete reset** (WARNING: deletes all data)

   ```bash
   make fresh  # Type 'YES' to confirm
   ```

### "Image not found" Error

#### Symptom

```text
Error: Required images not found: php node nginx
```

#### Solution

Build images first:

```bash
make build
make up
```

### Port Already in Use

#### Symptom

```text
Error: Bind for 0.0.0.0:8080 failed: port is already allocated
```

#### Solutions

1. **Find and stop the conflicting process**

   ```bash
   sudo lsof -i :8080
   kill <PID>
   ```

2. **Change the port in `.env`**

   ```bash
   NGINX_PORT=8081
   NGINX_SSL_PORT=8444
   ```

3. **Rebuild and restart**

   ```bash
   make restart
   ```

### Docker Compose Watch Not Working

#### Symptom

File changes not syncing to containers.

#### Solutions

1. **Check if watch is running**

   ```bash
   cat .docker-watch.pid
   ps aux | grep "docker.*watch"
   ```

2. **Restart containers**

   ```bash
   make down
   make up  # Automatically starts watch
   ```

3. **Manual restart of watch**

   ```bash
   docker compose watch &
   ```

## Permission Issues

### Linux: Permission Denied Errors

#### Symptom

```text
Permission denied: /app/vendor
Permission denied: /app/node_modules
```

#### Solutions

1. **Set correct user IDs in `.env`**

   ```bash
   # Find your IDs
   id -u  # e.g., 1000
   id -g  # e.g., 1000

   # Update .env
   USER_ID=1000
   GROUP_ID=1000
   ```

2. **Fix storage permissions**

   ```bash
   chmod 770 storage -R
   ```

3. **Fix vendor/node_modules permissions**

   ```bash
   # PHP container
   docker compose exec --user root php chown -R www-data:www-data /app/vendor

   # Node container
   docker compose exec --user root node chown -R node:node /app/node_modules
   ```

### Windows: Line Ending Issues

#### Symptom

Scripts fail with `\r` errors or "bad interpreter".

#### Solution

Configure Git to use LF line endings:

```bash
git config --global core.autocrlf input
git rm --cached -r .
git reset --hard
```

See [WINDOWS.md](setup/WINDOWS.md) for detailed Windows setup.

## PHP Issues

### 502 Bad Gateway

#### Symptom

Nginx returns 502 when accessing PHP pages.

#### Solutions

1. **Check PHP-FPM is running**

   ```bash
   docker compose exec php ps aux | grep php-fpm
   ```

2. **Check PHP-FPM socket**

   ```bash
   docker compose exec php ls -la /var/run/php-fpm/
   ```

3. **Check PHP container health**

   ```bash
   docker inspect docker-webdev-php --format='{{.State.Health.Status}}'
   ```

4. **View PHP logs**

   ```bash
   make logs-php
   ```

5. **Restart PHP container**

   ```bash
   docker compose restart php
   ```

### Composer Install Fails

#### Symptom

`make composer-install` fails with memory or permission errors.

#### Solutions

1. **Increase PHP memory**

   ```bash
   docker compose exec php php -d memory_limit=-1 /usr/bin/composer install
   ```

2. **Clear Composer cache**

   ```bash
   docker compose exec php composer clear-cache
   ```

3. **Run without scripts**

   ```bash
   docker compose exec php composer install --no-scripts
   ```

### Xdebug Not Connecting

#### Symptom

Breakpoints not hitting, debugger not connecting.

#### Solutions

1. **Enable Xdebug in `.env`**

   ```bash
   XDEBUG_MODE=develop,debug
   ```

2. **Restart PHP container**

   ```bash
   docker compose restart php
   ```

3. **Check Xdebug configuration**

   ```bash
   docker compose exec php php -i | grep xdebug
   ```

See [XDEBUG.md](development/XDEBUG.md) for detailed configuration.

## Node.js Issues

### Vite Dev Server Not Starting

#### Symptom

`make node-dev` fails or HMR not working.

#### Solutions

1. **Check Node container is running**

   ```bash
   docker compose ps node
   ```

2. **Check Node logs**

   ```bash
   make logs-node
   ```

3. **Reinstall dependencies**

   ```bash
   make pnpm-install
   ```

4. **Start manually**

   ```bash
   docker compose exec node pnpm run dev
   ```

### pnpm Install Fails

#### Symptom

`make pnpm-install` fails with permission or lock errors.

#### Solutions

1. **Fix permissions**

   ```bash
   docker compose exec --user root node chown -R node:node /app/node_modules
   ```

2. **Clear pnpm cache**

   ```bash
   docker compose exec node pnpm store prune
   ```

3. **Remove lock file and reinstall**

   ```bash
   rm pnpm-lock.yaml
   docker compose exec node pnpm install
   ```

### HMR (Hot Module Replacement) Not Working

#### Symptom

Changes not reflecting in browser without manual refresh.

#### Solutions

1. **Check Vite is running**

   ```bash
   docker compose exec node pnpm run dev
   ```

2. **Check browser console** for WebSocket errors

3. **Verify Vite config** allows external connections:

   ```javascript
   // vite.config.js
   server: {
     host: '0.0.0.0',
     hmr: {
       host: 'localhost'
     }
   }
   ```

## Database Issues

### Cannot Connect to Database

#### Symptom

Application can't connect to PostgreSQL/MariaDB.

#### Solutions

1. **Check database is running**

   ```bash
   docker compose ps postgres  # or mariadb
   ```

2. **Check database health**

   ```bash
   docker inspect docker-webdev-postgres --format='{{.State.Health.Status}}'
   ```

3. **Check database logs**

   ```bash
   make logs-postgres  # or logs-mariadb
   ```

4. **Verify connection settings in `.env`**

   ```bash
   DB_TYPE=postgres
   DB_HOST=postgres  # Container name
   DB_PORT=5432
   DB_NAME=app
   DB_USER=app
   ```

5. **Test connection manually**

   ```bash
   make postgres-cli  # or mariadb-cli
   ```

### Database Password Not Working

#### Symptom

Authentication failed errors.

#### Solutions

1. **Check secrets are generated**

   ```bash
   ls -la secrets/
   cat secrets/db_password.txt
   ```

2. **Regenerate secrets**

   ```bash
   make secrets
   ```

3. **Restart database container**

   ```bash
   docker compose restart postgres  # or mariadb
   ```

4. **Complete database reset** (WARNING: deletes data)

   ```bash
   docker volume rm docker-webdev-postgres-data
   make up
   ```

### Redis Connection Failed

#### Symptom

Cannot connect to Redis, TLS errors.

#### Solutions

1. **Check Redis is running**

   ```bash
   docker compose ps redis
   ```

2. **Check Redis health**

   ```bash
   docker inspect docker-webdev-redis --format='{{.State.Health.Status}}'
   ```

3. **Verify TLS certificates exist**

   ```bash
   ls -la docker/certs/
   ```

4. **Test connection**

   ```bash
   make redis-cli
   ```

## SSL/TLS Issues

### Certificate Errors in Browser

#### Symptom

Browser shows "Your connection is not private".

#### Solutions

1. **For development** - Accept the self-signed certificate
   - Click "Advanced" → "Proceed to localhost"

2. **Regenerate certificates**

   ```bash
   make ssl-selfsigned
   make restart
   ```

3. **For production** - Use Let's Encrypt

   ```bash
   make ssl-letsencrypt
   ```

### SSL Certificate Expired

#### Symptom

Browser shows certificate expiration error.

#### Solutions

1. **Check certificate info**

   ```bash
   make ssl-info
   ```

2. **Renew Let's Encrypt**

   ```bash
   make ssl-renew
   ```

3. **Regenerate self-signed**

   ```bash
   make ssl-selfsigned
   make restart
   ```

## Build Issues

### Build Takes Too Long

#### Solutions

1. **Use BuildKit cache**

   ```bash
   DOCKER_BUILDKIT=1 make build
   ```

2. **Build specific service only**

   ```bash
   docker compose build php  # or node, nginx, etc.
   ```

### Out of Disk Space

#### Symptom

Build fails with "no space left on device".

#### Solutions

1. **Clean Docker system**

   ```bash
   docker system prune -a  # WARNING: removes all unused images
   docker volume prune     # WARNING: removes unused volumes
   ```

2. **Remove project-specific resources**

   ```bash
   make clean
   ```

## Network Issues

### Container Cannot Reach Another Container

#### Symptom

PHP can't connect to database, Nginx can't reach Node.

#### Solutions

1. **Check network configuration**

   ```bash
   docker network ls | grep docker-webdev
   docker network inspect docker-webdev-backend
   ```

2. **Verify container is in correct network**

   ```bash
   docker inspect docker-webdev-php --format='{{json .NetworkSettings.Networks}}' | jq
   ```

3. **Restart networking**

   ```bash
   make down
   docker network prune
   make up
   ```

See [NETWORK.md](infrastructure/NETWORK.md) for network architecture details.

## Test Issues

### PHPUnit Tests Failing

#### Solutions

1. **Run with verbose output**

   ```bash
   docker compose exec php vendor/bin/phpunit -v
   ```

2. **Check test database**

   ```bash
   docker compose exec php php -r "echo getenv('DATABASE_URL');"
   ```

### Vitest Tests Failing

#### Solutions

1. **Run with verbose output**

   ```bash
   docker compose exec node pnpm test -- --reporter=verbose
   ```

2. **Update snapshots**

   ```bash
   docker compose exec node pnpm test -- -u
   ```

## Getting More Help

### Collect Debug Information

```bash
# System info
docker version
docker compose version
make --version

# Container status
make status

# Recent logs
docker compose logs --tail=100

# Environment
cat .env | grep -v PASSWORD
```

### Log Locations

| Log            | Command              |
| -------------- | -------------------- |
| All containers | `make logs`          |
| Nginx          | `make logs-nginx`    |
| PHP            | `make logs-php`      |
| Node           | `make logs-node`     |
| PostgreSQL     | `make logs-postgres` |
| MariaDB        | `make logs-mariadb`  |
| Redis          | `make logs-redis`    |

### Reset Everything

When all else fails, complete reset:

```bash
make fresh  # Type 'YES' to confirm
```

This removes ALL containers, images, and volumes, then rebuilds from scratch.
