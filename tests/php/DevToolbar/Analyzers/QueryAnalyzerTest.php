<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Analyzers;

use DevToolbar\Analyzers\QueryAnalyzer;
use PHPUnit\Framework\TestCase;

/**
 * Test QueryAnalyzer N+1 detection and query analysis
 */
class QueryAnalyzerTest extends TestCase
{
    public function testDetectsNPlusOnePattern(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 11],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 12],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals(3, $nPlusOnes[0]['count']);
        $this->assertEquals(33, $nPlusOnes[0]['total_time']);
        $this->assertEquals(11, $nPlusOnes[0]['avg_time']);
    }

    public function testDoesNotFlagDifferentQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE id = 1', 'time' => 11],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(0, $nPlusOnes);
    }

    public function testRequiresMinimumThreeOccurrences(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 10],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        // Only 2 occurrences, not flagged
        $this->assertCount(0, $nPlusOnes);
    }

    public function testNormalizesQueriesCorrectly(): void
    {
        $queries = [
            ['sql' => "SELECT * FROM users WHERE id = 1 AND name = 'John'", 'time' => 10],
            ['sql' => "SELECT * FROM users WHERE id = 2 AND name = 'Jane'", 'time' => 10],
            ['sql' => "SELECT * FROM users WHERE id = 3 AND name = 'Bob'", 'time' => 10],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        // Should detect as N+1 despite different values
        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals(3, $nPlusOnes[0]['count']);
    }

    public function testSortsByTotalTime(): void
    {
        $queries = [
            // Pattern 1: Fast queries
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 5],
            ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 5],
            ['sql' => 'SELECT * FROM users WHERE id = 3', 'time' => 5],

            // Pattern 2: Slow queries
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 50],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 50],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 50],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(2, $nPlusOnes);

        // First should be the slower pattern (posts)
        $this->assertEquals(150, $nPlusOnes[0]['total_time']);
        // Second should be the faster pattern (users)
        $this->assertEquals(15, $nPlusOnes[1]['total_time']);
    }

    public function testExtractsLocationFromBacktrace(): void
    {
        $queries = [
            [
                'sql' => 'SELECT * FROM users WHERE id = 1',
                'time' => 10,
                'backtrace' => [['file' => 'UserRepository.php', 'line' => 45]],
            ],
            [
                'sql' => 'SELECT * FROM users WHERE id = 2',
                'time' => 10,
                'backtrace' => [['file' => 'UserRepository.php', 'line' => 45]],
            ],
            [
                'sql' => 'SELECT * FROM users WHERE id = 3',
                'time' => 10,
                'backtrace' => [['file' => 'UserRepository.php', 'line' => 45]],
            ],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertEquals('UserRepository.php:45', $nPlusOnes[0]['location']);
    }

    public function testGeneratesSuggestion(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 10],
            ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 10],
        ];

        $nPlusOnes = QueryAnalyzer::detectNPlusOne($queries);

        $this->assertCount(1, $nPlusOnes);
        $this->assertArrayHasKey('suggestion', $nPlusOnes[0]);
        $this->assertNotEmpty($nPlusOnes[0]['suggestion']);
        $this->assertStringContainsString('WHERE IN', $nPlusOnes[0]['suggestion']);
    }

    public function testDetectsSlowQueries(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users', 'time' => 150],
            ['sql' => 'SELECT * FROM posts', 'time' => 50],
        ];

        $slowQueries = QueryAnalyzer::detectSlowQueries($queries, 100);

        $this->assertCount(1, $slowQueries);
        $this->assertEquals('SELECT * FROM users', $slowQueries[0]['sql']);
        $this->assertEquals(150, $slowQueries[0]['time']);
    }

    public function testSlowQueriesSortedByTime(): void
    {
        $queries = [
            ['sql' => 'SELECT 1', 'time' => 200],
            ['sql' => 'SELECT 2', 'time' => 500],
            ['sql' => 'SELECT 3', 'time' => 300],
        ];

        $slowQueries = QueryAnalyzer::detectSlowQueries($queries, 100);

        $this->assertCount(3, $slowQueries);
        $this->assertEquals(500, $slowQueries[0]['time']); // Slowest first
        $this->assertEquals(300, $slowQueries[1]['time']);
        $this->assertEquals(200, $slowQueries[2]['time']);
    }

    public function testGeneratesSlowQuerySuggestions(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM users', 'time' => 150], // SELECT *
            ['sql' => 'SELECT name FROM posts WHERE title LIKE "%test%"', 'time' => 200], // LIKE
        ];

        $slowQueries = QueryAnalyzer::detectSlowQueries($queries, 100);

        $this->assertCount(2, $slowQueries);

        // Check for SELECT * suggestion
        $this->assertStringContainsString('SELECT *', $slowQueries[0]['suggestion']);

        // Check for LIKE suggestion
        $this->assertStringContainsString('LIKE', $slowQueries[1]['suggestion']);
    }

    public function testGetStatistics(): void
    {
        $queries = [
            ['sql' => 'SELECT 1', 'time' => 10],
            ['sql' => 'SELECT 2', 'time' => 20],
            ['sql' => 'SELECT 3', 'time' => 30],
        ];

        $stats = QueryAnalyzer::getStatistics($queries);

        $this->assertEquals(3, $stats['total_count']);
        $this->assertEquals(60, $stats['total_time']);
        $this->assertEquals(20, $stats['avg_time']);
        $this->assertEquals(30, $stats['slowest']);
        $this->assertEquals(10, $stats['fastest']);
    }

    public function testGetStatisticsWithEmptyQueries(): void
    {
        $stats = QueryAnalyzer::getStatistics([]);

        $this->assertEquals(0, $stats['total_count']);
        $this->assertEquals(0, $stats['total_time']);
        $this->assertEquals(0, $stats['avg_time']);
        $this->assertEquals(0, $stats['slowest']);
        $this->assertEquals(0, $stats['fastest']);
    }
}
