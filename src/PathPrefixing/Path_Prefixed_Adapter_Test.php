<?php

declare (strict_types=1);
namespace League\Flysystem\Path_Prefixing;

use function iterator_to_array;
use League\Flysystem\Checksum_Provider;
use League\Flysystem\Config;
use League\Flysystem\In_Memory\In_Memory_Filesystem_Adapter;
use League\Flysystem\Unable_To_Generate_Public_Url;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use League\Flysystem\Visibility;
use Php_Unit\Framework\Test_Case;
class Path_Prefixed_Adapter_Test extends Test_Case
{
    public function test_prefix(): void
    {
        $adapter = new In_Memory_Filesystem_Adapter();
        $prefix = new Path_Prefixed_Adapter($adapter, 'foo');
        $prefix->write('foo.txt', 'bla', new Config());
        static::assert_true($prefix->file_exists('foo.txt'));
        static::assert_false($prefix->directory_exists('foo.txt'));
        static::assert_true($adapter->file_exists('foo/foo.txt'));
        static::assert_false($adapter->directory_exists('foo/foo.txt'));
        static::assert_same('bla', $prefix->read('foo.txt'));
        static::assert_same('bla', stream_get_contents($prefix->read_stream('foo.txt')));
        static::assert_same('text/plain', $prefix->mime_type('foo.txt')->mime_type());
        static::assert_same(3, $prefix->file_size('foo.txt')->file_size());
        static::assert_same(Visibility::PUBLIC, $prefix->visibility('foo.txt')->visibility());
        $prefix->set_visibility('foo.txt', Visibility::PRIVATE);
        static::assert_same(Visibility::PRIVATE, $prefix->visibility('foo.txt')->visibility());
        static::assert_equals_with_delta($prefix->last_modified('foo.txt')->last_modified(), time(), 2);
        $prefix->copy('foo.txt', 'bla.txt', new Config());
        static::assert_true($prefix->file_exists('bla.txt'));
        $prefix->create_directory('dir', new Config());
        static::assert_true($prefix->directory_exists('dir'));
        static::assert_false($prefix->directory_exists('dir2'));
        $prefix->delete_directory('dir');
        static::assert_false($prefix->directory_exists('dir'));
        $prefix->move('bla.txt', 'bla2.txt', new Config());
        static::assert_false($prefix->file_exists('bla.txt'));
        static::assert_true($prefix->file_exists('bla2.txt'));
        $prefix->delete('bla2.txt');
        static::assert_false($prefix->file_exists('bla2.txt'));
        $prefix->create_directory('test', new Config());
        $files = iterator_to_array($prefix->list_contents('', true));
        static::assert_count(2, $files);
    }
    public function test_write_stream(): void
    {
        $adapter = new In_Memory_Filesystem_Adapter();
        $prefix = new Path_Prefixed_Adapter($adapter, 'foo');
        $tmp_file = sys_get_temp_dir() . '/' . uniqid('test', true);
        file_put_contents($tmp_file, 'test');
        $prefix->write_stream('a.txt', fopen($tmp_file, 'rb'), new Config());
        static::assert_true($prefix->file_exists('a.txt'));
        static::assert_same('test', $prefix->read('a.txt'));
        static::assert_same('test', stream_get_contents($prefix->read_stream('a.txt')));
        unlink($tmp_file);
    }
    public function test_empty_prefix(): void
    {
        static::expect_exception(\InvalidArgumentException::class);
        new Path_Prefixed_Adapter(new In_Memory_Filesystem_Adapter(), '');
    }
    /**
     * @test
     */
    public function generating_a_public_url(): void
    {
        $adapter = new class extends In_Memory_Filesystem_Adapter implements Public_Url_Generator
        {
            public function public_url(string $path, Config $config): string
            {
                return 'memory://' . ltrim($path, '/');
            }
        };
        $prefixed_adapter = new Path_Prefixed_Adapter($adapter, 'prefix');
        $url = $prefixed_adapter->public_url('/path.txt', new Config());
        self::assert_equals('memory://prefix/path.txt', $url);
    }
    /**
     * @test
     */
    public function calculate_checksum_using_decorated_adapter(): void
    {
        $adapter = new class extends In_Memory_Filesystem_Adapter implements Checksum_Provider
        {
            public function checksum(string $path, Config $config): string
            {
                return hash('md5', $this->read($path));
            }
        };
        $prefixed_adapter = new Path_Prefixed_Adapter($adapter, 'prefix');
        $prefixed_adapter->write('foo.txt', 'bla', new Config());
        self::assert_equals('128ecf542a35ac5270a87dc740918404', $prefixed_adapter->checksum('foo.txt', new Config()));
    }
    /**
     * @test
     */
    public function calculate_checksum_using_current_adapter(): void
    {
        $adapter = new In_Memory_Filesystem_Adapter();
        $prefixed_adapter = new Path_Prefixed_Adapter($adapter, 'prefix');
        $prefixed_adapter->write('foo.txt', 'bla', new Config());
        self::assert_equals('128ecf542a35ac5270a87dc740918404', hash('md5', 'bla'));
        self::assert_equals('128ecf542a35ac5270a87dc740918404', $prefixed_adapter->checksum('foo.txt', new Config()));
    }
    /**
     * @test
     */
    public function failing_to_generate_a_public_url(): void
    {
        $prefixed_adapter = new Path_Prefixed_Adapter(new In_Memory_Filesystem_Adapter(), 'prefix');
        $this->expect_exception(Unable_To_Generate_Public_Url::class);
        $prefixed_adapter->public_url('/path.txt', new Config());
    }
}