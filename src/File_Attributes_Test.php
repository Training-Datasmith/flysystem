<?php

declare (strict_types=1);
namespace League\Flysystem;

use Generator;
use Php_Unit\Framework\Test_Case;
use RuntimeException;
use function time;
/**
 * @group core
 */
class File_Attributes_Test extends Test_Case
{
    /**
     * @test
     */
    public function exposing_some_values(): void
    {
        $attrs = new File_Attributes('path.txt');
        $this->assert_false($attrs->is_dir());
        $this->assert_true($attrs->is_file());
        $this->assert_equals('path.txt', $attrs->path());
        $this->assert_equals(Storage_Attributes::TYPE_FILE, $attrs->type());
        $this->assert_null($attrs->visibility());
        $this->assert_null($attrs->file_size());
        $this->assert_null($attrs->mime_type());
        $this->assert_null($attrs->last_modified());
    }
    /**
     * @test
     */
    public function exposing_all_values(): void
    {
        $attrs = new File_Attributes('path.txt', 1234, Visibility::PRIVATE, $now = time(), 'plain/text', ['key' => 'value']);
        $this->assert_equals('path.txt', $attrs->path());
        $this->assert_equals(Storage_Attributes::TYPE_FILE, $attrs->type());
        $this->assert_equals(Visibility::PRIVATE, $attrs->visibility());
        $this->assert_equals(1234, $attrs->file_size());
        $this->assert_equals($now, $attrs->last_modified());
        $this->assert_equals('plain/text', $attrs->mime_type());
        $this->assert_equals(['key' => 'value'], $attrs->extra_metadata());
    }
    /**
     * @test
     */
    public function implements_array_access(): void
    {
        $attrs = new File_Attributes('path.txt', 1234, Visibility::PRIVATE, $now = time(), 'plain/text', ['key' => 'value']);
        $this->assert_equals('path.txt', $attrs['path']);
        $this->assert_true(isset($attrs['path']));
        $this->assert_equals(Storage_Attributes::TYPE_FILE, $attrs['type']);
        $this->assert_equals(Visibility::PRIVATE, $attrs['visibility']);
        $this->assert_equals(1234, $attrs['file_size']);
        $this->assert_equals($now, $attrs['last_modified']);
        $this->assert_equals('plain/text', $attrs['mimeType']);
        $this->assert_equals(['key' => 'value'], $attrs['extra_metadata']);
    }
    /**
     * @test
     */
    public function properties_can_not_be_set(): void
    {
        $this->expect_exception(RuntimeException::class);
        $attrs = new File_Attributes('path.txt');
        $attrs['visibility'] = Visibility::PUBLIC;
    }
    /**
     * @test
     */
    public function properties_can_not_be_unset(): void
    {
        $this->expect_exception(RuntimeException::class);
        $attrs = new File_Attributes('path.txt');
        unset($attrs['visibility']);
    }
    /**
     * @dataProvider data_provider_for_json_transformation
     *
     * @test
     */
    public function json_transformations(File_Attributes $attributes): void
    {
        $payload = $attributes->jsonSerialize();
        $new_attributes = File_Attributes::from_array($payload);
        $this->assert_equals($attributes, $new_attributes);
    }
    public static function data_provider_for_json_transformation(): Generator
    {
        yield [new File_Attributes('path.txt', 1234, Visibility::PRIVATE, $now = time(), 'plain/text', ['key' => 'value'])];
        yield [new File_Attributes('another.txt')];
    }
}