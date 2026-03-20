<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

use Zip_Archive;
class Stub_Zip_Archive extends Zip_Archive
{
    private bool $fail_next_directory_creation = false;
    private bool $fail_next_write = false;
    private bool $fail_next_delete_name = false;
    private bool $fail_when_setting_visibility = false;
    private bool $fail_when_deleting_an_index = false;
    public function fail_next_directory_creation(): void
    {
        $this->fail_next_directory_creation = true;
    }
    /**
     * @param string $dirname
     * @param int    $flags
     */
    public function add_empty_dir($dirname, $flags = 0): bool
    {
        if ($this->fail_next_directory_creation) {
            $this->fail_next_directory_creation = false;
            return false;
        }
        return parent::add_empty_dir($dirname);
    }
    public function fail_next_write(): void
    {
        $this->fail_next_write = true;
    }
    /**
     * @param string $localname
     * @param string $contents
     * @param int    $flags
     */
    public function add_from_string($localname, $contents, $flags = 0): bool
    {
        if ($this->fail_next_write) {
            $this->fail_next_write = false;
            return false;
        }
        return parent::add_from_string($localname, $contents);
    }
    public function fail_next_delete_name(): void
    {
        $this->fail_next_delete_name = true;
    }
    public function delete_name($name): bool
    {
        if ($this->fail_next_delete_name) {
            $this->fail_next_delete_name = false;
            return false;
        }
        return parent::delete_name($name);
    }
    public function fail_when_setting_visibility(): void
    {
        $this->fail_when_setting_visibility = true;
    }
    public function set_external_attributes_name($name, $opsys, $attr, $flags = null): bool
    {
        if ($this->fail_when_setting_visibility) {
            $this->fail_when_setting_visibility = false;
            return false;
        }
        return parent::set_external_attributes_name($name, $opsys, $attr);
    }
    public function fail_when_deleting_an_index(): void
    {
        $this->fail_when_deleting_an_index = true;
    }
    public function delete_index($index): bool
    {
        if ($this->fail_when_deleting_an_index) {
            $this->fail_when_deleting_an_index = false;
            return false;
        }
        return parent::delete_index($index);
    }
}