<?php

declare (strict_types=1);
namespace League\Flysystem\Read_Only;

use League\Flysystem\Config;
use League\Flysystem\File_Attributes;
use League\Flysystem\In_Memory\In_Memory_Filesystem_Adapter;
use League\Flysystem\Unable_To_Copy_File;
use League\Flysystem\Unable_To_Create_Directory;
use League\Flysystem\Unable_To_Delete_Directory;
use League\Flysystem\Unable_To_Delete_File;
use League\Flysystem\Unable_To_Generate_Public_Url;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Unable_To_Write_File;
use League\Flysystem\Url_Generation\Public_Url_Generator;
use function ltrim;
use Php_Unit\Framework\Test_Case;
class Read_Only_Filesystem_Adapter_Test extends Test_Case
{
    /**
     * @test
     */
    public function can_perform_read_operations(): void
    {
        $adapter = $this->real_adapter();
        $adapter->write('foo/bar.txt', 'content', new Config());
        $adapter = new Read_Only_Filesystem_Adapter($adapter);
        $this->assert_true($adapter->file_exists('foo/bar.txt'));
        $this->assert_true($adapter->directory_exists('foo'));
        $this->assert_same('content', $adapter->read('foo/bar.txt'));
        $this->assert_same('content', \stream_get_contents($adapter->read_stream('foo/bar.txt')));
        $this->assert_instance_of(File_Attributes::class, $adapter->visibility('foo/bar.txt'));
        $this->assert_instance_of(File_Attributes::class, $adapter->mime_type('foo/bar.txt'));
        $this->assert_instance_of(File_Attributes::class, $adapter->last_modified('foo/bar.txt'));
        $this->assert_instance_of(File_Attributes::class, $adapter->file_size('foo/bar.txt'));
        $this->assert_count(1, iterator_to_array($adapter->list_contents('foo', true)));
    }
    /**
     * @test
     */
    public function cannot_write_stream(): void
    {
        $adapter = new Read_Only_Filesystem_Adapter($this->real_adapter());
        $this->expect_exception(Unable_To_Write_File::class);
        // @phpstan-ignore-next-line
        $adapter->write_stream('foo', 'content', new Config());
    }
    /**
     * @test
     */
    public function cannot_write(): void
    {
        $adapter = new Read_Only_Filesystem_Adapter($this->real_adapter());
        $this->expect_exception(Unable_To_Write_File::class);
        $adapter->write('foo', 'content', new Config());
    }
    /**
     * @test
     */
    public function cannot_delete_file(): void
    {
        $adapter = $this->real_adapter();
        $adapter->write('foo', 'content', new Config());
        $adapter = new Read_Only_Filesystem_Adapter($adapter);
        $this->expect_exception(Unable_To_Delete_File::class);
        $adapter->delete('foo');
    }
    /**
     * @test
     */
    public function cannot_delete_directory(): void
    {
        $adapter = $this->real_adapter();
        $adapter->create_directory('foo', new Config());
        $adapter = new Read_Only_Filesystem_Adapter($adapter);
        $this->expect_exception(Unable_To_Delete_Directory::class);
        $adapter->delete_directory('foo');
    }
    /**
     * @test
     */
    public function cannot_create_directory(): void
    {
        $adapter = new Read_Only_Filesystem_Adapter($this->real_adapter());
        $this->expect_exception(Unable_To_Create_Directory::class);
        $adapter->create_directory('foo', new Config());
    }
    /**
     * @test
     */
    public function cannot_set_visibility(): void
    {
        $adapter = $this->real_adapter();
        $adapter->write('foo', 'content', new Config());
        $adapter = new Read_Only_Filesystem_Adapter($adapter);
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $adapter->set_visibility('foo', 'private');
    }
    /**
     * @test
     */
    public function cannot_move(): void
    {
        $adapter = $this->real_adapter();
        $adapter->write('foo', 'content', new Config());
        $adapter = new Read_Only_Filesystem_Adapter($adapter);
        $this->expect_exception(Unable_To_Move_File::class);
        $adapter->move('foo', 'bar', new Config());
    }
    /**
     * @test
     */
    public function cannot_copy(): void
    {
        $adapter = $this->real_adapter();
        $adapter->write('foo', 'content', new Config());
        $adapter = new Read_Only_Filesystem_Adapter($adapter);
        $this->expect_exception(Unable_To_Copy_File::class);
        $adapter->copy('foo', 'bar', new Config());
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
        $read_only_adapter = new Read_Only_Filesystem_Adapter($adapter);
        $url = $read_only_adapter->public_url('/path.txt', new Config());
        self::assert_equals('memory://path.txt', $url);
    }
    /**
     * @test
     */
    public function failing_to_generate_a_public_url(): void
    {
        $adapter = new Read_Only_Filesystem_Adapter(new In_Memory_Filesystem_Adapter());
        $this->expect_exception(Unable_To_Generate_Public_Url::class);
        $adapter->public_url('/path.txt', new Config());
    }
    private function real_adapter(): In_Memory_Filesystem_Adapter
    {
        return new In_Memory_Filesystem_Adapter();
    }
}