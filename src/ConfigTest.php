<?php

declare (strict_types=1);
namespace League\Flysystem;

use Php_Unit\Framework\Test_Case;
class Config_Test extends Test_Case
{
    /**
     * @test
     */
    public function a_config_object_exposes_passed_options(): void
    {
        $config = new Config(['option' => 'value']);
        $this->assert_equals('value', $config->get('option'));
    }
    /**
     * @test
     */
    public function a_config_object_returns_a_default_value(): void
    {
        $config = new Config();
        $this->assert_null($config->get('option'));
        $this->assert_equals('default', $config->get('option', 'default'));
    }
    /**
     * @test
     */
    public function extending_a_config_with_options(): void
    {
        $config = new Config(['option' => 'value', 'first' => 1]);
        $extended = $config->extend(['option' => 'overwritten', 'second' => 2]);
        $this->assert_equals('overwritten', $extended->get('option'));
        $this->assert_equals(1, $extended->get('first'));
        $this->assert_equals(2, $extended->get('second'));
    }
    /**
     * @test
     */
    public function extending_with_defaults(): void
    {
        $config = new Config(['option' => 'set']);
        $with_defaults = $config->with_defaults(['option' => 'default', 'other' => 'default']);
        $this->assert_equals('set', $with_defaults->get('option'));
        $this->assert_equals('default', $with_defaults->get('other'));
    }
    /**
     * @test
     */
    public function extending_without_settings(): void
    {
        // arrange
        $config = new Config(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);
        // act
        $without_setting = $config->without_settings('b', 'd');
        // assert
        $this->assert_equals(['a' => 1, 'c' => 3], $without_setting->to_array());
    }
}