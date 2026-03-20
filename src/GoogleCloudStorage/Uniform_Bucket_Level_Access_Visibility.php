<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

use Google\Cloud\Storage\Storage_Object;
class Uniform_Bucket_Level_Access_Visibility implements Visibility_Handler
{
    public const NO_PREDEFINED_VISIBILITY = 'noPredefinedVisibility';
    public function set_visibility(Storage_Object $object, string $visibility): void
    {
        // noop
    }
    public function determine_visibility(Storage_Object $object): string
    {
        return self::NO_PREDEFINED_VISIBILITY;
    }
    public function visibility_to_predefined_acl(string $visibility): string
    {
        return self::NO_PREDEFINED_VISIBILITY;
    }
}