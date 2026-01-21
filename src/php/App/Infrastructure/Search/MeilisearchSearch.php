<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use Meilisearch\Client;
use Meilisearch\Contracts\IndexesQuery;
use Meilisearch\Endpoints\Indexes;
use Throwable;

/**
 * Meilisearch Search Implementation
 *
 * Thread-safe, lazy-connected Meilisearch client with automatic reconnection.
 * Supports HTTPS with self-signed certificates for development.
 *
 * Configuration via environment variables:
 * - MEILISEARCH_URL: Connection URL (default: https://meilisearch:7700)
 * - MEILISEARCH_MASTER_KEY: Master key from Docker secret or environment
 *
 * Constructor parameters:
 * - $url: Override MEILISEARCH_URL environment variable
 *
 * Error Handling:
 * - Connection failures return null/false/empty array (no exceptions thrown to caller)
 * - Use isAvailable() to check connection status
 * - Automatic reconnection on next operation after failure
 *
 * Usage:
 * <code>
 * $search = new MeilisearchSearch();
 *
 * // Index product data
 * $products = [
 *     ['id' => 1, 'name' => 'Laptop', 'price' => 999],
 *     ['id' => 2, 'name' => 'Mouse', 'price' => 29],
 * ];
 * $search->updateDocuments('products', $products);
 *
 * // Search
 * $results = $search->search('products', 'laptop', [
 *     'limit' => 10,
 *     'filter' => ['price > 500'],
 * ]);
 *
 * foreach ($results['hits'] as $hit) {
 *     echo $hit['name'] . ' - $' . $hit['price'];
 * }
 * </code>
 *
 * @package Infrastructure\Search
 */
final class MeilisearchSearch implements SearchInterface
{
    private ?Client $client = null;

    private readonly MeilisearchConfig $config;

    public function __construct(?string $url = null)
    {
        $this->config = new MeilisearchConfig($url);
    }

    /**
     * @SuppressWarnings("PHPMD.NPathComplexity") Options array has multiple optional keys
     */
    public function search(string $indexName, string $query, array $options = []): array
    {
        try {
            $client = $this->getClient();
            $index  = $client->index($indexName);

            // Build search parameters
            $searchParams = [];

            if (isset($options['limit'])) {
                $searchParams['limit'] = $options['limit'];
            }

            if (isset($options['offset'])) {
                $searchParams['offset'] = $options['offset'];
            }

            if (isset($options['filter'])) {
                $searchParams['filter'] = $options['filter'];
            }

            if (isset($options['sort'])) {
                $searchParams['sort'] = $options['sort'];
            }

            if (isset($options['attributesToRetrieve'])) {
                $searchParams['attributesToRetrieve'] = $options['attributesToRetrieve'];
            }

            if (isset($options['attributesToHighlight'])) {
                $searchParams['attributesToHighlight'] = $options['attributesToHighlight'];
            }

            if (isset($options['facets'])) {
                $searchParams['facets'] = $options['facets'];
            }

            $result = $index->search($query, $searchParams);

            return [
                'hits'               => $result->getHits(),
                'estimatedTotalHits' => $result->getEstimatedTotalHits() ?? 0,
                'processingTimeMs'   => $result->getProcessingTimeMs(),
                'query'              => $result->getQuery(),
            ];
        } catch (Throwable) {
            $this->disconnect();
            // @phpstan-ignore return.type (Empty array on error is documented behavior)
            return [];
        }
    }

    public function index(string $indexName): Indexes
    {
        // Client::index() returns a proxy without HTTP calls - always succeeds
        return $this->getClient()->index($indexName);
    }

    public function updateDocuments(string $indexName, array $documents, ?string $primaryKey = null): bool
    {
        try {
            $index = $this->getClient()->index($indexName);

            if ($primaryKey !== null) {
                $index->addDocuments($documents, $primaryKey);
            } else {
                $index->addDocuments($documents);
            }

            return true;
        } catch (Throwable) {
            $this->disconnect();
            return false;
        }
    }

    public function deleteDocuments(string $indexName, array $documentIds): bool
    {
        try {
            $index = $this->getClient()->index($indexName);
            $index->deleteDocuments($documentIds);
            return true;
        } catch (Throwable) {
            $this->disconnect();
            return false;
        }
    }

    public function deleteAllDocuments(string $indexName): bool
    {
        try {
            $index = $this->getClient()->index($indexName);
            $index->deleteAllDocuments();
            return true;
        } catch (Throwable) {
            $this->disconnect();
            return false;
        }
    }

    public function createIndex(string $indexName, ?string $primaryKey = null): bool
    {
        try {
            $client = $this->getClient();
            if ($primaryKey !== null) {
                $client->createIndex($indexName, ['primaryKey' => $primaryKey]);
            } else {
                $client->createIndex($indexName);
            }

            return true;
        } catch (Throwable) {
            $this->disconnect();
            return false;
        }
    }

    public function deleteIndex(string $indexName): bool
    {
        try {
            $this->getClient()->deleteIndex($indexName);
            return true;
        } catch (Throwable) {
            $this->disconnect();
            return false;
        }
    }

    public function getIndexes(): array
    {
        try {
            $response = $this->getClient()->getIndexes(new IndexesQuery());
            $indexes  = $response->getResults();

            $result = [];
            foreach ($indexes as $index) {
                $createdAt = $index->getCreatedAt();
                $updatedAt = $index->getUpdatedAt();

                $result[] = [
                    'uid'        => $index->getUid() ?? '',
                    'primaryKey' => $index->getPrimaryKey(),
                    'createdAt'  => $createdAt?->format('c') ?? '',
                    'updatedAt'  => $updatedAt?->format('c') ?? '',
                ];
            }

            return $result;
        } catch (Throwable) {
            $this->disconnect();
            return [];
        }
    }

    public function isAvailable(): bool
    {
        try {
            $this->getClient()->health();
            return true;
        } catch (Throwable) {
            $this->disconnect();
            return false;
        }
    }

    /**
     * Get or create Meilisearch client (lazy initialization)
     *
     * Note: Client constructor doesn't make HTTP calls, so no exception handling needed.
     * Exceptions occur during actual operations (search, addDocuments, etc.).
     */
    private function getClient(): Client
    {
        if (!$this->client instanceof Client) {
            $this->client = new Client(
                $this->config->url,
                $this->config->masterKey
            );
        }

        return $this->client;
    }

    /**
     * Close connection (for reconnection on next use)
     */
    private function disconnect(): void
    {
        $this->client = null;
    }
}
