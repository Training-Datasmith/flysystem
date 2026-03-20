<?php

declare (strict_types=1);
namespace League\Flysystem\Unix_Visibility;

use League\Flysystem\Invalid_Visibility_Provided;
use League\Flysystem\Visibility;
use Php_Unit\Framework\Test_Case;
/**
 * @group unix-visibility
 */
class Portable_Visibility_Converter_Test extends Test_Case
{
    /**
     * @test
     */
    public function determining_visibility_for_a_file(): void
    {
        $interpreter = new Portable_Visibility_Converter();
        $this->assert_equals(0644, $interpreter->for_file(Visibility::PUBLIC));
        $this->assert_equals(0600, $interpreter->for_file(Visibility::PRIVATE));
    }
    /**
     * @test
     */
    public function determining_an_incorrect_visibility_for_a_file(): void
    {
        $this->expect_exception(Invalid_Visibility_Provided::class);
        $interpreter = new Portable_Visibility_Converter();
        $interpreter->for_file('incorrect');
    }
    /**
     * @test
     */
    public function determining_visibility_for_a_directory(): void
    {
        $interpreter = new Portable_Visibility_Converter();
        $this->assert_equals(0755, $interpreter->for_directory(Visibility::PUBLIC));
        $this->assert_equals(0700, $interpreter->for_directory(Visibility::PRIVATE));
    }
    /**
     * @test
     */
    public function determining_an_incorrect_visibility_for_a_directory(): void
    {
        $this->expect_exception(Invalid_Visibility_Provided::class);
        $interpreter = new Portable_Visibility_Converter();
        $interpreter->for_directory('incorrect');
    }
    /**
     * @test
     */
    public function inversing_for_a_file(): void
    {
        $interpreter = new Portable_Visibility_Converter();
        $this->assert_equals(Visibility::PUBLIC, $interpreter->inverse_for_file(0644));
        $this->assert_equals(Visibility::PRIVATE, $interpreter->inverse_for_file(0600));
        $this->assert_equals(Visibility::PUBLIC, $interpreter->inverse_for_file(0404));
    }
    /**
     * @test
     */
    public function inversing_for_a_directory(): void
    {
        $interpreter = new Portable_Visibility_Converter();
        $this->assert_equals(Visibility::PUBLIC, $interpreter->inverse_for_directory(0755));
        $this->assert_equals(Visibility::PRIVATE, $interpreter->inverse_for_directory(0700));
        $this->assert_equals(Visibility::PUBLIC, $interpreter->inverse_for_directory(0404));
    }
    /**
     * @test
     */
    public function determining_default_for_directories(): void
    {
        $interpreter = new Portable_Visibility_Converter();
        $this->assert_equals(0700, $interpreter->default_for_directories());
        $interpreter = new Portable_Visibility_Converter(0644, 0600, 0755, 0700, Visibility::PUBLIC);
        $this->assert_equals(0755, $interpreter->default_for_directories());
    }
    /**
     * @test
     */
    public function creating_from_array(): void
    {
        $interpreter = Portable_Visibility_Converter::from_array(['file' => ['public' => 0640, 'private' => 0604], 'dir' => ['public' => 0740, 'private' => 7604]]);
        $this->assert_equals(0640, $interpreter->for_file(Visibility::PUBLIC));
        $this->assert_equals(0604, $interpreter->for_file(Visibility::PRIVATE));
        $this->assert_equals(0740, $interpreter->for_directory(Visibility::PUBLIC));
        $this->assert_equals(7604, $interpreter->for_directory(Visibility::PRIVATE));
    }
}