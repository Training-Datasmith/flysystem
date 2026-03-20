<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use function getenv;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Write_File;
/**
 * @group gcs
 */
class Google_Cloud_Storage_Adapter_Test extends Filesystem_Adapter_Test_Case
{
    private static string $adapter_prefix = 'ci';
    private static Stub_Rigged_Bucket $bucket;
    private static Path_Prefixer $prefixer;
    public static function set_up_before_class(): void
    {
        static::$adapter_prefix = 'frank-ci';
        // . bin2hex(random_bytes(10));
        static::$prefixer = new Path_Prefixer(static::$adapter_prefix);
    }
    protected static function bucket_name(): string|array|false
    {
        return 'flysystem';
    }
    protected static function visibility_handler(): Visibility_Handler
    {
        return new Portable_Visibility_Handler();
    }
    public function prefix_path(string $path): string
    {
        return static::$prefixer->prefix_path($path);
    }
    public function prefix_directory_path(string $path): string
    {
        return static::$prefixer->prefix_directory_path($path);
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        if (!file_exists(__DIR__ . '/../../google-cloud-service-account.json')) {
            self::mark_test_skipped('No google service account found in project root.');
        }
        $client_options = ['projectId' => getenv('GOOGLE_CLOUD_PROJECT'), 'keyFilePath' => __DIR__ . '/../../google-cloud-service-account.json'];
        $storage_client = new Stub_Storage_Client($client_options);
        /** @var StubRiggedBucket $bucket */
        $bucket = $storage_client->bucket(self::bucket_name());
        static::$bucket = $bucket;
        return new Google_Cloud_Storage_Adapter($bucket, static::$adapter_prefix, visibilityHandler: self::visibility_handler());
    }
    /**
     * @test
     */
    public function writing_with_specific_metadata(): void
    {
        $adapter = $this->adapter();
        $adapter->write('some/path.txt', 'contents', new Config(['metadata' => ['contentType' => 'text/plain+special']]));
        $mime_type = $adapter->mime_type('some/path.txt')->mime_type();
        $this->assert_equals('text/plain+special', $mime_type);
    }
    /**
     * @test
     */
    public function guessing_the_mime_type_when_writing(): void
    {
        $adapter = $this->adapter();
        $adapter->write('some/config.txt', '<?xml version="1.0" encoding="UTF-8"?><test/>', new Config());
        $mime_type = $adapter->mime_type('some/config.txt')->mime_type();
        $this->assert_equals('text/xml', $mime_type);
    }
    /**
     * @test
     */
    public function fetching_visibility_of_non_existing_file(): void
    {
        $this->mark_test_skipped("\n            Not relevant for this adapter since it's a missing ACL,\n            which turns into a 404 which is the expected outcome\n            of a private visibility. ¯\\_(ツ)_/¯\n        ");
    }
    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->mark_test_skipped('This adapter always returns a mime-type.');
    }
    /**
     * @test
     */
    public function listing_a_toplevel_directory(): void
    {
        $this->clear_storage();
        parent::listing_a_toplevel_directory();
    }
    /**
     * @test
     */
    public function failing_to_write_a_file(): void
    {
        $adapter = $this->adapter();
        static::$bucket->fail_for_upload($this->prefix_path('something.txt'));
        $this->expect_exception(Unable_To_Write_File::class);
        $adapter->write('something.txt', 'contents', new Config());
    }
    /**
     * @test
     */
    public function failing_to_delete_a_file(): void
    {
        $adapter = $this->adapter();
        static::$bucket->fail_for_object($this->prefix_path('filename.txt'));
        $this->expect_exception(Unable_To_Delete_File::class);
        $adapter->delete('filename.txt');
    }
    /**
     * @test
     */
    public function failing_to_delete_a_directory(): void
    {
        $adapter = $this->adapter();
        $this->given_we_have_an_existing_file('dir/filename.txt');
        static::$bucket->fail_for_object($this->prefix_path('dir/filename.txt'));
        $this->expect_exception(Unable_To_Delete_Directory::class);
        $adapter->delete_directory('dir');
    }
    /**
     * @test
     */
    public function failing_to_retrieve_visibility(): void
    {
        $adapter = $this->adapter();
        static::$bucket->fail_for_object($this->prefix_path('filename.txt'));
        $this->expect_exception(Unable_To_Retrieve_Metadata::class);
        $adapter->visibility('filename.txt');
    }
}