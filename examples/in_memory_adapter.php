<?php

declare(strict_types=1);

/**
 * Flysystem — In-Memory Adapter Examples
 *
 * The InMemory adapter is ideal for testing. No disk I/O occurs.
 * Run: php examples/in_memory_adapter.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\In_Memory_Filesystem_Adapter;
use League\Flysystem\Visibility;

$adapter    = new In_Memory_Filesystem_Adapter();
$filesystem = new Filesystem($adapter);

// Write several files
$filesystem->write('config/app.json', json_encode(['debug' => true, 'version' => '1.0']));
$filesystem->write('config/db.json', json_encode(['host' => 'localhost', 'port' => 5432]));
$filesystem->write('data/users.csv', "id,name\n1,Alice\n2,Bob\n");

// List all contents
echo "Contents:\n";
foreach ($filesystem->list_contents('', true) as $item) {
    echo '  ' . $item->path() . "\n";
}

// Read and decode JSON
$app = json_decode($filesystem->read('config/app.json'), true);
echo "\nApp debug mode: " . ($app['debug'] ? 'on' : 'off') . "\n";

// Demonstrate that two filesystems are isolated
$other = new Filesystem(new In_Memory_Filesystem_Adapter());
echo "Other filesystem has config/app.json: "
    . ($other->file_exists('config/app.json') ? 'yes' : 'no') . "\n";

echo "\nDone.\n";
