<?php

declare(strict_types=1);

namespace App\Infrastructure\Elasticsearch;

/**
 * Elasticsearch Interface for full-text search and analytics operations
 *
 * Provides a simple, type-safe interface for indexing, searching, and analytics.
 * Elasticsearch excels at log analysis, metrics, and complex aggregations.
 *
 * When to use Elasticsearch:
 * - Log aggregation and analysis
 * - Metrics and time-series data
 * - Complex aggregations (avg, sum, cardinality, histograms)
 * - Large-scale full-text search (billions of documents)
 * - Geospatial queries
 *
 * When to use Meilisearch instead:
 * - Simple product search, autocomplete
 * - Typo-tolerant, instant search
 * - Smaller datasets with simple requirements
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $es = $container->get(ElasticsearchInterface::class);
 *
 * // Index documents
 * $products = [
 *     ['id' => '1', 'name' => 'Laptop', 'price' => 999, 'category' => 'Electronics'],
 *     ['id' => '2', 'name' => 'Notebook', 'price' => 5, 'category' => 'Stationery'],
 * ];
 * $es->bulkIndex('products', $products);
 *
 * // Search with query DSL
 * $results = $es->search('products', [
 *     'query' => [
 *         'match' => ['name' => 'laptop'],
 *     ],
 * ]);
 *
 * // Aggregations
 * $agg = $es->aggregate('products', [
 *     'categories' => ['terms' => ['field' => 'category.keyword']],
 * ]);
 * </code>
 *
 * @package Infrastructure\Elasticsearch
 */
interface ElasticsearchInterface
{
    /**
     * Search for documents using Elasticsearch Query DSL
     *
     * @param string $indexName Index to search in
     * @param array<string, mixed> $query Elasticsearch query DSL
     * @param array<string, mixed> $options Additional options:
     *   - size: int - Maximum number of results (default: 10)
     *   - from: int - Offset for pagination (default: 0)
     *   - _source: array<string> - Fields to return
     *   - sort: array<array<string, string>> - Sort criteria
     *   - highlight: array<string, mixed> - Highlight configuration
     * @return array{hits: array{total: array{value: int, relation: string}, hits: array<array<string, mixed>>}, took: int}
     *   Returns array with 'hits.total.value' (total count), 'hits.hits' (documents), 'took' (ms)
     *   Returns empty array on error
     */
    public function search(string $indexName, array $query, array $options = []): array;

    /**
     * Get a single document by ID
     *
     * @param string $indexName Index name
     * @param string $documentId Document ID
     * @return array<string, mixed>|null Document source or null if not found
     */
    public function get(string $indexName, string $documentId): ?array;

    /**
     * Index a single document
     *
     * @param string $indexName Index name (will be created if not exists)
     * @param string|null $documentId Document ID (auto-generated if null)
     * @param array<string, mixed> $document Document data
     * @return string|null Document ID on success, null on error
     */
    public function index(string $indexName, ?string $documentId, array $document): ?string;

    /**
     * Bulk index multiple documents
     *
     * Documents should have an 'id' or '_id' field for updates.
     * If no ID is provided, Elasticsearch will auto-generate one.
     *
     * @param string $indexName Index name
     * @param array<array<string, mixed>> $documents Array of documents to index
     * @param string $idField Field name containing document ID (default: 'id')
     * @return array{indexed: int, errors: int} Count of indexed and failed documents
     */
    public function bulkIndex(string $indexName, array $documents, string $idField = 'id'): array;

    /**
     * Update a document (partial update)
     *
     * @param string $indexName Index name
     * @param string $documentId Document ID
     * @param array<string, mixed> $fields Fields to update
     * @return bool True on success, false on error
     */
    public function update(string $indexName, string $documentId, array $fields): bool;

    /**
     * Delete a document by ID
     *
     * @param string $indexName Index name
     * @param string $documentId Document ID
     * @return bool True on success, false on error
     */
    public function delete(string $indexName, string $documentId): bool;

    /**
     * Delete documents matching a query
     *
     * @param string $indexName Index name
     * @param array<string, mixed> $query Elasticsearch query DSL
     * @return int Number of deleted documents, -1 on error
     */
    public function deleteByQuery(string $indexName, array $query): int;

    /**
     * Execute aggregation query
     *
     * @param string $indexName Index name
     * @param array<string, mixed> $aggregations Aggregation definitions
     * @param array<string, mixed> $query Optional filter query (default: match_all)
     * @return array<string, mixed> Aggregation results, empty array on error
     */
    public function aggregate(string $indexName, array $aggregations, array $query = []): array;

    /**
     * Create an index with optional mappings and settings
     *
     * @param string $indexName Index name
     * @param array<string, mixed> $mappings Field mappings (optional)
     * @param array<string, mixed> $settings Index settings (optional)
     * @return bool True on success, false on error
     */
    public function createIndex(string $indexName, array $mappings = [], array $settings = []): bool;

    /**
     * Delete an index
     *
     * @param string $indexName Index name
     * @return bool True on success, false on error
     */
    public function deleteIndex(string $indexName): bool;

    /**
     * Check if an index exists
     *
     * @param string $indexName Index name
     * @return bool True if index exists
     */
    public function indexExists(string $indexName): bool;

    /**
     * Get index mappings
     *
     * @param string $indexName Index name
     * @return array<string, mixed> Index mappings, empty array on error
     */
    public function getMapping(string $indexName): array;

    /**
     * Update index mappings (add new fields)
     *
     * Note: Existing field mappings cannot be changed.
     *
     * @param string $indexName Index name
     * @param array<string, mixed> $mappings New field mappings
     * @return bool True on success, false on error
     */
    public function putMapping(string $indexName, array $mappings): bool;

    /**
     * Refresh an index (make recent changes visible to search)
     *
     * @param string $indexName Index name
     * @return bool True on success, false on error
     */
    public function refresh(string $indexName): bool;

    /**
     * Get cluster health status
     *
     * @return array{status: string, number_of_nodes: int, active_primary_shards: int}|null
     *   Cluster health or null on error
     */
    public function getClusterHealth(): ?array;

    /**
     * Check if Elasticsearch is available
     *
     * Use this for health checks or graceful degradation.
     *
     * @return bool True if Elasticsearch is connected and responsive
     */
    public function isAvailable(): bool;
}
