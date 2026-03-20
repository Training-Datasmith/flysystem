<?php

declare (strict_types=1);
namespace League\Flysystem;

use function array_key_exists;
use DateTimeInterface;
use Generator;
use function is_array;
use League\Flysystem\Url_Generation\Prefix_Public_Url_Generator;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Url_Generation\Sharded_Prefix_Public_Url_Generator;
use League\Flysystem\Url_Generation\Temporary_Url_Generator;
use Throwable;
/**
 * Storage-agnostic filesystem facade.
 *
 * All path arguments are normalised and validated before being forwarded to the
 * underlying {@see Filesystem_Adapter}. Path traversal sequences (e.g. `../`)
 * are detected and cause a {@see Path_Traversal_Detected} exception.
 *
 * @since 1.0
 */
class Filesystem implements Filesystem_Operator
{
    use Calculate_Checksum_From_Stream;

    private Config $config;
    private Path_Normalizer $path_normalizer;

    /**
     * @param Filesystem_Adapter        $adapter               The storage backend (local, S3, SFTP, …).
     * @param array<string, mixed>      $config                Default configuration merged with every operation's config.
     *                                                          Supported keys: {@see Config} constants.
     * @param ?Path_Normalizer          $path_normalizer       Custom path normalizer; defaults to {@see Whitespace_Path_Normalizer}.
     * @param ?Public_Url_Generator     $public_url_generator  Optional override for public_url(); if null, falls back to config or adapter.
     * @param ?Temporary_Url_Generator  $temporary_url_generator Optional override for temporary_url(); if null, delegates to adapter.
     *
     * @since 1.0
     */
    public function __construct(private Filesystem_Adapter $adapter, array $config = [], ?Path_Normalizer $path_normalizer = null, private ?Public_Url_Generator $public_url_generator = null, private ?Temporary_Url_Generator $temporary_url_generator = null)
    {
        $this->config = new Config($config);
        $this->path_normalizer = $path_normalizer ?? new Whitespace_Path_Normalizer();
    }

    /**
     * Checks whether a file exists at the given location.
     *
     * @param  string $location Relative path to check (e.g. 'images/photo.jpg').
     * @return bool   True when a file (not a directory) exists at that path.
     * @throws \League\Flysystem\Path_Traversal_Detected When $location contains path traversal sequences.
     * @since  1.0
     */
    public function file_exists(string $location): bool
    {
        return $this->adapter->file_exists($this->path_normalizer->normalize_path($location));
    }

    /**
     * Checks whether a directory exists at the given location.
     *
     * @param  string $location Relative path to check (e.g. 'uploads/avatars').
     * @return bool   True when a directory exists at that path.
     * @since  2.0
     */
    public function directory_exists(string $location): bool
    {
        return $this->adapter->directory_exists($this->path_normalizer->normalize_path($location));
    }

    /**
     * Returns true if a file OR directory exists at the given location.
     *
     * @param  string $location Relative path to check.
     * @return bool   True when anything exists at that path.
     * @since  1.0
     * @see    file_exists()      To check specifically for a file.
     * @see    directory_exists() To check specifically for a directory.
     */
    public function has(string $location): bool
    {
        $path = $this->path_normalizer->normalize_path($location);
        if ($this->adapter->file_exists($path)) {
            return true;
        }
        return $this->adapter->directory_exists($path);
    }

    /**
     * Writes a string to a file, creating intermediate directories as needed.
     *
     * If the file already exists it is overwritten.
     *
     * @param  string               $location Path to the file to write (e.g. 'logs/app.log').
     * @param  string               $contents The raw string content to store.
     * @param  array<string, mixed> $config   Per-call overrides, e.g. ['visibility' => Visibility::PUBLIC].
     *
     * @throws \League\Flysystem\Unable_To_Write_File On adapter-level write failure.
     * @since  1.0
     * @see    write_stream() For streaming large files without buffering into memory.
     */
    public function write(string $location, string $contents, array $config = []): void
    {
        $this->adapter->write($this->path_normalizer->normalize_path($location), $contents, $this->config->extend($config));
    }

    /**
     * Writes a stream resource to a file.
     *
     * The stream is rewound before writing if it is seekable.
     * Intermediate directories are created automatically.
     *
     * @param  string               $location Path to the file to write.
     * @param  resource             $contents A readable PHP stream resource.
     * @param  array<string, mixed> $config   Per-call overrides.
     *
     * @throws \League\Flysystem\Invalid_Stream_Provided When $contents is not a stream resource.
     * @throws \League\Flysystem\Unable_To_Write_File    On adapter-level write failure.
     * @since  1.0
     */
    public function write_stream(string $location, $contents, array $config = []): void
    {
        /* @var resource $contents */
        $this->assert_is_resource($contents);
        $this->rewind_stream($contents);
        $this->adapter->write_stream($this->path_normalizer->normalize_path($location), $contents, $this->config->extend($config));
    }

    /**
     * Reads a file and returns its full contents as a string.
     *
     * @param  string $location Relative path to the file.
     * @return string The raw file contents.
     *
     * @throws \League\Flysystem\Unable_To_Read_File When the file cannot be read.
     * @since  1.0
     * @see    read_stream() For large files that should not be loaded into memory.
     */
    public function read(string $location): string
    {
        return $this->adapter->read($this->path_normalizer->normalize_path($location));
    }

    /**
     * Opens a file and returns a readable stream resource.
     *
     * The caller is responsible for closing the returned stream.
     *
     * @param  string   $location Relative path to the file.
     * @return resource A readable PHP stream resource. Must be closed by the caller.
     *
     * @throws \League\Flysystem\Unable_To_Read_File When the file cannot be opened.
     * @since  1.0
     */
    public function read_stream(string $location)
    {
        return $this->adapter->read_stream($this->path_normalizer->normalize_path($location));
    }

    /**
     * Deletes a file.
     *
     * Silently succeeds if the file does not exist.
     *
     * @param string $location Relative path to the file to delete.
     * @throws \League\Flysystem\Unable_To_Delete_File On adapter-level deletion failure.
     * @since  1.0
     */
    public function delete(string $location): void
    {
        $this->adapter->delete($this->path_normalizer->normalize_path($location));
    }

    /**
     * Recursively deletes a directory and all its contents.
     *
     * Silently succeeds if the directory does not exist.
     *
     * @param string $location Relative path to the directory to delete.
     * @throws \League\Flysystem\Unable_To_Delete_Directory On adapter-level failure.
     * @since  1.0
     */
    public function delete_directory(string $location): void
    {
        $this->adapter->delete_directory($this->path_normalizer->normalize_path($location));
    }

    /**
     * Creates a directory, including all intermediate parent directories.
     *
     * Silently succeeds if the directory already exists.
     *
     * @param string               $location Relative path to the directory to create.
     * @param array<string, mixed> $config   Per-call overrides (e.g. visibility).
     *
     * @throws \League\Flysystem\Unable_To_Create_Directory On adapter-level failure.
     * @since  1.0
     */
    public function create_directory(string $location, array $config = []): void
    {
        $this->adapter->create_directory($this->path_normalizer->normalize_path($location), $this->config->extend($config));
    }

    /**
     * Lists the contents of a directory, optionally recursing into subdirectories.
     *
     * Returns a lazy {@see Directory_Listing} that yields {@see File_Attributes}
     * and {@see Directory_Attributes} objects. The listing is not fetched until
     * iterated.
     *
     * @param  string $location Relative path to the directory (empty string for root).
     * @param  bool   $deep     True to recurse into subdirectories; false for shallow listing.
     *
     * @return Directory_Listing Lazy iterable of storage attributes.
     * @since  1.0
     * @complexity O(n) where n is the number of entries in the directory tree.
     */
    public function list_contents(string $location, bool $deep = self::LIST_SHALLOW): Directory_Listing
    {
        $path = $this->path_normalizer->normalize_path($location);
        $listing = $this->adapter->list_contents($path, $deep);
        return new Directory_Listing($this->pipe_listing($location, $deep, $listing));
    }
    private function pipe_listing(string $location, bool $deep, iterable $listing): Generator
    {
        try {
            foreach ($listing as $item) {
                yield $item;
            }
        } catch (Throwable $exception) {
            throw Unable_To_List_Contents::at_location($location, $deep, $exception);
        }
    }
    /**
     * Moves a file from one location to another.
     *
     * Visibility is retained by default (configurable via {@see Config::OPTION_RETAIN_VISIBILITY}).
     * If source and destination normalise to the same path, behaviour depends on
     * {@see Config::OPTION_MOVE_IDENTICAL_PATH}: TRY (default), FAIL, or IGNORE.
     *
     * @param string               $source      Relative path of the file to move.
     * @param string               $destination Relative path of the new location.
     * @param array<string, mixed> $config      Per-call overrides.
     *
     * @throws \League\Flysystem\Unable_To_Move_File When source and destination are the same and
     *                                                OPTION_MOVE_IDENTICAL_PATH is FAIL, or on adapter failure.
     * @since 1.0
     * @see   copy() To keep the original file intact.
     */
    public function move(string $source, string $destination, array $config = []): void
    {
        $config = $this->resolve_config_for_move_and_copy($config);
        $from = $this->path_normalizer->normalize_path($source);
        $to = $this->path_normalizer->normalize_path($destination);
        if ($from === $to) {
            $resolution_strategy = $config->get(Config::OPTION_MOVE_IDENTICAL_PATH, Resolve_Identical_Path_Conflict::TRY);
            if ($resolution_strategy === Resolve_Identical_Path_Conflict::FAIL) {
                throw Unable_To_Move_File::source_and_destination_are_the_same($source, $destination);
            }
            if ($resolution_strategy === Resolve_Identical_Path_Conflict::IGNORE) {
                return;
            }
        }
        $this->adapter->move($from, $to, $config);
    }
    /**
     * Copies a file from one location to another.
     *
     * The original file is left intact. Visibility handling mirrors {@see move()}.
     *
     * @param string               $source      Relative path of the source file.
     * @param string               $destination Relative path of the copy destination.
     * @param array<string, mixed> $config      Per-call overrides.
     *
     * @throws \League\Flysystem\Unable_To_Copy_File On adapter failure.
     * @since  1.0
     */
    public function copy(string $source, string $destination, array $config = []): void
    {
        $config = $this->resolve_config_for_move_and_copy($config);
        $from = $this->path_normalizer->normalize_path($source);
        $to = $this->path_normalizer->normalize_path($destination);
        if ($from === $to) {
            $resolution_strategy = $config->get(Config::OPTION_COPY_IDENTICAL_PATH, Resolve_Identical_Path_Conflict::TRY);
            if ($resolution_strategy === Resolve_Identical_Path_Conflict::FAIL) {
                throw Unable_To_Copy_File::source_and_destination_are_the_same($source, $destination);
            }
            if ($resolution_strategy === Resolve_Identical_Path_Conflict::IGNORE) {
                return;
            }
        }
        $this->adapter->copy($from, $to, $config);
    }
    /**
     * Returns the last-modified timestamp of a file.
     *
     * @param  string $path Relative path to the file.
     * @return int    Unix timestamp (seconds since epoch).
     *
     * @throws \League\Flysystem\Unable_To_Retrieve_Metadata When metadata cannot be determined.
     * @since  1.0
     */
    public function last_modified(string $path): int
    {
        return $this->adapter->last_modified($this->path_normalizer->normalize_path($path))->last_modified();
    }

    /**
     * Returns the size of a file in bytes.
     *
     * @param  string $path Relative path to the file.
     * @return int    File size in bytes.
     *
     * @throws \League\Flysystem\Unable_To_Retrieve_Metadata When the size cannot be determined.
     * @since  1.0
     */
    public function file_size(string $path): int
    {
        return $this->adapter->file_size($this->path_normalizer->normalize_path($path))->file_size();
    }

    /**
     * Returns the MIME type of a file.
     *
     * Detection strategy is adapter-dependent (magic bytes, file extension, etc.).
     *
     * @param  string $path Relative path to the file.
     * @return string MIME type string (e.g. 'image/jpeg', 'text/plain').
     *
     * @throws \League\Flysystem\Unable_To_Retrieve_Metadata When the MIME type cannot be determined.
     * @since  1.0
     */
    public function mime_type(string $path): string
    {
        return $this->adapter->mime_type($this->path_normalizer->normalize_path($path))->mime_type();
    }

    /**
     * Sets the visibility of a file.
     *
     * @param string $path       Relative path to the file.
     * @param string $visibility One of {@see Visibility::PUBLIC} or {@see Visibility::PRIVATE}.
     *
     * @throws \League\Flysystem\Invalid_Visibility_Provided When $visibility is not a recognised value.
     * @throws \League\Flysystem\Unable_To_Set_Visibility    On adapter-level failure.
     * @since  1.0
     */
    public function set_visibility(string $path, string $visibility): void
    {
        $this->adapter->set_visibility($this->path_normalizer->normalize_path($path), $visibility);
    }

    /**
     * Returns the visibility of a file.
     *
     * @param  string $path Relative path to the file.
     * @return string {@see Visibility::PUBLIC} or {@see Visibility::PRIVATE}.
     *
     * @throws \League\Flysystem\Unable_To_Retrieve_Metadata When visibility cannot be determined.
     * @since  1.0
     */
    public function visibility(string $path): string
    {
        return $this->adapter->visibility($this->path_normalizer->normalize_path($path))->visibility();
    }

    /**
     * Generates a publicly accessible URL for a file.
     *
     * Requires either a Public_Url_Generator to be injected, a 'public_url' config
     * key to be set, or the adapter to implement {@see Public_Url_Generator}.
     *
     * @param  string               $path   Relative path to the file.
     * @param  array<string, mixed> $config Per-call overrides (e.g. CDN domain).
     * @return string The fully-qualified public URL.
     *
     * @throws \League\Flysystem\Unable_To_Generate_Public_Url When no URL generator is configured.
     * @since  3.0
     */
    public function public_url(string $path, array $config = []): string
    {
        $this->public_url_generator ??= $this->resolve_public_url_generator() ?? throw Unable_To_Generate_Public_Url::no_generator_configured($path);
        $config = $this->config->extend($config);
        return $this->public_url_generator->public_url($this->path_normalizer->normalize_path($path), $config);
    }
    /**
     * Generates a time-limited URL for accessing a (possibly private) file.
     *
     * @param  string               $path       Relative path to the file.
     * @param  DateTimeInterface    $expires_at When the URL should expire.
     * @param  array<string, mixed> $config     Per-call overrides.
     * @return string The pre-signed / time-limited URL.
     *
     * @throws \League\Flysystem\Unable_To_Generate_Temporary_Url When no generator is available.
     * @since  3.0
     */
    public function temporary_url(string $path, DateTimeInterface $expires_at, array $config = []): string
    {
        $generator = $this->temporary_url_generator ?? $this->adapter;
        if ($generator instanceof Temporary_Url_Generator) {
            return $generator->temporary_url($this->path_normalizer->normalize_path($path), $expires_at, $this->config->extend($config));
        }
        throw Unable_To_Generate_Temporary_Url::no_generator_configured($path);
    }
    /**
     * Computes a checksum of the file at the given path.
     *
     * The checksum algorithm can be specified via $config['checksum_algo']
     * (e.g. 'md5', 'sha256'). Defaults to the adapter's preferred algorithm.
     *
     * Falls back to streaming the file and hashing locally when the adapter
     * does not support {@see Checksum_Provider} or when the requested algorithm
     * is not supported by the adapter.
     *
     * @param  string               $path   Relative path to the file.
     * @param  array<string, mixed> $config Per-call overrides; use 'checksum_algo' to specify algorithm.
     * @return string The hex-encoded checksum.
     *
     * @throws \League\Flysystem\Unable_To_Provide_Checksum On failure.
     * @since  3.0
     * @complexity O(n) in the file size when falling back to stream hashing.
     */
    public function checksum(string $path, array $config = []): string
    {
        $config = $this->config->extend($config);
        if (!$this->adapter instanceof Checksum_Provider) {
            return $this->calculate_checksum_from_stream($path, $config);
        }
        try {
            return $this->adapter->checksum($this->path_normalizer->normalize_path($path), $config);
        } catch (Checksum_Algo_Is_Not_Supported) {
            return $this->calculate_checksum_from_stream($this->path_normalizer->normalize_path($path), $config);
        }
    }
    private function resolve_public_url_generator(): ?Public_Url_Generator
    {
        if ($public_url = $this->config->get('public_url')) {
            return match (true) {
                is_array($public_url) => new Sharded_Prefix_Public_Url_Generator($public_url),
                default => new Prefix_Public_Url_Generator($public_url),
            };
        }
        if ($this->adapter instanceof Public_Url_Generator) {
            return $this->adapter;
        }
        return null;
    }
    /**
     * @param mixed $contents
     */
    private function assert_is_resource($contents): void
    {
        if (is_resource($contents) === false) {
            throw new Invalid_Stream_Provided('Invalid stream provided, expected stream resource, received ' . gettype($contents));
        }
        if ($type = get_resource_type($contents) !== 'stream') {
            throw new Invalid_Stream_Provided('Invalid stream provided, expected stream resource, received resource of type ' . $type);
        }
    }
    /**
     * @param resource $resource
     */
    private function rewind_stream($resource): void
    {
        if (ftell($resource) !== 0 && stream_get_meta_data($resource)['seekable']) {
            rewind($resource);
        }
    }
    private function resolve_config_for_move_and_copy(array $config): Config
    {
        $retain_visibility = $this->config->get(Config::OPTION_RETAIN_VISIBILITY, $config[Config::OPTION_RETAIN_VISIBILITY] ?? true);
        $full_config = $this->config->extend($config);
        /*
         * By default, we retain visibility. When we do not retain visibility, the visibility setting
         * from the default configuration is ignored. Only when it is set explicitly, we propagate the
         * setting.
         */
        if ($retain_visibility && !array_key_exists(Config::OPTION_VISIBILITY, $config)) {
            return $full_config->without_settings(Config::OPTION_VISIBILITY)->extend($config);
        }
        return $full_config;
    }
}