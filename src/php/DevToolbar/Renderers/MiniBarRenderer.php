<?php

declare(strict_types=1);

namespace DevToolbar\Renderers;

use DevToolbar\DataCollectors\CollectorInterface;
use Throwable;

/**
 * Renders mini bar (always visible widget in bottom-right)
 */
class MiniBarRenderer implements RendererInterface
{
    /** @var array<string, CollectorInterface> */
    private array $collectors;

    /**
     * @param array<string, CollectorInterface> $collectors
     */
    public function __construct(array $collectors)
    {
        $this->collectors = $collectors;
    }

    public function render(): string
    {
        $requestData = $this->collectors['request']->getData();
        $queriesData = $this->collectors['queries']->getData();

        $time       = $requestData['execution_time'] ?? 0;
        $memory     = $requestData['memory_peak'] ?? 0;
        $queryCount = $queriesData['count'] ?? 0;

        // Get all active labels from cookies (set by client-side JS)
        $labels = $this->getDisplayLabels($requestData);

        return sprintf(
            '<div class="dev-toolbar-mini">
                %s
                <span class="dev-toolbar-mini-metric">%dms</span>
                <span class="dev-toolbar-mini-metric">%.1fMB</span>
                <span class="dev-toolbar-mini-metric">%d queries</span>
                <span class="dev-toolbar-mini-expand">↗</span>
            </div>',
            $labels,
            (int)$time,
            $memory,
            $queryCount
        );
    }

    /**
     * Get display labels based on configured types from cookies
     *
     * @param array<string, mixed> $requestData
     */
    private function getDisplayLabels(array $requestData): string
    {
        // Read label types from cookie (set by client-side JS)
        $labelsJson = $_COOKIE['devbar_labels'] ?? null;
        $types      = ['branding']; // default

        if ($labelsJson) {
            $decoded = json_decode(urldecode($labelsJson), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                $types = $decoded;
            }
        }

        $labelHtml = '';
        foreach ($types as $type) {
            $label = match ($type) {
                'branch' => $this->formatBranch(
                    $this->getGitBranch() ?? 'unknown',
                    $this->getBranchColors()
                ),
                'route' => $this->formatRoute(
                    $requestData['method'] ?? '',
                    $requestData['uri'] ?? ''
                ),
                'request-id' => $this->formatRequestId($this->getCurrentRequestId()),
                default      => '⚡', // branding
            };

            $labelHtml .= sprintf('<span class="dev-toolbar-mini-label">%s</span>', $label);
        }

        return $labelHtml;
    }

    /**
     * Get current git branch from .git/HEAD (read-only, no shell execution)
     */
    private function getGitBranch(): ?string
    {
        // Try environment variable first (can be set in CI/CD or .env)
        $envBranch = getenv('GIT_BRANCH');
        if ($envBranch !== false && $envBranch !== '') {
            return $envBranch;
        }

        // Fallback: Read from .git/HEAD (safer than shell_exec)
        $gitHeadPath = getcwd() . '/.git/HEAD';
        if (!file_exists($gitHeadPath)) {
            return null;
        }

        try {
            $headContent = file_get_contents($gitHeadPath);
            if ($headContent === false) {
                return null;
            }

            // Format: "ref: refs/heads/branch-name" or commit hash
            if (str_starts_with($headContent, 'ref: refs/heads/')) {
                return trim(substr($headContent, 16));
            }

            // Detached HEAD (commit hash)
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get branch colors from cookie or defaults
     *
     * @return array<string, string>
     */
    private function getBranchColors(): array
    {
        $defaults = [
            'feat'    => '#3b82f6',
            'fix'     => '#f59e0b',
            'hotfix'  => '#ef4444',
            'chore'   => '#6b7280',
            'default' => '#10b981',
        ];

        if (!isset($_COOKIE['devbar_colors'])) {
            return $defaults;
        }

        try {
            $decoded = json_decode(urldecode($_COOKIE['devbar_colors']), true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        } catch (Throwable $e) {
            // Invalid JSON, return defaults
        }

        return $defaults;
    }

    /**
     * Format branch display with color coding
     *
     * @param array<string, string> $colors
     */
    private function formatBranch(string $branch, array $colors): string
    {
        // Determine branch type
        $type = 'default';
        if (preg_match('/^(feat|feature)\//', $branch)) {
            $type = 'feat';
        } elseif (preg_match('/^fix\//', $branch)) {
            $type = 'fix';
        } elseif (preg_match('/^hotfix\//', $branch)) {
            $type = 'hotfix';
        } elseif (preg_match('/^chore\//', $branch)) {
            $type = 'chore';
        }

        // Get color for branch type
        $color = $colors[$type] ?? $colors['default'] ?? '#10b981';

        // Strip prefix for brevity
        $displayName = preg_replace('/^(feature|feat|fix|hotfix|chore)\//', '', $branch) ?? $branch;

        return sprintf(
            '<span style="color: %s">%s</span>',
            htmlspecialchars($color, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Format route display
     */
    private function formatRoute(string $method, string $uri): string
    {
        return sprintf(
            '%s %s',
            htmlspecialchars($method, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($uri, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Get current request ID
     */
    private function getCurrentRequestId(): string
    {
        // Generate unique request ID
        return 'req_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Format request ID display (shortened)
     */
    private function formatRequestId(string $id): string
    {
        // Show shortened version
        return htmlspecialchars(substr($id, 0, 12), ENT_QUOTES, 'UTF-8');
    }
}
