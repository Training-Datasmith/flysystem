<?php

declare (strict_types=1);
namespace League\Flysystem;

use function compact;
use DateTimeInterface;
use function method_exists;
use function sprintf;
use Throwable;
class Mount_Manager implements Filesystem_Operator
{
    /**
     * @var array<string, FilesystemOperator>
     */
    private array $filesystems = [];
    private \League\Flysystem\Config $config;
    /**
     * MountManager constructor.
     *
     * @param array<string,FilesystemOperator> $filesystems
     */
    public function __construct(array $filesystems = [], array $config = [])
    {
        $this->mount_filesystems($filesystems);
        $this->config = new Config($config);
    }
    /**
     * It is not recommended to mount filesystems after creation because interacting
     * with the Mount Manager becomes unpredictable. Use this as an escape hatch.
     */
    public function dangerously_mount_filesystems(string $key, Filesystem_Operator $filesystem): void
    {
        $this->mount_filesystem($key, $filesystem);
    }
    /**
     * @param array<string,FilesystemOperator> $filesystems
     */
    public function extend(array $filesystems, array $config = []): Mount_Manager
    {
        $clone = clone $this;
        $clone->config = $this->config->extend($config);
        $clone->mount_filesystems($filesystems);
        return $clone;
    }
    public function file_exists(string $location): bool
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->file_exists($path);
        } catch (Throwable $exception) {
            throw Unable_To_Check_File_Existence::for_location($location, $exception);
        }
    }
    public function has(string $location): bool
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            if ($filesystem->file_exists($path)) {
                return true;
            }
            return $filesystem->directory_exists($path);
        } catch (Throwable $exception) {
            throw Unable_To_Check_Existence::for_location($location, $exception);
        }
    }
    public function directory_exists(string $location): bool
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->directory_exists($path);
        } catch (Throwable $exception) {
            throw Unable_To_Check_Directory_Existence::for_location($location, $exception);
        }
    }
    public function read(string $location): string
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->read($path);
        } catch (Unable_To_Read_File $exception) {
            throw Unable_To_Read_File::from_location($location, $exception->reason(), $exception);
        }
    }
    public function read_stream(string $location)
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->read_stream($path);
        } catch (Unable_To_Read_File $exception) {
            throw Unable_To_Read_File::from_location($location, $exception->reason(), $exception);
        }
    }
    public function list_contents(string $location, bool $deep = self::LIST_SHALLOW): Directory_Listing
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path, $mount_identifier] = $this->determine_filesystem_and_path($location);
        return $filesystem->list_contents($path, $deep)->map(fn(Storage_Attributes $attributes) => $attributes->with_path(sprintf('%s://%s', $mount_identifier, $attributes->path())));
    }
    public function last_modified(string $location): int
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->last_modified($path);
        } catch (Unable_To_Retrieve_Metadata $exception) {
            throw Unable_To_Retrieve_Metadata::last_modified($location, $exception->reason(), $exception);
        }
    }
    public function file_size(string $location): int
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->file_size($path);
        } catch (Unable_To_Retrieve_Metadata $exception) {
            throw Unable_To_Retrieve_Metadata::file_size($location, $exception->reason(), $exception);
        }
    }
    public function mime_type(string $location): string
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            return $filesystem->mime_type($path);
        } catch (Unable_To_Retrieve_Metadata $exception) {
            throw Unable_To_Retrieve_Metadata::mime_type($location, $exception->reason(), $exception);
        }
    }
    public function visibility(string $path): string
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $location] = $this->determine_filesystem_and_path($path);
        try {
            return $filesystem->visibility($location);
        } catch (Unable_To_Retrieve_Metadata $exception) {
            throw Unable_To_Retrieve_Metadata::visibility($path, $exception->reason(), $exception);
        }
    }
    public function write(string $location, string $contents, array $config = []): void
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            $filesystem->write($path, $contents, $this->config->extend($config)->to_array());
        } catch (Unable_To_Write_File $exception) {
            throw Unable_To_Write_File::at_location($location, $exception->reason(), $exception);
        }
    }
    public function write_stream(string $location, $contents, array $config = []): void
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        $filesystem->write_stream($path, $contents, $this->config->extend($config)->to_array());
    }
    public function set_visibility(string $path, string $visibility): void
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($path);
        $filesystem->set_visibility($path, $visibility);
    }
    public function delete(string $location): void
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            $filesystem->delete($path);
        } catch (Unable_To_Delete_File $exception) {
            throw Unable_To_Delete_File::at_location($location, $exception->reason(), $exception);
        }
    }
    public function delete_directory(string $location): void
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            $filesystem->delete_directory($path);
        } catch (Unable_To_Delete_Directory $exception) {
            throw Unable_To_Delete_Directory::at_location($location, $exception->reason(), $exception);
        }
    }
    public function create_directory(string $location, array $config = []): void
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($location);
        try {
            $filesystem->create_directory($path, $this->config->extend($config)->to_array());
        } catch (Unable_To_Create_Directory $exception) {
            throw Unable_To_Create_Directory::due_to_failure($location, $exception);
        }
    }
    public function move(string $source, string $destination, array $config = []): void
    {
        /** @var FilesystemOperator $sourceFilesystem */
        /* @var FilesystemOperator $destinationFilesystem */
        [$source_filesystem, $source_path] = $this->determine_filesystem_and_path($source);
        [$destination_filesystem, $destination_path] = $this->determine_filesystem_and_path($destination);
        $source_filesystem === $destination_filesystem ? $this->move_in_the_same_filesystem($source_filesystem, $source_path, $destination_path, $source, $destination, $config) : $this->move_across_filesystems($source, $destination, $config);
    }
    public function copy(string $source, string $destination, array $config = []): void
    {
        /** @var FilesystemOperator $sourceFilesystem */
        /* @var FilesystemOperator $destinationFilesystem */
        [$source_filesystem, $source_path] = $this->determine_filesystem_and_path($source);
        [$destination_filesystem, $destination_path] = $this->determine_filesystem_and_path($destination);
        $source_filesystem === $destination_filesystem ? $this->copy_in_same_filesystem($source_filesystem, $source_path, $destination_path, $source, $destination, $config) : $this->copy_across_filesystem($source_filesystem, $source_path, $destination_filesystem, $destination_path, $source, $destination, $config);
    }
    public function public_url(string $path, array $config = []): string
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($path);
        if (!method_exists($filesystem, 'publicUrl')) {
            throw new Unable_To_Generate_Public_Url(sprintf('%s does not support generating public urls.', $filesystem::class), $path);
        }
        return $filesystem->public_url($path, $config);
    }
    public function temporary_url(string $path, DateTimeInterface $expires_at, array $config = []): string
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($path);
        if (!method_exists($filesystem, 'temporaryUrl')) {
            throw new Unable_To_Generate_Temporary_Url(sprintf('%s does not support generating public urls.', $filesystem::class), $path);
        }
        return $filesystem->temporary_url($path, $expires_at, $this->config->extend($config)->to_array());
    }
    public function checksum(string $path, array $config = []): string
    {
        /** @var FilesystemOperator $filesystem */
        [$filesystem, $path] = $this->determine_filesystem_and_path($path);
        if (!method_exists($filesystem, 'checksum')) {
            throw new Unable_To_Provide_Checksum(sprintf('%s does not support providing checksums.', $filesystem::class), $path);
        }
        return $filesystem->checksum($path, $this->config->extend($config)->to_array());
    }
    private function mount_filesystems(array $filesystems): void
    {
        foreach ($filesystems as $key => $filesystem) {
            $this->guard_against_invalid_mount($key, $filesystem);
            /* @var string $key */
            /* @var FilesystemOperator $filesystem */
            $this->mount_filesystem($key, $filesystem);
        }
    }
    private function guard_against_invalid_mount(mixed $key, mixed $filesystem): void
    {
        if (!is_string($key)) {
            throw Unable_To_Mount_Filesystem::because_the_key_is_not_valid($key);
        }
        if (!$filesystem instanceof Filesystem_Operator) {
            throw Unable_To_Mount_Filesystem::because_the_filesystem_was_not_valid($filesystem);
        }
    }
    private function mount_filesystem(string $key, Filesystem_Operator $filesystem): void
    {
        $this->filesystems[$key] = $filesystem;
    }
    /**
     * @return array{0:FilesystemOperator, 1:string, 2:string}
     */
    private function determine_filesystem_and_path(string $path): array
    {
        if (strpos($path, '://') < 1) {
            throw Unable_To_Resolve_Filesystem_Mount::because_the_separator_is_missing($path);
        }
        /** @var string $mountIdentifier */
        /** @var string $mountPath */
        [$mount_identifier, $mount_path] = explode('://', $path, 2);
        if (!array_key_exists($mount_identifier, $this->filesystems)) {
            throw Unable_To_Resolve_Filesystem_Mount::because_the_mount_was_not_registered($mount_identifier);
        }
        return [$this->filesystems[$mount_identifier], $mount_path, $mount_identifier];
    }
    private function copy_in_same_filesystem(Filesystem_Operator $source_filesystem, string $source_path, string $destination_path, string $source, string $destination, array $config): void
    {
        try {
            $source_filesystem->copy($source_path, $destination_path, $this->config->extend($config)->to_array());
        } catch (Unable_To_Copy_File $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    private function copy_across_filesystem(Filesystem_Operator $source_filesystem, string $source_path, Filesystem_Operator $destination_filesystem, string $destination_path, string $source, string $destination, array $config): void
    {
        $config = $this->config->extend($config);
        $retain_visibility = (bool) $config->get(Config::OPTION_RETAIN_VISIBILITY, true);
        $visibility = $config->get(Config::OPTION_VISIBILITY);
        try {
            if ($visibility == null && $retain_visibility) {
                $visibility = $source_filesystem->visibility($source_path);
                $config = $config->extend(compact('visibility'));
            }
            $stream = $source_filesystem->read_stream($source_path);
            $destination_filesystem->write_stream($destination_path, $stream, $config->to_array());
        } catch (Unable_To_Retrieve_Metadata|Unable_To_Read_File|Unable_To_Write_File $exception) {
            throw Unable_To_Copy_File::from_location_to($source, $destination, $exception);
        }
    }
    private function move_in_the_same_filesystem(Filesystem_Operator $source_filesystem, string $source_path, string $destination_path, string $source, string $destination, array $config): void
    {
        try {
            $source_filesystem->move($source_path, $destination_path, $this->config->extend($config)->to_array());
        } catch (Unable_To_Move_File $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
    }
    private function move_across_filesystems(string $source, string $destination, array $config = []): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Unable_To_Copy_File|Unable_To_Delete_File $exception) {
            throw Unable_To_Move_File::from_location_to($source, $destination, $exception);
        }
    }
}