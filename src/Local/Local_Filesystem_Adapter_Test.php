<?php

declare (strict_types=1);
namespace League\Flysystem\Local;

use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function is_resource;
use function iterator_to_array;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Symbolic_Link_Encountered;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Read_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Unix_Visibility\Portable_Visibility_Converter;
use League\Flysystem\Unix_Visibility\Visibility_Converter;
use League\Flysystem\Visibility;
use League\Mime_Type_Detection\Empty_Extension_To_Mime_Type_Map;
use League\Mime_Type_Detection\Extension_Mime_Type_Detector;
use League\Mime_Type_Detection\Finfo_Mime_Type_Detector;
use const LOCK_EX;
use function mkdir;
use function strnatcasecmp;
use function symlink;
use Traversable;
use function usort;
/**
 * @group local
 */
class Local_Filesystem_Adapter_Test extends Filesystem_Adapter_Test_Case
{
    public const ROOT = __DIR__ . '/test-root';
    protected function set_up(): void
    {
        reset_function_mocks();
        delete_directory(static::ROOT);
    }
    protected function tear_down(): void
    {
        reset_function_mocks();
        delete_directory(static::ROOT);
    }
    /**
     * @test
     */
    public function creating_a_local_filesystem_creates_a_root_directory(): void
    {
        new Local_Filesystem_Adapter(static::ROOT);
        $this->assert_directory_exists(static::ROOT);
    }
    /**
     * @test
     */
    public function creating_a_local_filesystem_does_not_create_a_root_directory_when_constructed_with_lazy_root_creation(): void
    {
        new Local_Filesystem_Adapter(static::ROOT, lazyRootCreation: true);
        $this->assert_directory_does_not_exist(static::ROOT);
    }
    /**
     * @test
     */
    public function not_being_able_to_create_a_root_directory_results_in_an_exception(): void
    {
        $this->expect_exception(Unable_To_Create_Directory::class);
        new Local_Filesystem_Adapter('/cannot-create/this-directory/');
    }
    /**
     * @test
     *
     * @see https://github.com/thephpleague/flysystem/issues/1442
     */
    public function falling_back_to_extension_lookup_when_finding_mime_type_of_empty_file(): void
    {
        $this->given_we_have_an_existing_file('something.csv', '');
        $mime_type = $this->adapter()->mime_type('something.csv');
        self::assert_equals('text/csv', $mime_type->mime_type());
    }
    /**
     * @test
     */
    public function writing_a_file(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('/file.txt', 'contents', new Config());
        $this->assert_file_exists(static::ROOT . '/file.txt');
        $contents = file_get_contents(static::ROOT . '/file.txt');
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     */
    public function writing_a_file_with_a_stream(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $stream = stream_with_contents('contents');
        $adapter->write_stream('/file.txt', $stream, new Config());
        fclose($stream);
        $this->assert_file_exists(static::ROOT . '/file.txt');
        $contents = file_get_contents(static::ROOT . '/file.txt');
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     *
     * @see https://github.com/thephpleague/flysystem/issues/1606
     */
    public function deleting_a_file_during_contents_listing(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT, visibility: new class implements Visibility_Converter
        {
            private Visibility_Converter $visibility;
            public function __construct()
            {
                $this->visibility = new Portable_Visibility_Converter();
            }
            public function for_file(string $visibility): int
            {
                return $this->visibility->for_file($visibility);
            }
            public function for_directory(string $visibility): int
            {
                return $this->visibility->for_directory($visibility);
            }
            public function inverse_for_file(int $visibility): string
            {
                unlink(Local_Filesystem_Adapter_Test::ROOT . '/file-1.txt');
                return $this->visibility->inverse_for_file($visibility);
            }
            public function inverse_for_directory(int $visibility): string
            {
                return $this->visibility->inverse_for_directory($visibility);
            }
            public function default_for_directories(): int
            {
                return $this->visibility->default_for_directories();
            }
        });
        $filesystem = new Filesystem($adapter);
        $filesystem->write('/file-1.txt', 'something');
        $listing = $filesystem->list_contents('/')->to_array();
        self::assert_count(0, $listing);
    }
    /**
     * @test
     */
    public function writing_a_file_with_a_stream_and_visibility(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $stream = stream_with_contents('something');
        $adapter->write_stream('/file.txt', $stream, new Config(['visibility' => Visibility::PRIVATE]));
        fclose($stream);
        $this->assert_file_contains(static::ROOT . '/file.txt', 'something');
        $this->assert_file_has_permissions(static::ROOT . '/file.txt', 0600);
    }
    /**
     * @test
     */
    public function writing_a_file_with_visibility(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT, new Portable_Visibility_Converter());
        $adapter->write('/file.txt', 'contents', new Config(['visibility' => 'private']));
        $this->assert_file_contains(static::ROOT . '/file.txt', 'contents');
        $this->assert_file_has_permissions(static::ROOT . '/file.txt', 0600);
    }
    /**
     * @test
     */
    public function failing_to_set_visibility(): void
    {
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->set_visibility('/file.txt', Visibility::PUBLIC);
    }
    /**
     * @test
     */
    public function failing_to_write_a_file(): void
    {
        $this->expect_exception(Unable_To_Write_File::class);
        (new Local_Filesystem_Adapter('/'))->write('/cannot-create-a-file-here', 'contents', new Config());
    }
    /**
     * @test
     */
    public function failing_to_write_a_file_using_a_stream(): void
    {
        $this->expect_exception(Unable_To_Write_File::class);
        try {
            $stream = stream_with_contents('something');
            (new Local_Filesystem_Adapter('/'))->write_stream('/cannot-create-a-file-here', $stream, new Config());
        } finally {
            isset($stream) && is_resource($stream) && fclose($stream);
        }
    }
    /**
     * @test
     */
    public function deleting_a_file(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        file_put_contents(static::ROOT . '/file.txt', 'contents');
        $adapter->delete('/file.txt');
        $this->assert_file_does_not_exist(static::ROOT . '/file.txt');
    }
    /**
     * @test
     */
    public function deleting_a_file_that_does_not_exist(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->delete('/file.txt');
        $this->assert_true(true);
    }
    /**
     * @test
     */
    public function deleting_a_file_that_cannot_be_deleted(): void
    {
        $this->given_we_have_an_existing_file('here.txt');
        mock_function('unlink', false);
        $this->expect_exception(Unable_To_Delete_File::class);
        $this->adapter()->delete('here.txt');
    }
    /**
     * @test
     */
    public function checking_if_a_file_exists(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('/file.txt', 'contents', new Config());
        $this->assert_true($adapter->file_exists('/file.txt'));
    }
    /**
     * @test
     */
    public function checking_if_a_file_exists_that_does_not_exsist(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $this->assert_false($adapter->file_exists('/file.txt'));
    }
    /**
     * @test
     */
    public function listing_contents(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('directory/filename.txt', 'content', new Config());
        $adapter->write('filename.txt', 'content', new Config());
        /** @var Traversable $contentListing */
        $content_listing = $adapter->list_contents('/', false);
        $contents = iterator_to_array($content_listing);
        $this->assert_count(2, $contents);
        $this->assert_contains_only_instances_of(Storage_Attributes::class, $contents);
    }
    /**
     * @test
     */
    public function listing_contents_recursively(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('directory/filename.txt', 'content', new Config());
        $adapter->write('filename.txt', 'content', new Config());
        /** @var Traversable $contentListing */
        $content_listing = $adapter->list_contents('/', true);
        $contents = iterator_to_array($content_listing);
        $this->assert_count(3, $contents);
        $this->assert_contains_only_instances_of(Storage_Attributes::class, $contents);
    }
    /**
     * @test
     */
    public function listing_a_non_existing_directory(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        /** @var Traversable $contentListing */
        $content_listing = $adapter->list_contents('/directory/', false);
        $contents = iterator_to_array($content_listing);
        $this->assert_count(0, $contents);
    }
    /**
     * @test
     */
    public function listing_directory_contents_with_link_skipping(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT, null, LOCK_EX, Local_Filesystem_Adapter::SKIP_LINKS);
        $adapter->write('/file.txt', 'content', new Config());
        symlink(static::ROOT . '/file.txt', static::ROOT . '/link.txt');
        /** @var Traversable $contentListing */
        $content_listing = $adapter->list_contents('/', true);
        $contents = iterator_to_array($content_listing);
        $this->assert_count(1, $contents);
    }
    /**
     * @test
     */
    public function listing_directory_contents_with_disallowing_links(): void
    {
        $this->expect_exception(Symbolic_Link_Encountered::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT, null, LOCK_EX, Local_Filesystem_Adapter::DISALLOW_LINKS);
        file_put_contents(static::ROOT . '/file.txt', 'content');
        symlink(static::ROOT . '/file.txt', static::ROOT . '/link.txt');
        /** @var Traversable $contentListing */
        $content_listing = $adapter->list_contents('/', true);
        iterator_to_array($content_listing);
    }
    /**
     * @test
     */
    public function retrieving_visibility_while_listing_directory_contents(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->create_directory('public', new Config(['visibility' => 'public']));
        $adapter->create_directory('private', new Config(['visibility' => 'private']));
        $adapter->write('public/private.txt', 'private', new Config(['visibility' => 'private']));
        $adapter->write('private/public.txt', 'public', new Config(['visibility' => 'public']));
        /** @var Traversable<StorageAttributes> $contentListing */
        $content_listing = $adapter->list_contents('/', true);
        $listing = iterator_to_array($content_listing);
        usort($listing, fn(Storage_Attributes $a, Storage_Attributes $b) => strnatcasecmp($a->path(), $b->path()));
        /**
         * @var StorageAttributes $publicDirectoryAttributes
         * @var StorageAttributes $privateFileAttributes
         * @var StorageAttributes $privateDirectoryAttributes
         * @var StorageAttributes $publicFileAttributes
         */
        [$private_directory_attributes, $public_file_attributes, $public_directory_attributes, $private_file_attributes] = $listing;
        $this->assert_equals('public', $public_directory_attributes->visibility());
        $this->assert_equals('private', $private_file_attributes->visibility());
        $this->assert_equals('private', $private_directory_attributes->visibility());
        $this->assert_equals('public', $public_file_attributes->visibility());
    }
    /**
     * @test
     */
    public function deleting_a_directory(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        mkdir(static::ROOT . '/directory/subdir/', 0744, true);
        $this->assert_directory_exists(static::ROOT . '/directory/subdir/');
        file_put_contents(static::ROOT . '/directory/subdir/file.txt', 'content');
        symlink(static::ROOT . '/directory/subdir/file.txt', static::ROOT . '/directory/subdir/link.txt');
        $adapter->delete_directory('directory/subdir');
        $this->assert_directory_does_not_exist(static::ROOT . '/directory/subdir/');
        $adapter->delete_directory('directory');
        $this->assert_directory_does_not_exist(static::ROOT . '/directory/');
    }
    /**
     * @test
     */
    public function deleting_directories_with_other_directories_in_it(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('a/b/c/d/e.txt', 'contents', new Config());
        $adapter->delete_directory('a/b');
        $this->assert_directory_exists(static::ROOT . '/a');
        $this->assert_directory_does_not_exist(static::ROOT . '/a/b');
    }
    /**
     * @test
     */
    public function deleting_a_non_existing_directory(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->delete_directory('/non-existing-directory/');
        $this->assert_true(true);
    }
    /**
     * @test
     */
    public function not_being_able_to_delete_a_directory(): void
    {
        $this->expect_exception(Unable_To_Delete_Directory::class);
        mock_function('rmdir', false);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->create_directory('/etc/', new Config());
        $adapter->delete_directory('/etc/');
    }
    /**
     * @test
     */
    public function not_being_able_to_delete_a_sub_directory(): void
    {
        $this->expect_exception(Unable_To_Delete_Directory::class);
        mock_function('rmdir', false);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->create_directory('/etc/subdirectory/', new Config());
        $adapter->delete_directory('/etc/');
    }
    /**
     * @test
     */
    public function creating_a_directory(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->create_directory('public', new Config(['visibility' => 'public']));
        $this->assert_directory_exists(static::ROOT . '/public');
        $this->assert_file_has_permissions(static::ROOT . '/public', 0755);
        $adapter->create_directory('private', new Config(['visibility' => 'private']));
        $this->assert_directory_exists(static::ROOT . '/private');
        $this->assert_file_has_permissions(static::ROOT . '/private', 0700);
        $adapter->create_directory('also_private', new Config(['directory_visibility' => 'private']));
        $this->assert_directory_exists(static::ROOT . '/also_private');
        $this->assert_file_has_permissions(static::ROOT . '/also_private', 0700);
    }
    /**
     * @test
     */
    public function not_being_able_to_create_a_directory(): void
    {
        $this->expect_exception(Unable_To_Create_Directory::class);
        $adapter = new Local_Filesystem_Adapter('/');
        $adapter->create_directory('/something/', new Config());
    }
    /**
     * @test
     */
    public function creating_a_directory_is_idempotent(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->create_directory('/something/', new Config(['visibility' => 'private']));
        $this->assert_file_has_permissions(static::ROOT . '/something', 0700);
        $adapter->create_directory('/something/', new Config(['visibility' => 'public']));
        $this->assert_file_has_permissions(static::ROOT . '/something', 0755);
    }
    /**
     * @test
     */
    public function retrieving_visibility(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('public.txt', 'contents', new Config(['visibility' => 'public']));
        $this->assert_equals('public', $adapter->visibility('public.txt')->visibility());
        $adapter->write('private.txt', 'contents', new Config(['visibility' => 'private']));
        $this->assert_equals('private', $adapter->visibility('private.txt')->visibility());
    }
    /**
     * @test
     */
    public function not_being_able_to_retrieve_visibility(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->visibility('something.txt');
    }
    /**
     * @test
     */
    public function moving_a_file(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('first.txt', 'contents', new Config());
        $this->assert_file_exists(static::ROOT . '/first.txt');
        $adapter->move('first.txt', 'second.txt', new Config());
        $this->assert_file_exists(static::ROOT . '/second.txt');
        $this->assert_file_does_not_exist(static::ROOT . '/first.txt');
    }
    /**
     * @test
     */
    public function moving_a_file_with_visibility(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT, new Portable_Visibility_Converter());
        $adapter->write('first.txt', 'contents', new Config());
        $this->assert_file_exists(static::ROOT . '/first.txt');
        $this->assert_file_has_permissions(static::ROOT . '/first.txt', 0644);
        $adapter->move('first.txt', 'second.txt', new Config(['visibility' => 'private']));
        $this->assert_file_exists(static::ROOT . '/second.txt');
        $this->assert_file_has_permissions(static::ROOT . '/second.txt', 0600);
    }
    /**
     * @test
     */
    public function not_being_able_to_move_a_file(): void
    {
        $this->expect_exception(Unable_To_Move_File::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->move('first.txt', 'second.txt', new Config());
    }
    /**
     * @test
     */
    public function copying_a_file(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('first.txt', 'contents', new Config());
        $adapter->copy('first.txt', 'second.txt', new Config());
        $this->assert_file_exists(static::ROOT . '/second.txt');
        $this->assert_file_exists(static::ROOT . '/first.txt');
    }
    /**
     * @test
     */
    public function copying_a_file_with_visibility(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT, new Portable_Visibility_Converter());
        $adapter->write('first.txt', 'contents', new Config());
        $adapter->copy('first.txt', 'second.txt', new Config(['visibility' => 'private']));
        $this->assert_file_exists(static::ROOT . '/first.txt');
        $this->assert_file_has_permissions(static::ROOT . '/first.txt', 0644);
        $this->assert_file_exists(static::ROOT . '/second.txt');
        $this->assert_file_has_permissions(static::ROOT . '/second.txt', 0600);
    }
    /**
     * @test
     */
    public function copying_a_file_retaining_visibility(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT, new Portable_Visibility_Converter());
        $adapter->write('first.txt', 'contents', new Config(['visibility' => 'private']));
        $adapter->copy('first.txt', 'retain.txt', new Config());
        $adapter->copy('first.txt', 'do-not-retain.txt', new Config(['retain_visibility' => false]));
        $this->assert_file_exists(static::ROOT . '/first.txt');
        $this->assert_file_has_permissions(static::ROOT . '/first.txt', 0600);
        $this->assert_file_exists(static::ROOT . '/retain.txt');
        $this->assert_file_has_permissions(static::ROOT . '/retain.txt', 0600);
        $this->assert_file_exists(static::ROOT . '/do-not-retain.txt');
        $this->assert_file_has_permissions(static::ROOT . '/do-not-retain.txt', 0644);
    }
    /**
     * @test
     */
    public function not_being_able_to_copy_a_file(): void
    {
        $this->expect_exception(Unable_To_Copy_File::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->copy('first.txt', 'second.txt', new Config());
    }
    /**
     * @test
     */
    public function getting_mimetype(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('flysystem.svg', (string) file_get_contents(__DIR__ . '/../AdapterTestUtilities/test_files/flysystem.svg'), new Config());
        $this->assert_string_starts_with('image/svg+xml', $adapter->mime_type('flysystem.svg')->mime_type());
    }
    /**
     * @test
     */
    public function failing_to_get_the_mimetype(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('file.unknown', '', new Config());
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter->mime_type('file.unknown');
    }
    /**
     * @test
     */
    public function allowing_inconclusive_mime_type(): void
    {
        $adapter = new Local_Filesystem_Adapter(location: static::ROOT, useInconclusiveMimeTypeFallback: true);
        $adapter->write('file.unknown', '', new Config());
        $this->assert_equals('application/x-empty', $adapter->mime_type('file.unknown')->mime_type());
    }
    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->use_adapter(new Local_Filesystem_Adapter(self::ROOT, null, LOCK_EX, Local_Filesystem_Adapter::DISALLOW_LINKS, new Extension_Mime_Type_Detector(new Empty_Extension_To_Mime_Type_Map())));
        parent::fetching_unknown_mime_type_of_a_file();
    }
    /**
     * @test
     */
    public function not_being_able_to_get_mimetype(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = new Local_Filesystem_Adapter(location: static::ROOT, mimeTypeDetector: new Finfo_Mime_Type_Detector());
        $adapter->mime_type('flysystem.svg');
    }
    /**
     * @test
     */
    public function getting_last_modified(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('first.txt', 'contents', new Config());
        mock_function('filemtime', $now = time());
        $last_modified = $adapter->last_modified('first.txt')->last_modified();
        $this->assert_equals($now, $last_modified);
    }
    /**
     * @test
     */
    public function not_being_able_to_get_last_modified(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->last_modified('first.txt');
    }
    /**
     * @test
     */
    public function getting_file_size(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('first.txt', 'contents', new Config());
        $file_size = $adapter->file_size('first.txt');
        $this->assert_equals(8, $file_size->file_size());
    }
    /**
     * @test
     */
    public function not_being_able_to_get_file_size(): void
    {
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->file_size('first.txt');
    }
    /**
     * @test
     */
    public function reading_a_file(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('path.txt', 'contents', new Config());
        $contents = $adapter->read('path.txt');
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     */
    public function not_being_able_to_read_a_file(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->read('path.txt');
    }
    /**
     * @test
     */
    public function reading_a_stream(): void
    {
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->write('path.txt', 'contents', new Config());
        $contents = $adapter->read_stream('path.txt');
        $this->assert_is_resource($contents);
        $file_contents = stream_get_contents($contents);
        fclose($contents);
        $this->assert_equals('contents', $file_contents);
    }
    /**
     * @test
     */
    public function not_being_able_to_stream_read_a_file(): void
    {
        $this->expect_exception(Unable_To_Read_File::class);
        $adapter = new Local_Filesystem_Adapter(static::ROOT);
        $adapter->read_stream('path.txt');
    }
    /* //////////////////////
       // These are the utils //
       ////////////////////// */
    private function assert_file_has_permissions(string $file, int $expected_permissions): void
    {
        clearstatcache(false, $file);
        $permissions = fileperms($file) & 0777;
        $this->assert_equals($expected_permissions, $permissions);
    }
    private function assert_file_contains(string $file, string $expected_contents): void
    {
        $this->assert_file_exists($file);
        $contents = file_get_contents($file);
        $this->assert_equals($expected_contents, $contents);
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        return new Local_Filesystem_Adapter(static::ROOT);
    }
    /**
     * @test
     */
    public function get_checksum_with_specified_algo(): void
    {
        /** @var LocalFilesystemAdapter $adapter */
        $adapter = $this->adapter();
        $adapter->write('path.txt', 'foobar', new Config());
        $checksum = $adapter->checksum('path.txt', new Config(['checksum_algo' => 'crc32c']));
        $this->assert_same('0d5f5c7f', $checksum);
    }
}