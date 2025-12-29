# SSL/TLS Certificate Management

This directory contains SSL/TLS certificates for HTTPS configuration.

## Quick Start

### Development (Self-Signed Certificate)

```bash
# Generate self-signed certificate
make ssl-selfsigned

# Or manually:
bash docker/nginx/certs/generate-selfsigned.sh localhost
```

### Production (Let's Encrypt)

```bash
# Setup Let's Encrypt certificate
make ssl-letsencrypt

# Or manually:
bash docker/nginx/certs/setup-letsencrypt.sh example.com admin@example.com
```

## Configuration Steps

### 1. Generate Certificates

Choose one method:

**Option A: Self-Signed (Development)**
```bash
make ssl-selfsigned
```

**Option B: Let's Encrypt (Production)**
```bash
make ssl-letsencrypt
# Follow the prompts to enter your domain and email
```

### 2. Enable SSL Configuration

**For Development:**
```bash
# Uncomment SSL port and volumes in compose.yaml
# - SSL port: NGINX_SSL_PORT
# - SSL volumes: ssl-development.conf and certs/
```

**For Production:**
```bash
# Copy and customize the production SSL template
cp docker/nginx/conf.d/ssl-production.conf.example docker/nginx/conf.d/ssl-production.conf

# Edit ssl-production.conf (update domain names)
nano docker/nginx/conf.d/ssl-production.conf

# Uncomment SSL volumes in compose.prod.yaml
```

### 3. Enable SSL Ports

Uncomment the HTTPS port and volumes in `compose.yaml` (development) or `compose.prod.yaml` (production):

**Development:**
```yaml
services:
  nginx:
    ports:
      - "${NGINX_SSL_PORT:-8443}:8443"
    volumes:
      - ./docker/nginx/conf.d/ssl-development.conf:/etc/nginx/conf.d/ssl.conf:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
```

**Production:**
```yaml
services:
  nginx:
    volumes:
      - ./docker/nginx/conf.d/ssl-production.conf:/etc/nginx/conf.d/ssl.conf:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
```

### 4. Update Environment Variables

Add to your `.env` file:

```bash
NGINX_SSL_PORT=8443
```

### 5. Mount Certificates

**Development (`compose.yaml`):**
```yaml
services:
  nginx:
    volumes:
      - ./docker/nginx/conf.d/ssl-development.conf:/etc/nginx/conf.d/ssl.conf:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
```

**Production (`compose.prod.yaml`):**
```yaml
services:
  nginx:
    volumes:
      - ./docker/nginx/conf.d/ssl-production.conf:/etc/nginx/conf.d/ssl.conf:ro
      - ./docker/nginx/certs:/etc/nginx/certs:ro
```

### 6. Restart Services

```bash
make restart
```

## Certificate Renewal

### Let's Encrypt Auto-Renewal

Let's Encrypt certificates expire every 90 days. Set up automatic renewal:

**Option A: Using Make (Recommended)**
```bash
# Add to crontab
0 0 * * * cd /path/to/project && make ssl-renew
```

**Option B: Using Certbot Directly**
```bash
# Add to crontab
0 0 * * * certbot renew --quiet --deploy-hook 'docker compose restart nginx'
```

**Test renewal:**
```bash
make ssl-renew
```

## Available Make Commands

| Command | Description |
|---------|-------------|
| `make ssl-selfsigned` | Generate self-signed certificate (dev) |
| `make ssl-letsencrypt` | Setup Let's Encrypt certificate (prod) |
| `make ssl-renew` | Renew Let's Encrypt certificate |
| `make ssl-info` | Show certificate information |
| `make ssl-clean` | Remove all certificates (destructive!) |

## File Structure

```
docker/nginx/certs/
├── README.md                    # This file
├── generate-selfsigned.sh       # Self-signed cert generator
├── setup-letsencrypt.sh         # Let's Encrypt setup script
├── cert.crt                     # Symlink to active certificate
├── cert.key                     # Symlink to active private key
├── selfsigned.crt              # Self-signed certificate (if generated)
├── selfsigned.key              # Self-signed private key (if generated)
└── letsencrypt/                # Let's Encrypt certificates (if generated)
    └── live/
        └── yourdomain.com/
            ├── fullchain.pem
            ├── privkey.pem
            └── chain.pem

docker/nginx/conf.d/
├── ssl-development.conf         # Zero-config SSL for development (committed)
└── ssl-production.conf.example  # Production SSL template (copy & customize)
```

## Security Notes

⚠️ **Important:**

1. **Never commit private keys** (`.key`, `.pem`) to version control
2. **Self-signed certificates** are for development only - browsers will show warnings
3. **Let's Encrypt** requires:
   - Your domain must point to your server's public IP
   - Port 80 must be accessible from the internet
   - Valid email for renewal notifications
4. **Certificate permissions** should be:
   - Certificates (`.crt`, `fullchain.pem`): `644` (readable)
   - Private keys (`.key`, `privkey.pem`): `600` (owner only)

## Troubleshooting

### "Certificate not found" error

```bash
# Check if certificates exist
ls -la docker/nginx/certs/

# Generate certificates
make ssl-selfsigned  # or make ssl-letsencrypt
```

### Browser shows "Not Secure" warning (Self-Signed)

This is expected for self-signed certificates. Options:
1. Add exception in browser (development only)
2. Use Let's Encrypt for valid certificates (production)

### Let's Encrypt validation fails

Check:
1. Domain DNS points to your server: `nslookup yourdomain.com`
2. Port 80 is open: `netstat -tuln | grep :80`
3. Nginx is running: `docker compose ps nginx`

### Certificate expired

```bash
# Renew certificate
make ssl-renew

# Set up auto-renewal (see "Certificate Renewal" section above)
```

## References

- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [SSL Labs Server Test](https://www.ssllabs.com/ssltest/)
- [OpenSSL Documentation](https://www.openssl.org/docs/)
