# 27: AI Infrastructure - MCP Knowledge Server

## Status

🟢 **Ready for Planning** - New feature, independent of existing work

## Goal

Implement shared knowledge infrastructure for AI CLI tools (Claude Code, OpenCode) using Model Context Protocol (MCP). Provides centralized vector-based knowledge storage with event logging, enabling seamless knowledge sharing across multiple AI development tools without incurring additional LLM API costs.

## Use Case

Enable local AI CLI tools to share knowledge via a common storage layer:

```
Claude Code CLI ──┐
                  ├──→ MCP Knowledge Server → ChromaDB + RabbitMQ
OpenCode CLI ─────┘        (Shared Knowledge)
```

**Key Point:** This is infrastructure-only. No direct LLM API calls. CLI tools (Claude Code, OpenCode) handle their own LLM interactions. This system only stores and retrieves knowledge for them.

## Prerequisites

- [ ] Docker Compose stack functional
- [ ] RabbitMQ optional service available (already exists)
- [ ] Python 3.12 support in development environment

## Architecture Overview

### Components

```
┌─────────────────────────────────────────────────────────┐
│           CLI-Tools (User's Terminal)                   │
│  • Claude Code CLI                                      │
│  • OpenCode CLI                                         │
│  • (Future tools)                                       │
└────────────┬────────────────────────────────────────────┘
             │ MCP Protocol (stdio)
             ▼
┌─────────────────────────────────────────────────────────┐
│        MCP KNOWLEDGE SERVER (Python + FastAPI)          │
│  • MCP Tools:                                           │
│    - store_knowledge(text, metadata)                    │
│    - search_knowledge(query, limit)                     │
│    - list_sessions()                                    │
│    - delete_knowledge(id)                               │
│  • Event Publishing (RabbitMQ)                          │
│  • Health Check Endpoint                                │
└───────┬─────────────────────────┬───────────────────────┘
        │                         │
        ▼                         ▼
   ChromaDB              RabbitMQ (Events)
  (Vectors)              ├─→ knowledge.stored
  - Collections          ├─→ knowledge.queried
  - Embeddings           └─→ knowledge.deleted
  - Semantic Search
```

### Why These Technologies?

**Python:**
- Native ChromaDB client (best performance)
- Rich AI ecosystem (sentence-transformers, langchain)
- MCP SDK official support
- Excellent libraries: pika (RabbitMQ), FastAPI

**ChromaDB:**
- Open-source vector database
- Built-in embeddings (sentence-transformers)
- Persistent storage
- Simple API

**RabbitMQ:**
- Event logging for audit trail
- Cross-tool awareness
- GDPR-compliant logging
- Already in zappzarapp stack

**Design Decision:** RabbitMQ is auto-enabled with `ENABLE_AI=true`
- Rationale: AI services without logging are hard to debug
- Provides audit trail for knowledge storage (GDPR compliance)
- Minimal overhead (~200 MB RAM)
- Essential for production monitoring

## Scope

### Docker Services

#### 1. ChromaDB Service

```yaml
# compose.yaml

services:
  chromadb:
    image: chromadb/chroma:0.4.22
    profiles: ["ai", "chromadb"]
    container_name: zappzarapp-chromadb
    restart: unless-stopped
    networks:
      - database
    volumes:
      - chromadb-data:/chroma/chroma
    ports:
      - "${CHROMADB_PORT:-8000}:8000"
    environment:
      - IS_PERSISTENT=true
      - ANONYMIZED_TELEMETRY=false  # Hardcoded, privacy by design
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/api/v1/heartbeat"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 10s

volumes:
  chromadb-data:
    driver: local
```

#### 2. MCP Knowledge Server Service

```yaml
# compose.yaml

services:
  mcp-server:
    build:
      context: .
      dockerfile: docker/python/Dockerfile
      target: ${ENV:-development}
    profiles: ["ai"]
    container_name: zappzarapp-mcp-server
    restart: unless-stopped
    networks:
      - backend
      - database
    volumes:
      - ./src/python:/app/src:delegated
      - ./storage/mcp:/app/storage:delegated
      - ./secrets:/run/secrets:ro
    ports:
      - "${MCP_SERVER_PORT:-8001}:8001"
    environment:
      - ENV=${ENV:-development}
      - CHROMADB_HOST=chromadb
      - CHROMADB_PORT=${CHROMADB_PORT:-8000}
      - RABBITMQ_HOST=rabbitmq
      - RABBITMQ_ENABLED=true  # Always true when AI enabled
      - LOG_LEVEL=${LOG_LEVEL:-info}
    depends_on:
      chromadb:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy
    secrets:
      - rabbitmq_user
      - rabbitmq_password
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8001/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 10s
```

#### 3. Python Dockerfile (Multi-Stage)

```dockerfile
# docker/python/Dockerfile

# ============================================================================
# Stage 1: Base
# ============================================================================
FROM python:3.12-slim AS base

WORKDIR /app

# System dependencies (minimal)
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Poetry for dependency management
RUN pip install --no-cache-dir poetry==1.8.0
RUN poetry config virtualenvs.create false

# Copy dependency files
COPY pyproject.toml poetry.lock ./

# ============================================================================
# Stage 2: Development
# ============================================================================
FROM base AS development

# Install all dependencies (including dev)
RUN poetry install --no-interaction --no-ansi

# Development tools
RUN pip install --no-cache-dir \
    debugpy \
    ipython

# Non-root user
RUN useradd -m -u 1000 appuser && chown -R appuser:appuser /app
USER appuser

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8001/health || exit 1

CMD ["python", "-m", "src.mcp_server.main"]

# ============================================================================
# Stage 3: Production
# ============================================================================
FROM base AS production

# Install only production dependencies
RUN poetry install --no-interaction --no-ansi --only main

# Copy application code
COPY src/python /app/src

# Non-root user
RUN useradd -m -u 1000 appuser && chown -R appuser:appuser /app
USER appuser

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8001/health || exit 1

CMD ["python", "-m", "src.mcp_server.main"]
```

### Python Application Structure

```
src/python/
├── mcp_server/              # MCP Knowledge Server
│   ├── __init__.py
│   ├── main.py              # FastAPI app + MCP stdio handler
│   ├── mcp_tools.py         # MCP tool implementations
│   ├── knowledge_store.py   # ChromaDB wrapper
│   ├── event_publisher.py   # RabbitMQ event publishing
│   ├── config.py            # Settings (Pydantic)
│   └── models.py            # Data models (Pydantic)
│
├── shared/                  # Shared utilities
│   ├── __init__.py
│   ├── secrets.py           # Docker secrets reader
│   ├── logger.py            # Structured logging
│   └── types.py             # Common types
│
└── tests/
    ├── __init__.py
    ├── test_mcp_tools.py
    ├── test_knowledge_store.py
    └── test_event_publisher.py
```

### Python Dependencies (Poetry)

```toml
# pyproject.toml

[tool.poetry]
name = "zappzarapp-mcp-server"
version = "0.1.0"
description = "MCP Knowledge Server for zappzarapp"
authors = ["Your Name <you@example.com>"]
readme = "README.md"
packages = [{include = "mcp_server", from = "src/python"}]

[tool.poetry.dependencies]
python = "^3.12"

# MCP Protocol
mcp = "^1.0.0"

# Vector Database
chromadb = "^0.4.22"

# Embeddings
sentence-transformers = "^3.0.0"

# Message Bus
pika = "^1.3.2"

# Web Framework (health checks + optional API)
fastapi = "^0.115.0"
uvicorn = {extras = ["standard"], version = "^0.32.0"}

# Utilities
pydantic = "^2.9.0"
pydantic-settings = "^2.5.0"
python-dotenv = "^1.0.1"
httpx = "^0.27.0"

[tool.poetry.group.dev.dependencies]
pytest = "^8.3.0"
pytest-asyncio = "^0.24.0"
pytest-cov = "^6.0.0"
black = "^24.10.0"
ruff = "^0.7.0"
mypy = "^1.13.0"
ipython = "^8.29.0"

[tool.ruff]
line-length = 100
target-version = "py312"

[tool.black]
line-length = 100
target-version = ['py312']

[tool.mypy]
python_version = "3.12"
strict = true

[build-system]
requires = ["poetry-core"]
build-backend = "poetry.core.masonry.api"
```

### MCP Tools Implementation

```python
# src/python/mcp_server/mcp_tools.py

from mcp import Tool
from typing import Dict, Any, List
from .knowledge_store import KnowledgeStore
from .event_publisher import EventPublisher

class MCPTools:
    """MCP tool implementations for knowledge management."""

    def __init__(self, knowledge_store: KnowledgeStore, event_publisher: EventPublisher):
        self.knowledge = knowledge_store
        self.events = event_publisher

    @Tool(
        name="store_knowledge",
        description="Store knowledge with semantic embeddings",
        parameters={
            "text": "Text content to store",
            "metadata": "Optional metadata (dict)",
            "collection": "Collection name (default: 'zappzarapp')"
        }
    )
    async def store_knowledge(
        self,
        text: str,
        metadata: Dict[str, Any] | None = None,
        collection: str = "zappzarapp"
    ) -> Dict[str, Any]:
        """Store knowledge in ChromaDB and publish event."""

        doc_id = await self.knowledge.store(
            text=text,
            metadata=metadata or {},
            collection=collection
        )

        # Publish event
        await self.events.publish("knowledge.stored", {
            "document_id": doc_id,
            "collection": collection,
            "metadata": metadata
        })

        return {
            "success": True,
            "document_id": doc_id,
            "message": f"Stored in collection '{collection}'"
        }

    @Tool(
        name="search_knowledge",
        description="Semantic search for stored knowledge",
        parameters={
            "query": "Search query",
            "limit": "Max results (default: 5)",
            "collection": "Collection name (default: 'zappzarapp')"
        }
    )
    async def search_knowledge(
        self,
        query: str,
        limit: int = 5,
        collection: str = "zappzarapp"
    ) -> Dict[str, Any]:
        """Search knowledge using semantic similarity."""

        results = await self.knowledge.search(
            query=query,
            limit=limit,
            collection=collection
        )

        # Publish event
        await self.events.publish("knowledge.queried", {
            "query": query,
            "results_count": len(results),
            "collection": collection
        })

        return {
            "success": True,
            "query": query,
            "results": results,
            "count": len(results)
        }

    @Tool(
        name="list_sessions",
        description="List all stored knowledge sessions",
        parameters={
            "collection": "Collection name (default: 'zappzarapp')"
        }
    )
    async def list_sessions(self, collection: str = "zappzarapp") -> Dict[str, Any]:
        """List all stored sessions with metadata."""

        sessions = await self.knowledge.list_sessions(collection)

        return {
            "success": True,
            "collection": collection,
            "sessions": sessions,
            "count": len(sessions)
        }

    @Tool(
        name="delete_knowledge",
        description="Delete knowledge by ID",
        parameters={
            "document_id": "Document ID to delete",
            "collection": "Collection name (default: 'zappzarapp')"
        }
    )
    async def delete_knowledge(
        self,
        document_id: str,
        collection: str = "zappzarapp"
    ) -> Dict[str, Any]:
        """Delete knowledge from ChromaDB."""

        await self.knowledge.delete(document_id, collection)

        # Publish event
        await self.events.publish("knowledge.deleted", {
            "document_id": document_id,
            "collection": collection
        })

        return {
            "success": True,
            "message": f"Deleted document {document_id}"
        }
```

### Knowledge Store (ChromaDB Wrapper)

```python
# src/python/mcp_server/knowledge_store.py

import chromadb
from chromadb.config import Settings
from typing import Dict, Any, List
import uuid

class KnowledgeStore:
    """Wrapper for ChromaDB vector database."""

    def __init__(self, host: str = "chromadb", port: int = 8000):
        self.client = chromadb.HttpClient(
            host=host,
            port=port,
            settings=Settings(anonymized_telemetry=False)
        )

    async def store(
        self,
        text: str,
        metadata: Dict[str, Any],
        collection: str = "zappzarapp"
    ) -> str:
        """Store text with embeddings in ChromaDB."""

        col = self.client.get_or_create_collection(
            name=collection,
            metadata={"description": "zappzarapp knowledge store"}
        )

        doc_id = str(uuid.uuid4())

        col.add(
            documents=[text],
            metadatas=[metadata],
            ids=[doc_id]
        )

        return doc_id

    async def search(
        self,
        query: str,
        limit: int = 5,
        collection: str = "zappzarapp"
    ) -> List[Dict[str, Any]]:
        """Semantic search using embeddings."""

        col = self.client.get_collection(name=collection)

        results = col.query(
            query_texts=[query],
            n_results=limit
        )

        # Format results
        formatted = []
        for i in range(len(results['ids'][0])):
            formatted.append({
                "id": results['ids'][0][i],
                "text": results['documents'][0][i],
                "metadata": results['metadatas'][0][i],
                "distance": results['distances'][0][i]
            })

        return formatted

    async def list_sessions(self, collection: str = "zappzarapp") -> List[Dict[str, Any]]:
        """List all stored sessions."""

        col = self.client.get_collection(name=collection)

        # Get all documents
        all_docs = col.get()

        return [
            {
                "id": all_docs['ids'][i],
                "metadata": all_docs['metadatas'][i]
            }
            for i in range(len(all_docs['ids']))
        ]

    async def delete(self, document_id: str, collection: str = "zappzarapp") -> None:
        """Delete document by ID."""

        col = self.client.get_collection(name=collection)
        col.delete(ids=[document_id])
```

### Event Publisher (RabbitMQ)

```python
# src/python/mcp_server/event_publisher.py

import pika
import json
from datetime import datetime
from typing import Dict, Any
from ..shared.secrets import read_secret

class EventPublisher:
    """RabbitMQ event publisher for knowledge events."""

    def __init__(self, host: str = "rabbitmq", enabled: bool = True):
        self.enabled = enabled

        if not enabled:
            return

        # Read credentials from Docker secrets
        username = read_secret("rabbitmq_user")
        password = read_secret("rabbitmq_password")

        credentials = pika.PlainCredentials(username, password)
        parameters = pika.ConnectionParameters(
            host=host,
            credentials=credentials
        )

        self.connection = pika.BlockingConnection(parameters)
        self.channel = self.connection.channel()

        # Declare exchange
        self.channel.exchange_declare(
            exchange='ai.knowledge',
            exchange_type='topic',
            durable=True
        )

    async def publish(self, event_type: str, data: Dict[str, Any]) -> None:
        """Publish event to RabbitMQ."""

        if not self.enabled:
            return

        event = {
            "type": event_type,
            "timestamp": datetime.utcnow().isoformat(),
            "data": data
        }

        self.channel.basic_publish(
            exchange='ai.knowledge',
            routing_key=f'knowledge.{event_type}',
            body=json.dumps(event)
        )

    def close(self) -> None:
        """Close connection."""
        if self.enabled and self.connection:
            self.connection.close()
```

### Configuration Management

```python
# src/python/mcp_server/config.py

from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    """Application settings from environment."""

    # Environment
    env: str = "development"
    log_level: str = "info"

    # ChromaDB
    chromadb_host: str = "chromadb"
    chromadb_port: int = 8000

    # RabbitMQ
    rabbitmq_host: str = "rabbitmq"
    rabbitmq_enabled: bool = True

    # MCP Server
    mcp_server_port: int = 8001

    class Config:
        env_file = ".env"
        case_sensitive = False

settings = Settings()
```

### Main Application

```python
# src/python/mcp_server/main.py

import asyncio
from fastapi import FastAPI
from .mcp_tools import MCPTools
from .knowledge_store import KnowledgeStore
from .event_publisher import EventPublisher
from .config import settings

app = FastAPI(title="MCP Knowledge Server")

# Initialize components
knowledge_store = KnowledgeStore(
    host=settings.chromadb_host,
    port=settings.chromadb_port
)

event_publisher = EventPublisher(
    host=settings.rabbitmq_host,
    enabled=settings.rabbitmq_enabled
)

mcp_tools = MCPTools(knowledge_store, event_publisher)

@app.get("/health")
async def health_check():
    """Health check endpoint."""
    return {
        "status": "healthy",
        "service": "mcp-knowledge-server",
        "version": "0.1.0"
    }

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "mcp_server.main:app",
        host="0.0.0.0",
        port=settings.mcp_server_port,
        reload=settings.env == "development"
    )
```

## Makefile Integration

### Profile Auto-Detection

```makefile
# Makefile Ergänzungen

# ============================================================================
# Profile Management (auto-detect from .env)
# ============================================================================

# Read ENABLE_* flags from .env and build profile list
COMPOSE_PROFILES := $(shell \
	profiles=""; \
	if grep -qE '^ENABLE_AI=true' .env; then \
		profiles="$$profiles,ai,rabbitmq"; \  # Auto-enable RabbitMQ with AI
	elif grep -qE '^ENABLE_CHROMADB=true' .env; then \
		profiles="$$profiles,chromadb"; \
	fi; \
	[ "$$(grep -E '^ENABLE_RABBITMQ=true' .env)" ] && profiles="$$profiles,rabbitmq"; \
	[ "$$(grep -E '^ENABLE_MERCURE=true' .env)" ] && profiles="$$profiles,mercure"; \
	[ "$$(grep -E '^ENABLE_MEILISEARCH=true' .env)" ] && profiles="$$profiles,meilisearch"; \
	[ "$$(grep -E '^ENABLE_ELASTICSEARCH=true' .env)" ] && profiles="$$profiles,elasticsearch"; \
	[ "$$(grep -E '^ENABLE_MAILPIT=true' .env)" ] && profiles="$$profiles,mailpit"; \
	[ "$$(grep -E '^ENABLE_SEAWEEDFS=true' .env)" ] && profiles="$$profiles,seaweedfs"; \
	echo "$$profiles" | sed 's/^,//' | sed 's/,,/,/g' \
)

# Build COMPOSE_PROFILES env var for docker compose
ifneq ($(COMPOSE_PROFILES),)
DOCKER_COMPOSE_ENV := COMPOSE_PROFILES=$(COMPOSE_PROFILES)
else
DOCKER_COMPOSE_ENV :=
endif

# ============================================================================
# Core Commands (profile-aware)
# ============================================================================

up: ## Start containers (auto-starts enabled services from .env)
	@echo "Starting containers..."
	@echo "Active profiles: $(COMPOSE_PROFILES)"
	@$(DOCKER_COMPOSE_ENV) docker compose up -d
	@$(MAKE) status

# ============================================================================
# AI Service Targets
# ============================================================================

.PHONY: ai-logs ai-shell ai-clean ai-install ai-test ai-check ai-status

ai-logs: ## Show AI service logs
	@docker compose logs -f chromadb mcp-server

ai-shell: ## Shell into MCP server
	@docker compose exec mcp-server bash

ai-shell-chromadb: ## Shell into ChromaDB
	@docker compose exec chromadb bash

ai-clean: ## Clean AI data (WARNING: deletes all stored knowledge)
	@echo "⚠️  This will delete all stored knowledge in ChromaDB!"
	@read -p "Continue? [y/N] " confirm && [ "$$confirm" = "y" ] || (echo "Cancelled"; exit 1)
	@docker compose stop chromadb mcp-server
	@docker volume rm zappzarapp_chromadb-data || true
	@echo "✓ AI data cleaned"

ai-install: ## Install Python dependencies
	@docker compose exec mcp-server poetry install

ai-test: ## Run Python tests
	@docker compose exec mcp-server poetry run pytest -v

ai-lint: ## Lint Python code
	@docker compose exec mcp-server poetry run ruff check src/python/

ai-format: ## Format Python code
	@docker compose exec mcp-server poetry run black src/python/
	@docker compose exec mcp-server poetry run ruff check --fix src/python/

ai-typecheck: ## Type-check Python code
	@docker compose exec mcp-server poetry run mypy src/python/

ai-check: ai-lint ai-typecheck ai-test ## Run all AI quality checks

ai-status: ## Show AI service status and stats
	@echo "=== AI Service Status ==="
	@docker compose ps chromadb mcp-server 2>/dev/null || echo "AI services not running"
	@echo ""
	@echo "=== ChromaDB Health ==="
	@curl -sf http://localhost:$(CHROMADB_PORT)/api/v1/heartbeat && echo "✓ Healthy" || echo "✗ Not responding"
	@echo ""
	@echo "=== MCP Server Health ==="
	@curl -sf http://localhost:$(MCP_SERVER_PORT)/health && echo "✓ Healthy" || echo "✗ Not responding"

ai-backup: ## Backup ChromaDB data
	@mkdir -p backups/chromadb
	@docker compose exec chromadb tar czf /tmp/chromadb-backup.tar.gz /chroma/chroma
	@docker compose cp chromadb:/tmp/chromadb-backup.tar.gz backups/chromadb/chromadb-$(shell date +%Y%m%d-%H%M%S).tar.gz
	@echo "✓ Backup saved to backups/chromadb/"

status: ## Show service status
	@echo "=== Container Status ==="
	@docker compose ps
	@echo ""
	@echo "=== Enabled Services ==="
	@grep -E '^ENABLE_[A-Z_]+=true' .env | sed 's/ENABLE_/  - /' | sed 's/=true//'
	@echo ""
	@echo "=== Active Profiles ==="
	@echo "  $(COMPOSE_PROFILES)" | tr ',' '\n' | sed 's/^/  - /'
```

## Environment Configuration

```bash
# .env Ergänzungen

# ============================================================================
# AI SERVICES (Optional)
# ============================================================================
# Knowledge infrastructure for CLI tools (Claude Code, OpenCode)
# Services: ChromaDB (vector store), MCP Server (knowledge API), RabbitMQ (events)
#
# When ENABLE_AI=true, RabbitMQ is automatically enabled for event logging.
# This provides audit trail and debugging capabilities.

# Enable AI infrastructure (activates: chromadb, mcp-server, rabbitmq)
ENABLE_AI=false

# Alternatively: Enable ChromaDB independently (without MCP server)
ENABLE_CHROMADB=false

# Ports
CHROMADB_PORT=8000
MCP_SERVER_PORT=8001

# RabbitMQ is auto-enabled with ENABLE_AI=true
# Or enable manually:
# ENABLE_RABBITMQ=true
```

## CLI Tool Integration

### Claude Code CLI

```json
// .claude/settings.json

{
  "mcp": {
    "zappzarapp-knowledge": {
      "command": "docker",
      "args": [
        "compose",
        "exec",
        "-T",
        "mcp-server",
        "python",
        "-m",
        "src.mcp_server.stdio"
      ],
      "env": {
        "CHROMADB_HOST": "chromadb"
      }
    }
  }
}
```

### OpenCode CLI

```yaml
# ~/.config/opencode/config.yaml

mcp:
  servers:
    - name: zappzarapp-knowledge
      command: docker compose exec -T mcp-server python -m src.mcp_server.stdio
      env:
        CHROMADB_HOST: chromadb
```

## Testing Strategy

### Unit Tests

```python
# tests/test_mcp_tools.py

import pytest
from src.mcp_server.mcp_tools import MCPTools
from src.mcp_server.knowledge_store import KnowledgeStore
from src.mcp_server.event_publisher import EventPublisher

@pytest.fixture
def mcp_tools():
    knowledge = KnowledgeStore(host="localhost", port=8000)
    events = EventPublisher(host="localhost", enabled=False)
    return MCPTools(knowledge, events)

@pytest.mark.asyncio
async def test_store_knowledge(mcp_tools):
    """Test storing knowledge."""
    result = await mcp_tools.store_knowledge(
        text="Test knowledge",
        metadata={"topic": "testing"}
    )

    assert result["success"] is True
    assert "document_id" in result

@pytest.mark.asyncio
async def test_search_knowledge(mcp_tools):
    """Test semantic search."""
    # First store
    await mcp_tools.store_knowledge(
        text="Python is a programming language",
        metadata={"topic": "programming"}
    )

    # Then search
    result = await mcp_tools.search_knowledge(
        query="programming language",
        limit=5
    )

    assert result["success"] is True
    assert result["count"] > 0
```

### Integration Tests

```python
# tests/test_integration.py

import pytest
from src.mcp_server.knowledge_store import KnowledgeStore

@pytest.mark.integration
def test_chromadb_connection():
    """Test ChromaDB connection."""
    store = KnowledgeStore(host="chromadb", port=8000)

    # Should not raise exception
    collections = store.client.list_collections()
    assert isinstance(collections, list)

@pytest.mark.integration
async def test_store_and_search_flow():
    """Test full store and search workflow."""
    store = KnowledgeStore(host="chromadb", port=8000)

    # Store knowledge
    doc_id = await store.store(
        text="FastAPI is a modern web framework",
        metadata={"framework": "fastapi"}
    )

    # Search
    results = await store.search(query="web framework", limit=5)

    # Verify
    assert len(results) > 0
    assert any(r["id"] == doc_id for r in results)
```

## Documentation

### New Documentation File

Create `.zappzarapp/docs/development/AI-INFRASTRUCTURE.md`:

```markdown
# AI Infrastructure

Knowledge-sharing infrastructure for AI CLI tools.

## Overview

The AI infrastructure provides a centralized knowledge store that AI CLI tools
(Claude Code, OpenCode) can use to share learnings, decisions, and context
across sessions.

## Components

| Service       | Purpose                | Port | RAM    |
|---------------|------------------------|------|--------|
| ChromaDB      | Vector database        | 8000 | 200 MB |
| MCP Server    | Knowledge API (MCP)    | 8001 | 100 MB |
| RabbitMQ      | Event logging          | 5672 | 200 MB |

## Quick Start

```bash
# 1. Enable in .env
ENABLE_AI=true

# 2. Start services
ENV=development make up

# 3. Verify
make ai-status

# 4. View logs
make ai-logs
```

## CLI Tool Setup

### Claude Code

Add to `.claude/settings.json`:

```json
{
  "mcp": {
    "zappzarapp-knowledge": {
      "command": "docker",
      "args": ["compose", "exec", "-T", "mcp-server", "python", "-m", "src.mcp_server.stdio"]
    }
  }
}
```

### OpenCode

Add to `~/.config/opencode/config.yaml`:

```yaml
mcp:
  servers:
    - name: zappzarapp-knowledge
      command: docker compose exec -T mcp-server python -m src.mcp_server.stdio
```

## Available MCP Tools

### store_knowledge

Store knowledge with semantic embeddings.

```python
# From CLI tool:
store_knowledge(
    text="Repository pattern isolates data access",
    metadata={"pattern": "repository", "file": "UserRepository.php"}
)
```

### search_knowledge

Semantic search for stored knowledge.

```python
# From CLI tool:
search_knowledge(
    query="how to implement repository pattern",
    limit=5
)
```

### list_sessions

List all stored knowledge sessions.

```python
# From CLI tool:
list_sessions(collection="zappzarapp")
```

### delete_knowledge

Delete knowledge by ID.

```python
# From CLI tool:
delete_knowledge(document_id="abc123")
```

## Event Logging

RabbitMQ logs all knowledge operations:

```json
// knowledge.stored event
{
  "type": "knowledge.stored",
  "timestamp": "2026-01-28T10:30:00Z",
  "data": {
    "document_id": "abc123",
    "collection": "zappzarapp",
    "metadata": {"topic": "security"}
  }
}

// knowledge.queried event
{
  "type": "knowledge.queried",
  "timestamp": "2026-01-28T10:31:00Z",
  "data": {
    "query": "security best practices",
    "results_count": 5
  }
}
```

## Backup & Recovery

```bash
# Backup ChromaDB
make ai-backup

# Restore (manual)
docker compose stop chromadb
tar xzf backups/chromadb/chromadb-YYYYMMDD-HHMMSS.tar.gz -C storage/chromadb/
docker compose start chromadb
```

## Troubleshooting

### ChromaDB not starting

```bash
# Check logs
make ai-logs

# Check health
curl http://localhost:8000/api/v1/heartbeat

# Rebuild
docker compose build chromadb
```

### MCP Server not responding

```bash
# Check health
curl http://localhost:8001/health

# Check Python logs
make ai-shell
python -m src.mcp_server.main

# Reinstall dependencies
make ai-install
```

### RabbitMQ connection issues

```bash
# Check RabbitMQ is running
docker compose ps rabbitmq

# Check credentials
cat secrets/rabbitmq_user.txt
cat secrets/rabbitmq_password.txt

# Restart RabbitMQ
docker compose restart rabbitmq
```

## Resource Usage

```
Development (ENABLE_AI=true):
- ChromaDB:   ~200 MB RAM
- MCP Server: ~100 MB RAM
- RabbitMQ:   ~200 MB RAM
- Total:      ~500 MB RAM

Disk: 1-5 GB (grows with stored knowledge)
CPU:  ~0.3 core (minimal)
```

## Production Notes

- ChromaDB telemetry is hardcoded to `false` (privacy by design)
- RabbitMQ provides GDPR-compliant audit trail
- Resource limits configured in `compose.production.yaml`
- Regular backups recommended (use `make ai-backup`)
```

## Resource Requirements

### Development Setup

| Service       | RAM     | CPU      | Disk      |
|---------------|---------|----------|-----------|
| ChromaDB      | 200 MB  | 0.2 Core | 1-5 GB    |
| MCP Server    | 100 MB  | 0.1 Core | < 100 MB  |
| RabbitMQ      | 200 MB  | 0.1 Core | < 500 MB  |
| **Total**     | **500 MB** | **0.4 Core** | **2-6 GB** |

### Full Stack Comparison

```
Standard Dev (ENABLE_AI=false):
  nginx + php + node + postgres + redis
  = ~440 MB RAM

Dev with AI (ENABLE_AI=true):
  nginx + php + node + postgres + redis + chromadb + mcp-server + rabbitmq
  = ~940 MB RAM (+500 MB)

Overhead: +500 MB RAM, +0.4 CPU cores
```

**Conclusion:** Very reasonable for local development. Even on 8GB RAM laptop.

## Success Criteria

Implementation complete when:

- [ ] Docker services configured (ChromaDB, MCP Server)
- [ ] Python Dockerfile (multi-stage, development + production)
- [ ] Python dependencies (Poetry, pyproject.toml)
- [ ] MCP tools implemented (store, search, list, delete)
- [ ] ChromaDB integration functional
- [ ] RabbitMQ event publishing working
- [ ] Makefile targets added (ai-up, ai-logs, ai-shell, ai-clean, ai-test, ai-check, ai-status, ai-backup)
- [ ] Profile auto-detection from .env working
- [ ] .env configuration documented
- [ ] CLI tool integration examples provided (Claude Code, OpenCode)
- [ ] Unit tests passing (>80% coverage)
- [ ] Integration tests passing
- [ ] Documentation created (AI-INFRASTRUCTURE.md)
- [ ] Health checks functional
- [ ] Secret management working (RabbitMQ credentials)
- [ ] Backup/restore tested
- [ ] No performance regression in existing services

## Estimated Effort

**Total: 12-16 hours**

Breakdown:

- Docker services setup: 2h
- Python project structure: 1h
- MCP tools implementation: 3-4h
- ChromaDB integration: 2h
- RabbitMQ event publishing: 1-2h
- Makefile integration: 2h
- Testing (unit + integration): 3h
- Documentation: 2h
- CLI tool integration guides: 1h

## Priority

**Medium-High** - Valuable for developers using AI tools extensively

## Dependencies

- Docker Compose stack functional
- RabbitMQ optional service exists
- Make-based workflow established
- Poetry available in development environment

## Next Steps

1. Create feature branch: `feature/ai-infrastructure-mcp`
2. Implement Docker services (ChromaDB, MCP Server)
3. Create Python project structure
4. Implement MCP tools
5. Integrate ChromaDB and RabbitMQ
6. Add Makefile targets
7. Write tests (unit + integration)
8. Create documentation
9. Test with Claude Code CLI
10. Test with OpenCode CLI
11. Code review
12. Merge to develop

## Related Tasks

- None (new feature, independent)

## Notes

- **No LLM API costs:** This is infrastructure only. CLI tools handle their own LLM interactions.
- **Privacy:** ChromaDB telemetry is hardcoded to `false`.
- **GDPR:** RabbitMQ provides audit trail for knowledge storage.
- **Scalability:** Can later add more MCP servers for different use cases.
- **Extensibility:** Can add more MCP tools (e.g., knowledge_graph, code_analysis).
