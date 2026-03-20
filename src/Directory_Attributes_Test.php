<?php

declare (strict_types=1);
namespace League\Flysystem;

use Php_Unit\Framework\Test_Case;
/**
 * @group core
 */
class Directory_Attributes_Test extends Test_Case
{
    /**
     * @test
     */
    public function exposing_some_values(): void
    {
        $attrs = new Directory_Attributes('some/path');
        $this->assert_true($attrs->is_dir());
        $this->assert_false($attrs->is_file());
        $this->assert_equals(Storage_Attributes::TYPE_DIRECTORY, $attrs->type());
        $this->assert_equals('some/path', $attrs->path());
        $this->assert_null($attrs->visibility());
    }
    /**
     * @test
     */
    public function exposing_visibility(): void
    {
        $attrs = new Directory_Attributes('some/path', Visibility::PRIVATE);
        $this->assert_equals(Visibility::PRIVATE, $attrs->visibility());
    }
    /**
     * @test
     */
    public function exposing_last_modified(): void
    {
        $attrs = new Directory_Attributes('some/path', null, $timestamp = time());
        $this->assert_equals($timestamp, $attrs->last_modified());
    }
    /**
     * @test
     */
    public function exposing_extra_meta_data(): void
    {
        $attrs = new Directory_Attributes('some/path', null, null, ['key' => 'value']);
        $this->assert_equals(['key' => 'value'], $attrs->extra_metadata());
    }
    /**
     * @test
     */
    public function serialization_capabilities(): void
    {
        $attrs = new Directory_Attributes('some/path');
        $payload = $attrs->jsonSerialize();
        $attrs_from_payload = Directory_Attributes::from_array($payload);
        $this->assert_equals($attrs, $attrs_from_payload);
    }
}