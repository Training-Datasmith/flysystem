<?php

declare (strict_types=1);
namespace League\Flysystem\Async_Aws_S3;

use Async_Aws\Core\Exception\Http\Client_Exception;
use Async_Aws\Core\Exception\Http\Network_Exception;
use Async_Aws\Core\Test\Http\Simple_Mocked_Response;
use Async_Aws\Core\Test\Result_Mock_Factory;
use Async_Aws\S3\Result\Head_Object_Output;
use Async_Aws\S3\Result\List_Objects_V2output;
use Async_Aws\S3\Result\Put_Object_Output;
use Async_Aws\S3\S3Client;
use Async_Aws\S3\Value_Object\Aws_Object;
use Async_Aws\Simple_S3\Simple_S3client;
use Exception;
use function getenv;
use function iterator_to_array;
use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Aws_S3v3\Aws_S3v3adapter;
use League\Flysystem\Checksum_Algo_Is_Not_Supported;
use League\Flysystem\Config;
use League\Flysystem\File_Attributes;
use League\Flysystem\Filesystem_Adapter;
use League\Flysystem\Storage_Attributes;
use League\Flysystem\Unable_To_Check_File_Existence;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_List_Contents;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Retrieve_Metadata;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Visibility;
/**
 * @group aws
 */
class Async_Aws_S3adapter_Test extends Filesystem_Adapter_Test_Case
{
    private bool $should_clean_up = false;
    private static string $adapter_prefix = 'test-prefix';
    /**
     * @var S3Client|null
     */
    private static ?\Async_Aws\Simple_S3\Simple_S3client $s3Client = null;
    private static ?\League\Flysystem\Async_Aws_S3\S3client_Stub $stub_s3client = null;
    private static function aws_config(): array
    {
        $key = getenv('FLYSYSTEM_AWS_S3_KEY');
        $secret = getenv('FLYSYSTEM_AWS_S3_SECRET');
        $region = getenv('FLYSYSTEM_AWS_S3_REGION') ?: 'eu-central-1';
        if (!$key || !$secret) {
            self::mark_test_skipped('No AWS credentials present for testing.');
        }
        return ['accessKeyId' => $key, 'accessKeySecret' => $secret, 'region' => $region];
    }
    protected function set_up(): void
    {
        parent::set_up();
        $this->retry_on_exception(Network_Exception::class);
    }
    public static function set_up_before_class(): void
    {
        static::$adapter_prefix = 'ci/' . bin2hex(random_bytes(10));
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
    }
    private static function s3Client(): S3Client
    {
        if (static::$s3Client instanceof S3Client) {
            return static::$s3Client;
        }
        $bucket = getenv('FLYSYSTEM_AWS_S3_BUCKET');
        if (!$bucket) {
            self::mark_test_skipped('No AWS credentials present for testing.');
        }
        static::$s3Client = new Simple_S3client(self::aws_config());
        return static::$s3Client;
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
        static::$stub_s3client->throw_exception_when_executing_command('CopyObject');
        $this->expect_exception(Unable_To_Move_File::class);
        $adapter->move('source.txt', 'destination.txt', new Config());
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
    public function delete_directory_replaces_special_characters_by_xml_entity_codes(): void
    {
        $this->run_scenario(function (): void {
            $directory = 'to-delete';
            $object = sprintf('/%s/\'\"&<>.txt', $directory);
            $adapter = $this->adapter();
            $adapter->write($object, '', new Config());
            $adapter->delete_directory($directory);
            $this->assert_false($adapter->file_exists($object));
            $this->assert_false($adapter->directory_exists($directory));
        });
    }
    /**
     * @test
     */
    public function delete_directory_throws_exception_if_object_key_can_not_be_escaped_correctly(): void
    {
        $list_objects_mock = $this->get_mock_builder(List_Objects_V2output::class)->disable_original_constructor()->only_methods(['getContents'])->get_mock();
        $list_objects_mock->expects(self::once())->method('getContents')->will_return([new Aws_Object(['Key' => "\x8f.txt"])]);
        $s3Client = $this->get_mock_builder(S3Client::class)->disable_original_constructor()->only_methods(['ListObjectsV2'])->get_mock();
        $s3Client->expects(self::once())->method('ListObjectsV2')->will_return($list_objects_mock);
        $filesystem = new Async_Aws_S3adapter($s3Client, 'my-bucket');
        $this->expect_exception(Unable_To_Delete_Directory::class);
        $this->expect_exception_message_matches('/htmlentities\(\) returned an empty string/');
        $filesystem->delete_directory('directory/containing/objects/with/un-escapable/key');
    }
    /**
     * @test
     */
    public function fetching_unknown_mime_type_of_a_file(): void
    {
        $this->adapter();
        $result = Result_Mock_Factory::create(Head_Object_Output::class, []);
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
        $result = Result_Mock_Factory::create(Head_Object_Output::class, []);
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
        $exception = new Client_Exception(new Simple_Mocked_Response());
        static::$stub_s3client->throw_exception_when_executing_command('ObjectExists', $exception);
        $this->expect_exception(Unable_To_Check_File_Existence::class);
        $adapter->file_exists('something-that-does-exist.txt');
    }
    /**
     * @test
     */
    public function configuring_http_streaming_via_options(): void
    {
        $adapter = $this->use_adapter($this->create_filesystem_adapter());
        $this->given_we_have_an_existing_file('path.txt');
        $resource = $adapter->read_stream('path.txt');
        $metadata = stream_get_meta_data($resource);
        fclose($resource);
        $this->assert_true($metadata['seekable']);
    }
    /**
     * @test
     */
    public function write_with_s3_client(): void
    {
        $file = 'foo/bar.txt';
        $prefix = 'all-files';
        $bucket = 'foobar';
        $contents = 'contents';
        $s3Client = $this->get_mock_builder(S3Client::class)->disable_original_constructor()->only_methods(['putObject'])->get_mock();
        $s3Client->expects(self::once())->method('putObject')->with(self::callback(function (array $input) use ($file, $prefix, $bucket, $contents): bool {
            if ($input['Key'] !== $prefix . '/' . $file) {
                return false;
            }
            if ($contents !== $input['Body']) {
                return false;
            }
            if ($input['Bucket'] !== $bucket) {
                return false;
            }
            return true;
        }))->will_return(Result_Mock_Factory::create(Put_Object_Output::class));
        $filesystem = new Async_Aws_S3adapter($s3Client, $bucket, $prefix);
        $filesystem->write($file, $contents, new Config());
    }
    /**
     * @test
     */
    public function write_with_simple_s3_client(): void
    {
        $file = 'foo/bar.txt';
        $prefix = 'all-files';
        $bucket = 'foobar';
        $contents = 'contents';
        $s3Client = $this->get_mock_builder(Simple_S3client::class)->disable_original_constructor()->only_methods(['upload', 'putObject'])->get_mock();
        $s3Client->expects(self::never())->method('putObject');
        $s3Client->expects(self::once())->method('upload')->with($bucket, $prefix . '/' . $file, $contents);
        $filesystem = new Async_Aws_S3adapter($s3Client, $bucket, $prefix);
        $filesystem->write($file, $contents, new Config());
    }
    /**
     * @test
     */
    public function failing_to_write_a_file(): void
    {
        $adapter = $this->adapter();
        static::$stub_s3client->throw_exception_when_executing_command('PutObject');
        $this->expect_exception(Unable_To_Write_File::class);
        $adapter->write('foo/bar.txt', 'contents', new Config());
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
    /**
     * @test
     */
    public function copying_a_file_with_non_ascii_characters(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('ıÇöü🤔.txt', 'contents to be copied', new Config());
            $adapter->copy('ıÇöü🤔.txt', 'ıÇöü🤔_copy.txt', new Config());
            $this->assert_true($adapter->file_exists('ıÇöü🤔.txt'));
            $this->assert_true($adapter->file_exists('ıÇöü🤔_copy.txt'));
            $this->assert_equals('contents to be copied', $adapter->read('ıÇöü🤔_copy.txt'));
        });
    }
    /**
     * @test
     */
    public function top_level_directory_excluded_from_listing(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('directory/file.txt', '', new Config());
            $adapter->create_directory('empty', new Config());
            $adapter->create_directory('nested/nested', new Config());
            $listing1 = iterator_to_array($adapter->list_contents('directory', true));
            $listing2 = iterator_to_array($adapter->list_contents('empty', true));
            $listing3 = iterator_to_array($adapter->list_contents('nested', true));
            self::assert_count(1, $listing1);
            self::assert_count(0, $listing2);
            self::assert_count(1, $listing3);
        });
    }
    /**
     * @test
     */
    public function failing_to_list_contents(): void
    {
        $adapter = $this->adapter();
        static::$stub_s3client->throw_exception_when_executing_command('ListObjectsV2');
        $this->expect_exception(Unable_To_List_Contents::class);
        iterator_to_array($adapter->list_contents('/path', false));
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        static::$stub_s3client = new S3client_Stub(static::s3Client(), self::aws_config());
        /** @var string $bucket */
        $bucket = getenv('FLYSYSTEM_AWS_S3_BUCKET');
        $prefix = getenv('FLYSYSTEM_AWS_S3_PREFIX') ?: static::$adapter_prefix;
        return new Async_Aws_S3adapter(static::$stub_s3client, $bucket, $prefix);
    }
}