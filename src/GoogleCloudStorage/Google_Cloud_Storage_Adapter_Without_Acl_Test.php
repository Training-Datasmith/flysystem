<?php

declare (strict_types=1);
namespace League\Flysystem\Google_Cloud_Storage;

class Google_Cloud_Storage_Adapter_Without_Acl_Test extends Google_Cloud_Storage_Adapter_Test
{
    protected static function visibility_handler(): Visibility_Handler
    {
        return new Uniform_Bucket_Level_Access_Visibility();
    }
    protected static function bucket_name(): string|array|false
    {
        return 'no-acl-bucket-for-ci';
    }
}