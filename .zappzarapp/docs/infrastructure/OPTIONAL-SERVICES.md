# Optional Services

This document describes the optional services available in zappzarapp.

## Overview

| Service       | Profile         | Port        | Network  | Purpose                          |
| ------------- | --------------- | ----------- | -------- | -------------------------------- |
| Mercure       | `mercure`       | 8081        | backend  | Real-time messaging (SSE)        |
| Meilisearch   | `meilisearch`   | 7700        | database | Lightweight search engine        |
| Elasticsearch | `elasticsearch` | 9200, 9300  | database | Full-featured search & analytics |
| Mailpit       | `mailpit`       | 1025, 8025  | backend  | Email testing (SMTP catch-all)   |
| SeaweedFS     | `seaweedfs`     | 8333        | backend  | S3-compatible object storage     |
| RabbitMQ      | `rabbitmq`      | 5672, 15672 | backend  | Enterprise message broker        |

## Enabling Services

Enable services in `.env`:

```bash
# Enable individual services
ENABLE_MERCURE=true
ENABLE_MEILISEARCH=true
ENABLE_ELASTICSEARCH=true
ENABLE_MAILPIT=true
ENABLE_SEAWEEDFS=true
ENABLE_RABBITMQ=true
```

Then run:

```bash
make build  # Build service images
make up     # Start all enabled services
```

---

## Mercure

Real-time messaging server using Server-Sent Events (SSE). Native integration
with Symfony/API Platform.

### Configuration

```bash
# .env
ENABLE_MERCURE=true
MERCURE_PORT=8081
# JWT secret auto-generated in secrets/mercure_jwt_secret.txt
```

### Usage

**Development URL:** `http://localhost:8081/.well-known/mercure`

**PHP Integration (Symfony):**

```php
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

public function publish(HubInterface $hub): void
{
    $update = new Update(
        'https://example.com/books/1',
        json_encode(['status' => 'published'])
    );
    $hub->publish($update);
}
```

**JavaScript Client:**

```javascript
const eventSource = new EventSource(
  'http://localhost:8081/.well-known/mercure?topic=https://example.com/books/1'
);
eventSource.onmessage = (event) => {
  console.log(JSON.parse(event.data));
};
```

### Commands

```bash
make logs-mercure   # View logs
make shell-mercure  # Open shell
```

### Resources

- [Mercure Documentation](https://mercure.rocks/docs)
- [Symfony Mercure Bundle](https://symfony.com/doc/current/mercure.html)

---

## Meilisearch

Lightning-fast, typo-tolerant search engine. Lightweight alternative to
Elasticsearch.

### Configuration

```bash
# .env
ENABLE_MEILISEARCH=true
MEILISEARCH_PORT=7700
#MEILISEARCH_HOST=meilisearch
#MEILISEARCH_URL=http://meilisearch:7700
```

**Master Key:** Auto-generated in `secrets/meilisearch_master_key.txt` by
`make setup`.

### Usage

**Development URL:** `http://localhost:7700`

**PHP Integration:**

```php
use Meilisearch\Client;

$client = new Client('http://meilisearch:7700', file_get_contents('/run/secrets/meilisearch_master_key.txt'));

// Index documents
$client->index('products')->addDocuments([
    ['id' => 1, 'name' => 'iPhone 15', 'price' => 999],
    ['id' => 2, 'name' => 'Samsung Galaxy', 'price' => 899],
]);

// Search
$results = $client->index('products')->search('iPhone');
```

**Node.js Integration:**

```typescript
import { MeiliSearch } from 'meilisearch';
import { readFileSync } from 'fs';

const client = new MeiliSearch({
  host: 'http://meilisearch:7700',
  apiKey: readFileSync(
    '/run/secrets/meilisearch_master_key.txt',
    'utf8'
  ).trim(),
});

// Search
const results = await client.index('products').search('iPhone');
```

### Commands

```bash
make logs-meilisearch   # View logs
make shell-meilisearch  # Open shell
```

### Resources

- [Meilisearch Documentation](https://www.meilisearch.com/docs)
- [PHP SDK](https://github.com/meilisearch/meilisearch-php)
- [JavaScript SDK](https://github.com/meilisearch/meilisearch-js)

---

## Elasticsearch

Full-text search engine with security enabled by default.

### Quick Start

```bash
# 1. Enable Elasticsearch
ENABLE_ELASTICSEARCH=true
make up

# 2. Wait for healthy (may take 60+ seconds)
make status  # Wait until elasticsearch shows "healthy"

# 3. Generate API key (one-time setup)
make es-setup-api-key

# 4. Verify
make es-health
```

### Authentication

Elasticsearch uses API key authentication. The boilerplate generates a
development API key during setup.

| Credential         | Location                                       | Purpose                     |
| ------------------ | ---------------------------------------------- | --------------------------- |
| Bootstrap password | `secrets/elasticsearch_bootstrap_password.txt` | Initial setup, healthchecks |
| API key            | `secrets/elasticsearch_api_key.txt`            | Application access          |

### Production Setup

1. Generate strong bootstrap password:

   ```bash
   openssl rand -base64 32 > secrets/elasticsearch_bootstrap_password.txt
   ```

2. Start Elasticsearch and generate API key:

   ```bash
   make up
   make es-setup-api-key
   ```

3. Create application-specific API keys with limited permissions:

   ```bash
   curl -sk -u "elastic:$(cat secrets/elasticsearch_bootstrap_password.txt)" \
     -X POST "https://localhost:9200/_security/api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "name": "app-readonly",
       "role_descriptors": {
         "app_role": {
           "indices": [{"names": ["app-*"], "privileges": ["read"]}]
         }
       }
     }'
   ```

### Make Targets

| Target                  | Description                                 |
| ----------------------- | ------------------------------------------- |
| `make es-setup-api-key` | Generate API key (run once after ES starts) |
| `make es-health`        | Check cluster health                        |
| `make es-api-key`       | Show current API key                        |

### PHP Integration (Elasticsearch PHP Client)

```php
use Elastic\Elasticsearch\ClientBuilder;

// Load API key from secret
$apiKey = file_get_contents('/run/secrets/elasticsearch_api_key.txt');

$client = ClientBuilder::create()
    ->setHosts(['https://elasticsearch:9200'])
    ->setApiKey($apiKey)
    ->setSSLVerification(false) // For self-signed certs in development
    ->build();

// Index document
$client->index([
    'index' => 'products',
    'id' => '1',
    'body' => ['name' => 'iPhone 15', 'price' => 999]
]);

// Search
$response = $client->search([
    'index' => 'products',
    'body' => [
        'query' => ['match' => ['name' => 'iPhone']]
    ]
]);
```

### Node.js Integration

```typescript
import { Client } from '@elastic/elasticsearch';
import { readFileSync } from 'fs';

// Load API key from secret
const apiKey = readFileSync(
  '/run/secrets/elasticsearch_api_key.txt',
  'utf8'
).trim();

const client = new Client({
  node: 'https://elasticsearch:9200',
  auth: {
    apiKey: apiKey,
  },
  tls: {
    rejectUnauthorized: false, // For self-signed certs in development
  },
});

// Search
const result = await client.search({
  index: 'products',
  query: { match: { name: 'iPhone' } },
});
```

### Commands

```bash
make logs-elasticsearch   # View logs
make shell-elasticsearch  # Open shell
```

### Backup Commands

```bash
make backup-elasticsearch         # Create Elasticsearch snapshot
make backup-elasticsearch-list    # List all snapshots
make backup-elasticsearch-restore # Restore from snapshot
```

Elasticsearch backups use the native Snapshot API with a file system repository
in `backups/elasticsearch/`. Snapshots are incremental and include all indices.

### Resources

- [Elasticsearch Documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)
- [PHP Client](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html)
- [JavaScript Client](https://www.elastic.co/guide/en/elasticsearch/client/javascript-api/current/index.html)

---

## Mailpit

Email testing tool that catches all outgoing SMTP emails. Perfect for testing
email functionality without sending real emails.

### Configuration

```bash
# .env
ENABLE_MAILPIT=true
MAILPIT_SMTP_PORT=1025
MAILPIT_UI_PORT=8025
MAILPIT_MAX_MESSAGES=500
```

### Usage

**Web UI:** `http://localhost:8025` **SMTP Server:** `mailpit:1025` (from
containers) or `localhost:1025` (from host)

**PHP Integration (Configure your app):**

```php
// config/mail.php or .env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_ENCRYPTION=null
```

**Node.js Integration (Nodemailer):**

```typescript
import nodemailer from 'nodemailer';

const transporter = nodemailer.createTransport({
  host: 'mailpit',
  port: 1025,
  secure: false,
});

await transporter.sendMail({
  from: 'test@example.com',
  to: 'user@example.com',
  subject: 'Test Email',
  text: 'This will be caught by Mailpit!',
});
```

### Commands

```bash
make logs-mailpit   # View logs
make shell-mailpit  # Open shell
```

### Production Note

Mailpit is disabled in production (`replicas: 0`). Configure a real email
provider (SendGrid, Mailgun, SES) for production.

### Resources

- [Mailpit Documentation](https://mailpit.axllent.org/)
- [Mailpit GitHub](https://github.com/axllent/mailpit)

---

## SeaweedFS

S3-compatible object storage for local development. Your application code using
AWS S3 SDK will work seamlessly with both SeaweedFS and real AWS S3.

### Configuration

```bash
# .env
ENABLE_SEAWEEDFS=true
SEAWEEDFS_S3_PORT=8333
SEAWEEDFS_CONSOLE_PORT=8888

# For external S3/SeaweedFS instances (production)
#S3_ENDPOINT=https://seaweedfs:8333
#S3_ACCESS_KEY=your-access-key
#S3_SECRET_KEY=your-secret-key
#S3_BUCKET=uploads
#S3_REGION=us-east-1
```

**Credentials:** Auto-generated in `secrets/seaweedfs_admin_user.txt` and
`secrets/seaweedfs_admin_password.txt` by `make setup`.

### Usage

**Console URL:** `https://localhost:8888` **API Endpoint:**
`https://localhost:8333`

**PHP Integration (AWS SDK):**

```php
use Aws\S3\S3Client;

$client = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => 'https://seaweedfs:8333',
    'use_path_style_endpoint' => true,  // Required for SeaweedFS
    'credentials' => [
        'key' => trim(file_get_contents('/run/secrets/seaweedfs_access_key.txt')),
        'secret' => trim(file_get_contents('/run/secrets/seaweedfs_secret_key.txt'))
    ]
]);

// Upload file
$client->putObject([
    'Bucket' => 'uploads',
    'Key' => 'example.txt',
    'Body' => 'Hello, SeaweedFS!'
]);
```

**Node.js Integration (AWS SDK v3):**

```typescript
import { S3Client, PutObjectCommand } from '@aws-sdk/client-s3';
import { readFileSync } from 'fs';

const client = new S3Client({
  region: 'us-east-1',
  endpoint: 'https://seaweedfs:8333',
  forcePathStyle: true, // Required for SeaweedFS
  credentials: {
    accessKeyId: readFileSync(
      '/run/secrets/seaweedfs_access_key.txt',
      'utf8'
    ).trim(),
    secretAccessKey: readFileSync(
      '/run/secrets/seaweedfs_secret_key.txt',
      'utf8'
    ).trim(),
  },
});

await client.send(
  new PutObjectCommand({
    Bucket: 'uploads',
    Key: 'example.txt',
    Body: 'Hello, SeaweedFS!',
  })
);
```

### Commands

```bash
make logs-seaweedfs   # View logs
make shell-seaweedfs  # Open shell
```

### Backup Commands

```bash
make backup-seaweedfs         # Create encrypted SeaweedFS backup
make backup-seaweedfs-list    # List all backups
make backup-seaweedfs-restore # Restore from backup
```

SeaweedFS backups use `weed shell` to export all buckets and objects to
`backups/seaweedfs/`. Backups are encrypted with the backup encryption key.

### SSL/TLS

SeaweedFS is configured with TLS by default using the certificates in
`./docker/certs/`. The API and Console are accessible via HTTPS:

- **API:** `https://seaweedfs:8333` (internal) or `https://localhost:8333`
  (host)
- **Console:** `https://localhost:8888`

### Production Notes

**Version Pinning:** SeaweedFS uses semantic versioning. For production, pin to
a specific release in the Dockerfile:

```dockerfile
ARG SEAWEEDFS_VERSION=3.75
```

**Storage Options:** The internal SeaweedFS service can be used in production
with proper configuration (TLS, backups, monitoring). Alternatively, use real
AWS S3 or an external SeaweedFS instance - same SDK code works with all options,
just change the endpoint and credentials.

### Resources

- [SeaweedFS Documentation](https://github.com/seaweedfs/seaweedfs/wiki)
- [SeaweedFS S3 Gateway](https://github.com/seaweedfs/seaweedfs/wiki/Amazon-S3-API)
- [AWS SDK for PHP](https://aws.amazon.com/sdk-for-php/)
- [AWS SDK for JavaScript](https://aws.amazon.com/sdk-for-javascript/)

---

## RabbitMQ

Enterprise message broker for background job processing, microservice
communication, and event-driven architectures.

### Configuration

```bash
# .env
ENABLE_RABBITMQ=true
RABBITMQ_PORT=5672
RABBITMQ_MANAGEMENT_PORT=15672
RABBITMQ_VHOST=/

# For external RabbitMQ instances (production)
#RABBITMQ_HOST=rabbitmq
#RABBITMQ_URL=amqp://user:password@rabbitmq:5672/
```

**Credentials:** Auto-generated in `secrets/rabbitmq_user.txt` and
`secrets/rabbitmq_password.txt` by `make setup`.

### Usage

**Management UI:** `http://localhost:15672` **AMQP Endpoint:**
`amqp://rabbitmq:5672`

**PHP Integration (php-amqplib):**

```php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection(
    'rabbitmq',
    5672,
    trim(file_get_contents('/run/secrets/rabbitmq_user.txt')),
    trim(file_get_contents('/run/secrets/rabbitmq_password.txt'))
);

$channel = $connection->channel();
$channel->queue_declare('task_queue', false, true, false, false);

$msg = new AMQPMessage(json_encode(['task' => 'process_order', 'id' => 123]));
$channel->basic_publish($msg, '', 'task_queue');
```

**Node.js Integration (amqplib):**

```typescript
import amqp from 'amqplib';
import { readFileSync } from 'fs';

const user = readFileSync('/run/secrets/rabbitmq_user.txt', 'utf8').trim();
const password = readFileSync(
  '/run/secrets/rabbitmq_password.txt',
  'utf8'
).trim();

const connection = await amqp.connect(
  `amqp://${user}:${password}@rabbitmq:5672`
);
const channel = await connection.createChannel();

await channel.assertQueue('task_queue', { durable: true });
channel.sendToQueue(
  'task_queue',
  Buffer.from(
    JSON.stringify({
      task: 'process_order',
      id: 123,
    })
  )
);
```

### Commands

```bash
make logs-rabbitmq   # View logs
make shell-rabbitmq  # Open shell
```

### Backup Commands

```bash
make backup-rabbitmq         # Export RabbitMQ definitions
make backup-rabbitmq-list    # List all backups
make backup-rabbitmq-restore # Import definitions from backup
```

RabbitMQ backups export all definitions (users, vhosts, permissions, exchanges,
queues, bindings, policies) via the Management API to `backups/rabbitmq/`.
Backups are JSON files that can be easily inspected and edited.

**Note:** RabbitMQ backups do NOT include message data. For persistent messages,
consider draining queues before backup or using a separate message archival
strategy.

### SSL/TLS

RabbitMQ is configured with TLS support:

**Development:**

- AMQP: Port 5672 (plaintext) + Port 5671 (TLS)
- Management UI: Port 15672 (HTTP)

**Production:**

- AMQP: Port 5671 only (TLS enforced, plaintext disabled)
- Management UI: Port 15671 (HTTPS)

**TLS Connection URL:**

```text
amqps://user:password@rabbitmq:5671
```

### RabbitMQ vs Redis for Queues

| Feature         | RabbitMQ                               | Redis                   |
| --------------- | -------------------------------------- | ----------------------- |
| **Protocol**    | AMQP 0-9-1                             | Custom (pub/sub, lists) |
| **Durability**  | Full message persistence               | Optional AOF            |
| **Routing**     | Advanced (exchanges, bindings)         | Basic                   |
| **Dead Letter** | Built-in                               | Manual implementation   |
| **Management**  | Web UI included                        | External tools          |
| **Best for**    | Complex workflows, guaranteed delivery | Simple queues, caching  |

**Recommendation:** Use Redis for simple job queues. Use RabbitMQ for complex
routing, guaranteed delivery, or enterprise requirements.

### Resources

- [RabbitMQ Documentation](https://www.rabbitmq.com/documentation.html)
- [RabbitMQ Tutorials](https://www.rabbitmq.com/getstarted.html)
- [php-amqplib](https://github.com/php-amqplib/php-amqplib)
- [amqplib (Node.js)](https://github.com/amqp-node/amqplib)

---

## Choosing Between Meilisearch and Elasticsearch

| Feature            | Meilisearch                  | Elasticsearch              |
| ------------------ | ---------------------------- | -------------------------- |
| **Memory**         | ~100-200MB                   | 2GB+ minimum               |
| **Setup**          | Zero config                  | Complex                    |
| **Typo tolerance** | Built-in                     | Manual config              |
| **Aggregations**   | Basic                        | Advanced                   |
| **Geo queries**    | Limited                      | Full support               |
| **Analytics**      | No                           | Yes (Kibana)               |
| **Clustering**     | No (planned)                 | Yes                        |
| **Best for**       | Product search, autocomplete | Complex analytics, logging |

**Recommendation:**

- Start with **Meilisearch** for most web applications
- Use **Elasticsearch** only if you need advanced aggregations, geo queries, or
  the ELK stack

---

## Production Considerations

### Mercure

- JWT secret is auto-generated and stored in `secrets/mercure_jwt_secret.txt`
- Configure `CORS_ORIGINS` to restrict origins (in `MERCURE_EXTRA_DIRECTIVES`)
- Consider using a reverse proxy for HTTPS

### Meilisearch

- Master key is auto-generated and stored in `secrets/`
- Enable production mode: `MEILI_ENV=production`
- Configure resource limits in `compose.production.yaml`

### Elasticsearch

- Enable security: `xpack.security.enabled=true`
- Configure authentication and TLS
- Increase heap size: `ELASTICSEARCH_HEAP_SIZE=2g`
- Monitor cluster health and disk usage

### Mailpit

- **Do not use in production** - disabled by default (`replicas: 0`)
- Configure a real email provider (SendGrid, Mailgun, AWS SES)

### SeaweedFS

- Use real AWS S3 or external SeaweedFS for production
- Same SDK code works with both - just change endpoint/credentials
- Ensure proper backup strategy for stored objects

### RabbitMQ

- Use strong credentials (auto-generated secrets are a good start)
- Configure TLS for production
- Set up monitoring and alerts for queue depth
- Consider clustering for high availability

---

## Troubleshooting

### Service not starting

1. Check if service is enabled: `grep ENABLE_ .env`
2. Check logs: `make logs-{servicename}`
3. Verify health: `make check-health`

### Connection refused

1. Ensure service is in correct network (backend/database)
2. Use Docker service names, not localhost
3. Check port mappings in `docker compose ps`

### Elasticsearch out of memory

1. Increase heap size in `.env`: `ELASTICSEARCH_HEAP_SIZE=1g`
2. Check system memory: `docker stats`
3. Reduce other container memory limits
