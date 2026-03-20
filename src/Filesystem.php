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
class Filesystem implements Filesystem_Operator
{
    use Calculate_Checksum_From_Stream;
    private Config $config;
    private Path_Normalizer $path_normalizer;
    public function __construct(private Filesystem_Adapter $adapter, array $config = [], ?Path_Normalizer $path_normalizer = null, private ?Public_Url_Generator $public_url_generator = null, private ?Temporary_Url_Generator $temporary_url_generator = null)
    {
        $this->config = new Config($config);
        $this->path_normalizer = $path_normalizer ?? new Whitespace_Path_Normalizer();
    }
    public function file_exists(string $location): bool
    {
        return $this->adapter->file_exists($this->path_normalizer->normalize_path($location));
    }
    public function directory_exists(string $location): bool
    {
        return $this->adapter->directory_exists($this->path_normalizer->normalize_path($location));
    }
    public function has(string $location): bool
    {
        $path = $this->path_normalizer->normalize_path($location);
        if ($this->adapter->file_exists($path)) {
            return true;
        }
        return $this->adapter->directory_exists($path);
    }
    public function write(string $location, string $contents, array $config = []): void
    {
        $this->adapter->write($this->path_normalizer->normalize_path($location), $contents, $this->config->extend($config));
    }
    public function write_stream(string $location, $contents, array $config = []): void
    {
        /* @var resource $contents */
        $this->assert_is_resource($contents);
        $this->rewind_stream($contents);
        $this->adapter->write_stream($this->path_normalizer->normalize_path($location), $contents, $this->config->extend($config));
    }
    public function read(string $location): string
    {
        return $this->adapter->read($this->path_normalizer->normalize_path($location));
    }
    public function read_stream(string $location)
    {
        return $this->adapter->read_stream($this->path_normalizer->normalize_path($location));
    }
    public function delete(string $location): void
    {
        $this->adapter->delete($this->path_normalizer->normalize_path($location));
    }
    public function delete_directory(string $location): void
    {
        $this->adapter->delete_directory($this->path_normalizer->normalize_path($location));
    }
    public function create_directory(string $location, array $config = []): void
    {
        $this->adapter->create_directory($this->path_normalizer->normalize_path($location), $this->config->extend($config));
    }
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
    public function last_modified(string $path): int
    {
        return $this->adapter->last_modified($this->path_normalizer->normalize_path($path))->last_modified();
    }
    public function file_size(string $path): int
    {
        return $this->adapter->file_size($this->path_normalizer->normalize_path($path))->file_size();
    }
    public function mime_type(string $path): string
    {
        return $this->adapter->mime_type($this->path_normalizer->normalize_path($path))->mime_type();
    }
    public function set_visibility(string $path, string $visibility): void
    {
        $this->adapter->set_visibility($this->path_normalizer->normalize_path($path), $visibility);
    }
    public function visibility(string $path): string
    {
        return $this->adapter->visibility($this->path_normalizer->normalize_path($path))->visibility();
    }
    public function public_url(string $path, array $config = []): string
    {
        $this->public_url_generator ??= $this->resolve_public_url_generator() ?? throw Unable_To_Generate_Public_Url::no_generator_configured($path);
        $config = $this->config->extend($config);
        return $this->public_url_generator->public_url($this->path_normalizer->normalize_path($path), $config);
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, array $config = []): string
    {
        $generator = $this->temporary_url_generator ?? $this->adapter;
        if ($generator instanceof Temporary_Url_Generator) {
            return $generator->temporary_url($this->path_normalizer->normalize_path($path), $expires_at, $this->config->extend($config));
        }
        throw Unable_To_Generate_Temporary_Url::no_generator_configured($path);
    }
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