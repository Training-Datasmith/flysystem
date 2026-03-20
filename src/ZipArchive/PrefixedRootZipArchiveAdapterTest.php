<?php

declare (strict_types=1);
namespace League\Flysystem\Zip_Archive;

/**
 * @group zip
 */
final class Prefixed_Root_Zip_Archive_Adapter_Test extends Zip_Archive_Adapter_Test_Case
{
    protected static function get_root(): string
    {
        return '/prefixed-path';
    }
}