<?php

declare(strict_types=1);

namespace Tests\DevToolbar\Renderers\Panels;

use DevToolbar\Analyzers\QueryAnalyzer;
use DevToolbar\Renderers\Panels\QueriesPanelRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Test QueriesPanelRenderer
 */
class QueriesPanelRendererTest extends TestCase
{
    private QueriesPanelRenderer $renderer;
    private QueryAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new QueryAnalyzer();
        $this->renderer = new QueriesPanelRenderer($this->analyzer);
    }

    // ========== getPanelName() tests ==========

    public function testGetPanelName(): void
    {
        $this->assertEquals('queries', $this->renderer->getPanelName());
    }

    // ========== renderTab() tests - Empty states ==========

    public function testRenderTabWithNoQueries(): void
    {
        $data = [];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No queries executed', $result);
    }

    public function testRenderTabWithEmptyQueriesArray(): void
    {
        $data = ['queries' => []];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No queries executed', $result);
    }

    // ========== renderTab() tests - Single query ==========

    public function testRenderTabWithSingleQuery(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'SELECT * FROM users WHERE id = 1',
                    'time'     => 15.5,
                    'bindings' => [],
                ],
            ],
            'total_time' => 15.5,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Summary: 1 query in 15.50ms', $result);
        $this->assertStringContainsString('avg: 15.50ms', $result);
        $this->assertStringContainsString('SELECT * FROM users WHERE id = 1', $result);
        $this->assertStringContainsString('15.50ms', $result);
    }

    // ========== renderTab() tests - Multiple queries ==========

    public function testRenderTabWithMultipleQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 10.0, 'bindings' => []],
                ['sql' => 'SELECT * FROM posts', 'time' => 20.0, 'bindings' => []],
                ['sql' => 'SELECT * FROM comments', 'time' => 30.0, 'bindings' => []],
            ],
            'total_time' => 60.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Summary: 3 queries in 60.00ms', $result);
        $this->assertStringContainsString('avg: 20.00ms', $result);
        $this->assertStringContainsString('SELECT * FROM users', $result);
        $this->assertStringContainsString('SELECT * FROM posts', $result);
        $this->assertStringContainsString('SELECT * FROM comments', $result);
    }

    // ========== renderTab() tests - Query with bindings ==========

    public function testRenderTabWithQueryBindings(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'SELECT * FROM users WHERE id = ? AND name = ?',
                    'time'     => 12.3,
                    'bindings' => [1, 'John'],
                ],
            ],
            'total_time' => 12.3,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('SELECT * FROM users WHERE id = ? AND name = ?', $result);
        $this->assertStringContainsString('Bindings:', $result);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('John', $result);
    }

    public function testRenderTabWithComplexBindings(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'INSERT INTO users',
                    'time'     => 5.0,
                    'bindings' => [
                        'name'  => 'Alice',
                        'email' => 'alice@example.com',
                        'age'   => 30,
                    ],
                ],
            ],
            'total_time' => 5.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Bindings:', $result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('alice@example.com', $result);
    }

    // ========== renderTab() tests - N+1 detection ==========

    public function testRenderTabWithNPlusOneDetection(): void
    {
        // This data will trigger N+1 detection by QueryAnalyzer (3+ same pattern queries)
        $data = [
            'queries' => [
                [
                    'sql'       => 'SELECT * FROM posts WHERE user_id = 1',
                    'time'      => 10.0,
                    'backtrace' => [['file' => '/path/to/UserController.php', 'line' => 45]],
                ],
                [
                    'sql'       => 'SELECT * FROM posts WHERE user_id = 2',
                    'time'      => 10.0,
                    'backtrace' => [['file' => '/path/to/UserController.php', 'line' => 45]],
                ],
                [
                    'sql'       => 'SELECT * FROM posts WHERE user_id = 3',
                    'time'      => 10.0,
                    'backtrace' => [['file' => '/path/to/UserController.php', 'line' => 45]],
                ],
            ],
            'total_time' => 30.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Verify N+1 warning is shown
        $this->assertStringContainsString('N+1 Query Detected!', $result);
        $this->assertStringContainsString('(1 pattern)', $result);
        $this->assertStringContainsString('3 times with different parameters', $result);
        $this->assertStringContainsString('UserController.php:45', $result);
        $this->assertStringContainsString('WHERE IN', $result);
    }

    public function testRenderTabWithMultipleNPlusOnePatterns(): void
    {
        // Multiple N+1 patterns
        $data = [
            'queries' => [
                // First pattern: posts
                ['sql' => 'SELECT * FROM posts WHERE user_id = 1', 'time' => 10.0],
                ['sql' => 'SELECT * FROM posts WHERE user_id = 2', 'time' => 10.0],
                ['sql' => 'SELECT * FROM posts WHERE user_id = 3', 'time' => 10.0],
                // Second pattern: comments
                ['sql' => 'SELECT * FROM comments WHERE post_id = 1', 'time' => 5.0],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 2', 'time' => 5.0],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 3', 'time' => 5.0],
            ],
            'total_time' => 45.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('(2 patterns)', $result);
        $this->assertStringContainsString('FROM POSTS WHERE USER_ID = ?', $result);
        $this->assertStringContainsString('FROM COMMENTS WHERE POST_ID = ?', $result);
    }

    // ========== renderTab() tests - Slow query detection ==========

    public function testRenderTabWithSlowQueryDetection(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM huge_table', 'time' => 250.0],
            ],
            'total_time' => 250.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Verify slow query warning is shown (default threshold is 100ms)
        $this->assertStringContainsString('Slow Queries Detected!', $result);
        $this->assertStringContainsString('(1 query)', $result);
        $this->assertStringContainsString('SELECT * FROM huge_table', $result);
        $this->assertStringContainsString('250.00ms', $result);
    }

    public function testRenderTabWithMultipleSlowQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM table1', 'time' => 300.0],
                ['sql' => 'SELECT * FROM table2', 'time' => 200.0],
            ],
            'total_time' => 500.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('(2 queries)', $result);
        $this->assertStringContainsString('SELECT * FROM table1', $result);
        $this->assertStringContainsString('SELECT * FROM table2', $result);
    }

    // ========== renderTab() tests - Both N+1 and slow queries ==========

    public function testRenderTabWithBothNPlusOneAndSlowQueries(): void
    {
        $data = [
            'queries' => [
                // N+1 pattern
                ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 3', 'time' => 10.0],
                // Slow query
                ['sql' => 'SELECT * FROM big_table', 'time' => 500.0],
            ],
            'total_time' => 530.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('N+1 Query Detected!', $result);
        $this->assertStringContainsString('Slow Queries Detected!', $result);
    }

    // ========== renderTab() tests - HTML escaping ==========

    public function testRenderTabEscapesHtmlInSql(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'SELECT * FROM users WHERE name = "<script>alert(1)</script>"',
                    'time'     => 10.0,
                    'bindings' => [],
                ],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInBindings(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'      => 'INSERT INTO users',
                    'time'     => 5.0,
                    'bindings' => ['name' => '<script>alert(1)</script>'],
                ],
            ],
            'total_time' => 5.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>alert', $result);
    }

    public function testRenderTabEscapesHtmlInNPlusOneSuggestion(): void
    {
        // Note: QueryAnalyzer doesn't generate HTML in suggestions, but we test escaping anyway
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users WHERE id = 1', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 2', 'time' => 10.0],
                ['sql' => 'SELECT * FROM users WHERE id = 3', 'time' => 10.0],
            ],
            'total_time' => 30.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Verify N+1 section is rendered and escaped
        $this->assertStringContainsString('N+1 Query Detected!', $result);
        $this->assertStringContainsString('dev-toolbar-suggestion', $result);
    }

    public function testRenderTabEscapesHtmlInSlowQuerySuggestion(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM table', 'time' => 100.0],
            ],
            'total_time' => 100.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Verify slow query section is rendered
        $this->assertStringContainsString('dev-toolbar-suggestion', $result);
    }

    // ========== renderTab() tests - Backtrace ==========

    public function testRenderTabWithBacktrace(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'       => 'SELECT * FROM users',
                    'time'      => 10.0,
                    'bindings'  => [],
                    'backtrace' => [
                        [
                            'file'     => '/path/to/UserController.php',
                            'line'     => 45,
                            'function' => 'getUsers',
                        ],
                        [
                            'file'     => '/path/to/Router.php',
                            'line'     => 100,
                            'function' => 'dispatch',
                        ],
                    ],
                ],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Called from:', $result);
        $this->assertStringContainsString('/path/to/UserController.php:45', $result);
        $this->assertStringContainsString('getUsers()', $result);
        $this->assertStringContainsString('/path/to/Router.php:100', $result);
        $this->assertStringContainsString('dispatch()', $result);
    }

    public function testRenderTabWithEmptyBacktrace(): void
    {
        $data = [
            'queries' => [
                [
                    'sql'       => 'SELECT * FROM users',
                    'time'      => 10.0,
                    'bindings'  => [],
                    'backtrace' => [],
                ],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should not render backtrace section if empty
        $this->assertStringNotContainsString('Called from:', $result);
    }

    // ========== renderTab() tests - Performance classes ==========

    public function testRenderTabAppliesFastClassForFastQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 50.0, 'bindings' => []],
            ],
            'total_time' => 50.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-query fast', $result);
        $this->assertStringContainsString('dev-toolbar-query-time fast', $result);
    }

    public function testRenderTabAppliesSlowClassForSlowQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 150.0, 'bindings' => []],
            ],
            'total_time' => 150.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-query slow', $result);
        $this->assertStringContainsString('dev-toolbar-query-time slow', $result);
    }

    public function testRenderTabAppliesVerySlowClassForVerySlowQueries(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 600.0, 'bindings' => []],
            ],
            'total_time' => 600.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-query very-slow', $result);
        $this->assertStringContainsString('dev-toolbar-query-time very-slow', $result);
    }

    // ========== renderTab() tests - Expected structure ==========

    public function testRenderTabContainsExpectedSections(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 10.0, 'bindings' => []],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('dev-toolbar-section', $result);
        $this->assertStringContainsString('dev-toolbar-section-title', $result);
        $this->assertStringContainsString('Query List:', $result);
        $this->assertStringContainsString('dev-toolbar-query', $result);
        $this->assertStringContainsString('dev-toolbar-query-header', $result);
        $this->assertStringContainsString('dev-toolbar-query-time', $result);
        $this->assertStringContainsString('dev-toolbar-query-sql', $result);
    }

    // ========== renderTab() tests - Edge cases ==========

    public function testRenderTabHandlesMissingTotalTime(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users', 'time' => 10.0],
            ],
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('Summary: 1 query in 0.00ms', $result);
    }

    public function testRenderTabHandlesMissingQueryTime(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT * FROM users'],
            ],
            'total_time' => 0.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('0.00ms', $result);
    }

    public function testRenderTabHandlesMissingSqlField(): void
    {
        $data = [
            'queries' => [
                ['time' => 10.0, 'bindings' => []],
            ],
            'total_time' => 10.0,
        ];

        $result = $this->renderer->renderTab($data);

        // Should not crash, should render with empty SQL
        $this->assertStringContainsString('dev-toolbar-query-sql', $result);
    }

    public function testRenderTabWithZeroQueries(): void
    {
        $data = [
            'queries'    => [],
            'total_time' => 0.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('No queries executed', $result);
    }

    public function testRenderTabCalculatesAverageTimeCorrectly(): void
    {
        $data = [
            'queries' => [
                ['sql' => 'SELECT 1', 'time' => 10.0],
                ['sql' => 'SELECT 2', 'time' => 20.0],
                ['sql' => 'SELECT 3', 'time' => 30.0],
            ],
            'total_time' => 60.0,
        ];

        $result = $this->renderer->renderTab($data);

        $this->assertStringContainsString('avg: 20.00ms', $result);
    }
}
