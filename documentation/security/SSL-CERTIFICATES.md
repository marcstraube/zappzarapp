# SSL/TLS Certificate Management

This guide covers SSL/TLS certificate setup and management for development and production environments.

## Quick Start

### Development (Self-Signed Certificate)

```bash
# Generate self-signed certificate (included in make setup)
make ssl-selfsigned
```

### Production (Let's Encrypt)

```bash
# 1. Setup Let's Encrypt certificate
make ssl-letsencrypt

# 2. Generate production nginx config
make ssl-prod-enable

# 3. Deploy
ENV=production make build && make up
```

## Configuration

### Development

SSL is **automatically configured** via the nginx entrypoint using `ssl-development.conf.template`.
No manual steps required - just ensure certificates exist (created by `make setup`).

### Production

```bash
# Generate ssl-production.conf from template
make ssl-prod-enable

# This will:
# - Prompt for domain if not set in .env
# - Generate ssl-production.conf with your domain
# - Show next steps including cron setup
```

## Certificate Renewal

### Auto-Renewal Setup (Required for Production)

Let's Encrypt certificates expire every 90 days. Set up automatic renewal:

```bash
# Edit crontab
crontab -e

# Add this line (daily at midnight):
0 0 * * * cd /path/to/project && make ssl-renew >> /var/log/ssl-renew.log 2>&1
```

### What `make ssl-renew` Does

1. Checks if certificate needs renewal (certbot handles this)
2. Renews certificate if due (within 30 days of expiry)
3. Reloads all SSL-dependent services:
   - **Nginx**: Graceful reload (`nginx -s reload`)
   - **PostgreSQL**: Restart (entrypoint re-copies certs with correct permissions)
   - **MariaDB**: Restart (entrypoint re-copies certs with correct permissions)
   - **Redis**: Restart (no graceful TLS reload available)

### Manual Commands

```bash
# Test renewal (won't actually renew unless due)
make ssl-renew

# Force reload all SSL services (without renewal)
make ssl-reload-services

# Show certificate expiry date
make ssl-info
```

## Certificate Permissions

### Why Permissions Matter

Database containers (PostgreSQL, MariaDB) require specific file permissions for SSL certificates:
- **Certificate files** (`.crt`): `644` (readable by all)
- **Private keys** (`.key`): `600` (owner only, **critical for security**)

### How Permissions Are Handled

**Development Mode:**
- Certificates are mounted to `/tmp/certs/`
- Container entrypoint scripts copy them to the correct location with proper permissions
- This happens on every container start/restart

**Production Mode:**
- Certificates are mounted directly to the final path
- Host file permissions are used (ensure correct permissions on host)

### After Certificate Renewal

The `make ssl-reload-services` command restarts database containers to ensure:
1. New certificates are loaded
2. Entrypoint scripts re-copy certs with correct permissions (dev mode)
3. All services use the renewed certificate

## Available Make Commands

| Command                    | Description                                         |
|----------------------------|-----------------------------------------------------|
| `make ssl-selfsigned`      | Generate self-signed certificate (dev)              |
| `make ssl-letsencrypt`     | Setup Let's Encrypt certificate (prod)              |
| `make ssl-prod-enable`     | Generate ssl-production.conf from template          |
| `make ssl-renew`           | Renew Let's Encrypt certificate and reload services |
| `make ssl-reload-services` | Reload all SSL-dependent services                   |
| `make ssl-info`            | Show certificate information                        |
| `make ssl-clean`           | Remove all certificates (destructive!)              |

## File Structure

```
docker/certs/
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
├── ssl-development.conf.template  # Development SSL template
├── ssl-production.conf.template   # Production SSL template
└── ssl-production.conf            # Generated (gitignored)
```

## Security Notes

1. **Never commit private keys** (`.key`, `.pem`) to version control
2. **Self-signed certificates** are for development only - browsers will show warnings
3. **Let's Encrypt** requires:
   - Domain pointing to your server's public IP
   - Port 80 accessible from the internet
   - Valid email for renewal notifications

## Troubleshooting

### "Certificate not found" error

```bash
# Check if certificates exist
ls -la docker/certs/

# Generate certificates
make ssl-selfsigned  # or make ssl-letsencrypt
```

### Browser shows "Not Secure" warning

Expected for self-signed certificates. Options:
1. Add exception in browser (development only)
2. Use Let's Encrypt for valid certificates (production)

### Let's Encrypt validation fails

Check:
1. Domain DNS points to your server: `nslookup yourdomain.com`
2. Port 80 is open: `netstat -tuln | grep :80`
3. Nginx is running: `docker compose ps nginx`

### Certificate not updating after renewal

```bash
# Force reload all services
make ssl-reload-services

# Check certificate expiry
make ssl-info
```

## References

- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [SSL Labs Server Test](https://www.ssllabs.com/ssltest/)
