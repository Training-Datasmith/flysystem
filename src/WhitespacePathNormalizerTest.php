<?php

declare (strict_types=1);
namespace League\Flysystem;

use Php_Unit\Framework\Test_Case;
class Whitespace_Path_Normalizer_Test extends Test_Case
{
    private \League\Flysystem\Whitespace_Path_Normalizer $normalizer;
    protected function set_up(): void
    {
        $this->normalizer = new Whitespace_Path_Normalizer();
    }
    /**
     * @test
     *
     * @dataProvider  pathProvider
     */
    public function path_normalizing(string $input, string $expected): void
    {
        $result = $this->normalizer->normalize_path($input);
        $double = $this->normalizer->normalize_path($this->normalizer->normalize_path($input));
        $this->assert_equals($expected, $result);
        $this->assert_equals($expected, $double);
    }
    /**
     * @return array<array<string>>
     */
    public static function path_provider(): array
    {
        return [['.', ''], ['/path/to/dir/.', 'path/to/dir'], ['/dirname/', 'dirname'], ['dirname/..', ''], ['dirname/../', ''], ['dirname./', 'dirname.'], ['dirname/./', 'dirname'], ['dirname/.', 'dirname'], ['./dir/../././', ''], ['/something/deep/../../dirname', 'dirname'], ['00004869/files/other/10-75..stl', '00004869/files/other/10-75..stl'], ['/dirname//subdir///subsubdir', 'dirname/subdir/subsubdir'], ['\dirname\\\\subdir\\\\\\subsubdir', 'dirname/subdir/subsubdir'], ['\\\\some\shared\\\\drive', 'some/shared/drive'], ['C:\dirname\\\\subdir\\\\\\subsubdir', 'C:/dirname/subdir/subsubdir'], ['C:\\\\dirname\subdir\\\\subsubdir', 'C:/dirname/subdir/subsubdir'], ['example/path/..txt', 'example/path/..txt'], ['\example\path.txt', 'example/path.txt'], ['\example\..\path.txt', 'path.txt']];
    }
    /**
     * @test
     *
     * @dataProvider invalidPathProvider
     */
    public function guarding_against_path_traversal(string $input): void
    {
        $this->expect_exception(Path_Traversal_Detected::class);
        $this->normalizer->normalize_path($input);
    }
    /**
     * @test
     *
     * @dataProvider dpFunkyWhitespacePaths
     */
    public function rejecting_funky_whitespace(string $path): void
    {
        self::expect_exception(Corrupted_Path_Detected::class);
        $this->normalizer->normalize_path($path);
    }
    public static function dp_funky_whitespace_paths(): iterable
    {
        return [["some\x00/path.txt"], ["s\ti.php"]];
    }
    /**
     * @return array<array<string>>
     */
    public static function invalid_path_provider(): array
    {
        return [['something/../../../hehe'], ['/something/../../..'], ['..'], ['something\..\..'], ['\something\..\..\dirname']];
    }
}