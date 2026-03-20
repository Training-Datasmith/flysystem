<?php

declare (strict_types=1);
namespace League\Flysystem\Web_Dav;

use League\Flysystem\Adapter_Test_Utilities\Filesystem_Adapter_Test_Case;
use League\Flysystem\Config;
use League\Flysystem\Unable_To_Move_File;
use League\Flysystem\Unable_To_Set_Visibility;
use League\Flysystem\Visibility;
use Sabre\DAV\Client;
abstract class Web_Dav_Adapter_Test_Case extends Filesystem_Adapter_Test_Case
{
    /**
     * @test
     */
    public function setting_visibility(): void
    {
        $adapter = $this->adapter();
        $this->given_we_have_an_existing_file('some/file.txt');
        $this->expect_exception(Unable_To_Set_Visibility::class);
        $adapter->set_visibility('some/file.txt', Visibility::PRIVATE);
    }
    /**
     * @test
     */
    public function overwriting_a_file(): void
    {
        $this->run_scenario(function (): void {
            $this->given_we_have_an_existing_file('path.txt', 'contents');
            $adapter = $this->adapter();
            $adapter->write('path.txt', 'new contents', new Config());
            $contents = $adapter->read('path.txt');
            $this->assert_equals('new contents', $contents);
        });
    }
    /**
     * @test
     */
    public function creating_a_directory_with_leading_and_trailing_slashes(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->create_directory('/some/directory/', new Config());
            self::assert_true($adapter->directory_exists('/some/directory/'));
        });
    }
    /**
     * @test
     */
    public function copying_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config());
            $adapter->copy('source.txt', 'destination.txt', new Config());
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function copying_a_file_again(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config());
            $adapter->copy('source.txt', 'destination.txt', new Config());
            $this->assert_true($adapter->file_exists('source.txt'));
            $this->assert_true($adapter->file_exists('destination.txt'));
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function moving_a_file(): void
    {
        $this->run_scenario(function (): void {
            $adapter = $this->adapter();
            $adapter->write('source.txt', 'contents to be copied', new Config());
            $adapter->move('source.txt', 'destination.txt', new Config());
            $this->assert_false($adapter->file_exists('source.txt'), 'After moving a file should no longer exist in the original location.');
            $this->assert_true($adapter->file_exists('destination.txt'), 'After moving, a file should be present at the new location.');
            $this->assert_equals('contents to be copied', $adapter->read('destination.txt'));
        });
    }
    /**
     * @test
     */
    public function moving_a_file_that_does_not_exist(): void
    {
        $this->expect_exception(Unable_To_Move_File::class);
        $this->run_scenario(function (): void {
            $this->adapter()->move('source.txt', 'destination.txt', new Config());
        });
    }
    /**
     * @test
     */
    public function part_of_prefix_already_exists(): void
    {
        $this->run_scenario(function (): void {
            $config = new Config();
            $adapter1 = new Web_Dav_Adapter(new Client(['baseUri' => 'http://localhost:4040/']), 'directory1/prefix1');
            $adapter1->create_directory('folder1', $config);
            self::assert_true($adapter1->directory_exists('/folder1'));
            $adapter2 = new Web_Dav_Adapter(new Client(['baseUri' => 'http://localhost:4040/']), 'directory1/prefix2');
            $adapter2->create_directory('folder2', $config);
            self::assert_true($adapter2->directory_exists('/folder2'));
        });
    }
}