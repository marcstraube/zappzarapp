<?php

declare(strict_types=1);

namespace Tests\App\Unit\Infrastructure\Elasticsearch;

use App\Infrastructure\Elasticsearch\ElasticsearchClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * Unit tests for ElasticsearchClient
 *
 * Tests the Elasticsearch client configuration and error handling.
 * HTTP response parsing requires integration tests with actual Elasticsearch
 * or a mock server - see tests/php/App/Feature/.
 */
#[CoversClass(ElasticsearchClient::class)]
final class ElasticsearchClientTest extends TestCase
{
    private const string TEST_URL = 'https://localhost:9200';

    public function testConstructorAcceptsCustomUrl(): void
    {
        $client = new ElasticsearchClient('https://custom:9200');

        $reflection     = new ReflectionClass($client);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($client);

        $this->assertEquals('https://custom:9200', $config->url);
    }

    public function testConstructorUsesDefaultWhenNoUrlProvided(): void
    {
        // Clear environment variables to ensure defaults are used
        $_ENV['ELASTICSEARCH_URL']  = '';
        $_ENV['ELASTICSEARCH_HOST'] = '';
        putenv('ELASTICSEARCH_URL=');
        putenv('ELASTICSEARCH_HOST=');

        $client = new ElasticsearchClient();

        $reflection     = new ReflectionClass($client);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($client);

        // Default is https://elasticsearch:9200
        $this->assertStringContainsString('elasticsearch', $config->url);
        $this->assertStringContainsString('9200', $config->url);
    }

    public function testBulkIndexReturnsZeroForEmptyDocuments(): void
    {
        $client = new ElasticsearchClient(self::TEST_URL);

        $result = $client->bulkIndex('test-index', []);

        $this->assertEquals(['indexed' => 0, 'errors' => 0], $result);
    }

    /**
     * Test that all methods handle connection errors gracefully
     *
     * When no Elasticsearch server is available, all methods should return
     * safe default values (null, false, empty array) instead of throwing exceptions.
     */
    public function testAllMethodsHandleConnectionErrorsGracefully(): void
    {
        $client = new ElasticsearchClient(self::TEST_URL);

        // Search returns empty result structure, aggregate returns empty array
        $this->assertEquals(
            ['hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []], 'took' => 0],
            $client->search('test', ['match_all' => new stdClass()])
        );
        $this->assertEquals([], $client->aggregate('test', ['agg' => []]));
        $this->assertEquals([], $client->getMapping('test'));

        // Get methods return null
        $this->assertNull($client->get('test', 'doc-1'));
        $this->assertNull($client->index('test', 'doc-1', ['field' => 'value']));
        $this->assertNull($client->getClusterHealth());

        // Mutation methods return false
        $this->assertFalse($client->update('test', 'doc-1', ['field' => 'value']));
        $this->assertFalse($client->delete('test', 'doc-1'));
        $this->assertFalse($client->createIndex('test'));
        $this->assertFalse($client->deleteIndex('test'));
        $this->assertFalse($client->indexExists('test'));
        $this->assertFalse($client->putMapping('test', []));
        $this->assertFalse($client->refresh('test'));
        $this->assertFalse($client->isAvailable());

        // deleteByQuery returns -1 on error
        $this->assertEquals(-1, $client->deleteByQuery('test', ['match_all' => new stdClass()]));

        // bulkIndex returns error count
        $docs   = [['id' => '1', 'name' => 'Test']];
        $result = $client->bulkIndex('test', $docs);
        $this->assertEquals(0, $result['indexed']);
        $this->assertEquals(1, $result['errors']);
    }

    public function testConfigDetectsHttpsUseTls(): void
    {
        $client = new ElasticsearchClient('https://secure:9200');

        $reflection     = new ReflectionClass($client);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($client);

        $this->assertTrue($config->useTls);
    }

    public function testConfigDetectsHttpNoTls(): void
    {
        $client = new ElasticsearchClient('http://local:9200');

        $reflection     = new ReflectionClass($client);
        $configProperty = $reflection->getProperty('config');
        $config         = $configProperty->getValue($client);

        $this->assertFalse($config->useTls);
    }
}
