# PHP Tests

This directory contains tests for the PHP backend using PHPUnit (similar to Vitest for Node.js).

## Directory Structure

```
tests/php/               # PHP tests (PHPUnit)
├── App/                 # Application tests
│   ├── Unit/            # Unit tests for individual classes/functions
│   └── Feature/         # Feature/Integration tests for complex workflows
└── DevDashboard/        # DevDashboard tests
    ├── Controllers/
    └── Services/

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
docker compose exec php composer test -- --coverage-html build/coverage/php
```

## Coverage Reports

Coverage reports are generated in `build/coverage/php/`:
- `build/coverage/php/index.html` - HTML coverage report (similar to Vitest)
- `build/coverage/php/clover.xml` - Clover XML format for CI/CD integration

## Writing Tests

### Unit Tests

Place unit tests in `tests/php/App/Unit/` directory. Example:

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Utils\Calculator;
use DivisionByZeroError;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{
    public function testAddition(): void
    {
        $calculator = new Calculator();
        $this->assertEquals(5, $calculator->add(2, 3));
    }

    public function testDivisionByZero(): void
    {
        $this->expectException(DivisionByZeroError::class);
        $calculator = new Calculator();
        $calculator->divide(10, 0);
    }
}
```

### Feature Tests

Place feature/integration tests in `tests/php/App/Feature/` directory. Example:

```php
<?php

declare(strict_types=1);

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
- Test directories: `tests/php/App/Unit/` and `tests/php/App/Feature/`

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

### Rector (Automated Refactoring)

Refactor code to modern PHP:
```bash
make rector
```

Preview changes without applying:
```bash
make rector-dry
```

### Complete Quality Check

Run all quality checks at once:
```bash
make check  # Runs cs-check, analyse, and test
```
