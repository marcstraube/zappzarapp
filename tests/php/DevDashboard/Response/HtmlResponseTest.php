<?php

declare(strict_types=1);

namespace Tests\DevDashboard\Response;

use DevDashboard\Response\HtmlResponse;
use DevDashboard\Response\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DevDashboard HtmlResponse
 *
 * send() emits output and calls header()/http_response_code().
 * Output is captured via ob_start()/ob_get_clean().
 * Header assertions require RunInSeparateProcess so http_response_code() is fresh.
 */
#[CoversClass(HtmlResponse::class)]
final class HtmlResponseTest extends TestCase
{
    // ===== Interface contract =====

    public function testImplementsResponseInterface(): void
    {
        $response = new HtmlResponse('<p>Test</p>');

        $this->assertInstanceOf(Response::class, $response);
    }

    // ===== Output content =====

    public function testSendOutputsContent(): void
    {
        $response = new HtmlResponse('<h1>Hello</h1>');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('<h1>Hello</h1>', $output);
    }

    public function testSendOutputsEmptyContent(): void
    {
        $response = new HtmlResponse('');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function testSendOutputsFullHtmlDocument(): void
    {
        $html     = "<!DOCTYPE html>\n<html><head></head><body>Test</body></html>";
        $response = new HtmlResponse($html);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame($html, $output);
    }

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
    public function testSendEmitsDefaultStatus200(): void
    {
        $response = new HtmlResponse('<p>OK</p>');

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(200, http_response_code());
    }

    #[RunInSeparateProcess]
    public function testSendEmitsCustomStatus404(): void
    {
        $response = new HtmlResponse('<h1>Not Found</h1>', 404);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    #[RunInSeparateProcess]
    public function testSendEmitsCustomStatus500(): void
    {
        $response = new HtmlResponse('<h1>Error</h1>', 500);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(500, http_response_code());
    }

    #[RunInSeparateProcess]
    public function testSendEmitsStatus301(): void
    {
        $response = new HtmlResponse('', 301);

        ob_start();
        $response->send();
        ob_get_clean();

        $this->assertSame(301, http_response_code());
    }

    // ===== fromView factory =====

    public function testFromViewReturnsSelf(): void
    {
        // A non-existent view returns a 404 HtmlResponse
        $response = HtmlResponse::fromView('this-view-does-not-exist-xyz');

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    public function testFromViewReturns404WhenViewNotFound(): void
    {
        ob_start();
        HtmlResponse::fromView('this-view-does-not-exist-xyz')->send();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('View not found', $output);
    }

    #[RunInSeparateProcess]
    public function testFromViewEmits404StatusWhenViewNotFound(): void
    {
        ob_start();
        HtmlResponse::fromView('nonexistent-view')->send();
        ob_get_clean();

        $this->assertSame(404, http_response_code());
    }

    public function testFromViewRendersExistingView(): void
    {
        // fromView includes files from src/php/DevDashboard/Views/ (relative to Response/ dir).
        // We create a minimal test view there, test it, then remove it.
        $viewsDir  = __DIR__ . '/../../../../src/php/DevDashboard/Views';
        $viewsDir  = realpath($viewsDir);
        $viewName  = '_test_view_' . uniqid();
        $viewFile  = $viewsDir . '/' . $viewName . '.php';

        if ($viewsDir === false || !is_dir($viewsDir)) {
            $this->markTestSkipped('DevDashboard/Views directory not available in this environment');
        }

        file_put_contents($viewFile, '<p>test view</p>');

        try {
            $response = HtmlResponse::fromView($viewName);

            ob_start();
            $response->send();
            $output = (string) ob_get_clean();

            $this->assertSame('<p>test view</p>', $output);
        } finally {
            if (file_exists($viewFile)) {
                unlink($viewFile);
            }
        }
    }

    public function testFromViewPassesDataToView(): void
    {
        $viewsDir = __DIR__ . '/../../../../src/php/DevDashboard/Views';
        $viewsDir = realpath($viewsDir);
        $viewName = '_test_view_data_' . uniqid();
        $viewFile = $viewsDir . '/' . $viewName . '.php';

        if ($viewsDir === false || !is_dir($viewsDir)) {
            $this->markTestSkipped('DevDashboard/Views directory not available in this environment');
        }

        // View can access extracted $data vars
        file_put_contents($viewFile, '<?php echo $greeting; ?>');

        try {
            $response = HtmlResponse::fromView($viewName, ['greeting' => 'Hello, world!']);

            ob_start();
            $response->send();
            $output = (string) ob_get_clean();

            $this->assertSame('Hello, world!', $output);
        } finally {
            if (file_exists($viewFile)) {
                unlink($viewFile);
            }
        }
    }
}
