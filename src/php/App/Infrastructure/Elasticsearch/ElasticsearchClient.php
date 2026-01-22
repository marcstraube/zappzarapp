<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch;

use CurlHandle;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Elasticsearch Client Implementation
 *
 * Thread-safe, lazy-connected Elasticsearch client using native PHP cURL.
 * No external dependencies required - communicates directly with Elasticsearch REST API.
 *
 * Configuration via environment variables:
 * - ELASTICSEARCH_URL: Connection URL (default: https://elasticsearch:9200)
 * - ELASTICSEARCH_API_KEY: API key from Docker secret or environment
 * - ELASTICSEARCH_VERIFY_SSL: Verify SSL certificate (default: false for dev)
 *
 * Constructor parameters:
 * - $url: Override ELASTICSEARCH_URL environment variable
 *
 * Error Handling:
 * - Connection failures return null/false/empty array (no exceptions thrown to caller)
 * - Use isAvailable() to check connection status
 *
 * Usage:
 * <code>
 * $es = new ElasticsearchClient();
 *
 * // Index documents
 * $es->bulkIndex('products', [
 *     ['id' => '1', 'name' => 'Laptop', 'price' => 999],
 *     ['id' => '2', 'name' => 'Mouse', 'price' => 29],
 * ]);
 *
 * // Search
 * $results = $es->search('products', [
 *     'query' => ['match' => ['name' => 'laptop']],
 * ]);
 *
 * // Aggregation
 * $agg = $es->aggregate('products', [
 *     'avg_price' => ['avg' => ['field' => 'price']],
 * ]);
 * </code>
 *
 * @package Infrastructure\Elasticsearch
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods") Interface requires many methods
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") REST client needs multiple operations
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity") REST API client requires many operations
 */
final class ElasticsearchClient implements ElasticsearchInterface
{
    private readonly ElasticsearchConfig $config;

    /** @var CurlHandle|null cURL handle for connection pooling */
    private ?CurlHandle $curlHandle = null;

    public function __construct(?string $url = null)
    {
        $this->config = new ElasticsearchConfig($url);
    }

    public function search(string $indexName, array $query, array $options = []): array
    {
        try {
            $body = ['query' => $query];

            if (isset($options['size'])) {
                $body['size'] = $options['size'];
            }

            if (isset($options['from'])) {
                $body['from'] = $options['from'];
            }

            if (isset($options['_source'])) {
                $body['_source'] = $options['_source'];
            }

            if (isset($options['sort'])) {
                $body['sort'] = $options['sort'];
            }

            if (isset($options['highlight'])) {
                $body['highlight'] = $options['highlight'];
            }

            $response = $this->request('GET', sprintf('/%s/_search', $indexName), $body);

            if ($response === null) {
                return [
                    'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []],
                    'took' => 0,
                ];
            }

            return [
                'hits' => [
                    'total' => $response['hits']['total'] ?? ['value' => 0, 'relation' => 'eq'],
                    'hits'  => $response['hits']['hits'] ?? [],
                ],
                'took' => $response['took'] ?? 0,
            ];
        } catch (Throwable) {
            return [
                'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []],
                'took' => 0,
            ];
        }
    }

    public function get(string $indexName, string $documentId): ?array
    {
        try {
            $response = $this->request('GET', sprintf('/%s/_doc/%s', $indexName, $documentId));

            if ($response === null || !isset($response['found']) || $response['found'] !== true) {
                return null;
            }

            return $response['_source'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    public function index(string $indexName, ?string $documentId, array $document): ?string
    {
        try {
            $method = 'POST';
            $path   = sprintf('/%s/_doc', $indexName);

            if ($documentId !== null) {
                $method = 'PUT';
                $path   = sprintf('/%s/_doc/%s', $indexName, $documentId);
            }

            $response = $this->request($method, $path, $document);

            if ($response === null) {
                return null;
            }

            return $response['_id'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    public function bulkIndex(string $indexName, array $documents, string $idField = 'id'): array
    {
        $indexed = 0;
        $errors  = 0;

        if ($documents === []) {
            return ['indexed' => 0, 'errors' => 0];
        }

        try {
            // Build NDJSON body for bulk API
            $body = '';
            foreach ($documents as $document) {
                $docId = $document[$idField] ?? null;
                unset($document[$idField]);

                $action = ['index' => ['_index' => $indexName]];
                if ($docId !== null) {
                    $action['index']['_id'] = (string) $docId;
                }

                $body .= json_encode($action, JSON_THROW_ON_ERROR) . "\n";
                $body .= json_encode($document, JSON_THROW_ON_ERROR) . "\n";
            }

            $response = $this->request('POST', '/_bulk', null, $body, 'application/x-ndjson');

            if ($response === null) {
                return ['indexed' => 0, 'errors' => count($documents)];
            }

            // Count successes and errors
            foreach ($response['items'] ?? [] as $item) {
                $status = $item['index']['status'] ?? 500;
                if ($status >= 200 && $status < 300) {
                    $indexed++;
                } else {
                    $errors++;
                }
            }

            return ['indexed' => $indexed, 'errors' => $errors];
        } catch (Throwable) {
            return ['indexed' => 0, 'errors' => count($documents)];
        }
    }

    public function update(string $indexName, string $documentId, array $fields): bool
    {
        try {
            $response = $this->request(
                'POST',
                sprintf('/%s/_update/%s', $indexName, $documentId),
                ['doc' => $fields]
            );

            return $response !== null && isset($response['result']);
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $indexName, string $documentId): bool
    {
        try {
            $response = $this->request('DELETE', sprintf('/%s/_doc/%s', $indexName, $documentId));

            return $response !== null && ($response['result'] ?? '') === 'deleted';
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteByQuery(string $indexName, array $query): int
    {
        try {
            $response = $this->request('POST', sprintf('/%s/_delete_by_query', $indexName), ['query' => $query]);

            if ($response === null) {
                return -1;
            }

            return $response['deleted'] ?? 0;
        } catch (Throwable) {
            return -1;
        }
    }

    public function aggregate(string $indexName, array $aggregations, array $query = []): array
    {
        try {
            $body = [
                'size' => 0, // Don't return documents, only aggregations
                'aggs' => $aggregations,
            ];

            if ($query !== []) {
                $body['query'] = $query;
            }

            $response = $this->request('GET', sprintf('/%s/_search', $indexName), $body);

            if ($response === null) {
                return [];
            }

            return $response['aggregations'] ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    public function createIndex(string $indexName, array $mappings = [], array $settings = []): bool
    {
        try {
            $body = [];

            if ($mappings !== []) {
                $body['mappings'] = $mappings;
            }

            if ($settings !== []) {
                $body['settings'] = $settings;
            }

            $response = $this->request('PUT', '/' . $indexName, empty($body) ? null : $body);

            return $response !== null && ($response['acknowledged'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function deleteIndex(string $indexName): bool
    {
        try {
            $response = $this->request('DELETE', '/' . $indexName);

            return $response !== null && ($response['acknowledged'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function indexExists(string $indexName): bool
    {
        try {
            $response = $this->request('HEAD', '/' . $indexName);

            // HEAD request returns empty array on success (200), null on 404
            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function getMapping(string $indexName): array
    {
        try {
            $response = $this->request('GET', sprintf('/%s/_mapping', $indexName));

            if ($response === null) {
                return [];
            }

            // Response is {indexName: {mappings: {...}}}
            return $response[$indexName]['mappings'] ?? [];
        } catch (Throwable) {
            return [];
        }
    }

    public function putMapping(string $indexName, array $mappings): bool
    {
        try {
            $response = $this->request('PUT', sprintf('/%s/_mapping', $indexName), $mappings);

            return $response !== null && ($response['acknowledged'] ?? false) === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function refresh(string $indexName): bool
    {
        try {
            $response = $this->request('POST', sprintf('/%s/_refresh', $indexName));

            return $response !== null;
        } catch (Throwable) {
            return false;
        }
    }

    public function getClusterHealth(): ?array
    {
        try {
            $response = $this->request('GET', '/_cluster/health');

            if ($response === null) {
                return null;
            }

            return [
                'status'                => $response['status'] ?? 'unknown',
                'number_of_nodes'       => $response['number_of_nodes'] ?? 0,
                'active_primary_shards' => $response['active_primary_shards'] ?? 0,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function isAvailable(): bool
    {
        try {
            $health = $this->getClusterHealth();

            if ($health === null) {
                return false;
            }

            // Accept green or yellow status
            return in_array($health['status'], ['green', 'yellow'], true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Execute an HTTP request to Elasticsearch
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, HEAD)
     * @param string $path API path (e.g., /products/_search)
     * @param array<string, mixed>|null $body Request body (JSON encoded)
     * @param string|null $rawBody Raw body string (for bulk API)
     * @param string $contentType Content-Type header
     * @return array<string, mixed>|null Response data or null on error
     * @throws JsonException When JSON encoding/decoding fails
     * @throws RuntimeException When cURL initialization fails
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $rawBody = null,
        string $contentType = 'application/json'
    ): ?array {
        $curl = $this->getCurlHandle();
        $url  = rtrim($this->config->url, '/') . $path;

        $this->configureCurl($curl, $url, $method, $contentType);
        $this->setRequestBody($curl, $body, $rawBody);

        $response   = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        return $this->parseResponse($response, $statusCode, $method);
    }

    /**
     * Configure cURL handle for the request
     */
    private function configureCurl(CurlHandle $curl, string $url, string $method, string $contentType): void
    {
        curl_reset($curl);
        /** @phpstan-ignore argument.type (URL is guaranteed non-empty from config) */
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);

        $this->configureSsl($curl);
        $this->configureHeaders($curl, $contentType);
        $this->configureMethod($curl, $method);
    }

    /**
     * Configure SSL options
     */
    private function configureSsl(CurlHandle $curl): void
    {
        if ($this->config->useTls) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $this->config->verifySsl);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->config->verifySsl ? 2 : 0);
        }
    }

    /**
     * Configure request headers
     */
    private function configureHeaders(CurlHandle $curl, string $contentType): void
    {
        $headers = ['Content-Type: ' . $contentType];
        if ($this->config->apiKey !== '') {
            $headers[] = 'Authorization: ApiKey ' . $this->config->apiKey;
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * Configure HTTP method
     */
    private function configureMethod(CurlHandle $curl, string $method): void
    {
        match ($method) {
            'POST'   => curl_setopt($curl, CURLOPT_POST, true),
            'PUT'    => curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT'),
            'DELETE' => curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE'),
            'HEAD'   => curl_setopt($curl, CURLOPT_NOBODY, true),
            default  => curl_setopt($curl, CURLOPT_HTTPGET, true),
        };
    }

    /**
     * Set request body
     *
     * @param array<string, mixed>|null $body
     * @throws JsonException When JSON encoding fails
     */
    private function setRequestBody(CurlHandle $curl, ?array $body, ?string $rawBody): void
    {
        if ($rawBody !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $rawBody);
        } elseif ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
    }

    /**
     * Parse response from Elasticsearch
     *
     * @return array<string, mixed>|null
     * @throws JsonException When JSON decoding fails
     */
    private function parseResponse(string|bool $response, int $statusCode, string $method): ?array
    {
        if ($response === false) {
            return null;
        }

        if ($method === 'HEAD') {
            return $statusCode >= 200 && $statusCode < 300 ? [] : null;
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return null;
        }

        if (!is_string($response) || $response === '' || $response === '{}') {
            return [];
        }

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Get or create cURL handle (connection pooling)
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
