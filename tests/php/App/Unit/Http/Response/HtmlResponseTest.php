<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http\Response;

use App\Http\Response\HtmlResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HtmlResponse
 *
 * send() emits output and calls header()/http_response_code().
 * Output is captured via ob_start()/ob_get_clean().
 * Header assertions require RunInSeparateProcess so headers_list() is accurate.
 */
#[CoversClass(HtmlResponse::class)]
final class HtmlResponseTest extends TestCase
{
    // ===== Output =====

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

    public function testSendPreservesSpecialCharacters(): void
    {
        // HtmlResponse outputs content verbatim — caller is responsible for escaping
        $content  = '<script>alert("xss")</script>';
        $response = new HtmlResponse($content);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        $this->assertSame($content, $output);
    }

    // ===== Status code =====
    // Note: headers_list() always returns [] in PHP CLI mode, so Content-Type
    // header cannot be asserted here. http_response_code() works in CLI.

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
}
