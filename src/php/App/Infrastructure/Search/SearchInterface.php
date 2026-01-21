<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

/**
 * Search Interface for full-text search operations
 *
 * Provides a simple, type-safe interface for indexing and searching documents.
 * Implementations can use Meilisearch, Elasticsearch, or other search engines.
 *
 * When to use:
 * - Full-text search across multiple fields
 * - Faceted search with filters
 * - Typo-tolerant search
 * - Fast autocomplete/instant search
 * - Product catalogs, documentation, user directories
 *
 * When NOT to use:
 * - Simple LIKE queries (use database instead)
 * - Exact matching only (use database indexes)
 * - Real-time updates required within milliseconds
 * - Complex joins and relationships (use database)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $search = $container->get(SearchInterface::class);
 *
 * // Index documents
 * $products = [
 *     ['id' => 1, 'name' => 'Laptop', 'price' => 999, 'category' => 'Electronics'],
 *     ['id' => 2, 'name' => 'Notebook', 'price' => 5, 'category' => 'Stationery'],
 * ];
 * $search->updateDocuments('products', $products);
 *
 * // Search with options
 * $results = $search->search('products', 'laptop', [
 *     'limit' => 10,
 *     'filter' => ['category = Electronics'],
 *     'attributesToHighlight' => ['name'],
 * ]);
 *
 * // Delete specific documents
 * $search->deleteDocuments('products', [1, 2]);
 *
 * // Clear entire index
 * $search->deleteAllDocuments('products');
 * </code>
 *
 * @package Infrastructure\Search
 */
interface SearchInterface
{
    /**
     * Search for documents in an index
     *
     * @param string $indexName Index to search in
     * @param string $query Search query (empty string returns all documents)
     * @param array<string, mixed> $options Search options:
     *   - limit: int - Maximum number of results (default: 20)
     *   - offset: int - Number of results to skip (default: 0)
     *   - filter: array<string> - Filter expressions (e.g., ['price > 100', 'category = Electronics'])
     *   - sort: array<string> - Sort criteria (e.g., ['price:asc', 'name:desc'])
     *   - attributesToRetrieve: array<string> - Fields to return (default: all)
     *   - attributesToHighlight: array<string> - Fields to highlight matches
     *   - facets: array<string> - Fields to compute facets for
     * @return array{hits: array<array<string, mixed>>, estimatedTotalHits: int, processingTimeMs: int, query: string}
     *   Returns array with 'hits' (matching documents), 'estimatedTotalHits', 'processingTimeMs', 'query'
     *   Returns empty array on error
     */
    public function search(string $indexName, string $query, array $options = []): array;

    /**
     * Get an index by name
     *
     * Useful to access raw index methods not exposed in this interface.
     *
     * @param string $indexName Index name
     * @return mixed Index object or null if not available
     */
    public function index(string $indexName): mixed;

    /**
     * Add or update documents in an index
     *
     * Documents must have an 'id' field (string or int).
     * If document with same ID exists, it will be replaced.
     *
     * @param string $indexName Index name (will be created if not exists)
     * @param array<array<string, mixed>> $documents Array of documents to index
     * @param string|null $primaryKey Primary key field name (default: 'id')
     * @return bool True if indexing task was enqueued successfully
     */
    public function updateDocuments(string $indexName, array $documents, ?string $primaryKey = null): bool;

    /**
     * Delete specific documents from an index
     *
     * @param string $indexName Index name
     * @param array<int|string> $documentIds Array of document IDs to delete
     * @return bool True if deletion task was enqueued successfully
     */
    public function deleteDocuments(string $indexName, array $documentIds): bool;

    /**
     * Delete all documents from an index
     *
     * The index itself remains (with settings), only documents are removed.
     *
     * @param string $indexName Index name
     * @return bool True if deletion task was enqueued successfully
     */
    public function deleteAllDocuments(string $indexName): bool;

    /**
     * Create a new index
     *
     * @param string $indexName Index name
     * @param string|null $primaryKey Primary key field name (default: 'id')
     * @return bool True if index was created successfully
     */
    public function createIndex(string $indexName, ?string $primaryKey = null): bool;

    /**
     * Delete an index and all its documents
     *
     * @param string $indexName Index name
     * @return bool True if deletion task was enqueued successfully
     */
    public function deleteIndex(string $indexName): bool;

    /**
     * Get all indexes
     *
     * @return array<array{uid: string, primaryKey: string|null, createdAt: string, updatedAt: string}>
     *   Returns array of index information, empty array on error
     */
    public function getIndexes(): array;

    /**
     * Check if search backend is available
     *
     * Use this for health checks or graceful degradation.
     *
     * @return bool True if search is connected and responsive
     */
    public function isAvailable(): bool;
}
