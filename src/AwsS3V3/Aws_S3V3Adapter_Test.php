<?php

declare (strict_types=1);
namespace League\Flysystem\Aws_S3v3;

use Aws\Result;
use Aws\S3\S3Client;
use Aws\S3\S3client_Interface;
use Exception;
use Generator;
use function getenv;
use function iterator_to_array;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Checksum_Algo_Is_Not_Supported;
use League\Flysystem\Config;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Path_Prefixer;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Visibility;
use RuntimeException;
/**
 * @group aws
 */
class Aws_S3v3adapter_Test extends Filesystem_Adapter_Test_Case
{
    private bool $should_clean_up = false;
    /**
     * @var string
     */
    private static $adapter_prefix = 'test-prefix';
    /**
     * @var S3ClientInterface|null
     */
    private static ?\Aws\S3\S3Client $s3Client = null;
    private static ?\League\Flysystem\Aws_S3v3\S3client_Stub $stub_s3client = null;
    public static function set_up_before_class(): void
    {
        static::$adapter_prefix = getenv('FLYSYSTEM_AWS_S3_PREFIX') ?: 'ci/' . bin2hex(random_bytes(10));
    }
    protected function tear_down(): void
    {
        if (!$this->should_clean_up) {
            return;
        }
        $adapter = $this->adapter();
        $adapter->delete_directory('/');
        /** @var StorageAttributes[] $listing */
        $listing = $adapter->list_contents('', false);
        foreach ($listing as $item) {
            if ($item->is_file()) {
                $adapter->delete($item->path());
            } else {
                $adapter->delete_directory($item->path());
            }
        }
        self::$adapter = null;
    }
    protected function set_up(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->mark_test_skipped('AWS does not support this anymore.');
        }
        parent::set_up();
    }
    private static function s3Client(): S3client_Interface
    {
        if (static::$s3Client instanceof S3client_Interface) {
            return static::$s3Client;
        }
        $key = getenv('FLYSYSTEM_AWS_S3_KEY');
        $secret = getenv('FLYSYSTEM_AWS_S3_SECRET');
        $bucket = getenv('FLYSYSTEM_AWS_S3_BUCKET');
        $region = getenv('FLYSYSTEM_AWS_S3_REGION') ?: 'eu-central-1';
        if (!$key || !$secret || !$bucket) {
            self::mark_test_skipped('No AWS credentials present for testing.');
        }
        $options = ['version' => 'latest', 'credentials' => compact('key', 'secret'), 'region' => $region];
        return static::$s3Client = new S3Client($options);
    }
    /**
     * @test
     */
    public function writing_with_a_specific_mime_type(): void
    {
        $adapter = $this->adapter();
        $adapter->write('some/path.txt', 'contents', new Config(['ContentType' => 'text/plain+special']));
        $mime_type = $adapter->mime_type('some/path.txt')->mime_type();
        $this->assert_equals('text/plain+special', $mime_type);
    }
    /**
     * @test
     */
    public function writing_a_file_with_explicit_mime_type(): void
    {
        $adapter = $this->adapter();
        $adapter->write('some/path.txt', 'contents', new Config(['mimetype' => 'text/plain+special']));
        $mime_type = $adapter->mime_type('some/path.txt')->mime_type();
        $this->assert_equals('text/plain+special', $mime_type);
    }
    /**
     * @test
     *
     * @see https://github.com/thephpleague/flysystem-aws-s3-v3/issues/291
     */
    public function issue_291(): void
    {
        $adapter = $this->adapter();
        $adapter->create_directory('directory', new Config());
        $listing = iterator_to_array($adapter->list_contents('directory', true));
        self::assert_count(0, $listing);
    }
    /**
     * @test
     */
    public function listing_contents_recursive(): void
    {
        $adapter = $this->adapter();
        $adapter->write('something/0/here.txt', 'contents', new Config());
        $adapter->write('something/1/also/here.txt', 'contents', new Config());
        $contents = iterator_to_array($adapter->list_contents('', true));
        $this->assert_count(2, $contents);
        $this->assert_contains_only_instances_of(File_Attributes::class, $contents);
        /** @var FileAttributes $file */
        $file = $contents[0];
        $this->assert_equals('something/0/here.txt', $file->path());
        /** @var FileAttributes $file */
        $file = $contents[1];
        $this->assert_equals('something/1/also/here.txt', $file->path());
    }
    /**
     * @test
     */
    public function failing_to_delete_while_moving(): void
    {
        $adapter = $this->adapter();
        $adapter->write('source.txt', 'contents to be copied', new Config());
        static::$stub_s3client->fail_on_next_copy();
        $this->expect_exception(Unable_To_Move_File::class);
        $adapter->move('source.txt', 'destination.txt', new Config());
    }
    /**
     * @test
     *
     * @see https://github.com/thephpleague/flysystem-aws-s3-v3/issues/287
     */
    public function issue_287(): void
    {
        $adapter = $this->adapter();
        $adapter->write('KmFVvKqo/QLMExy2U/620ff60c8a154.pdf', 'pdf content', new Config());
        self::assert_true($adapter->directory_exists('KmFVvKqo'));
    }
    /**
     * @test
     */
    public function failing_to_write_a_file(): void
    {
        $adapter = $this->adapter();
        static::$stub_s3client->throw_during_upload(new RuntimeException('Oh no'));
        $this->expect_exception(Unable_To_Write_File::class);
        $adapter->write('path.txt', 'contents', new Config());
    }
    /**
     * @test
     */
    public function failing_to_delete_a_file(): void
    {
        $adapter = $this->adapter();
        static::$stub_s3client->throw_exception_when_executing_command('DeleteObject');
        $this->expect_exception(Unable_To_Delete_File::class);
        $adapter->delete('path.txt');
    }
    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->adapter();
        $result = new Result(['Key' => static::$adapter_prefix . '/unknown-mime-type.md5']);
        static::$stub_s3client->stage_result_for_command('HeadObject', $result);
        parent::fetching_unknown_mime_type_of_a_file();
    }
    /**
     * @test
     *
     * @dataProvider dpFailingMetadataGetters
     */
    public function failing_to_retrieve_metadata(Exception $exception, string $getter_name): void
    {
        $adapter = $this->adapter();
        $result = new Result(['Key' => static::$adapter_prefix . '/filename.txt']);
        static::$stub_s3client->stage_result_for_command('HeadObject', $result);
        $this->expect_exception_object($exception);
        $adapter->{$getter_name}('filename.txt');
    }
    public static function dp_failing_metadata_getters(): iterable
    {
        yield 'mimeType' => [Unable_To_Retrieve_Metadata::mime_type('filename.txt'), 'mimeType'];
        yield 'lastModified' => [Unable_To_Retrieve_Metadata::last_modified('filename.txt'), 'lastModified'];
        yield 'fileSize' => [Unable_To_Retrieve_Metadata::file_size('filename.txt'), 'fileSize'];
    }
    /**
     * @test
     */
    public function failing_to_check_for_file_existence(): void
    {
        $adapter = $this->adapter();
        static::$stub_s3client->throw500exception_when_executing_command('HeadObject');
        $this->expect_exception(Unable_To_Check_File_Existence::class);
        $adapter->file_exists('something-that-does-exist.txt');
    }
    /**
     * @test
     *
     * @dataProvider casesWhereHttpStreamingInfluencesSeekability
     */
    public function streaming_reads_are_not_seekable_and_non_streaming_are(bool $streaming, bool $seekable): void
    {
        if (getenv('COMPOSER_OPTS') === '--prefer-lowest') {
            $this->mark_test_skipped('The SDK does not support streaming in low versions.');
        }
        $adapter = $this->use_adapter($this->create_filesystem_adapter($streaming));
        $this->given_we_have_an_existing_file('path.txt');
        $resource = $adapter->read_stream('path.txt');
        $metadata = stream_get_meta_data($resource);
        fclose($resource);
        $this->assert_equals($seekable, $metadata['seekable']);
    }
    public static function cases_where_http_streaming_influences_seekability(): Generator
    {
        yield 'not streaming reads have seekable stream' => [false, true];
        yield 'streaming reads have non-seekable stream' => [true, false];
    }
    /**
     * @test
     *
     * @dataProvider casesWhereHttpStreamingInfluencesSeekability
     */
    public function configuring_http_streaming_via_options(bool $streaming): void
    {
        $adapter = $this->use_adapter($this->create_filesystem_adapter($streaming, ['@http' => ['stream' => false]]));
        $this->given_we_have_an_existing_file('path.txt');
        $resource = $adapter->read_stream('path.txt');
        $metadata = stream_get_meta_data($resource);
        fclose($resource);
        $this->assert_true($metadata['seekable']);
    }
    /**
     * @test
     *
     * @dataProvider casesWhereHttpStreamingInfluencesSeekability
     */
    public function use_globally_configured_options(bool $streaming): void
    {
        $adapter = $this->use_adapter($this->create_filesystem_adapter($streaming, ['ContentType' => 'text/plain+special']));
        $this->given_we_have_an_existing_file('path.txt');
        $mime_type = $adapter->mime_type('path.txt')->mime_type();
        $this->assert_same('text/plain+special', $mime_type);
    }
    /**
     * @test
     */
    public function moving_with_updated_metadata(): void
    {
        $adapter = $this->adapter();
        $adapter->write('source.txt', 'contents to be moved', new Config(['ContentType' => 'text/plain']));
        $mime_type_source = $adapter->mime_type('source.txt')->mime_type();
        $this->assert_same('text/plain', $mime_type_source);
        $adapter->move('source.txt', 'destination.txt', new Config(['ContentType' => 'text/plain+special', 'MetadataDirective' => 'REPLACE']));
        $mime_type_destination = $adapter->mime_type('destination.txt')->mime_type();
        $this->assert_same('text/plain+special', $mime_type_destination);
    }
    /**
     * @test
     */
    public function moving_without_updated_metadata(): void
    {
        $adapter = $this->adapter();
        $adapter->write('source.txt', 'contents to be moved', new Config(['ContentType' => 'text/plain']));
        $mime_type_source = $adapter->mime_type('source.txt')->mime_type();
        $this->assert_same('text/plain', $mime_type_source);
        $adapter->move('source.txt', 'destination.txt', new Config(['ContentType' => 'text/plain+special']));
        $mime_type_destination = $adapter->mime_type('destination.txt')->mime_type();
        $this->assert_same('text/plain', $mime_type_destination);
    }
    /**
     * @test
     */
    public function copying_with_updated_metadata(): void
    {
        $adapter = $this->adapter();
        $adapter->write('source.txt', 'contents to be moved', new Config(['ContentType' => 'text/plain']));
        $mime_type_source = $adapter->mime_type('source.txt')->mime_type();
        $this->assert_same('text/plain', $mime_type_source);
        $adapter->copy('source.txt', 'destination.txt', new Config(['ContentType' => 'text/plain+special', 'MetadataDirective' => 'REPLACE']));
        $mime_type_destination = $adapter->mime_type('destination.txt')->mime_type();
        $this->assert_same('text/plain+special', $mime_type_destination);
    }
    /**
     * @test
     */
    public function setting_acl_via_options(): void
    {
        $adapter = $this->adapter();
        $prefixer = new Path_Prefixer(static::$adapter_prefix);
        $prefixed_path = $prefixer->prefix_path('path.txt');
        $adapter->write('path.txt', 'contents', new Config(['ACL' => 'bucket-owner-full-control']));
        $arguments = ['Bucket' => getenv('FLYSYSTEM_AWS_S3_BUCKET'), 'Key' => $prefixed_path];
        $command = static::$s3Client->get_command('GetObjectAcl', $arguments);
        $response = static::$s3Client->execute($command)->to_array();
        $permission = $response['Grants'][0]['Permission'];
        self::assert_equals('FULL_CONTROL', $permission);
    }
    /**
     * @test
     */
    public function moving_a_file_with_visibility(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->move('source.txt', 'destination.txt', new Config([Config::OPTION_VISIBILITY => Visibility::PRIVATE]));
            $this->assert_false($adapter->file_exists('source.txt'), 'After moving a file should no longer exist in the original location.');
            $this->assert_true($adapter->file_exists('destination.txt'), 'After moving, a file should be present at the new location.');
            $this->assert_equals(Visibility::PRIVATE, $adapter->visibility('destination.txt')->visibility());
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function specifying_a_custom_checksum_algo_is_not_supported(): void
    {
        /** @var AwsS3V3Adapter $adapter */
        $adapter = $this->adapter();
        $this->expect_exception(Checksum_Algo_Is_Not_Supported::class);
        $adapter->checksum('something', new Config(['checksum_algo' => 'md5']));
    }
    /**
     * @test
     */
    public function copying_a_file_with_visibility(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            $adapter->copy('source.txt', 'destination.txt', new Config([Config::OPTION_VISIBILITY => Visibility::PRIVATE]));
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals(Visibility::PRIVATE, $adapter->visibility('destination.txt')->visibility());
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    protected static function create_filesystem_adapter(bool $streaming = true, array $options = []): Filesystem_Adapter
    {
        static::$stub_s3client = new S3client_Stub(static::s3Client());
        /** @var string $bucket */
        $bucket = getenv('FLYSYSTEM_AWS_S3_BUCKET');
        $prefix = static::$adapter_prefix;
        return new Aws_S3v3adapter(static::$stub_s3client, $bucket, $prefix, null, null, $options, $streaming);
    }
}