<?php

declare (strict_types=1);
namespace League\Flysystem\In_Memory;

use League\Flysystem\Config;
use League\Flysystem\Filesystem_Adapter;
class Static_In_Memory_Adapter_Registry_Test extends In_Memory_Filesystem_Adapter_Test
{
    /**
     * @test
     */
    public function using_different_name_to_segment_adapters(): void
    {
        $first = Static_In_Memory_Adapter_Registry::get();
        $second = Static_In_Memory_Adapter_Registry::get('second');
        $first->write('foo.txt', 'foo', new Config());
        $second->write('bar.txt', 'bar', new Config());
        $this->assert_true($first->file_exists('foo.txt'));
        $this->assert_false($first->file_exists('bar.txt'));
        $this->assert_true($second->file_exists('bar.txt'));
        $this->assert_false($second->file_exists('foo.txt'));
    }
    /**
     * @test
     */
    public function files_persist_between_instances(): void
    {
        $first = Static_In_Memory_Adapter_Registry::get();
        $second = Static_In_Memory_Adapter_Registry::get('second');
        $first->write('foo.txt', 'foo', new Config());
        $second->write('bar.txt', 'bar', new Config());
        $this->assert_true($first->file_exists('foo.txt'));
        $this->assert_true($second->file_exists('bar.txt'));
        $first = Static_In_Memory_Adapter_Registry::get();
        $second = Static_In_Memory_Adapter_Registry::get('second');
        $this->assert_true($first->file_exists('foo.txt'));
        $this->assert_true($second->file_exists('bar.txt'));
    }
    protected function tear_down(): void
    {
        Static_In_Memory_Adapter_Registry::delete_all_filesystems();
    }
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        return Static_In_Memory_Adapter_Registry::get();
    }
}