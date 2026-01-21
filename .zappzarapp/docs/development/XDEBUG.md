# Xdebug Configuration

Xdebug is pre-configured for step debugging, profiling, and code coverage in the
development environment.

## Overview

- **Installation:** Via PIE (PHP Installer for Extensions)
- **Activation:** Only in development mode (via `development.ini`)
- **Production:** Xdebug is not loaded (zero overhead)

## Configuration

Xdebug is automatically configured in the development environment:

| Setting                     | Value                  | Description                        |
| --------------------------- | ---------------------- | ---------------------------------- |
| `xdebug.mode`               | `develop,debug`        | Improved var_dump + step debugging |
| `xdebug.client_host`        | `host.docker.internal` | Connects automatically to your IDE |
| `xdebug.client_port`        | `9003`                 | Default Xdebug port                |
| `xdebug.idekey`             | `PHPSTORM`             | IDE key for PhpStorm               |
| `xdebug.start_with_request` | `trigger`              | Only starts when triggered         |

**Configuration files:**

- `docker/php/conf.d/development.ini` - Loads Xdebug
  (`zend_extension=xdebug.so`)
- `docker/php/conf.d/xdebug.ini` - Xdebug settings (only mounted in dev mode)

---

## PhpStorm Setup

### 1. Configure PHP Interpreter

1. **Settings → PHP**
2. Click **...** next to CLI Interpreter
3. Click **+** → **From Docker, Vagrant, VM, WSL, Remote...**
4. Select **Docker Compose**
5. Configuration file: `./compose.yaml`
6. Service: `php`

### 2. Configure Xdebug

1. **Settings → PHP → Debug**
2. **Debug port:** `9003`
3. **Can accept external connections:** ✓

### 3. Configure Server

1. **Settings → PHP → Servers**
2. Click **+** to add a server
3. **Name:** `Docker`
4. **Host:** `localhost`
5. **Port:** `8080` (or your `NGINX_PORT`)
6. **Debugger:** `Xdebug`
7. **Use path mappings:** ✓
   - Project root → `/var/www/html`

---

## Starting a Debug Session

### Option 1: Browser Extension (Recommended)

1. **Install extension:**
   - [Chrome: Xdebug Helper](https://chromewebstore.google.com/detail/xdebug-helper/eadndfjplgieldjbigjakmdgkmoaaaoc)
   - [Firefox: Xdebug Helper](https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-for-firefox/)

2. **Configure extension:** Set IDE key to `PHPSTORM`

3. **Start debugging:**
   - In PhpStorm: Click **Start Listening for PHP Debug Connections**
   - In browser: Enable debug in extension
   - Reload page

### Option 2: Query Parameter

Add `XDEBUG_TRIGGER=1` to any URL:

```text
http://localhost:8080/?XDEBUG_TRIGGER=1
http://localhost:8080/api/endpoint?XDEBUG_TRIGGER=1
```

### Option 3: Cookie

Set a cookie manually:

```text
XDEBUG_TRIGGER=PHPSTORM
```

---

## Check Xdebug Status

```bash
# In development mode
docker compose exec php php -m | grep xdebug
# Expected output: xdebug

# In production mode
docker compose exec php php -m | grep xdebug
# Expected output: (empty)
```

**Detailed information:**

```bash
docker compose exec php php -i | grep xdebug
```

---

## Troubleshooting

### Xdebug Not Connecting

**1. Check if Xdebug is loaded:**

```bash
docker compose exec php php -m | grep xdebug
```

**2. Check configuration:**

```bash
docker compose exec php php -i | grep "xdebug.client_host"
```

**3. Enable verbose logging:**

Edit `docker/php/conf.d/xdebug.ini`:

```ini
xdebug.log = /var/log/php/xdebug.log
xdebug.log_level = 7  # Very verbose
```

Then `make restart` and check `logs/php/xdebug.log`.

### Firewall Issues

Ensure PhpStorm can receive connections on port 9003. On some systems, you may
need to allow this port in your firewall.

### host.docker.internal Not Reachable

On Linux without Docker Desktop, `host.docker.internal` must be configured
manually:

```yaml
# compose.override.yaml
services:
  php:
    extra_hosts:
      - 'host.docker.internal:host-gateway'
```

---

## Coverage Reports

Xdebug can also be used for code coverage:

```bash
# PHP tests with coverage
make test-coverage-php

# Manually
docker compose exec php sh -c 'XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-html build/coverage/php'
```

---

## Profiling

For performance profiling:

1. **Change xdebug.mode:**

   ```ini
   xdebug.mode = profile
   xdebug.output_dir = /var/log/php
   ```

2. **Restart container:** `make restart`

3. **Analyze profiling data:**
   - Cachegrind files are created in `logs/php/`
   - Analyze with KCachegrind, QCachegrind, or PhpStorm

---

## Production

In production, Xdebug is **not loaded** - this means:

- Zero performance overhead
- No security risks from debug endpoints
- No memory overhead

The extension is installed in the Docker image (via PIE with `--no-enable`) but
only activated through `development.ini` in development mode.

---

## References

- [Xdebug Documentation](https://xdebug.org/docs/)
- [PhpStorm Docker Documentation](https://www.jetbrains.com/help/phpstorm/docker.html)
- [Docker & Xdebug Guide](https://www.jetbrains.com/help/phpstorm/configuring-xdebug.html)
