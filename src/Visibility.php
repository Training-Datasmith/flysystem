<?php

declare(strict_types=1);

namespace League\Flysystem;

/**
 * String constants for file visibility levels.
 *
 * These values are passed to adapters and stored in config arrays. They remain
 * as plain string constants for broad compatibility (PHP 8.0+). For PHP 8.1+
 * projects that want enum-level type safety, use {@see Visibility_Level} instead.
 *
 * @since 1.0
 */
final class Visibility
{
    /**
     * Files with this visibility are accessible to everyone (world-readable).
     *
     * In cloud storage adapters (S3, GCS, Azure), this maps to public ACLs or
     * bucket-level public access. On local filesystems, this maps to 0644 file
     * permissions and 0755 directory permissions.
     */
    public const PUBLIC = 'public';

    /**
     * Files with this visibility are accessible only to authenticated / authorised users.
     *
     * In cloud storage adapters, this maps to private ACLs. On local filesystems,
     * this maps to 0600 file permissions and 0700 directory permissions.
     */
    public const PRIVATE = 'private';
}
