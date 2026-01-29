# Task 01: Add Architecture Diagrams

## Priority

MEDIUM - Post-Release v1.1

## Estimated Effort

2-3 hours

## Context

During the Documentation & Developer Experience review, it was identified that
while text descriptions of the architecture are excellent, visual diagrams
would significantly improve understanding, especially for:

- Network topology (three-tier network segmentation)
- Service interactions (which services talk to which)
- Request flow (how HTTP requests traverse the stack)
- Data flow (how data moves through the system)

Mermaid diagrams are ideal because:

- Render natively in GitHub/GitLab
- Version-controlled as text
- Easy to maintain
- No external tools required to view

## Current State

- `.zappzarapp/docs/infrastructure/ARCHITECTURE.md` exists with text descriptions
- `.zappzarapp/docs/infrastructure/NETWORK.md` describes network topology in text
- No visual diagrams exist

## Target State

Add Mermaid diagrams to documentation:

1. Network topology diagram
2. Service interaction diagram
3. Request flow diagram
4. Development vs Production comparison

## Implementation Steps

### Step 1: Add Network Topology Diagram

Edit `.zappzarapp/docs/infrastructure/NETWORK.md` and add:

```markdown
## Network Topology Diagram

```mermaid
graph TB
    subgraph Internet
        User[User Browser]
    end

    subgraph "Frontend Network"
        Nginx[Nginx<br/>:8080/:8443]
    end

    subgraph "Backend Network"
        PHP[PHP-FPM<br/>:9000]
        Node[Node.js<br/>:3000]
        Redis[Redis<br/>:6379]
        RabbitMQ[RabbitMQ<br/>:5672]
        Mercure[Mercure<br/>:3001]
    end

    subgraph "Database Network"
        Postgres[(PostgreSQL<br/>:5432)]
        MariaDB[(MariaDB<br/>:3306)]
        Elasticsearch[(Elasticsearch<br/>:9200)]
        Meilisearch[(Meilisearch<br/>:7700)]
        SeaweedFS[(SeaweedFS<br/>:8333)]
    end

    User -->|HTTPS| Nginx
    Nginx -->|FastCGI| PHP
    Nginx -->|HTTP/WS| Node
    Nginx -->|SSE| Mercure

    PHP --> Redis
    PHP --> RabbitMQ
    PHP --> Postgres
    PHP --> MariaDB
    PHP --> Elasticsearch
    PHP --> Meilisearch
    PHP --> SeaweedFS

    Node --> Redis
    Node --> RabbitMQ
    Node --> Postgres
    Node --> Elasticsearch
    Node --> Meilisearch

    classDef frontend fill:#e1f5fe
    classDef backend fill:#fff3e0
    classDef database fill:#e8f5e9

    class Nginx frontend
    class PHP,Node,Redis,RabbitMQ,Mercure backend
    class Postgres,MariaDB,Elasticsearch,Meilisearch,SeaweedFS database
```
```

### Step 2: Add Service Architecture Diagram

Edit `.zappzarapp/docs/infrastructure/ARCHITECTURE.md` and add:

```markdown
## Service Architecture

```mermaid
graph LR
    subgraph "Web Layer"
        Nginx[Nginx]
    end

    subgraph "Application Layer"
        PHP[PHP-FPM]
        Node[Node.js]
    end

    subgraph "Cache Layer"
        Redis[Redis]
    end

    subgraph "Message Layer"
        RabbitMQ[RabbitMQ]
        Mercure[Mercure]
    end

    subgraph "Data Layer"
        Postgres[(PostgreSQL)]
        MariaDB[(MariaDB)]
    end

    subgraph "Search Layer"
        Elasticsearch[(Elasticsearch)]
        Meilisearch[(Meilisearch)]
    end

    subgraph "Storage Layer"
        SeaweedFS[(SeaweedFS)]
    end

    Nginx --> PHP
    Nginx --> Node
    Nginx --> Mercure

    PHP --> Redis
    PHP --> RabbitMQ
    PHP --> Postgres
    PHP --> MariaDB
    PHP --> Elasticsearch
    PHP --> Meilisearch
    PHP --> SeaweedFS

    Node --> Redis
    Node --> RabbitMQ
    Node --> Postgres
    Node --> Elasticsearch
    Node --> Meilisearch
```
```

### Step 3: Add Request Flow Diagram

Add to ARCHITECTURE.md:

```markdown
## Request Flow

### PHP Request

```mermaid
sequenceDiagram
    participant User
    participant Nginx
    participant PHP
    participant Redis
    participant Database

    User->>Nginx: HTTPS Request
    Nginx->>PHP: FastCGI (port 9000)
    PHP->>Redis: Session Check
    Redis-->>PHP: Session Data
    PHP->>Database: Query
    Database-->>PHP: Results
    PHP-->>Nginx: Response
    Nginx-->>User: HTTPS Response
```

### Node.js Request

```mermaid
sequenceDiagram
    participant User
    participant Nginx
    participant Node
    participant Redis
    participant Database

    User->>Nginx: HTTPS Request
    Nginx->>Node: HTTP Proxy (port 3000)
    Node->>Redis: Cache Check
    Redis-->>Node: Cache Hit/Miss
    Node->>Database: Query (if cache miss)
    Database-->>Node: Results
    Node->>Redis: Cache Update
    Node-->>Nginx: JSON Response
    Nginx-->>User: HTTPS Response
```

### WebSocket/SSE Flow

```mermaid
sequenceDiagram
    participant User
    participant Nginx
    participant Mercure
    participant PHP

    User->>Nginx: WebSocket Upgrade
    Nginx->>Mercure: SSE Connection
    Mercure-->>User: SSE Stream

    PHP->>Mercure: Publish Event
    Mercure-->>User: Push Notification
```
```

### Step 4: Add Development vs Production Diagram

```markdown
## Environment Comparison

```mermaid
graph TB
    subgraph "Development"
        Dev_Nginx[Nginx<br/>bind mounts]
        Dev_PHP[PHP + Xdebug]
        Dev_Node[Node + HMR]
        Dev_Vite[Vite Dev Server<br/>:5173]
        Dev_DB[(Database<br/>ephemeral)]
    end

    subgraph "Production"
        Prod_Nginx[Nginx<br/>read-only]
        Prod_PHP[PHP + OPcache JIT]
        Prod_Node[Node + PM2 Cluster]
        Prod_Assets[Built Assets]
        Prod_DB[(Database<br/>persistent volumes)]
    end

    Dev_Nginx -.->|make build| Prod_Nginx
    Dev_PHP -.->|make build| Prod_PHP
    Dev_Node -.->|make build| Prod_Node
    Dev_Vite -.->|pnpm build| Prod_Assets
```
```

### Step 5: Add to README

Add a visual overview to the main `README.md`:

```markdown
## Architecture Overview

```mermaid
graph LR
    User((User)) --> Nginx
    Nginx --> PHP & Node
    PHP & Node --> Redis & Database & Search
```

See [Architecture Documentation](.zappzarapp/docs/infrastructure/ARCHITECTURE.md)
for detailed diagrams.
```

## Verification

1. **Diagrams render in GitHub:**
   - Push changes to a branch
   - View the markdown files in GitHub web interface
   - Verify Mermaid diagrams render correctly

2. **Local preview (optional):**

   ```bash
   # Install mermaid-cli for local preview
   pnpm add -g @mermaid-js/mermaid-cli
   mmdc -i docs/ARCHITECTURE.md -o architecture.png
   ```

3. **Documentation links work:**

   ```bash
   grep -l "mermaid" .zappzarapp/docs/infrastructure/*.md
   # Should return files with diagrams
   ```

## Files to Modify

1. `.zappzarapp/docs/infrastructure/ARCHITECTURE.md` - Service architecture,
   request flow
2. `.zappzarapp/docs/infrastructure/NETWORK.md` - Network topology
3. `README.md` - Add visual overview

## Notes

- Mermaid syntax: https://mermaid.js.org/
- GitHub supports Mermaid in markdown since 2022
- Keep diagrams simple and focused on one concept each
- Use consistent colors/styles across diagrams
- Test rendering in GitHub before merging
