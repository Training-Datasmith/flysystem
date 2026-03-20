<?php

declare (strict_types=1);
namespace League\Flysystem\Url_Generation;

use League\Flysystem\Config;
use League\Flysystem\Unable_To_Generate_Public_Url;
use Php_Unit\Framework\Test_Case;
final class Chained_Public_Url_Generator_Test extends Test_Case
{
    /**
     * @test
     */
    public function can_generate_url_for_supported_generator(): void
    {
        $generator = new Chained_Public_Url_Generator([new class implements Public_Url_Generator
        {
            public function public_url(string $path, Config $config): string
            {
                throw new Unable_To_Generate_Public_Url('not supported', $path);
            }
        }, new Prefix_Public_Url_Generator('/prefix')]);
        $this->assert_same('/prefix/some/path', $generator->public_url('some/path', new Config()));
    }
    /**
     * @test
     */
    public function no_supported_generator_found_throws_exception(): void
    {
        $generator = new Chained_Public_Url_Generator([new class implements Public_Url_Generator
        {
            public function public_url(string $path, Config $config): string
            {
                throw new Unable_To_Generate_Public_Url('not supported', $path);
            }
        }]);
        $this->expect_exception(Unable_To_Generate_Public_Url::class);
        $this->expect_exception_message('Unable to generate public url for some/path: No supported public url generator found.');
        $generator->public_url('some/path', new Config());
    }
}