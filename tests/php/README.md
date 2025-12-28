# PHP Tests

This directory contains tests for the PHP backend using PHPUnit (similar to Vitest for Node.js).

## Directory Structure

```
tests/php/               # PHP tests (PHPUnit)
├── Unit/                # Unit tests for individual classes/functions
│   └── .gitkeep
├── Feature/             # Feature/Integration tests for complex workflows
│   └── .gitkeep
└── README.md

Note: Node.js tests are in tests/node/ (Vitest), mirroring the src/php/ and src/node/ structure.
```

## Running Tests

```bash
# Run all PHP tests
make test-php

# Run PHP tests with Xdebug for debugging
make test-php-debug

# Generate coverage report
make test-coverage-php
```

Alternatively, you can use composer commands directly in the container:

```bash
# Run tests via composer
docker compose exec php composer test

# With coverage
docker compose exec php composer test -- --coverage-html build/coverage
```

## Coverage Reports

Coverage reports are generated in `build/coverage/`:
- `build/coverage/index.html` - HTML coverage report (similar to Vitest)
- `build/coverage/clover.xml` - Clover XML format for CI/CD integration

## Writing Tests

### Unit Tests

Place unit tests in `tests/php/Unit/` directory. Example:

```php
<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Utils\Calculator;

class CalculatorTest extends TestCase
{
    public function testAddition(): void
    {
        $calculator = new Calculator();
        $this->assertEquals(5, $calculator->add(2, 3));
    }

    public function testDivisionByZero(): void
    {
        $this->expectException(\DivisionByZeroError::class);
        $calculator = new Calculator();
        $calculator->divide(10, 0);
    }
}
```

### Feature Tests

Place feature/integration tests in `tests/php/Feature/` directory. Example:

```php
<?php

namespace App\Tests\Feature;

use PHPUnit\Framework\TestCase;

class ApiEndpointTest extends TestCase
{
    public function testHealthEndpoint(): void
    {
        // Test full API workflow
        $response = $this->makeRequest('/health');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getBody());
    }
}
```

## Test Configuration

PHPUnit configuration is in `phpunit.xml` or `phpunit.xml.dist` at the project root.

Key configurations:
- Test suites: Unit and Feature
- Coverage thresholds: 80% (similar to Node.js Vitest)
- Test directories: `tests/php/Unit/` and `tests/php/Feature/`

## Quality Thresholds

Minimum coverage requirements (should match PHPUnit configuration):
- Lines: 80%
- Functions: 80%
- Branches: 80%
- Statements: 80%

Similar to Vitest's coverage requirements in the Node.js stack.

## Additional Tools

### PHPStan (Static Analysis)

Run static analysis:
```bash
make analyse
```

### PHP-CS-Fixer (Code Style)

Check code style:
```bash
make cs-check
```

Fix code style:
```bash
make cs-fix
```

### Complete Quality Check

Run all quality checks at once:
```bash
make check  # Runs cs-check, analyse, and test
```
