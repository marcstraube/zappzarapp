# Access Log Monitoring Guide

> GDPR Art. 32 Compliance: Security of Processing

---

## Scope

**Important:** Access log monitoring tools (fail2ban, Logwatch, alerting) are
**host-level configurations** and not part of this boilerplate. This document
provides guidance on which tools to consider and how they integrate with the
Docker setup.

---

## Why Access Log Monitoring?

**GDPR Art. 32 requires appropriate security measures:**

- Detection of unauthorized access attempts
- Identification of brute-force attacks
- Monitoring for suspicious activity patterns
- Incident response capability

**Security benefits:**

- Automatic blocking of malicious IPs
- Early warning for security incidents
- Forensic analysis capability
- Compliance audit trail

---

## Relevant Log Locations

### Docker Container Logs

```bash
# View container logs directly
docker logs nginx
docker logs php
docker logs postgres
docker logs mariadb

# Follow logs in real-time
docker logs -f nginx

# Logs with timestamps
docker logs -t nginx
```

### Application Logs (Mounted Volumes)

| Log Type    | Container Path                         | Host Path (default)        |
| ----------- | -------------------------------------- | -------------------------- |
| Nginx       | `/var/log/nginx/`                      | Via `docker logs nginx`    |
| PHP         | `/var/log/php/`                        | Via `docker logs php`      |
| Application | `/var/www/html/storage/logs/`          | `./storage/logs/`          |
| Audit       | `/var/www/html/storage/logs/audit.log` | `./storage/logs/audit.log` |

### Export Logs to Host (Optional)

To make logs accessible for host-based tools, add volume mounts:

```yaml
# compose.override.yaml
services:
  nginx:
    volumes:
      - ./storage/logs/nginx:/var/log/nginx
```

---

## Recommended Tools

### 1. fail2ban (Intrusion Prevention)

Automatically bans IPs showing malicious signs.

**Use cases:**

- Block repeated failed SSH attempts
- Block HTTP brute-force attacks
- Block repeated 4xx/5xx errors

**Resources:**

- Official docs: <https://www.fail2ban.org/>
- Docker integration: Use host-based fail2ban reading container logs

**Example jail for nginx (host-side):**

```ini
# /etc/fail2ban/jail.d/nginx.conf
[nginx-http-auth]
enabled = true
filter = nginx-http-auth
logpath = /path/to/project/storage/logs/nginx/error.log
maxretry = 3
bantime = 3600

[nginx-botsearch]
enabled = true
filter = nginx-botsearch
logpath = /path/to/project/storage/logs/nginx/access.log
maxretry = 2
bantime = 86400
```

### 2. Logwatch (Log Analysis & Reporting)

Daily log analysis and summary reports.

**Use cases:**

- Daily security summary emails
- Anomaly detection
- Trend analysis

**Resources:**

- Official docs: <https://sourceforge.net/projects/logwatch/>

### 3. Prometheus + Grafana (Metrics & Alerting)

Real-time monitoring and alerting.

**Use cases:**

- Request rate monitoring
- Error rate alerting
- Resource usage tracking

**Resources:**

- Prometheus: <https://prometheus.io/>
- Grafana: <https://grafana.com/>

### 4. Loki (Log Aggregation)

Centralized log management (pairs with Grafana).

**Use cases:**

- Centralized log search
- Log correlation across services
- Long-term log retention

**Resources:**

- Official docs: <https://grafana.com/oss/loki/>

### 5. OSSEC/Wazuh (Host-based IDS)

Comprehensive intrusion detection system.

**Use cases:**

- File integrity monitoring
- Rootkit detection
- Active response

**Resources:**

- Wazuh: <https://wazuh.com/>
- OSSEC: <https://www.ossec.net/>

---

## Quick Start Recommendations

| Need                      | Recommended Tool     | Complexity |
| ------------------------- | -------------------- | ---------- |
| Block brute-force attacks | fail2ban             | Low        |
| Daily log summaries       | Logwatch             | Low        |
| Real-time alerting        | Prometheus + Grafana | Medium     |
| Centralized logging       | Loki + Grafana       | Medium     |
| Full IDS/SIEM             | Wazuh                | High       |

**Minimum recommendation:** Start with fail2ban for basic intrusion prevention.

---

## Integration with Audit Logging

The application's [Audit Logging](./AUDIT-LOGGING.md) system provides:

- User action tracking (stored in database)
- GDPR-compliant access records
- Tamper-proof logs with checksums

Access log monitoring complements this by:

- Detecting attacks before authentication
- Blocking malicious IPs at network level
- Monitoring infrastructure-level events

---

## Related Documentation

- [Audit Logging Guide](./AUDIT-LOGGING.md) - Application-level access logging
- [Retention Policy Guide](./RETENTION-POLICY.md) - Log retention and cleanup
- [Backup Guide](./BACKUP.md) - Log backup considerations
