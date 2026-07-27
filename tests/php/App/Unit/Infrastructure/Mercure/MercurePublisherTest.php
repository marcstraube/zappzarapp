<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Mercure;

use App\Infrastructure\Config\CredentialLoader;
use App\Infrastructure\Mercure\MercureConfig;
use App\Infrastructure\Mercure\MercureException;
use App\Infrastructure\Mercure\MercurePublisher;
use App\Infrastructure\TlsConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Zappzarapp\Security\Secrets\FileSecretSource;
use Zappzarapp\Security\Secrets\SecretLoader;

/**
 * Tests for MercurePublisher — all HTTP I/O is intercepted via the protected
 * request() seam, so no real hub connection is made.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
#[CoversClass(MercurePublisher::class)]
#[UsesClass(MercureConfig::class)]
#[UsesClass(CredentialLoader::class)]
#[UsesClass(TlsConfig::class)]
final class MercurePublisherTest extends TestCase
{
    private string $secretsDir;

    protected function setUp(): void
    {
        $this->secretsDir = sys_get_temp_dir() . '/mercure-publisher-test-' . bin2hex(random_bytes(4));
        mkdir($this->secretsDir, 0o700, true);
        $this->clearEnvVars();
    }

    protected function tearDown(): void
    {
        $files = glob($this->secretsDir . '/*');
        foreach ($files === false ? [] : $files as $file) {
            unlink($file);
        }
        rmdir($this->secretsDir);
        $this->clearEnvVars();
    }

    private function clearEnvVars(): void
    {
        foreach (['MERCURE_URL', 'MERCURE_PUBLISH_URL', 'MERCURE_JWT_SECRET', 'MERCURE_PUBLISHER_JWT_KEY'] as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }

    private function config(string $jwtKey = 'test-key', string $url = 'https://hub/.well-known/mercure'): MercureConfig
    {
        if ($jwtKey !== '') {
            putenv('MERCURE_JWT_SECRET=' . $jwtKey);
        }

        return new MercureConfig($url, new CredentialLoader(new SecretLoader(new FileSecretSource($this->secretsDir))));
    }

    /**
     * Build a publisher whose request() seam records the call and returns a canned response.
     *
     * @param array{status: int, body: string, error: string|null} $response
     */
    private function publisher(MercureConfig $config, array $response): RecordingMercurePublisher
    {
        return new RecordingMercurePublisher($config, $response);
    }

    private const array OK_RESPONSE = ['status' => 200, 'body' => 'urn:uuid:event-1', 'error' => null];

    #[Test]
    public function itPublishesAndReturnsTheEventId(): void
    {
        $publisher = $this->publisher($this->config(), self::OK_RESPONSE);

        $eventId = $publisher->publish('https://example.com/books/1', '{"status":"sold"}');

        self::assertSame('urn:uuid:event-1', $eventId);
        self::assertSame('POST', $publisher->method);
        self::assertSame('https://hub/.well-known/mercure', $publisher->url);
    }

    #[Test]
    public function itSendsTopicAndDataInTheBody(): void
    {
        $publisher = $this->publisher($this->config(), self::OK_RESPONSE);

        $publisher->publish('https://example.com/books/1', 'hello world');

        self::assertIsString($publisher->body);
        parse_str($publisher->body, $parsed);
        self::assertSame('https://example.com/books/1', $parsed['topic']);
        self::assertSame('hello world', $parsed['data']);
    }

    #[Test]
    public function itEncodesMultipleTopicsAndOptions(): void
    {
        $publisher = $this->publisher($this->config(), self::OK_RESPONSE);

        $publisher->publish(
            ['https://example.com/a', 'https://example.com/b'],
            'payload',
            ['private' => true, 'id' => 'msg-42', 'type' => 'chat', 'retry' => 3000],
        );

        $body = (string) $publisher->body;
        self::assertStringContainsString('topic=' . rawurlencode('https://example.com/a'), $body);
        self::assertStringContainsString('topic=' . rawurlencode('https://example.com/b'), $body);
        self::assertStringContainsString('private=on', $body);
        self::assertStringContainsString('id=msg-42', $body);
        self::assertStringContainsString('type=chat', $body);
        self::assertStringContainsString('retry=3000', $body);
    }

    #[Test]
    public function itCoercesTheRetryValueToAnIntegerToPreventFieldInjection(): void
    {
        $publisher = $this->publisher($this->config(), self::OK_RESPONSE);

        // The array shape types retry as int, but PHP does not enforce that at
        // runtime; a string must not be able to inject extra form fields.
        /** @phpstan-ignore argument.type (deliberately passing an invalid runtime type to prove coercion) */
        $publisher->publish('https://example.com/books/1', 'data', ['retry' => '5&private=on']);

        $body = (string) $publisher->body;
        self::assertStringContainsString('retry=5', $body);
        self::assertStringNotContainsString('private=on', $body);
    }

    #[Test]
    public function itSignsAValidHs256PublisherToken(): void
    {
        $publisher = $this->publisher($this->config('the-signing-key'), self::OK_RESPONSE);

        $publisher->publish('https://example.com/books/1', 'data');

        $authHeader = null;
        foreach ($publisher->headers as $header) {
            if (str_starts_with($header, 'Authorization: Bearer ')) {
                $authHeader = substr($header, strlen('Authorization: Bearer '));
            }
        }

        self::assertNotNull($authHeader);
        [$header64, $payload64, $signature64] = explode('.', $authHeader);

        $header  = json_decode($this->base64UrlDecode($header64), true);
        $payload = json_decode($this->base64UrlDecode($payload64), true);
        self::assertSame('HS256', $header['alg']);
        self::assertSame(['*'], $payload['mercure']['publish']);

        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', $header64 . '.' . $payload64, 'the-signing-key', true),
        );
        self::assertSame($expected, $signature64);
    }

    #[Test]
    public function itThrowsWhenTheJwtKeyIsMissing(): void
    {
        $publisher = $this->publisher($this->config(''), self::OK_RESPONSE);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('JWT key is not configured');

        $publisher->publish('https://example.com/books/1', 'data');
    }

    #[Test]
    public function itThrowsWhenTheHubIsNotReachable(): void
    {
        $publisher = $this->publisher($this->config(), ['status' => 0, 'body' => '', 'error' => 'Connection refused']);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('not reachable');

        $publisher->publish('https://example.com/books/1', 'data');
    }

    #[Test]
    public function itThrowsOnNonSuccessStatus(): void
    {
        $publisher = $this->publisher($this->config(), ['status' => 401, 'body' => 'Unauthorized', 'error' => null]);

        $this->expectException(MercureException::class);
        $this->expectExceptionMessage('HTTP 401');

        $publisher->publish('https://example.com/books/1', 'data');
    }

    #[Test]
    public function itReportsAvailableWhenTheHubAnswers(): void
    {
        $publisher = $this->publisher($this->config(), ['status' => 400, 'body' => '', 'error' => null]);

        self::assertTrue($publisher->isAvailable());
        self::assertSame('GET', $publisher->method);
    }

    #[Test]
    public function itReportsUnavailableOnTransportError(): void
    {
        $publisher = $this->publisher($this->config(), ['status' => 0, 'body' => '', 'error' => 'timeout']);

        self::assertFalse($publisher->isAvailable());
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
