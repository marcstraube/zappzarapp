<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use CurlHandle;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

/**
 * S3-Compatible Storage Client
 *
 * Native PHP implementation of S3 API using cURL.
 * No external dependencies - implements AWS Signature Version 4.
 *
 * Compatible with:
 * - SeaweedFS (recommended for this boilerplate)
 * - MinIO
 * - AWS S3
 * - Other S3-compatible services
 *
 * Configuration via environment variables:
 * - S3_ENDPOINT_URL or SEAWEEDFS_ENDPOINT: Storage endpoint
 * - S3_ACCESS_KEY or SEAWEEDFS_S3_ACCESS_KEY: Access key
 * - S3_SECRET_KEY or SEAWEEDFS_S3_SECRET_KEY: Secret key
 * - S3_REGION or SEAWEEDFS_REGION: Region (default: us-east-1)
 * - S3_BUCKET or SEAWEEDFS_BUCKET: Default bucket
 *
 * Error Handling:
 * - Connection failures return null/false (no exceptions thrown to caller)
 * - Use isAvailable() to check connection status
 *
 * Usage:
 * <code>
 * $storage = new S3Storage();
 *
 * // Upload
 * $url = $storage->upload('avatars/user-123.jpg', $imageData, 'image/jpeg');
 *
 * // Download
 * $content = $storage->download('avatars/user-123.jpg');
 *
 * // List
 * $files = $storage->list('avatars/');
 *
 * // Delete
 * $storage->delete('avatars/user-123.jpg');
 * </code>
 *
 * @package Infrastructure\Storage
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Interface requires many methods
 * @SuppressWarnings("PHPMD.TooManyMethods") S3 API with AWS Sig V4 requires many helper methods
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") S3 client needs multiple operations
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") S3 API requires many operations
 * @SuppressWarnings("PHPMD.ExcessiveClassLength") S3 API with AWS Sig V4 requires many helper methods
 */
final class S3Storage implements StorageInterface
{
    private readonly StorageConfig $config;

    /** @var CurlHandle|null cURL handle for connection pooling */
    private ?CurlHandle $curlHandle = null;

    public function __construct(
        ?string $endpoint = null,
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?string $region = null,
        ?string $bucket = null
    ) {
        $this->config = new StorageConfig($endpoint, $accessKey, $secretKey, $region, $bucket);
    }

    public function upload(string $key, string $content, string $contentType, array $metadata = []): ?string
    {
        try {
            $headers = [
                'Content-Type'   => $contentType,
                'Content-Length' => (string) strlen($content),
            ];

            // Add custom metadata
            foreach ($metadata as $name => $value) {
                $headers['x-amz-meta-' . strtolower($name)] = $value;
            }

            $response = $this->request('PUT', $key, $content, $headers);

            if ($response === null) {
                return null;
            }

            return $this->getPublicUrl($key);
        } catch (Throwable) {
            return null;
        }
    }

    public function uploadFile(string $key, string $filePath, ?string $contentType = null, array $metadata = []): ?string
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Auto-detect content type if not provided
        if ($contentType === null) {
            $contentType = $this->detectContentType($filePath);
        }

        return $this->upload($key, $content, $contentType, $metadata);
    }

    public function download(string $key): ?string
    {
        try {
            return $this->request('GET', $key);
        } catch (Throwable) {
            return null;
        }
    }

    public function downloadFile(string $key, string $filePath): bool
    {
        $content = $this->download($key);

        if ($content === null) {
            return false;
        }

        $result = file_put_contents($filePath, $content);

        return $result !== false;
    }

    public function exists(string $key): bool
    {
        try {
            $response = $this->request('HEAD', $key);

            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $response = $this->request('DELETE', $key);

            // DELETE returns empty response on success
            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteMultiple(array $keys): int
    {
        $deleted = 0;

        foreach ($keys as $key) {
            if ($this->delete($key)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function list(string $prefix = '', int $maxKeys = 1000): array
    {
        try {
            $queryParams = ['list-type' => '2', 'max-keys' => (string) $maxKeys];

            if ($prefix !== '') {
                $queryParams['prefix'] = $prefix;
            }

            $response = $this->request('GET', '', null, [], $queryParams);

            if ($response === null) {
                return [];
            }

            // Parse XML response
            return $this->parseListResponse($response);
        } catch (Throwable) {
            return [];
        }
    }

    public function copy(string $sourceKey, string $destKey): bool
    {
        try {
            $headers = [
                'x-amz-copy-source' => '/' . $this->config->bucket . '/' . ltrim($sourceKey, '/'),
            ];

            $response = $this->request('PUT', $destKey, null, $headers);

            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function move(string $sourceKey, string $destKey): bool
    {
        if (!$this->copy($sourceKey, $destKey)) {
            return false;
        }

        return $this->delete($sourceKey);
    }

    /**
     * @SuppressWarnings("PHPMD.UnusedFormalParameter") $curlHandle required by cURL callback signature
     */
    public function getMetadata(string $key): ?array
    {
        try {
            $curl     = $this->getCurlHandle();
            $url      = $this->buildUrl($key);
            $headers  = $this->signRequest('HEAD', $key);
            $httpHead = [];

            foreach ($headers as $name => $value) {
                $httpHead[] = sprintf('%s: %s', $name, $value);
            }

            /** @var array<string, string> $responseHeaders */
            $responseHeaders = [];

            curl_reset($curl);
            /** @phpstan-ignore argument.type (URL is guaranteed non-empty from config) */
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_NOBODY, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHead);
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curlHandle, $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts  = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            });

            $this->configureSsl($curl);

            curl_exec($curl);
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($statusCode !== 200) {
                return null;
            }

            $metadata = [];
            foreach ($responseHeaders as $name => $value) {
                if (str_starts_with($name, 'x-amz-meta-')) {
                    $metadata[substr($name, 11)] = $value;
                }
            }

            return [
                'size'         => (int) ($responseHeaders['content-length'] ?? 0),
                'contentType'  => $responseHeaders['content-type'] ?? 'application/octet-stream',
                'lastModified' => $responseHeaders['last-modified'] ?? '',
                'metadata'     => $metadata,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function getPresignedUrl(string $key, int $expiresIn = 3600, string $method = 'GET'): ?string
    {
        // Generate pre-signed URL using AWS Signature V4
        try {
            $now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $dateTime = $now->format('Ymd\THis\Z');
            $date     = $now->format('Ymd');

            $host       = parse_url($this->config->endpoint, PHP_URL_HOST);
            $scheme     = parse_url($this->config->endpoint, PHP_URL_SCHEME);
            $port       = parse_url($this->config->endpoint, PHP_URL_PORT);
            $portSuffix = '';

            if ($port !== null && (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))) {
                $portSuffix = ':' . $port;
            }

            $path = $this->config->usePathStyle
                ? '/' . $this->config->bucket . '/' . ltrim($key, '/')
                : '/' . ltrim($key, '/');

            $credential = sprintf(
                '%s/%s/%s/s3/aws4_request',
                $this->config->accessKey,
                $date,
                $this->config->region
            );

            $queryParams = [
                'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
                'X-Amz-Credential'    => $credential,
                'X-Amz-Date'          => $dateTime,
                'X-Amz-Expires'       => (string) $expiresIn,
                'X-Amz-SignedHeaders' => 'host',
            ];

            ksort($queryParams);
            $canonicalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

            $canonicalHeaders = 'host:' . $host . $portSuffix . "\n";
            $signedHeaders    = 'host';

            $canonicalRequest = implode("\n", [
                $method,
                $path,
                $canonicalQueryString,
                $canonicalHeaders,
                $signedHeaders,
                'UNSIGNED-PAYLOAD',
            ]);

            $stringToSign = implode("\n", [
                'AWS4-HMAC-SHA256',
                $dateTime,
                sprintf('%s/%s/s3/aws4_request', $date, $this->config->region),
                hash('sha256', $canonicalRequest),
            ]);

            $signature                      = $this->calculateSignature($date, $stringToSign);
            $queryParams['X-Amz-Signature'] = $signature;

            ksort($queryParams);
            $finalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

            return sprintf('%s://%s%s%s?%s', $scheme, $host, $portSuffix, $path, $finalQueryString);
        } catch (Throwable) {
            return null;
        }
    }

    public function getPublicUrl(string $key): string
    {
        if ($this->config->usePathStyle) {
            return sprintf('%s/%s/%s', $this->config->endpoint, $this->config->bucket, ltrim($key, '/'));
        }

        // Virtual-hosted style (not commonly used with SeaweedFS)
        $parsedUrl = parse_url($this->config->endpoint);
        $scheme    = $parsedUrl['scheme'] ?? 'http';
        $host      = $parsedUrl['host'] ?? 'localhost';
        $port      = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';

        return sprintf('%s://%s.%s%s/%s', $scheme, $this->config->bucket, $host, $port, ltrim($key, '/'));
    }

    public function createBucket(?string $bucket = null): bool
    {
        try {
            $bucket ??= $this->config->bucket;
            $response = $this->request('PUT', '', null, [], [], $bucket);

            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function bucketExists(?string $bucket = null): bool
    {
        try {
            $bucket ??= $this->config->bucket;
            $response = $this->request('HEAD', '', null, [], [], $bucket);

            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function isPresignedSupported(): bool
    {
        // Most S3-compatible services support pre-signed URLs
        return true;
    }

    public function isAvailable(): bool
    {
        // Check if we can list the bucket (minimal operation)
        return $this->bucketExists();
    }

    /**
     * Execute an HTTP request to S3
     *
     * @param string $method HTTP method
     * @param string $key Object key (can be empty for bucket operations)
     * @param string|null $body Request body
     * @param array<string, string> $extraHeaders Additional headers
     * @param array<string, string> $queryParams Query parameters
     * @param string|null $bucket Override bucket
     * @return string|null Response body or null on error
     */
    private function request(
        string $method,
        string $key,
        ?string $body = null,
        array $extraHeaders = [],
        array $queryParams = [],
        ?string $bucket = null
    ): ?string {
        $curl   = $this->getCurlHandle();
        $bucket ??= $this->config->bucket;
        $url    = $this->buildUrl($key, $queryParams, $bucket);

        // Build headers with signature
        $headers = $this->signRequest($method, $key, $body, $extraHeaders, $queryParams, $bucket);

        $httpHeaders = [];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = sprintf('%s: %s', $name, $value);
        }

        curl_reset($curl);
        /** @phpstan-ignore argument.type (URL is guaranteed non-empty from config) */
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $httpHeaders);

        $this->configureSsl($curl);

        switch ($method) {
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($body !== null) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
                }

                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'HEAD':
                curl_setopt($curl, CURLOPT_NOBODY, true);
                break;
            default:
                curl_setopt($curl, CURLOPT_HTTPGET, true);
                break;
        }

        $response   = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response === false) {
            return null;
        }

        // Check HTTP status
        if ($statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        // 204 No Content is success with empty body (DELETE)
        if ($statusCode === 204) {
            return '';
        }

        return is_string($response) ? $response : null;
    }

    /**
     * Sign a request using AWS Signature Version 4
     *
     * @param array<string, string> $extraHeaders
     * @param array<string, string> $queryParams
     * @return array<string, string> Signed headers
     */
    private function signRequest(
        string $method,
        string $key,
        ?string $body = null,
        array $extraHeaders = [],
        array $queryParams = [],
        ?string $bucket = null
    ): array {
        $bucket ??= $this->config->bucket;
        $now      = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $dateTime = $now->format('Ymd\THis\Z');
        $date     = $now->format('Ymd');

        $hostWithPort = $this->getHostWithPort();
        $path         = $this->buildRequestPath($key, $bucket);
        $payloadHash  = hash('sha256', $body ?? '');

        $headers = $this->buildBaseHeaders($hostWithPort, $dateTime, $payloadHash, $extraHeaders, $body);

        [$canonicalHeaders, $signedHeadersStr] = $this->buildCanonicalHeaders($headers);

        ksort($queryParams);
        $canonicalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $canonicalRequest = $this->buildCanonicalRequest(
            $method,
            $path,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeadersStr,
            $payloadHash
        );

        $stringToSign = $this->buildStringToSign($dateTime, $date, $canonicalRequest);
        $signature    = $this->calculateSignature($date, $stringToSign);

        $headers['Authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s/%s/s3/aws4_request, SignedHeaders=%s, Signature=%s',
            $this->config->accessKey,
            $date,
            $this->config->region,
            $signedHeadersStr,
            $signature
        );

        return $headers;
    }

    /**
     * Get host with port suffix if needed
     */
    private function getHostWithPort(): string
    {
        $host = parse_url($this->config->endpoint, PHP_URL_HOST);
        $port = parse_url($this->config->endpoint, PHP_URL_PORT);

        // Ensure host is a string (parse_url returns false on failure)
        $hostStr = is_string($host) ? $host : '';

        if ($port === null) {
            return $hostStr;
        }

        $scheme        = parse_url($this->config->endpoint, PHP_URL_SCHEME);
        $isDefaultPort = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);

        return $isDefaultPort ? $hostStr : $hostStr . ':' . $port;
    }

    /**
     * Build request path based on path style setting
     */
    private function buildRequestPath(string $key, string $bucket): string
    {
        if ($this->config->usePathStyle) {
            return '/' . $bucket . ($key !== '' ? '/' . ltrim($key, '/') : '');
        }

        return '/' . ltrim($key, '/');
    }

    /**
     * Build base headers for signing
     *
     * @param array<string, string> $extraHeaders
     * @return array<string, string>
     */
    private function buildBaseHeaders(
        string $hostWithPort,
        string $dateTime,
        string $payloadHash,
        array $extraHeaders,
        ?string $body
    ): array {
        $headers = array_merge([
            'Host'                 => $hostWithPort,
            'x-amz-date'           => $dateTime,
            'x-amz-content-sha256' => $payloadHash,
        ], $extraHeaders);

        if ($body !== null && !isset($headers['Content-Length'])) {
            $headers['Content-Length'] = (string) strlen($body);
        }

        return $headers;
    }

    /**
     * Build canonical headers for AWS signature
     *
     * @param array<string, string> $headers
     * @return array{0: string, 1: string} [canonicalHeaders, signedHeadersStr]
     */
    private function buildCanonicalHeaders(array $headers): array
    {
        $sortedHeaders = [];
        foreach ($headers as $name => $value) {
            $sortedHeaders[strtolower($name)] = $value;
        }

        ksort($sortedHeaders);

        $canonicalHeaders = '';
        $signedHeaders    = [];
        foreach ($sortedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
            $signedHeaders[]   = $name;
        }

        return [$canonicalHeaders, implode(';', $signedHeaders)];
    }

    /**
     * Build canonical request for AWS signature
     */
    private function buildCanonicalRequest(
        string $method,
        string $path,
        string $queryString,
        string $headers,
        string $signedHeaders,
        string $payloadHash
    ): string {
        return implode("\n", [$method, $path, $queryString, $headers, $signedHeaders, $payloadHash]);
    }

    /**
     * Build string to sign for AWS signature
     */
    private function buildStringToSign(string $dateTime, string $date, string $canonicalRequest): string
    {
        return implode("\n", [
            'AWS4-HMAC-SHA256',
            $dateTime,
            sprintf('%s/%s/s3/aws4_request', $date, $this->config->region),
            hash('sha256', $canonicalRequest),
        ]);
    }

    /**
     * Calculate AWS Signature V4 signature
     */
    private function calculateSignature(string $date, string $stringToSign): string
    {
        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->config->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->config->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return hash_hmac('sha256', $stringToSign, $kSigning);
    }

    /**
     * Build the full URL for a request
     *
     * @param array<string, string> $queryParams
     */
    private function buildUrl(string $key, array $queryParams = [], ?string $bucket = null): string
    {
        $bucket ??= $this->config->bucket;
        $path   = $this->config->usePathStyle
            ? '/' . $bucket . ($key !== '' ? '/' . ltrim($key, '/') : '')
            : '/' . ltrim($key, '/');

        $url = $this->config->endpoint . $path;

        if ($queryParams !== []) {
            ksort($queryParams);
            $url .= '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * Configure SSL options for cURL
     */
    private function configureSsl(CurlHandle $curl): void
    {
        if ($this->config->useTls) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->config->verifySsl);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->config->verifySsl ? 2 : 0);
        }
    }

    /**
     * Parse S3 ListObjectsV2 XML response
     *
     * @return array<array{key: string, size: int, lastModified: string}>
     */
    private function parseListResponse(string $xml): array
    {
        $result = [];

        try {
            $doc = new SimpleXMLElement($xml);

            foreach ($doc->Contents as $content) {
                $result[] = [
                    'key'          => (string) $content->Key,
                    'size'         => (int) $content->Size,
                    'lastModified' => (string) $content->LastModified,
                ];
            }
        } catch (Throwable) {
            // Return empty array on parse error
        }

        return $result;
    }

    /**
     * Detect content type from file extension
     */
    private function detectContentType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            // Images
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            // Documents
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            // Text
            'txt'  => 'text/plain',
            'html' => 'text/html',
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'xml'  => 'application/xml',
            // Archives
            'zip'  => 'application/zip',
            'gz'   => 'application/gzip',
            'tar'  => 'application/x-tar',
            // Media
            'mp3'  => 'audio/mpeg',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Get or create cURL handle
     */
    private function getCurlHandle(): CurlHandle
    {
        if (!$this->curlHandle instanceof CurlHandle) {
            $handle = curl_init();
            if ($handle === false) {
                throw new RuntimeException('Failed to initialize cURL handle');
            }

            $this->curlHandle = $handle;
        }

        return $this->curlHandle;
    }

    /**
     * Clean up cURL handle on destruction
     */
    public function __destruct()
    {
        if ($this->curlHandle instanceof CurlHandle) {
            curl_close($this->curlHandle);
        }
    }
}
