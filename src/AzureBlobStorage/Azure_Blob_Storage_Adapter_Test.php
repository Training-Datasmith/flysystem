<?php

declare (strict_types=1);
namespace League\Flysystem\Azure_Blob_Storage;

use function getenv;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case as TestCase;
use League\Flysystem\Config;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Visibility;
use Microsoft_Azure\Storage\Blob\Blob_Rest_Proxy;
use Microsoft_Azure\Storage\Common\Internal\Storage_Service_Settings;
/**
 * @group azure
 */
class Azure_Blob_Storage_Adapter_Test extends Test_Case
{
    public const CONTAINER_NAME = 'flysystem';
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        $dsn = getenv('FLYSYSTEM_AZURE_DSN');
        if (empty($dsn)) {
            self::mark_test_skipped('FLYSYSTEM_AZURE_DSN is not provided.');
        }
        $client = Blob_Rest_Proxy::create_blob_service($dsn);
        $service_settings = Storage_Service_Settings::create_from_connection_string($dsn);
        return new Azure_Blob_Storage_Adapter($client, self::CONTAINER_NAME, 'ci', serviceSettings: $service_settings);
    }
    /**
     * @test
     */
    public function overwriting_a_file(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('path.txt', 'contents');
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config());
            $contents = $adapter->read('path.txt');
            $this->assert_equals('new contents', $contents);
        });
    }
    /**
     * @test
     */
    public function setting_visibility(): void
    {
        self::mark_test_skipped('Azure does not support visibility');
    }
    /**
     * @test
     */
    public function failing_to_set_visibility(): void
    {
        self::mark_test_skipped('Azure does not support visibility');
    }
    /**
     * @test
     */
    public function failing_to_check_visibility(): void
    {
        self::mark_test_skipped('Azure does not support visibility');
    }
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->mark_test_skipped('This adapter always returns a mime-type');
    }
    public function listing_contents_recursive(): void
    {
        $this->mark_test_skipped('This adapter does not support creating directories');
    }
    /**
     * @test
     */
    public function copying_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->copy('source.txt', 'destination.txt', new Config());
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function moving_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assert_false($adapter->file_exists('source.txt'), 'After moving a file should no longer exist in the original location.');
            $this->assert_true($adapter->file_exists('destination.txt'), 'After moving, a file should be present at the new location.');
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function copying_a_file_again(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config());
            $adapter->copy('source.txt', 'destination.txt', new Config());
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function setting_visibility_can_be_ignored_not_supported(): void
    {
        $this->given_we_have_an_existing_file('some-file.md');
        $this->expect_not_to_perform_assertions();
        $client = Blob_Rest_Proxy::create_blob_service(getenv('FLYSYSTEM_AZURE_DSN'));
        $adapter = new Azure_Blob_Storage_Adapter($client, self::CONTAINER_NAME, 'ci', null, 50000, Azure_Blob_Storage_Adapter::ON_VISIBILITY_IGNORE);
        $adapter->set_visibility('some-file.md', 'public');
    }
    /**
     * @test
     */
    public function setting_visibility_causes_errors(): void
    {
        $this->given_we_have_an_existing_file('some-file.md');
        $adapter = $this->adapter();
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $adapter->set_visibility('some-file.md', 'public');
    }
    /**
     * @test
     */
    public function checking_if_a_directory_exists_after_creating_it(): void
    {
        $this->mark_test_skipped('This adapter does not support creating directories');
    }
    /**
     * @test
     */
    public function setting_visibility_on_a_file_that_does_not_exist(): void
    {
        $this->mark_test_skipped('This adapter does not support visibility');
    }
    /**
     * @test
     */
    public function creating_a_directory(): void
    {
        $this->mark_test_skipped('This adapter does not support creating directories');
    }
}