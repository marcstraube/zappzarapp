<?php

declare(strict_types=1);

namespace Tests\App\Unit\Http;

use App\Http\ErrorPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ErrorPage class
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
class ErrorPageTest extends TestCase
{
    // ===== getErrorInfo Tests =====

    public function testGetErrorInfoReturns404Info(): void
    {
        $info = ErrorPage::getErrorInfo(404);

        $this->assertEquals('Page Not Found', $info['title']);
        $this->assertStringContainsString('exist', $info['message']);
    }

    public function testGetErrorInfoReturns500Info(): void
    {
        $info = ErrorPage::getErrorInfo(500);

        $this->assertEquals('Internal Server Error', $info['title']);
        $this->assertStringContainsString('went wrong', $info['message']);
    }

    public function testGetErrorInfoReturnsDefaultForUnknownCode(): void
    {
        $info = ErrorPage::getErrorInfo(999);

        $this->assertEquals('Error', $info['title']);
        $this->assertEquals('An unexpected error occurred.', $info['message']);
    }

    #[DataProvider('supportedErrorCodesProvider')]
    public function testGetErrorInfoReturnsInfoForAllSupportedCodes(int $code): void
    {
        $info = ErrorPage::getErrorInfo($code);

        $this->assertArrayHasKey('title', $info);
        $this->assertArrayHasKey('message', $info);
        $this->assertNotEmpty($info['title']);
        $this->assertNotEmpty($info['message']);
    }

    // ===== hasErrorInfo Tests =====

    public function testHasErrorInfoReturnsTrueForKnownCodes(): void
    {
        $this->assertTrue(ErrorPage::hasErrorInfo(400));
        $this->assertTrue(ErrorPage::hasErrorInfo(401));
        $this->assertTrue(ErrorPage::hasErrorInfo(403));
        $this->assertTrue(ErrorPage::hasErrorInfo(404));
        $this->assertTrue(ErrorPage::hasErrorInfo(500));
        $this->assertTrue(ErrorPage::hasErrorInfo(502));
        $this->assertTrue(ErrorPage::hasErrorInfo(503));
    }

    public function testHasErrorInfoReturnsFalseForUnknownCodes(): void
    {
        $this->assertFalse(ErrorPage::hasErrorInfo(200));
        $this->assertFalse(ErrorPage::hasErrorInfo(301));
        $this->assertFalse(ErrorPage::hasErrorInfo(999));
    }

    // ===== getSupportedCodes Tests =====

    public function testGetSupportedCodesReturnsAllCodes(): void
    {
        $codes = ErrorPage::getSupportedCodes();

        $this->assertContains(400, $codes);
        $this->assertContains(401, $codes);
        $this->assertContains(403, $codes);
        $this->assertContains(404, $codes);
        $this->assertContains(500, $codes);
        $this->assertContains(502, $codes);
        $this->assertContains(503, $codes);
        $this->assertCount(7, $codes);
    }

    // ===== renderHtml Tests =====

    public function testRenderHtmlContainsErrorCode(): void
    {
        $html = ErrorPage::renderHtml(404);

        $this->assertStringContainsString('404', $html);
    }

    public function testRenderHtmlContainsTitle(): void
    {
        $html = ErrorPage::renderHtml(404);

        $this->assertStringContainsString('Page Not Found', $html);
    }

    public function testRenderHtmlContainsMessage(): void
    {
        $html = ErrorPage::renderHtml(404);

        // Note: apostrophes are HTML-escaped to &#039;
        $this->assertStringContainsString('exist', $html);
    }

    public function testRenderHtmlContainsPathWhenProvided(): void
    {
        $html = ErrorPage::renderHtml(404, '/test/path');

        $this->assertStringContainsString('/test/path', $html);
    }

    public function testRenderHtmlDoesNotContainPathWhenNull(): void
    {
        $html = ErrorPage::renderHtml(404);

        $this->assertStringNotContainsString('Path:', $html);
    }

    public function testRenderHtmlEscapesPath(): void
    {
        $html = ErrorPage::renderHtml(404, '/path/<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderHtmlIsValidHtml(): void
    {
        $html = ErrorPage::renderHtml(404);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('</html>', $html);
        $this->assertStringContainsString('<head>', $html);
        $this->assertStringContainsString('</head>', $html);
        $this->assertStringContainsString('<body>', $html);
        $this->assertStringContainsString('</body>', $html);
    }

    public function testRenderHtmlContainsBackLink(): void
    {
        $html = ErrorPage::renderHtml(404);

        $this->assertStringContainsString('href="/"', $html);
        $this->assertStringContainsString('Back to Home', $html);
    }

    #[DataProvider('supportedErrorCodesProvider')]
    public function testRenderHtmlWorksForAllSupportedCodes(int $code): void
    {
        $html = ErrorPage::renderHtml($code);

        $this->assertStringContainsString((string) $code, $html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
    }

    // ===== Data Providers =====

    /**
     * @return array<string, array{int}>
     */
    public static function supportedErrorCodesProvider(): array
    {
        return [
            '400 Bad Request'           => [400],
            '401 Unauthorized'          => [401],
            '403 Forbidden'             => [403],
            '404 Not Found'             => [404],
            '500 Internal Server Error' => [500],
            '502 Bad Gateway'           => [502],
            '503 Service Unavailable'   => [503],
        ];
    }
}
