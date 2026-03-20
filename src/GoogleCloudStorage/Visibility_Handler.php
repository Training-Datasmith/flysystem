<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use Google\Cloud\Storage\Storage_Object;
interface Visibility_Handler
{
    public function set_visibility(Storage_Object $object, string $visibility): void;
    public function determine_visibility(Storage_Object $object): string;
    public function visibility_to_predefined_acl(string $visibility): string;
}