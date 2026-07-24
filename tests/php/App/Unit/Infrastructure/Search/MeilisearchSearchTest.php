<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Search;

use App\Infrastructure\Search\MeilisearchConfig;
use App\Infrastructure\Search\MeilisearchSearch;
use DateTime;
use Meilisearch\Client;
use Meilisearch\Contracts\IndexesResults;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\CommunicationException;
use Meilisearch\Search\SearchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for MeilisearchSearch
 *
 * These tests use mocking to test the search logic without a real Meilisearch connection.
 * For integration tests with actual Meilisearch, see tests/php/App/Feature/.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[CoversClass(MeilisearchSearch::class)]
#[UsesClass(MeilisearchConfig::class)]
final class MeilisearchSearchTest extends TestCase
{
    private const string TEST_URL = 'https://localhost:7700';

    public function testSearchReturnsResultsWhenSuccessful(): void
    {
        $mockSearchResult = $this->createStub(SearchResult::class);
        $mockSearchResult->method('getHits')->willReturn([
            ['id' => 1, 'name' => 'Laptop'],
            ['id' => 2, 'name' => 'Mouse'],
        ]);
        $mockSearchResult->method('getEstimatedTotalHits')->willReturn(2);
        $mockSearchResult->method('getProcessingTimeMs')->willReturn(5);
        $mockSearchResult->method('getQuery')->willReturn('laptop');

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->expects($this->once())
            ->method('search')
            ->with('laptop', [])
            ->willReturn($mockSearchResult);

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->with('products')
            ->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->search('products', 'laptop');

        $this->assertArrayHasKey('hits', $result);
        $this->assertArrayHasKey('estimatedTotalHits', $result);
        $this->assertCount(2, $result['hits']);
        $this->assertEquals(2, $result['estimatedTotalHits']);
    }

    public function testSearchReturnsEmptyArrayOnApiException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->willThrowException(new CommunicationException('Index not found'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->search('nonexistent', 'query');

        $this->assertEquals([], $result);
    }

    public function testSearchReturnsEmptyArrayOnCommunicationException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->willThrowException(new CommunicationException('Connection failed'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->search('products', 'query');

        $this->assertEquals([], $result);
    }

    public function testSearchPassesOptionsToMeilisearch(): void
    {
        $mockSearchResult = $this->createStub(SearchResult::class);
        $mockSearchResult->method('getHits')->willReturn([]);
        $mockSearchResult->method('getEstimatedTotalHits')->willReturn(0);
        $mockSearchResult->method('getProcessingTimeMs')->willReturn(1);
        $mockSearchResult->method('getQuery')->willReturn('test');

        $expectedOptions = [
            'limit'                 => 50,
            'offset'                => 10,
            'filter'                => ['price > 100'],
            'sort'                  => ['price:asc'],
            'attributesToRetrieve'  => ['name', 'price'],
            'attributesToHighlight' => ['name'],
            'facets'                => ['category'],
        ];

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->expects($this->once())
            ->method('search')
            ->with('test', $expectedOptions)
            ->willReturn($mockSearchResult);

        $mockClient = $this->createStub(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $search->search('products', 'test', $expectedOptions);
    }

    public function testIndexReturnsIndexObject(): void
    {
        $mockIndex = $this->createStub(Indexes::class);

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->with('products')
            ->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->index('products');

        $this->assertSame($mockIndex, $result);
    }

    public function testUpdateDocumentsIndexesDocuments(): void
    {
        $documents = [
            ['id' => 1, 'name' => 'Laptop'],
            ['id' => 2, 'name' => 'Mouse'],
        ];

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->expects($this->once())
            ->method('addDocuments')
            ->with($documents);

        $mockClient = $this->createStub(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->updateDocuments('products', $documents);

        $this->assertTrue($result);
    }

    public function testUpdateDocumentsWithCustomPrimaryKey(): void
    {
        $documents = [['customId' => 1, 'name' => 'Item']];

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->expects($this->once())
            ->method('addDocuments')
            ->with($documents, 'customId');

        $mockClient = $this->createStub(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->updateDocuments('products', $documents, 'customId');

        $this->assertTrue($result);
    }

    public function testUpdateDocumentsReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->willThrowException(new CommunicationException('Error'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->updateDocuments('products', []);

        $this->assertFalse($result);
    }

    public function testDeleteDocumentsRemovesSpecifiedDocuments(): void
    {
        $documentIds = [1, 2, 3];

        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->expects($this->once())
            ->method('deleteDocuments')
            ->with($documentIds);

        $mockClient = $this->createStub(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->deleteDocuments('products', $documentIds);

        $this->assertTrue($result);
    }

    public function testDeleteDocumentsReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->willThrowException(new CommunicationException('Error'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->deleteDocuments('products', [1]);

        $this->assertFalse($result);
    }

    public function testDeleteAllDocumentsClearsIndex(): void
    {
        $mockIndex = $this->createMock(Indexes::class);
        $mockIndex->expects($this->once())
            ->method('deleteAllDocuments');

        $mockClient = $this->createStub(Client::class);
        $mockClient->method('index')->willReturn($mockIndex);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->deleteAllDocuments('products');

        $this->assertTrue($result);
    }

    public function testDeleteAllDocumentsReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('index')
            ->willThrowException(new CommunicationException('Error'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->deleteAllDocuments('products');

        $this->assertFalse($result);
    }

    public function testCreateIndexCreatesNewIndex(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('createIndex')
            ->with('products');

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->createIndex('products');

        $this->assertTrue($result);
    }

    public function testCreateIndexWithPrimaryKey(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('createIndex')
            ->with('products', ['primaryKey' => 'customId']);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->createIndex('products', 'customId');

        $this->assertTrue($result);
    }

    public function testCreateIndexReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('createIndex')
            ->willThrowException(new CommunicationException('Error'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->createIndex('products');

        $this->assertFalse($result);
    }

    public function testDeleteIndexRemovesIndex(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('deleteIndex')
            ->with('products');

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->deleteIndex('products');

        $this->assertTrue($result);
    }

    public function testDeleteIndexReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('deleteIndex')
            ->willThrowException(new CommunicationException('Error'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->deleteIndex('products');

        $this->assertFalse($result);
    }

    public function testGetIndexesReturnsAllIndexes(): void
    {
        $mockIndex1 = $this->createStub(Indexes::class);
        $mockIndex1->method('getUid')->willReturn('products');
        $mockIndex1->method('getPrimaryKey')->willReturn('id');
        $mockIndex1->method('getCreatedAt')->willReturn(new DateTime('2024-01-01T00:00:00Z'));
        $mockIndex1->method('getUpdatedAt')->willReturn(new DateTime('2024-01-02T00:00:00Z'));

        $mockIndex2 = $this->createStub(Indexes::class);
        $mockIndex2->method('getUid')->willReturn('users');
        $mockIndex2->method('getPrimaryKey')->willReturn(null);
        $mockIndex2->method('getCreatedAt')->willReturn(new DateTime('2024-01-03T00:00:00Z'));
        $mockIndex2->method('getUpdatedAt')->willReturn(new DateTime('2024-01-04T00:00:00Z'));

        $mockIndexesResults = $this->createStub(IndexesResults::class);
        $mockIndexesResults->method('getResults')->willReturn([$mockIndex1, $mockIndex2]);

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('getIndexes')
            ->willReturn($mockIndexesResults);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->getIndexes();

        $this->assertCount(2, $result);
        $this->assertEquals('products', $result[0]['uid']);
        $this->assertEquals('id', $result[0]['primaryKey']);
        $this->assertNull($result[1]['primaryKey']);
    }

    public function testGetIndexesReturnsEmptyArrayOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('getIndexes')
            ->willThrowException(new CommunicationException('Error'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->getIndexes();

        $this->assertEquals([], $result);
    }

    public function testIsAvailableReturnsTrueWhenHealthy(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('health')
            ->willReturn(['status' => 'available']);

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->isAvailable();

        $this->assertTrue($result);
    }

    public function testIsAvailableReturnsFalseOnException(): void
    {
        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->once())
            ->method('health')
            ->willThrowException(new CommunicationException('Connection failed'));

        $search = $this->createSearchWithMockedClient($mockClient);

        $result = $search->isAvailable();

        $this->assertFalse($result);
    }

    public function testConstructorAcceptsCustomUrl(): void
    {
        $search = new MeilisearchSearch('https://custom:9000');

        // Use reflection to check config
        $reflection     = new ReflectionClass($search);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($search);

        $this->assertEquals('https://custom:9000', $config->url);
    }

    /**
     * Create a MeilisearchSearch instance with a mocked client
     */
    private function createSearchWithMockedClient(Client $mockClient): MeilisearchSearch
    {
        $search = new MeilisearchSearch(self::TEST_URL);

        // Inject mock via reflection
        $reflection = new ReflectionClass($search);
        $property   = $reflection->getProperty('client');
        $property->setValue($search, $mockClient);

        return $search;
    }
}
