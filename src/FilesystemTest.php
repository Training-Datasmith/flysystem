<?php

declare (strict_types=1);
namespace League\Flysystem;

use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Guzzle_Http\Psr7\Stream_Wrapper;
use Guzzle_Http\Psr7\Utils;
use function iterator_to_array;
use IteratorAggregate;
use League\Flysystem\In_Memory\In_Memory_Filesystem_Adapter;
use League\Flysystem\Local\Local_Filesystem_Adapter;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Url_Generation\Temporary_Url_Generator;
use LogicException;
use Php_Unit\Framework\Test_Case;
/**
 * @group core
 */
class Filesystem_Test extends Test_Case
{
    public const ROOT = __DIR__ . '/../test_files/test-root';
    private ?\League\Flysystem\Filesystem $filesystem = null;
    /**
     * @before
     */
    public function setup_filesystem(): void
    {
        $adapter = new Local_Filesystem_Adapter(self::ROOT);
        $filesystem = new Filesystem($adapter);
        $this->filesystem = $filesystem;
    }
    /**
     * @after
     */
    public function remove_files(): void
    {
        delete_directory(static::ROOT);
    }
    /**
     * @test
     */
    public function writing_and_reading_files(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $contents = $this->filesystem->read('path.txt');
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     *
     * @dataProvider invalidStreamInput
     *
     * @param mixed $input
     */
    public function trying_to_write_with_an_invalid_stream_arguments($input): void
    {
        $this->expect_exception(Invalid_Stream_Provided::class);
        $this->filesystem->write_stream('path.txt', $input);
    }
    public static function invalid_stream_input(): Generator
    {
        $handle = tmpfile();
        fclose($handle);
        yield 'resource that is not open' => [$handle];
        yield 'something that is not a resource' => [false];
    }
    /**
     * @test
     */
    public function writing_and_reading_a_stream(): void
    {
        $write_stream = stream_with_contents('contents');
        $this->filesystem->write_stream('path.txt', $write_stream);
        $read_stream = $this->filesystem->read_stream('path.txt');
        fclose($write_stream);
        $this->assert_is_resource($read_stream);
        $this->assert_equals('contents', stream_get_contents($read_stream));
        fclose($read_stream);
    }
    /**
     * @test
     */
    public function writing_using_a_stream_wrapper(): void
    {
        $contents = 'contents of the file';
        $stream = Utils::stream_for($contents);
        $resource = Stream_Wrapper::get_resource($stream);
        $this->filesystem->write_stream('from-stream-wrapper.txt', $resource);
        fclose($resource);
        $this->assert_equals($contents, $this->filesystem->read('from-stream-wrapper.txt'));
    }
    /**
     * @test
     */
    public function checking_if_files_exist(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $path_dot_txt_exists = $this->filesystem->file_exists('path.txt');
        $other_file_exists = $this->filesystem->file_exists('other.txt');
        $this->assert_true($path_dot_txt_exists);
        $this->assert_false($other_file_exists);
    }
    /**
     * @test
     */
    public function checking_if_directories_exist(): void
    {
        $this->filesystem->create_directory('existing-directory');
        $existing_directory = $this->filesystem->directory_exists('existing-directory');
        $not_existing_directory = $this->filesystem->directory_exists('not-existing-directory');
        $this->assert_true($existing_directory);
        $this->assert_false($not_existing_directory);
    }
    /**
     * @test
     */
    public function deleting_a_file(): void
    {
        $this->filesystem->write('path.txt', 'content');
        $this->filesystem->delete('path.txt');
        $this->assert_false($this->filesystem->file_exists('path.txt'));
    }
    /**
     * @test
     */
    public function creating_a_directory(): void
    {
        $this->filesystem->create_directory('here');
        $directory_attrs = $this->filesystem->list_contents('')->to_array()[0];
        $this->assert_instance_of(Directory_Attributes::class, $directory_attrs);
        $this->assert_equals('here', $directory_attrs->path());
    }
    /**
     * @test
     */
    public function deleting_a_directory(): void
    {
        $this->filesystem->write('dirname/a.txt', 'contents');
        $this->filesystem->write('dirname/b.txt', 'contents');
        $this->filesystem->write('dirname/c.txt', 'contents');
        $this->filesystem->delete_directory('dir');
        $this->assert_true($this->filesystem->file_exists('dirname/a.txt'));
        $this->filesystem->delete_directory('dirname');
        $this->assert_false($this->filesystem->file_exists('dirname/a.txt'));
        $this->assert_false($this->filesystem->file_exists('dirname/b.txt'));
        $this->assert_false($this->filesystem->file_exists('dirname/c.txt'));
    }
    /**
     * @test
     */
    public function listing_directory_contents(): void
    {
        $this->filesystem->write('dirname/a.txt', 'contents');
        $this->filesystem->write('dirname/b.txt', 'contents');
        $this->filesystem->write('dirname/c.txt', 'contents');
        $listing = $this->filesystem->list_contents('', false);
        $this->assert_instance_of(Directory_Listing::class, $listing);
        $this->assert_instance_of(IteratorAggregate::class, $listing);
        $attribute_listing = iterator_to_array($listing);
        $this->assert_contains_only_instances_of(Storage_Attributes::class, $attribute_listing);
        $this->assert_count(1, $attribute_listing);
    }
    /**
     * @test
     */
    public function listing_directory_contents_recursive(): void
    {
        $this->filesystem->write('dirname/a.txt', 'contents');
        $this->filesystem->write('dirname/b.txt', 'contents');
        $this->filesystem->write('dirname/c.txt', 'contents');
        $listing = $this->filesystem->list_contents('', true);
        $attribute_listing = $listing->to_array();
        $this->assert_contains_only_instances_of(Storage_Attributes::class, $attribute_listing);
        $this->assert_count(4, $attribute_listing);
    }
    /**
     * @test
     */
    public function copying_files(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $this->filesystem->copy('path.txt', 'new-path.txt');
        $this->assert_true($this->filesystem->file_exists('path.txt'));
        $this->assert_true($this->filesystem->file_exists('new-path.txt'));
    }
    /**
     * @test
     */
    public function moving_files(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $this->filesystem->move('path.txt', 'new-path.txt');
        $this->assert_false($this->filesystem->file_exists('path.txt'));
        $this->assert_true($this->filesystem->file_exists('new-path.txt'));
    }
    /**
     * @test
     */
    public function fetching_last_modified(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $last_modified = $this->filesystem->last_modified('path.txt');
        $this->assert_is_int($last_modified);
        $this->assert_true($last_modified > time() - 30);
        $this->assert_true($last_modified < time() + 30);
    }
    /**
     * @test
     */
    public function fetching_mime_type(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $mime_type = $this->filesystem->mime_type('path.txt');
        $this->assert_equals('text/plain', $mime_type);
    }
    /**
     * @test
     */
    public function fetching_file_size(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $file_size = $this->filesystem->file_size('path.txt');
        $this->assert_equals(8, $file_size);
    }
    /**
     * @test
     */
    public function ensuring_streams_are_rewound_when_writing(): void
    {
        $write_stream = stream_with_contents('contents');
        fseek($write_stream, 4);
        $this->filesystem->write_stream('path.txt', $write_stream);
        $contents = $this->filesystem->read('path.txt');
        $this->assert_equals('contents', $contents);
    }
    /**
     * @test
     */
    public function setting_visibility(): void
    {
        $this->filesystem->write('path.txt', 'contents');
        $this->filesystem->set_visibility('path.txt', Visibility::PUBLIC);
        $public_visibility = $this->filesystem->visibility('path.txt');
        $this->filesystem->set_visibility('path.txt', Visibility::PRIVATE);
        $private_visibility = $this->filesystem->visibility('path.txt');
        $this->assert_equals(Visibility::PUBLIC, $public_visibility);
        $this->assert_equals(Visibility::PRIVATE, $private_visibility);
    }
    /**
     * @test
     *
     * @dataProvider scenariosCausingPathTraversal
     */
    public function protecting_against_path_traversals(callable $scenario): void
    {
        $this->expect_exception(Path_Traversal_Detected::class);
        $scenario($this->filesystem);
    }
    public static function scenarios_causing_path_traversal(): Generator
    {
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->delete('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->delete_directory('../path');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->create_directory('../path');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->read('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->read_stream('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->write('../path.txt', 'contents');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $stream = stream_with_contents('contents');
            try {
                $filesystem->write_stream('../path.txt', $stream);
            } finally {
                fclose($stream);
            }
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->list_contents('../path');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->file_exists('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->mime_type('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->file_size('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->last_modified('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->visibility('../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->set_visibility('../path.txt', Visibility::PUBLIC);
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->copy('../path.txt', 'path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->copy('path.txt', '../path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->move('../path.txt', 'path.txt');
        }];
        yield [function (Filesystem_Operator $filesystem): void {
            $filesystem->move('path.txt', '../path.txt');
        }];
    }
    /**
     * @test
     */
    public function listing_exceptions_are_uniformely_represented(): void
    {
        $filesystem = new Filesystem(new class extends In_Memory_Filesystem_Adapter
        {
            public function list_contents(string $path, bool $deep): iterable
            {
                yield from parent::list_contents($path, $deep);
                throw new LogicException('Oh no.');
            }
        });
        $items = $filesystem->list_contents('', true);
        $this->expect_exception(Unable_To_List_Contents::class);
        iterator_to_array($items);
        // force the yields
    }
    /**
     * @test
     */
    public function failing_to_create_a_public_url(): void
    {
        $filesystem = new Filesystem(new class extends In_Memory_Filesystem_Adapter implements Public_Url_Generator
        {
            public function public_url(string $path, Config $config): string
            {
                throw new Unable_To_Generate_Public_Url('No reason', $path);
            }
        });
        $this->expect_exception(Unable_To_Generate_Public_Url::class);
        $filesystem->public_url('path.txt');
    }
    /**
     * @test
     */
    public function not_configuring_a_public_url(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter());
        $this->expect_exception(Unable_To_Generate_Public_Url::class);
        $filesystem->public_url('path.txt');
    }
    /**
     * @test
     */
    public function creating_a_public_url(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), ['public_url' => 'https://example.org/public/']);
        $url = $filesystem->public_url('path.txt');
        self::assert_equals('https://example.org/public/path.txt', $url);
    }
    /**
     * @test
     */
    public function public_url_array_uses_multi_prefixer(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), ['public_url' => ['https://cdn1', 'https://cdn2']]);
        $url1 = $filesystem->public_url('first-path1.txt');
        $url2 = $filesystem->public_url('path2.txt');
        $url3 = $filesystem->public_url('first-path1.txt');
        // deterministic
        $url4 = $filesystem->public_url('/some/path-here.txt');
        $url5 = $filesystem->public_url('some/path-here.txt');
        // deterministic even with leading "/"
        self::assert_equals('https://cdn1/first-path1.txt', $url1);
        self::assert_equals('https://cdn2/path2.txt', $url2);
        self::assert_equals('https://cdn1/first-path1.txt', $url3);
        self::assert_equals('https://cdn2/some/path-here.txt', $url4);
        self::assert_equals('https://cdn2/some/path-here.txt', $url5);
    }
    /**
     * @test
     */
    public function custom_public_url_generator(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), [], publicUrlGenerator: new class implements Public_Url_Generator
        {
            public function public_url(string $path, Config $config): string
            {
                return 'custom/' . $path;
            }
        });
        self::assert_same('custom/file.txt', $filesystem->public_url('file.txt'));
    }
    /**
     * @test
     */
    public function copying_from_and_to_the_same_location_fails(): void
    {
        $this->expect_exception_object(Unable_To_Copy_File::source_and_destination_are_the_same('from.txt', 'from.txt'));
        $config = [Config::OPTION_COPY_IDENTICAL_PATH => Resolve_Identical_Path_Conflict::FAIL];
        $this->filesystem->copy('from.txt', 'from.txt', $config);
    }
    /**
     * @test
     */
    public function moving_from_and_to_the_same_location_fails(): void
    {
        $this->expect_exception_object(Unable_To_Move_File::from_location_to('from.txt', 'from.txt'));
        $config = [Config::OPTION_MOVE_IDENTICAL_PATH => Resolve_Identical_Path_Conflict::FAIL];
        $this->filesystem->move('from.txt', 'from.txt', $config);
    }
    /**
     * @test
     */
    public function get_checksum_for_adapter_that_supports(): void
    {
        $this->filesystem->write('path.txt', 'foobar');
        $this->assert_same('3858f62230ac3c915f300c664312c63f', $this->filesystem->checksum('path.txt'));
    }
    /**
     * @test
     */
    public function get_checksum_for_adapter_that_does_not_support(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter());
        $filesystem->write('path.txt', 'foobar');
        $this->assert_same('3858f62230ac3c915f300c664312c63f', $filesystem->checksum('path.txt'));
    }
    /**
     * @test
     */
    public function get_checksum_for_adapter_that_does_not_support_specific_algo(): void
    {
        $adapter = new class extends In_Memory_Filesystem_Adapter implements Checksum_Provider
        {
            public function checksum(string $path, Config $config): string
            {
                throw new Checksum_Algo_Is_Not_Supported();
            }
        };
        $filesystem = new Filesystem($adapter);
        $filesystem->write('path.txt', 'foobar');
        $this->assert_same('3858f62230ac3c915f300c664312c63f', $filesystem->checksum('path.txt'));
    }
    /**
     * @test
     */
    public function get_sha256_checksum_for_adapter_that_does_not_support(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), ['checksum_algo' => 'sha256']);
        $filesystem->write('path.txt', 'foobar');
        $this->assert_same('c3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2', $filesystem->checksum('path.txt'));
    }
    /**
     * @test
     */
    public function get_sha256_checksum_for_adapter_that_does_not_support_while_crc32c_is_the_default(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), ['checksum_algo' => 'crc32c']);
        $filesystem->write('path.txt', 'foobar');
        $checksum = $filesystem->checksum('path.txt', ['checksum_algo' => 'sha256']);
        $this->assert_same('c3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2', $checksum);
    }
    /**
     * @test
     */
    public function unable_to_get_checksum_for_for_file_that_does_not_exist(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter());
        $this->expect_exception(Unable_To_Provide_Checksum::class);
        $filesystem->checksum('path.txt');
    }
    /**
     * @test
     */
    public function generating_temporary_urls(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), temporaryUrlGenerator: new class implements Temporary_Url_Generator
        {
            public function temporary_url(string $path, DateTimeInterface $expires_at, Config $config): string
            {
                return 'https://flysystem.thephpleague.com/' . $path . '?exporesAt=' . $expires_at->format('U');
            }
        });
        $now = \time();
        $temporary_url = $filesystem->temporary_url('some/file.txt', new DateTimeImmutable('@' . $now));
        $expected_url = 'https://flysystem.thephpleague.com/some/file.txt?exporesAt=' . $now;
        self::assert_equals($expected_url, $temporary_url);
    }
    /**
     * @test
     */
    public function not_being_able_to_generate_temporary_urls(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter());
        $this->expect_exception(Unable_To_Generate_Temporary_Url::class);
        $filesystem->temporary_url('some/file.txt', new DateTimeImmutable());
    }
    /**
     * @test
     */
    public function ignoring_same_paths_for_move_and_copy(): void
    {
        $this->expect_not_to_perform_assertions();
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), [Config::OPTION_COPY_IDENTICAL_PATH => Resolve_Identical_Path_Conflict::IGNORE, Config::OPTION_MOVE_IDENTICAL_PATH => Resolve_Identical_Path_Conflict::IGNORE]);
        $filesystem->move('from.txt', 'from.txt');
        $filesystem->copy('from.txt', 'from.txt');
    }
    /**
     * @test
     */
    public function failing_same_paths_for_move(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), [Config::OPTION_MOVE_IDENTICAL_PATH => Resolve_Identical_Path_Conflict::FAIL]);
        $this->expect_exception_object(Unable_To_Move_File::from_location_to('from.txt', 'from.txt'));
        $filesystem->move('from.txt', 'from.txt');
    }
    /**
     * @test
     */
    public function failing_same_paths_for_copy(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), [Config::OPTION_COPY_IDENTICAL_PATH => Resolve_Identical_Path_Conflict::FAIL]);
        $this->expect_exception_object(Unable_To_Copy_File::from_location_to('from.txt', 'from.txt'));
        $filesystem->copy('from.txt', 'from.txt');
    }
    /**
     * @test
     */
    public function unable_to_get_checksum_directory(): void
    {
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter());
        $filesystem->create_directory('foo');
        $this->expect_exception(Unable_To_Provide_Checksum::class);
        $filesystem->checksum('foo');
    }
    /**
     * @test
     *
     * @dataProvider fileMoveOrCopyScenarios
     */
    public function moving_a_file_with_visibility_scenario(array $main_config, array $move_config, ?string $write_visibility, string $expected_visibility): void
    {
        // arrange
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), $main_config);
        $write_config = $write_visibility ? ['visibility' => $write_visibility] : [];
        $filesystem->write('from.txt', 'contents', $write_config);
        // act
        $filesystem->move('from.txt', 'to.txt', $move_config);
        // assert
        $this->assert_equals($expected_visibility, $filesystem->visibility('to.txt'));
    }
    /**
     * @test
     *
     * @dataProvider fileMoveOrCopyScenarios
     */
    public function copying_a_file_with_visibility_scenario(array $main_config, array $copy_config, ?string $write_visibility, string $expected_visibility): void
    {
        // arrange
        $filesystem = new Filesystem(new In_Memory_Filesystem_Adapter(), $main_config);
        $write_config = $write_visibility ? ['visibility' => $write_visibility] : [];
        $filesystem->write('from.txt', 'contents', $write_config);
        // act
        $filesystem->copy('from.txt', 'to.txt', $copy_config);
        // assert
        $this->assert_equals($expected_visibility, $filesystem->visibility('to.txt'));
    }
    public static function file_move_or_copy_scenarios(): iterable
    {
        yield 'retain visibility, write default, default private' => [['retain_visibility' => true, 'visibility' => 'private'], [], null, 'private'];
        yield 'retain visibility, write default, default public' => [['retain_visibility' => true, 'visibility' => 'public'], [], null, 'public'];
        yield 'retain visibility, write public, default private' => [['retain_visibility' => true, 'visibility' => 'private'], [], 'public', 'public'];
        yield 'retain visibility, write private, default public' => [['retain_visibility' => true, 'visibility' => 'public'], [], 'private', 'private'];
        yield 'retain visibility, write default, default private, execute public' => [['retain_visibility' => true, 'visibility' => 'private'], ['visibility' => 'public'], null, 'public'];
        yield 'retain visibility, write default, default public, execute private' => [['retain_visibility' => true, 'visibility' => 'public'], ['visibility' => 'private'], null, 'private'];
        yield 'retain visibility, write public, default private, execute private' => [['retain_visibility' => true, 'visibility' => 'private'], ['visibility' => 'private'], 'public', 'private'];
        yield 'retain visibility, write private, default public, execute public' => [['retain_visibility' => true, 'visibility' => 'public'], ['visibility' => 'public'], 'private', 'public'];
        yield 'do not retain visibility, write default, default private' => [['retain_visibility' => false, 'visibility' => 'private'], [], null, 'private'];
        yield 'do not retain visibility, write default, default public' => [['retain_visibility' => false, 'visibility' => 'public'], [], null, 'public'];
        yield 'do not retain visibility, write public, default private' => [['retain_visibility' => false, 'visibility' => 'private'], [], 'public', 'private'];
        yield 'do not retain visibility, write private, default public' => [['retain_visibility' => false, 'visibility' => 'public'], [], 'private', 'public'];
        yield 'do not retain visibility, write default, default private, execute public' => [['retain_visibility' => false, 'visibility' => 'private'], ['visibility' => 'public'], null, 'public'];
        yield 'do not retain visibility, write default, default public, execute private' => [['retain_visibility' => false, 'visibility' => 'public'], ['visibility' => 'private'], null, 'private'];
        yield 'do not retain visibility, write public, default private, execute public' => [['retain_visibility' => false, 'visibility' => 'private'], ['visibility' => 'public'], 'public', 'public'];
        yield 'do not retain visibility, write private, default public, execute private' => [['retain_visibility' => false, 'visibility' => 'public'], ['visibility' => 'private'], 'private', 'private'];
    }
}