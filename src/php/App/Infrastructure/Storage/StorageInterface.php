<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

/**
 * Storage Interface for S3-compatible object storage
 *
 * Provides a simple, type-safe interface for file upload and download.
 * Works with SeaweedFS, MinIO, AWS S3, and other S3-compatible services.
 *
 * When to use:
 * - File uploads (user avatars, documents, media)
 * - Large file storage (videos, backups)
 * - Static asset hosting
 * - Distributed file storage across nodes
 *
 * When NOT to use:
 * - Small config files (use filesystem or database)
 * - Frequently updated data (use database)
 * - Real-time file access (use local filesystem)
 *
 * Usage:
 * <code>
 * // Get from DI container
 * $storage = $container->get(StorageInterface::class);
 *
 * // Upload a file
 * $url = $storage->upload('avatars/user-123.jpg', $fileContents, 'image/jpeg');
 *
 * // Upload from a local file
 * $url = $storage->uploadFile('documents/report.pdf', '/tmp/report.pdf', 'application/pdf');
 *
 * // Download a file
 * $contents = $storage->download('avatars/user-123.jpg');
 *
 * // Get a pre-signed URL (for direct browser access)
 * $url = $storage->getPresignedUrl('documents/report.pdf', 3600); // 1 hour
 *
 * // Delete a file
 * $storage->delete('avatars/user-123.jpg');
 * </code>
 *
 * @package Infrastructure\Storage
 */
interface StorageInterface
{
    /**
     * Upload content as a file
     *
     * @param string $key Object key (path within bucket, e.g., 'avatars/user-123.jpg')
     * @param string $content File content
     * @param string $contentType MIME type (e.g., 'image/jpeg', 'application/pdf')
     * @param array<string, string> $metadata Optional metadata headers (x-amz-meta-*)
     * @return string|null Public URL on success, null on error
     */
    public function upload(string $key, string $content, string $contentType, array $metadata = []): ?string;

    /**
     * Upload a file from local filesystem
     *
     * @param string $key Object key (path within bucket)
     * @param string $filePath Local file path
     * @param string|null $contentType MIME type (auto-detected if null)
     * @param array<string, string> $metadata Optional metadata headers
     * @return string|null Public URL on success, null on error
     */
    public function uploadFile(string $key, string $filePath, ?string $contentType = null, array $metadata = []): ?string;

    /**
     * Download file content
     *
     * @param string $key Object key
     * @return string|null File content or null if not found/error
     */
    public function download(string $key): ?string;

    /**
     * Download file to local filesystem
     *
     * @param string $key Object key
     * @param string $filePath Local destination path
     * @return bool True on success, false on error
     */
    public function downloadFile(string $key, string $filePath): bool;

    /**
     * Check if an object exists
     *
     * @param string $key Object key
     * @return bool True if object exists
     */
    public function exists(string $key): bool;

    /**
     * Delete an object
     *
     * @param string $key Object key
     * @return bool True on success (or if already deleted), false on error
     */
    public function delete(string $key): bool;

    /**
     * Delete multiple objects
     *
     * @param array<string> $keys Array of object keys
     * @return int Number of successfully deleted objects
     */
    public function deleteMultiple(array $keys): int;

    /**
     * List objects with optional prefix
     *
     * @param string $prefix Filter by prefix (e.g., 'avatars/')
     * @param int $maxKeys Maximum number of keys to return (default: 1000)
     * @return array<array{key: string, size: int, lastModified: string}> List of objects
     */
    public function list(string $prefix = '', int $maxKeys = 1000): array;

    /**
     * Copy an object to a new location
     *
     * @param string $sourceKey Source object key
     * @param string $destKey Destination object key
     * @return bool True on success, false on error
     */
    public function copy(string $sourceKey, string $destKey): bool;

    /**
     * Move an object to a new location (copy + delete)
     *
     * @param string $sourceKey Source object key
     * @param string $destKey Destination object key
     * @return bool True on success, false on error
     */
    public function move(string $sourceKey, string $destKey): bool;

    /**
     * Get object metadata (size, content type, last modified, etc.)
     *
     * @param string $key Object key
     * @return array{size: int, contentType: string, lastModified: string, metadata: array<string, string>}|null
     *   Metadata or null if not found
     */
    public function getMetadata(string $key): ?array;

    /**
     * Generate a pre-signed URL for direct access
     *
     * Note: SeaweedFS may not support pre-signed URLs.
     * Check isPresignedSupported() before using.
     *
     * @param string $key Object key
     * @param int $expiresIn Expiration time in seconds (default: 3600)
     * @param string $method HTTP method ('GET' for download, 'PUT' for upload)
     * @return string|null Pre-signed URL or null if not supported/error
     */
    public function getPresignedUrl(string $key, int $expiresIn = 3600, string $method = 'GET'): ?string;

    /**
     * Get the public URL for an object (if public access is enabled)
     *
     * @param string $key Object key
     * @return string Public URL
     */
    public function getPublicUrl(string $key): string;

    /**
     * Create a bucket if it doesn't exist
     *
     * @param string|null $bucket Bucket name (uses configured bucket if null)
     * @return bool True on success (or if already exists), false on error
     */
    public function createBucket(?string $bucket = null): bool;

    /**
     * Check if the bucket exists
     *
     * @param string|null $bucket Bucket name (uses configured bucket if null)
     * @return bool True if bucket exists
     */
    public function bucketExists(?string $bucket = null): bool;

    /**
     * Check if pre-signed URLs are supported
     *
     * @return bool True if pre-signed URLs are supported
     */
    public function isPresignedSupported(): bool;

    /**
     * Check if storage backend is available
     *
     * Use this for health checks or graceful degradation.
     *
     * @return bool True if storage is connected and responsive
     */
    public function isAvailable(): bool;
}
