<?php

declare(strict_types=1);

namespace League\Flysystem;

/**
 * Enum representation of file visibility levels for PHP 8.1+ projects.
 *
 * This enum backs every case with the same string value used by the
 * legacy {@see Visibility} class constants, so it is fully interoperable:
 *
 * ```php
 * $filesystem->set_visibility($path, Visibility_Level::Public->value);
 * // identical to:
 * $filesystem->set_visibility($path, Visibility::PUBLIC);
 * ```
 *
 * Prefer this enum in new code for type safety; adapters and interfaces
 * still accept the plain string constants for backward compatibility.
 *
 * @since 3.2 (PHP 8.1+ only)
 */
enum Visibility_Level: string
{
    /**
     * Files are accessible to everyone (world-readable).
     *
     * Maps to public ACLs in cloud storage; 0644/0755 on local filesystems.
     */
    case Public = 'public';

    /**
     * Files are accessible only to authorised users.
     *
     * Maps to private ACLs in cloud storage; 0600/0700 on local filesystems.
     */
    case Private = 'private';

    /**
     * Creates a Visibility_Level from a legacy string constant.
     *
     * @param  string           $value One of {@see Visibility::PUBLIC} or {@see Visibility::PRIVATE}.
     * @return self
     * @throws \ValueError      When $value is not a recognised visibility string.
     * @since  3.2
     */
    public static function from_string(string $value): self
    {
        return self::from($value);
    }

    /**
     * Returns the plain string constant value compatible with adapter interfaces.
     *
     * Equivalent to reading `->value`, provided for discoverability.
     *
     * @return string 'public' or 'private'
     * @since  3.2
     */
    public function to_string(): string
    {
        return $this->value;
    }
}
