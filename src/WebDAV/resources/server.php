<?php

declare (strict_types=1);
use Sabre\DAV\FS\Directory;
use Sabre\DAV\Server;
include __DIR__ . '/../../../vendor/autoload.php';
error_reporting(E_ALL ^ E_DEPRECATED);
$root_path = __DIR__ . '/data';
if (!is_dir($root_path)) {
    mkdir($root_path);
}
$root_directory = new Directory($root_path);
$server = new Server($root_directory);
$server->add_plugin(new Sabre\DAV\Browser\Plugin());
if (!str_contains($_SERVER['REQUEST_URI'], 'unknown-mime-type.md5')) {
    $guesser = new Sabre\DAV\Browser\Guess_Content_Type();
    $guesser->extension_map['svg'] = 'image/svg+xml';
    $server->add_plugin($guesser);
}
$server->start();