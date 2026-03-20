<?php

declare (strict_types=1);
namespace League\Flysystem;

use function fclose;
use function is_resource;
use League\Flysystem\Adapter_Test_Utilities\Exception_Throwing_Filesystem_Adapter;
use League\Flysystem\In_Memory\In_Memory_Filesystem_Adapter;
use Php_Unit\Framework\Test_Case;
use function stream_get_contents;
use function tmpfile;
/**
 * @group core
 */
class Mount_Manager_Test extends Test_Case
{
    private \League\Flysystem\Adapter_Test_Utilities\Exception_Throwing_Filesystem_Adapter $first_stub_adapter;
    private \League\Flysystem\Adapter_Test_Utilities\Exception_Throwing_Filesystem_Adapter $second_stub_adapter;
    private \League\Flysystem\Mount_Manager $mount_manager;
    private ?\League\Flysystem\Filesystem $first_filesystem = null;
    private ?\League\Flysystem\Filesystem $second_filesystem = null;
    protected function set_up(): void
    {
        $first_filesystem_adapter = new In_Memory_Filesystem_Adapter();
        $second_filesystem_adapter = new In_Memory_Filesystem_Adapter();
        $this->first_stub_adapter = new Exception_Throwing_Filesystem_Adapter($first_filesystem_adapter);
        $this->second_stub_adapter = new Exception_Throwing_Filesystem_Adapter($second_filesystem_adapter);
        $this->mount_manager = new Mount_Manager(['first' => $this->first_filesystem = new Filesystem($this->first_stub_adapter), 'second' => $this->second_filesystem = new Filesystem($this->second_stub_adapter)]);
    }
    /**
     * @test
     */
    public function copying_without_retaining_visibility(): void
    {
        // arrange
        $first_filesystem_adapter = new In_Memory_Filesystem_Adapter();
        $second_filesystem_adapter = new In_Memory_Filesystem_Adapter();
        $mount_manager = new Mount_Manager(['first' => new Filesystem($first_filesystem_adapter, ['visibility' => 'public']), 'second' => new Filesystem($second_filesystem_adapter, ['visibility' => 'private'])], ['retain_visibility' => false]);
        // act
        $mount_manager->write('first://file.txt', 'contents');
        $mount_manager->copy('first://file.txt', 'second://file.txt');
        // assert
        $visibility = $mount_manager->visibility('second://file.txt');
        self::assert_equals('private', $visibility);
    }
    /**
     * @test
     */
    public function extending_without_new_mounts_is_equal_but_not_the_same(): void
    {
        $mount_manager = $this->mount_manager->extend([]);
        $this->assert_not_same($this->mount_manager, $mount_manager);
        $this->assert_equals($this->mount_manager, $mount_manager);
    }
    /**
     * @test
     */
    public function extending_with_new_mounts_is_not_equal(): void
    {
        $mount_manager = $this->mount_manager->extend(['third' => new Filesystem(new In_Memory_Filesystem_Adapter())]);
        $this->assert_not_equals($this->mount_manager, $mount_manager);
    }
    /**
     * @test
     */
    public function extending_exposes_a_usable_mount_on_the_extension(): void
    {
        $mount_manager = $this->mount_manager->extend(['third' => new Filesystem(new In_Memory_Filesystem_Adapter())]);
        $mount_manager->write('third://path.txt', 'this');
        $contents = $mount_manager->read('third://path.txt');
        $this->assert_equals('this', $contents);
    }
    /**
     * @test
     */
    public function extending_does_not_mount_on_the_original_mount_manager(): void
    {
        $this->mount_manager->extend(['third' => new Filesystem(new In_Memory_Filesystem_Adapter())]);
        $this->expect_exception(Unable_To_Resolve_Filesystem_Mount::class);
        $this->mount_manager->write('third://path.txt', 'this');
    }
    /**
     * @test
     */
    public function copying_while_retaining_visibility(): void
    {
        // arrange
        $first_filesystem_adapter = new In_Memory_Filesystem_Adapter();
        $second_filesystem_adapter = new In_Memory_Filesystem_Adapter();
        $mount_manager = new Mount_Manager(['first' => new Filesystem($first_filesystem_adapter, ['visibility' => 'public']), 'second' => new Filesystem($second_filesystem_adapter, ['visibility' => 'private'])], ['retain_visibility' => true]);
        // act
        $mount_manager->write('first://file.txt', 'contents');
        $mount_manager->copy('first://file.txt', 'second://file.txt');
        // assert
        $visibility = $mount_manager->visibility('second://file.txt');
        self::assert_equals('public', $visibility);
    }
    /**
     * @test
     */
    public function writing_a_file(): void
    {
        $this->mount_manager->write('first://file.txt', 'content');
        $this->mount_manager->write('second://another-file.txt', 'content');
        $this->assert_true($this->first_filesystem->file_exists('file.txt'));
        $this->assert_false($this->second_filesystem->file_exists('file.txt'));
        $this->assert_false($this->first_filesystem->file_exists('another-file.txt'));
        $this->assert_true($this->second_filesystem->file_exists('another-file.txt'));
    }
    /**
     * @test
     */
    public function writing_a_file_with_a_stream(): void
    {
        $stream = stream_with_contents('contents');
        $this->mount_manager->write_stream('first://location.txt', $stream);
        $this->assert_true($this->first_filesystem->file_exists('location.txt'));
        $this->assert_equals('contents', $this->first_filesystem->read('location.txt'));
    }
    /**
     * @test
     */
    public function not_being_able_to_write_a_file(): void
    {
        $this->first_stub_adapter->stage_exception('write', 'file.txt', Unable_To_Write_File::at_location('file.txt'));
        $this->expect_exception(Unable_To_Write_File::class);
        $this->mount_manager->write('first://file.txt', 'content');
    }
    /**
     * @test
     */
    public function not_being_able_to_stream_write_a_file(): void
    {
        $handle = tmpfile();
        $this->first_stub_adapter->stage_exception('writeStream', 'file.txt', Unable_To_Write_File::at_location('file.txt'));
        $this->expect_exception(Unable_To_Write_File::class);
        try {
            $this->mount_manager->write_stream('first://file.txt', $handle);
        } finally {
            is_resource($handle) && fclose($handle);
        }
    }
    /**
     * @description This test method is so ugly, but I don't have the energy to create a nice test for every single one of these method.
     *
     * @test
     *
     * @dataProvider dpMetadataRetrieverMethods
     */
    public function failing_a_one_param_method(string $method, Filesystem_Operation_Failed $exception): void
    {
        $this->first_stub_adapter->stage_exception($method, 'location.txt', $exception);
        $this->expect_exception($exception::class);
        $this->mount_manager->{$method}('first://location.txt');
    }
    public static function dp_metadata_retriever_methods(): iterable
    {
        yield 'mimeType' => ['mimeType', Unable_To_Retrieve_Metadata::mime_type('location.txt')];
        yield 'fileSize' => ['fileSize', Unable_To_Retrieve_Metadata::file_size('location.txt')];
        yield 'lastModified' => ['lastModified', Unable_To_Retrieve_Metadata::last_modified('location.txt')];
        yield 'visibility' => ['visibility', Unable_To_Retrieve_Metadata::visibility('location.txt')];
        yield 'delete' => ['delete', Unable_To_Delete_File::at_location('location.txt')];
        yield 'deleteDirectory' => ['deleteDirectory', Unable_To_Delete_Directory::at_location('location.txt')];
        yield 'createDirectory' => ['createDirectory', Unable_To_Create_Directory::at_location('location.txt')];
        yield 'read' => ['read', Unable_To_Read_File::from_location('location.txt')];
        yield 'readStream' => ['readStream', Unable_To_Read_File::from_location('location.txt')];
        yield 'fileExists' => ['fileExists', Unable_To_Check_File_Existence::for_location('location.txt')];
    }
    /**
     * @test
     */
    public function reading_a_file(): void
    {
        $this->second_filesystem->write('location.txt', 'contents');
        $contents = $this->mount_manager->read('second://location.txt');
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     */
    public function reading_a_file_as_a_stream(): void
    {
        $this->second_filesystem->write('location.txt', 'contents');
        $handle = $this->mount_manager->read_stream('second://location.txt');
        $contents = stream_get_contents($handle);
        fclose($handle);
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_existing_file(): void
    {
        $this->second_filesystem->write('location.txt', 'contents');
        $existence = $this->mount_manager->file_exists('second://location.txt');
        $this->assert_true($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_non_existing_file(): void
    {
        $existence = $this->mount_manager->file_exists('second://location.txt');
        $this->assert_false($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_non_existing_directory(): void
    {
        $existence = $this->mount_manager->directory_exists('second://some-directory');
        $this->assert_false($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_existing_directory(): void
    {
        $this->second_filesystem->write('nested/location.txt', 'contents');
        $existence = $this->mount_manager->directory_exists('second://nested');
        $this->assert_true($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_existing_file_using_has(): void
    {
        $this->second_filesystem->write('location.txt', 'contents');
        $existence = $this->mount_manager->has('second://location.txt');
        $this->assert_true($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_non_existing_file_using_has(): void
    {
        $existence = $this->mount_manager->has('second://location.txt');
        $this->assert_false($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_non_existing_directory_using_has(): void
    {
        $existence = $this->mount_manager->has('second://some-directory');
        $this->assert_false($existence);
    }
    /**
     * @test
     */
    public function checking_existence_for_an_existing_directory_using_has(): void
    {
        $this->second_filesystem->write('nested/location.txt', 'contents');
        $existence = $this->mount_manager->has('second://nested');
        $this->assert_true($existence);
    }
    /**
     * @test
     */
    public function deleting_a_file(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->mount_manager->delete('first://location.txt');
        $this->assert_false($this->first_filesystem->file_exists('location.txt'));
    }
    /**
     * @test
     */
    public function deleting_a_directory(): void
    {
        $this->first_filesystem->write('dirname/location.txt', 'contents');
        $this->mount_manager->delete_directory('first://dirname');
        $this->assert_false($this->first_filesystem->file_exists('dirname/location.txt'));
    }
    /**
     * @test
     */
    public function setting_visibility(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->first_filesystem->set_visibility('location.txt', Visibility::PRIVATE);
        $this->mount_manager->set_visibility('first://location.txt', Visibility::PUBLIC);
        $this->assert_equals(Visibility::PUBLIC, $this->first_filesystem->visibility('location.txt'));
    }
    /**
     * @test
     */
    public function retrieving_metadata(): void
    {
        $now = time();
        $this->first_filesystem->write('location.txt', 'contents');
        $last_modified = $this->mount_manager->last_modified('first://location.txt');
        $file_size = $this->mount_manager->file_size('first://location.txt');
        $mime_type = $this->mount_manager->mime_type('first://location.txt');
        $this->assert_greater_than_or_equal($now, $last_modified);
        $this->assert_equals(8, $file_size);
        $this->assert_equals('text/plain', $mime_type);
    }
    /**
     * @test
     */
    public function creating_a_directory(): void
    {
        $this->mount_manager->create_directory('first://directory');
        $directory_listing = $this->first_filesystem->list_contents('/')->to_array();
        $this->assert_count(1, $directory_listing);
        /** @var DirectoryAttributes $directory */
        $directory = $directory_listing[0];
        $this->assert_instance_of(Directory_Attributes::class, $directory);
        $this->assert_equals('directory', $directory->path());
    }
    /**
     * @test
     */
    public function list_directory(): void
    {
        $this->mount_manager->create_directory('first://directory');
        $this->mount_manager->write('first://directory/file', 'foo');
        $directory_listing = $this->mount_manager->list_contents('first://', Filesystem::LIST_DEEP)->to_array();
        $this->assert_count(2, $directory_listing);
        /** @var DirectoryAttributes $directory */
        $directory = $directory_listing[0];
        $this->assert_instance_of(Directory_Attributes::class, $directory);
        $this->assert_equals('first://directory', $directory->path());
        /** @var FileAttributes $file */
        $file = $directory_listing[1];
        $this->assert_instance_of(File_Attributes::class, $file);
        $this->assert_equals('first://directory/file', $file->path());
    }
    /**
     * @test
     */
    public function copying_in_the_same_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->assert_true($this->first_filesystem->file_exists('location.txt'));
        $this->mount_manager->copy('first://location.txt', 'first://new-location.txt');
        $this->assert_true($this->first_filesystem->file_exists('location.txt'));
        $this->assert_true($this->first_filesystem->file_exists('new-location.txt'));
    }
    /**
     * @test
     */
    public function failing_to_copy_in_the_same_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->first_stub_adapter->stage_exception('copy', 'location.txt', Unable_To_Copy_File::from_location_to('a', 'b'));
        $this->expect_exception(Unable_To_Copy_File::class);
        $this->mount_manager->copy('first://location.txt', 'first://new-location.txt');
    }
    /**
     * @test
     */
    public function failing_to_move_in_the_same_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->first_stub_adapter->stage_exception('move', 'location.txt', Unable_To_Move_File::from_location_to('a', 'b'));
        $this->expect_exception(Unable_To_Move_File::class);
        $this->mount_manager->move('first://location.txt', 'first://new-location.txt');
    }
    /**
     * @test
     */
    public function moving_in_the_same_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->assert_true($this->first_filesystem->file_exists('location.txt'));
        $this->mount_manager->move('first://location.txt', 'first://new-location.txt');
        $this->assert_false($this->first_filesystem->file_exists('location.txt'));
        $this->assert_true($this->first_filesystem->file_exists('new-location.txt'));
    }
    /**
     * @test
     */
    public function moving_across_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->assert_true($this->first_filesystem->file_exists('location.txt'));
        $this->mount_manager->move('first://location.txt', 'second://new-location.txt');
        $this->assert_false($this->first_filesystem->file_exists('location.txt'));
        $this->assert_true($this->second_filesystem->file_exists('new-location.txt'));
    }
    /**
     * @test
     */
    public function failing_to_move_across_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->first_stub_adapter->stage_exception('visibility', 'location.txt', Unable_To_Retrieve_Metadata::visibility('location.txt'));
        $this->expect_exception(Unable_To_Move_File::class);
        $this->mount_manager->move('first://location.txt', 'second://new-location.txt');
    }
    /**
     * @test
     */
    public function failing_to_copy_across_filesystem(): void
    {
        $this->first_filesystem->write('location.txt', 'contents');
        $this->first_stub_adapter->stage_exception('visibility', 'location.txt', Unable_To_Retrieve_Metadata::visibility('location.txt'));
        $this->expect_exception(Unable_To_Copy_File::class);
        $this->mount_manager->copy('first://location.txt', 'second://new-location.txt');
    }
    /**
     * @test
     */
    public function listing_contents(): void
    {
        $this->first_filesystem->write('contents.txt', 'file contents');
        $this->first_filesystem->write('dirname/contents.txt', 'file contents');
        $this->second_filesystem->write('dirname/contents.txt', 'file contents');
        $contents = $this->mount_manager->list_contents('first://', Filesystem_Reader::LIST_DEEP)->to_array();
        $this->assert_count(3, $contents);
    }
    /**
     * @test
     */
    public function dangerously_mounting_additional_filesystems(): void
    {
        $this->first_filesystem->write('contents.txt', 'file contents');
        $this->mount_manager->dangerously_mount_filesystems('unknown', $this->first_filesystem);
        $this->assert_true($this->mount_manager->file_exists('unknown://contents.txt'));
    }
    /**
     * @test
     */
    public function guarding_against_valid_mount_identifiers(): void
    {
        $this->expect_exception(Unable_To_Mount_Filesystem::class);
        /* @phpstan-ignore-next-line */
        new Mount_Manager([1 => new Filesystem(new In_Memory_Filesystem_Adapter())]);
    }
    /**
     * @test
     */
    public function guarding_against_mounting_invalid_filesystems(): void
    {
        $this->expect_exception(Unable_To_Mount_Filesystem::class);
        /* @phpstan-ignore-next-line */
        new Mount_Manager(['valid' => 'something else']);
    }
    /**
     * @test
     */
    public function guarding_against_using_paths_without_mount_prefix(): void
    {
        $this->expect_exception(Unable_To_Resolve_Filesystem_Mount::class);
        $this->mount_manager->read('path-without-mount-prefix.txt');
    }
    /**
     * @test
     */
    public function guard_against_using_unknown_mount(): void
    {
        $this->expect_exception(Unable_To_Resolve_Filesystem_Mount::class);
        $this->mount_manager->read('unknown://location.txt');
    }
    /**
     * @test
     */
    public function generate_public_url(): void
    {
        $mount_manager = new Mount_Manager(['first' => new Filesystem($this->first_stub_adapter, ['public_url' => 'first.example.com']), 'second' => new Filesystem($this->second_stub_adapter, ['public_url' => 'second.example.com'])]);
        $mount_manager->write('first://file1.txt', 'content');
        $mount_manager->write('second://file2.txt', 'content');
        $this->assert_same('first.example.com/file1.txt', $mount_manager->public_url('first://file1.txt'));
        $this->assert_same('second.example.com/file2.txt', $mount_manager->public_url('second://file2.txt'));
    }
    /**
     * @test
     */
    public function provide_checksum(): void
    {
        $this->mount_manager->write('first://file.txt', 'content');
        $this->assert_same('9a0364b9e99bb480dd25e1f0284c8555', $this->mount_manager->checksum('first://file.txt'));
    }
}