<?php

declare (strict_types=1);
namespace League\Flysystem;

use function array_diff_key;
use function array_flip;
use function array_merge;
/**
 * Immutable configuration bag passed through the Filesystem API to adapters.
 *
 * Config objects are value objects: every method that would mutate state
 * returns a new instance instead. This makes it safe to pass a Config
 * through concurrent operations and middleware-like layers.
 *
 * @since 1.0
 */
class Config
{
    /**
     * Config key: behaviour when copy source and destination paths are identical.
     * Accepted values: {@see Resolve_Identical_Path_Conflict} enum cases.
     */
    public const OPTION_COPY_IDENTICAL_PATH = 'copy_destination_same_as_source';

    /**
     * Config key: behaviour when move source and destination paths are identical.
     * Accepted values: {@see Resolve_Identical_Path_Conflict} enum cases.
     */
    public const OPTION_MOVE_IDENTICAL_PATH = 'move_destination_same_as_source';

    /**
     * Config key: visibility setting for files.
     * Accepted values: {@see Visibility::PUBLIC}, {@see Visibility::PRIVATE}.
     */
    public const OPTION_VISIBILITY = 'visibility';

    /**
     * Config key: visibility setting applied when creating directories.
     * Accepted values: {@see Visibility::PUBLIC}, {@see Visibility::PRIVATE}.
     */
    public const OPTION_DIRECTORY_VISIBILITY = 'directory_visibility';

    /**
     * Config key: whether to copy file visibility during move/copy operations.
     * Accepted values: bool (true = retain original visibility).
     */
    public const OPTION_RETAIN_VISIBILITY = 'retain_visibility';

    /**
     * @param array<string, mixed> $options Initial option key-value pairs.
     * @since 1.0
     */
    public function __construct(private array $options = [])
    {
    }

    /**
     * Returns an option value, falling back to a default when the key is absent.
     *
     * @param  string $property The option key to retrieve.
     * @param  mixed  $default  Value to return when the key is not present.
     * @return mixed  The option value or $default.
     * @since  1.0
     */
    public function get(string $property, mixed $default = null): mixed
    {
        return $this->options[$property] ?? $default;
    }

    /**
     * Returns a new Config with $options merged on top of the current options.
     *
     * Keys present in $options overwrite existing keys.
     *
     * @param  array<string, mixed> $options Options to merge over the current set.
     * @return self New instance with merged options.
     * @since  1.0
     * @see    with_defaults() To only fill in keys that are not already set.
     */
    public function extend(array $options): Config
    {
        return new Config(array_merge($this->options, $options));
    }

    /**
     * Returns a new Config where $defaults fill in any keys not already present.
     *
     * Keys that already exist in the current config are NOT overwritten.
     *
     * @param  array<string, mixed> $defaults Fallback values for missing keys.
     * @return self New instance with defaults applied.
     * @since  1.0
     * @see    extend() To overwrite existing keys.
     */
    public function with_defaults(array $defaults): Config
    {
        return new Config($this->options + $defaults);
    }

    /**
     * Returns the underlying options as a plain associative array.
     *
     * @return array<string, mixed>
     * @since  1.0
     */
    public function to_array(): array
    {
        return $this->options;
    }

    /**
     * Returns a new Config with a single option added or overwritten.
     *
     * @param  string $property The option key to set.
     * @param  mixed  $setting  The value to assign.
     * @return self New instance with the option set.
     * @since  2.0
     */
    public function with_setting(string $property, mixed $setting): Config
    {
        return $this->extend([$property => $setting]);
    }

    /**
     * Returns a new Config with specific options removed.
     *
     * @param  string ...$settings Option keys to exclude from the new instance.
     * @return self New instance without the specified keys.
     * @since  2.0
     */
    public function without_settings(string ...$settings): Config
    {
        return new Config(array_diff_key($this->options, array_flip($settings)));
    }
}