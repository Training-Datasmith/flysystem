<?php

declare (strict_types=1);
namespace League\Flysystem\Web_Dav;

use League\Flysystem\Filesystem_Adapter;
use Sabre\DAV\Client;
class Sabre_Server_Test extends Web_Dav_Adapter_Test_Case
{
    protected static function create_filesystem_adapter(): Filesystem_Adapter
    {
        $client = new Client(['baseUri' => 'http://localhost:4040/']);
        return new Web_Dav_Adapter($client, 'directory/prefix');
    }
}