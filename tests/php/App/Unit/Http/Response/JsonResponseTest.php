<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http\Response;

use App\Http\Response\JsonResponse;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for JsonResponse
 *
 * send() emits output and calls header()/http_response_code().
 * Output is captured via ob_start()/ob_get_clean().
 * Header assertions require RunInSeparateProcess so headers_list() is accurate.
 */
#[CoversClass(JsonResponse::class)]
final class JsonResponseTest extends TestCase
{
    // ===== Output (no header inspection needed) =====

    #[Test]
    public function testSendOutputsJsonEncodedData(): void
    {
        $response = new JsonResponse(['key' => 'value']);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('value', $data['key']);
    }

    #[Test]
    public function testSendOutputsArrayData(): void
    {
        $response = new JsonResponse([1, 2, 3]);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('[1,2,3]', $output);
    }

    #[Test]
    public function testSendOutputsObjectData(): void
    {
        $response = new JsonResponse(['nested' => ['a' => 1, 'b' => 2]]);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame(1, $data['nested']['a']);
        $this->assertSame(2, $data['nested']['b']);
    }

    #[Test]
    public function testSendOutputsEmptyArray(): void
    {
        $response = new JsonResponse([]);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('[]', $output);
    }

    #[Test]
    public function testSendOutputsNullData(): void
    {
        $response = new JsonResponse(null);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('null', $output);
    }

    #[Test]
    public function testSendOutputsScalarString(): void
    {
        $response = new JsonResponse('hello');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('"hello"', $output);
    }

    #[Test]
    public function testSendOutputsIntegerData(): void
    {
        $response = new JsonResponse(42);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('42', $output);
    }

    #[Test]
    public function testSendThrowsJsonExceptionForUnencodableData(): void
    {
        // JSON_THROW_ON_ERROR (default) causes an exception for invalid UTF-8
        $response = new JsonResponse("\xB1\x31");

        ob_start();

        try {
            $this->expectException(JsonException::class);
            $response->send();
        } finally {
            ob_end_clean();
        }
    }

    // ===== Status code =====
    // Note: headers_list() always returns [] in PHP CLI mode, so Content-Type
    // header cannot be asserted here. http_response_code() works in CLI.

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsDefaultStatus200(): void
    {
        $response = new JsonResponse(['ok' => true]);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsCustomStatus201(): void
    {
        $response = new JsonResponse(['created' => true], 201);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(201, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsStatus404(): void
    {
        $response = new JsonResponse(['error' => 'Not Found'], 404);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsStatus500(): void
    {
        $response = new JsonResponse(['error' => 'Server Error'], 500);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(500, http_response_code());
    }

    // ===== Custom JSON flags =====

    #[Test]
    public function testSendRespectsCustomJsonFlags(): void
    {
        $response = new JsonResponse(['a' => 1], 200, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        // JSON_PRETTY_PRINT produces whitespace
        $this->assertStringContainsString("\n", (string) $output);
    }
}
