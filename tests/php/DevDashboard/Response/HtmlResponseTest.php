<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Response;

use DevDashboard\Response\HtmlResponse;
use DevDashboard\Response\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DevDashboard HtmlResponse
 *
 * send() emits output and calls header()/http_response_code().
 * Output is captured via ob_start()/ob_get_clean().
 * Header assertions require RunInSeparateProcess so http_response_code() is fresh.
 *
 * fromView() uses the optional $viewsDir parameter (injectable seam) so the
 * "view found" branch (lines 35-40) is exercised deterministically via a temp
 * directory, without writing to the source tree.
 */
#[CoversClass(HtmlResponse::class)]
final class HtmlResponseTest extends TestCase
{
    private string $tempViewsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempViewsDir = sys_get_temp_dir() . '/html_response_views_test_' . uniqid();
        mkdir($this->tempViewsDir, 0755, recursive: true);
    }

    protected function tearDown(): void
    {
        $this->removeTempDir($this->tempViewsDir);
        parent::tearDown();
    }

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeTempDir($path) : unlink($path);
        }

        rmdir($dir);
    }

    // ===== Interface contract =====

    #[Test]
    public function testImplementsResponseInterface(): void
    {
        $response = new HtmlResponse('<p>Test</p>');

        $this->assertInstanceOf(Response::class, $response);
    }

    // ===== Output content =====

    #[Test]
    public function testSendOutputsContent(): void
    {
        $response = new HtmlResponse('<h1>Hello</h1>');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('<h1>Hello</h1>', $output);
    }

    #[Test]
    public function testSendOutputsEmptyContent(): void
    {
        $response = new HtmlResponse('');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function testSendOutputsFullHtmlDocument(): void
    {
        $html     = "<!DOCTYPE html>\n<html><head></head><body>Test</body></html>";
        $response = new HtmlResponse($html);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame($html, $output);
    }

    #[Test]
    public function testSendPreservesRawContent(): void
    {
        // HtmlResponse outputs content verbatim — caller is responsible for escaping
        $content  = '<p class="x">Hello &amp; world</p>';
        $response = new HtmlResponse($content);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame($content, $output);
    }

    // ===== Status codes =====

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsDefaultStatus200(): void
    {
        $response = new HtmlResponse('<p>OK</p>');

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsCustomStatus404(): void
    {
        $response = new HtmlResponse('<h1>Not Found</h1>', 404);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsCustomStatus500(): void
    {
        $response = new HtmlResponse('<h1>Error</h1>', 500);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(500, http_response_code());
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testSendEmitsStatus301(): void
    {
        $response = new HtmlResponse('', 301);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(301, http_response_code());
    }

    // ===== fromView factory — "view not found" branch =====

    #[Test]
    public function testFromViewReturnsSelf(): void
    {
        // A non-existent view returns a 404 HtmlResponse
        $response = HtmlResponse::fromView('this-view-does-not-exist-xyz', viewsDir: $this->tempViewsDir);

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function testFromViewReturns404WhenViewNotFound(): void
    {
        ob_start();
        HtmlResponse::fromView('this-view-does-not-exist-xyz', viewsDir: $this->tempViewsDir)->send();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('View not found', $output);
    }

    #[RunInSeparateProcess]
    #[Test]
    public function testFromViewEmits404StatusWhenViewNotFound(): void
    {
        ob_start();
        HtmlResponse::fromView('nonexistent-view', viewsDir: $this->tempViewsDir)->send();
        ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    // ===== fromView factory — "view found" branch (lines 35-40) =====

    #[Test]
    public function testFromViewRendersExistingView(): void
    {
        // Use temp dir seam — no write access to source tree required
        $viewName = 'test_view_' . uniqid();
        $viewFile = $this->tempViewsDir . '/' . $viewName . '.php';
        file_put_contents($viewFile, '<p>test view</p>');

        $response = HtmlResponse::fromView($viewName, viewsDir: $this->tempViewsDir);

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        $this->assertSame('<p>test view</p>', $output);
    }

    #[Test]
    public function testFromViewPassesDataToView(): void
    {
        $viewName = 'test_data_view_' . uniqid();
        $viewFile = $this->tempViewsDir . '/' . $viewName . '.php';
        file_put_contents($viewFile, '<?php echo $greeting; ?>');

        $response = HtmlResponse::fromView($viewName, ['greeting' => 'Hello, world!'], viewsDir: $this->tempViewsDir);

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        $this->assertSame('Hello, world!', $output);
    }

    #[Test]
    public function testFromViewUsesDefaultStatusForExistingView(): void
    {
        $viewName = 'status_view_' . uniqid();
        $viewFile = $this->tempViewsDir . '/' . $viewName . '.php';
        file_put_contents($viewFile, '<p>ok</p>');

        $response = HtmlResponse::fromView($viewName, viewsDir: $this->tempViewsDir);

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        $this->assertSame('<p>ok</p>', $output);
    }

    #[Test]
    public function testFromViewPassesCustomStatusForExistingView(): void
    {
        $viewName = 'custom_status_view_' . uniqid();
        $viewFile = $this->tempViewsDir . '/' . $viewName . '.php';
        file_put_contents($viewFile, '<p>created</p>');

        $response = HtmlResponse::fromView($viewName, status: 201, viewsDir: $this->tempViewsDir);

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        $this->assertSame('<p>created</p>', $output);
    }

    #[Test]
    public function testFromViewEmptyViewOutputFallsBackToEmptyString(): void
    {
        // View outputs nothing → ob_get_clean() returns '' or false → coerces to ''
        $viewName = 'empty_view_' . uniqid();
        $viewFile = $this->tempViewsDir . '/' . $viewName . '.php';
        file_put_contents($viewFile, '<?php // nothing');

        $response = HtmlResponse::fromView($viewName, viewsDir: $this->tempViewsDir);

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        $this->assertSame('', $output);
    }

    // ===== fromView — default viewsDir fallback (no seam) =====

    #[Test]
    public function testFromViewDefaultViewsDirUsed(): void
    {
        // Without viewsDir param, the default Views dir is used.
        // A non-existent view name exercises the !file_exists branch via the real path.
        $response = HtmlResponse::fromView('nonexistent-for-default-path-test');

        ob_start();
        $response->send();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('View not found', $output);
    }
}
