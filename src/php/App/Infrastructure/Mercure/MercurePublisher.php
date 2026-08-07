<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use App\Infrastructure\TlsConfig;
use CurlHandle;
use JsonException;

/**
 * Mercure Publisher
 *
 * Publishes updates to the Mercure hub over HTTP. Authenticates with a
 * short JWT (HS256) signed with the shared publisher key and grants the
 * "publish all topics" claim.
 *
 * All network I/O goes through the protected {@see request()} seam so the
 * publish logic (JWT, body building, response handling) is unit-testable
 * without a running hub.
 *
 * @package Infrastructure\Mercure
 */
class MercurePublisher implements MercureInterface
{
    /** Total request timeout in seconds */
    private const int REQUEST_TIMEOUT = 5;

    /** Connection timeout in seconds */
    private const int CONNECT_TIMEOUT = 2;

    public function __construct(private readonly MercureConfig $config)
    {
    }

    public function publish(string|array $topics, string $data, array $options = []): string
    {
        if ($this->config->jwtKey === '') {
            throw new MercureException('Mercure JWT key is not configured');
        }

        $body     = $this->buildBody($topics, $data, $options);
        $response = $this->request('POST', $this->config->url, $body, [
            'Authorization: Bearer ' . $this->generateToken(),
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($response['error'] !== null) {
            throw new MercureException(sprintf('Mercure hub not reachable: %s', $response['error']));
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new MercureException(sprintf('Mercure hub returned HTTP %d', $response['status']));
        }

        return trim($response['body']);
    }

    public function isAvailable(): bool
    {
        // A GET without a topic returns 4xx but still proves the hub answers.
        $response = $this->request('GET', $this->config->url, null, []);

        return $response['error'] === null && $response['status'] > 0;
    }

    /**
     * Build the application/x-www-form-urlencoded publish body
     *
     * @param string|list<string> $topics
     * @param array{private?: bool, id?: string, type?: string, retry?: int} $options
     */
    private function buildBody(string|array $topics, string $data, array $options): string
    {
        $topicList = is_array($topics) ? $topics : [$topics];

        if ($topicList === []) {
            throw new MercureException('At least one topic is required');
        }

        $fields = [];
        foreach ($topicList as $topic) {
            $fields[] = 'topic=' . rawurlencode($topic);
        }

        $fields[] = 'data=' . rawurlencode($data);

        if (($options['private'] ?? false) === true) {
            $fields[] = 'private=on';
        }

        foreach (['id', 'type'] as $key) {
            $value = $options[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $fields[] = $key . '=' . rawurlencode($value);
            }
        }

        if (isset($options['retry'])) {
            // Encode like every other field: the array shape types this as int,
            // but PHP does not enforce that at runtime, so a stray string must
            // not be able to inject extra form fields into the request body.
            $fields[] = 'retry=' . rawurlencode((string) $options['retry']);
        }

        return implode('&', $fields);
    }

    /**
     * Generate a short-lived publisher JWT (HS256) granting publish on all topics
     */
    private function generateToken(): string
    {
        try {
            $header  = json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR);
            $payload = json_encode(['mercure' => ['publish' => ['*']]], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MercureException('Failed to encode JWT: ' . $exception->getMessage(), 0, $exception);
        }

        $segments     = [$this->base64UrlEncode($header), $this->base64UrlEncode($payload)];
        $signingInput = implode('.', $segments);
        $signature    = hash_hmac('sha256', $signingInput, $this->config->jwtKey, true);
        $segments[]   = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * URL-safe base64 without padding (RFC 7515 base64url)
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send an HTTP request to the hub (I/O seam, overridden in tests)
     *
     * @param non-empty-string $method
     * @param array<int, string> $headers
     * @return array{status: int, body: string, error: string|null}
     */
    protected function request(string $method, string $url, ?string $body, array $headers): array
    {
        if ($url === '') {
            return ['status' => 0, 'body' => '', 'error' => 'Empty hub URL'];
        }

        $curl = curl_init();
        if ($curl === false) {
            return ['status' => 0, 'body' => '', 'error' => 'Failed to initialize cURL'];
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        if ($headers !== []) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        $this->configureTls($curl);

        $responseBody = curl_exec($curl);

        if ($responseBody === false) {
            return ['status' => 0, 'body' => '', 'error' => curl_error($curl) ?: 'Unknown cURL error'];
        }

        return [
            'status' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'body'   => is_string($responseBody) ? $responseBody : '',
            'error'  => null,
        ];
    }

    /**
     * Apply TLS verification settings for HTTPS hub URLs
     */
    private function configureTls(CurlHandle $curl): void
    {
        if (!$this->config->useTls) {
            return;
        }

        $verify = TlsConfig::shouldVerify();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

        $caFile = TlsConfig::getCaFile();
        if ($caFile !== null && $caFile !== '') {
            curl_setopt($curl, CURLOPT_CAINFO, $caFile);
        }
    }
}
