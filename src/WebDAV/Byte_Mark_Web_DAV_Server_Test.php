<?php

declare (strict_types=1);
namespace League\Flysystem\Web_Dav;

use League\Flysystem\Filesystem_Adapter;
class Byte_Mark_Web_Dav_Server_Test extends Web_Dav_Adapter_Test_Case
{
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        if (($_ENV['TEST_WEBDAV'] ?? '') !== 'YES') {
            self::mark_test_skipped('Library regression');
        }
        $client = new Url_Prefixing_Client_Stub(['baseUri' => 'http://localhost:4080/', 'userName' => 'alice', 'password' => 'secret1234']);
        return new Web_Dav_Adapter($client, manualCopy: true, manualMove: true);
    }
}