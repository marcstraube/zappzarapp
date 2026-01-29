# Task 06: Add Interactive API Explorer

## Priority

LOW - Future Backlog

## Estimated Effort

4-6 hours

## Context

An interactive API explorer allows developers to discover and test API endpoints
directly in the browser without writing code. This significantly improves the
developer experience, especially for teams consuming APIs built with the
boilerplate.

## Current State

- No interactive API documentation
- API endpoints documented in markdown only
- Testing requires external tools (curl, Postman, Insomnia)
- No standardized OpenAPI/Swagger specification

## Target State

1. Add OpenAPI 3.0 specification for example endpoints
2. Integrate Swagger UI for interactive exploration
3. Document how to extend specification for custom endpoints
4. Optional: Add Redoc as alternative documentation view

## Implementation Outline

### OpenAPI Specification

**Create `docs/api/openapi.yaml`:**

```yaml
openapi: 3.0.3
info:
  title: zappzarapp API
  description: |
    Example API documentation for the zappzarapp boilerplate.

    This specification demonstrates how to document your own APIs.
    Replace these example endpoints with your actual API.
  version: 1.0.0
  contact:
    name: API Support
    url: https://github.com/marcstraube/zappzarapp
  license:
    name: MIT
    url: https://opensource.org/licenses/MIT

servers:
  - url: https://localhost
    description: Local development
  - url: https://api.example.com
    description: Production (replace with your URL)

tags:
  - name: Health
    description: Health check endpoints
  - name: Examples
    description: Example CRUD endpoints

paths:
  /health:
    get:
      tags: [Health]
      summary: Health check
      description: Returns service health status
      operationId: getHealth
      responses:
        '200':
          description: Service is healthy
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/HealthResponse'
              example:
                status: healthy
                timestamp: '2024-01-15T10:30:00Z'
                services:
                  php: up
                  node: up
                  redis: up
                  postgres: up

  /api/examples:
    get:
      tags: [Examples]
      summary: List examples
      description: Returns a paginated list of examples
      operationId: listExamples
      parameters:
        - name: page
          in: query
          description: Page number
          schema:
            type: integer
            default: 1
            minimum: 1
        - name: limit
          in: query
          description: Items per page
          schema:
            type: integer
            default: 10
            minimum: 1
            maximum: 100
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ExampleList'

    post:
      tags: [Examples]
      summary: Create example
      description: Creates a new example resource
      operationId: createExample
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ExampleCreate'
      responses:
        '201':
          description: Created successfully
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Example'
        '400':
          $ref: '#/components/responses/BadRequest'
        '422':
          $ref: '#/components/responses/ValidationError'

  /api/examples/{id}:
    parameters:
      - name: id
        in: path
        required: true
        description: Example ID
        schema:
          type: string
          format: uuid

    get:
      tags: [Examples]
      summary: Get example
      description: Returns a single example by ID
      operationId: getExample
      responses:
        '200':
          description: Successful response
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Example'
        '404':
          $ref: '#/components/responses/NotFound'

    put:
      tags: [Examples]
      summary: Update example
      description: Updates an existing example
      operationId: updateExample
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ExampleUpdate'
      responses:
        '200':
          description: Updated successfully
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Example'
        '404':
          $ref: '#/components/responses/NotFound'

    delete:
      tags: [Examples]
      summary: Delete example
      description: Deletes an example
      operationId: deleteExample
      responses:
        '204':
          description: Deleted successfully
        '404':
          $ref: '#/components/responses/NotFound'

components:
  schemas:
    HealthResponse:
      type: object
      required: [status, timestamp]
      properties:
        status:
          type: string
          enum: [healthy, degraded, unhealthy]
        timestamp:
          type: string
          format: date-time
        services:
          type: object
          additionalProperties:
            type: string
            enum: [up, down]

    Example:
      type: object
      required: [id, name, createdAt]
      properties:
        id:
          type: string
          format: uuid
        name:
          type: string
          minLength: 1
          maxLength: 255
        description:
          type: string
          maxLength: 1000
        createdAt:
          type: string
          format: date-time
        updatedAt:
          type: string
          format: date-time

    ExampleCreate:
      type: object
      required: [name]
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 255
        description:
          type: string
          maxLength: 1000

    ExampleUpdate:
      type: object
      properties:
        name:
          type: string
          minLength: 1
          maxLength: 255
        description:
          type: string
          maxLength: 1000

    ExampleList:
      type: object
      required: [data, meta]
      properties:
        data:
          type: array
          items:
            $ref: '#/components/schemas/Example'
        meta:
          $ref: '#/components/schemas/PaginationMeta'

    PaginationMeta:
      type: object
      required: [page, limit, total, totalPages]
      properties:
        page:
          type: integer
        limit:
          type: integer
        total:
          type: integer
        totalPages:
          type: integer

    Error:
      type: object
      required: [code, message]
      properties:
        code:
          type: string
        message:
          type: string
        details:
          type: object

  responses:
    BadRequest:
      description: Bad request
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: BAD_REQUEST
            message: Invalid request format

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: NOT_FOUND
            message: Resource not found

    ValidationError:
      description: Validation error
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/Error'
          example:
            code: VALIDATION_ERROR
            message: Validation failed
            details:
              name: Name is required

  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: JWT token authentication

security:
  - bearerAuth: []
```

### Swagger UI Integration

**Option 1: Static HTML (Simplest)**

**Create `docs/api/index.html`:**

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>zappzarapp API Documentation</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
  <style>
    body { margin: 0; padding: 0; }
    .swagger-ui .topbar { display: none; }
  </style>
</head>
<body>
  <div id="swagger-ui"></div>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script>
    window.onload = () => {
      SwaggerUIBundle({
        url: './openapi.yaml',
        dom_id: '#swagger-ui',
        presets: [
          SwaggerUIBundle.presets.apis,
          SwaggerUIBundle.SwaggerUIStandalonePreset
        ],
        layout: 'StandaloneLayout',
        deepLinking: true,
        tryItOutEnabled: true
      });
    };
  </script>
</body>
</html>
```

**Option 2: Docker Service (For Development)**

**Add to `compose.yaml` or `compose.tools.yaml`:**

```yaml
services:
  swagger-ui:
    image: swaggerapi/swagger-ui:v5.11.0
    profiles: [tools]
    environment:
      - SWAGGER_JSON=/api/openapi.yaml
      - BASE_URL=/api-docs
    volumes:
      - ./docs/api:/api:ro
    ports:
      - '8082:8080'
    networks:
      - frontend
```

**Option 3: Nginx Route (Integrated)**

**Add to nginx config:**

```nginx
location /api-docs {
    alias /var/www/docs/api;
    index index.html;
    try_files $uri $uri/ =404;
}
```

### Redoc Alternative

**Create `docs/api/redoc.html`:**

```html
<!DOCTYPE html>
<html>
<head>
  <title>zappzarapp API Reference</title>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">
  <style>
    body { margin: 0; padding: 0; }
  </style>
</head>
<body>
  <redoc spec-url='./openapi.yaml'></redoc>
  <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
```

### PHP OpenAPI Annotations (Optional)

For auto-generating OpenAPI from PHP code:

**Install:**

```bash
composer require zircote/swagger-php
```

**Example Controller Annotation:**

```php
<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Attributes as OA;

#[OA\Info(title: 'zappzarapp API', version: '1.0.0')]
class ApiController
{
    #[OA\Get(
        path: '/api/examples',
        summary: 'List examples',
        tags: ['Examples'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success'),
        ]
    )]
    public function list(): Response
    {
        // ...
    }
}
```

**Generate spec from annotations:**

```bash
./vendor/bin/openapi src -o docs/api/openapi.yaml
```

### Makefile Targets

```makefile
##@ API Documentation

.PHONY: api-docs api-docs-serve api-docs-validate

api-docs: ## Generate OpenAPI spec from annotations (if using swagger-php)
	docker compose exec php vendor/bin/openapi src -o docs/api/openapi.yaml

api-docs-serve: ## Serve API docs locally (requires swagger-ui profile)
	docker compose --profile tools up -d swagger-ui
	@echo "API docs available at http://localhost:8082"

api-docs-validate: ## Validate OpenAPI specification
	docker run --rm -v $$(pwd)/docs/api:/api openapitools/openapi-generator-cli validate -i /api/openapi.yaml
```

### Documentation Update

**Create `docs/api/README.md`:**

```markdown
# API Documentation

This directory contains the OpenAPI specification and interactive documentation
for the zappzarapp API.

## Viewing Documentation

### Option 1: Static HTML

Open `index.html` directly in your browser, or serve it:

```bash
# Python
python -m http.server 8000 -d docs/api

# Node.js
npx serve docs/api
```

Then visit http://localhost:8000

### Option 2: Docker Service

```bash
make api-docs-serve
```

Visit http://localhost:8082

### Option 3: Via Nginx

If configured, visit https://localhost/api-docs

## Files

| File | Description |
|------|-------------|
| `openapi.yaml` | OpenAPI 3.0 specification |
| `index.html` | Swagger UI viewer |
| `redoc.html` | Redoc viewer (alternative) |

## Extending the API

1. Edit `openapi.yaml` to add your endpoints
2. Or use swagger-php annotations in PHP controllers
3. Run `make api-docs` to regenerate from annotations

## Validation

Validate your OpenAPI spec:

```bash
make api-docs-validate
```
```

## Directory Structure

```
docs/
└── api/
    ├── README.md        # Documentation guide
    ├── openapi.yaml     # OpenAPI 3.0 specification
    ├── index.html       # Swagger UI viewer
    └── redoc.html       # Redoc viewer (alternative)
```

## Notes

- OpenAPI specification is example/template - users extend for their APIs
- Swagger UI allows "Try it out" for live API testing
- Consider generating spec from code annotations for consistency
- Keep spec in sync with actual API implementation
- Can publish to Swagger Hub or Postman for team collaboration

