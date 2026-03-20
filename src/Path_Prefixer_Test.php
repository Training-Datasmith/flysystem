<?php

declare (strict_types=1);
namespace League\Flysystem;

use Php_Unit\Framework\Test_Case;
class Path_Prefixer_Test extends Test_Case
{
    /**
     * @test
     */
    public function path_prefixing_with_a_prefix(): void
    {
        $prefixer = new Path_Prefixer('prefix');
        $prefixed_path = $prefixer->prefix_path('some/path.txt');
        $this->assert_equals('prefix/some/path.txt', $prefixed_path);
    }
    /**
     * @test
     */
    public function path_stripping_with_a_prefix(): void
    {
        $prefixer = new Path_Prefixer('prefix');
        $stripped_path = $prefixer->strip_prefix('prefix/some/path.txt');
        $this->assert_equals('some/path.txt', $stripped_path);
    }
    /**
     * @test
     *
     * @dataProvider dpRootPaths
     */
    public function an_absolute_root_path_is_supported(string $root_path, string $separator, string $path, string $expected_path): void
    {
        $prefixer = new Path_Prefixer($root_path, $separator);
        $prefixed_path = $prefixer->prefix_path($path);
        $this->assert_equals($expected_path, $prefixed_path);
    }
    public static function dp_root_paths(): iterable
    {
        yield 'unix-style root path' => ['/', '/', 'path.txt', '/path.txt'];
        yield 'windows-style root path' => ['\\', '\\', 'path.txt', '\path.txt'];
    }
    /**
     * @test
     */
    public function path_stripping_is_reversable(): void
    {
        $prefixer = new Path_Prefixer('prefix');
        $stripped_path = $prefixer->strip_prefix('prefix/some/path.txt');
        $this->assert_equals('prefix/some/path.txt', $prefixer->prefix_path($stripped_path));
        $prefixed_path = $prefixer->prefix_path('some/path.txt');
        $this->assert_equals('some/path.txt', $prefixer->strip_prefix($prefixed_path));
    }
    /**
     * @test
     */
    public function prefixing_without_a_prefix(): void
    {
        $prefixer = new Path_Prefixer('');
        $path = $prefixer->prefix_path('path/to/prefix.txt');
        $this->assert_equals('path/to/prefix.txt', $path);
        $path = $prefixer->prefix_path('/path/to/prefix.txt');
        $this->assert_equals('path/to/prefix.txt', $path);
    }
    /**
     * @test
     */
    public function prefixing_for_a_directory(): void
    {
        $prefixer = new Path_Prefixer('/prefix');
        $path = $prefixer->prefix_directory_path('something');
        $this->assert_equals('/prefix/something/', $path);
        $path = $prefixer->prefix_directory_path('');
        $this->assert_equals('/prefix/', $path);
    }
    /**
     * @test
     */
    public function prefixing_for_a_directory_without_a_prefix(): void
    {
        $prefixer = new Path_Prefixer('');
        $path = $prefixer->prefix_directory_path('something');
        $this->assert_equals('something/', $path);
        $path = $prefixer->prefix_directory_path('');
        $this->assert_equals('', $path);
    }
    /**
     * @test
     */
    public function stripping_a_directory_prefix(): void
    {
        $prefixer = new Path_Prefixer('/something/');
        $path = $prefixer->strip_directory_prefix('/something/this/');
        $this->assert_equals('this', $path);
        $path = $prefixer->strip_directory_prefix('/something/and-this\\');
        $this->assert_equals('and-this', $path);
    }
}