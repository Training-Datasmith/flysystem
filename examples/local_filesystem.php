<?php

declare(strict_types=1);

/**
 * Flysystem — Local Filesystem Examples
 *
 * Demonstrates file operations, directory listing, visibility, and move/copy.
 * Run: php examples/local_filesystem.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use League\Flysystem\Filesystem;
use League\Flysystem\Local\Local_Filesystem_Adapter;
use League\Flysystem\Visibility;
use League\Flysystem\Filesystem_Exception;

$adapter    = new Local_Filesystem_Adapter(sys_get_temp_dir() . '/flysystem-demo');
$filesystem = new Filesystem($adapter);

// ── 1. Write and read a file ─────────────────────────────────────────────────

$filesystem->write('notes/hello.txt', "Hello, Flysystem!\nLine two.\n");

echo "File exists: " . ($filesystem->file_exists('notes/hello.txt') ? 'yes' : 'no') . "\n";
echo "Contents: " . $filesystem->read('notes/hello.txt');

// ── 2. Write with explicit visibility ───────────────────────────────────────

$filesystem->write('public/readme.md', '# Public file', [
    'visibility' => Visibility::PUBLIC,
]);

echo "Visibility: " . $filesystem->visibility('public/readme.md') . "\n";

// ── 3. Stream write / stream read ───────────────────────────────────────────

$tmpFile = tmpfile();
fwrite($tmpFile, str_repeat('A', 1024 * 4)); // 4 KB
rewind($tmpFile);

$filesystem->write_stream('uploads/large.bin', $tmpFile);

$stream = $filesystem->read_stream('uploads/large.bin');
echo "Read stream bytes: " . strlen(stream_get_contents($stream)) . "\n";
fclose($stream);

// ── 4. List contents of a directory ─────────────────────────────────────────

echo "\nDirectory listing:\n";
foreach ($filesystem->list_contents('', true) as $item) {
    $type = $item->isFile() ? 'file' : 'dir ';
    echo "  [{$type}] {$item->path()}\n";
}

// ── 5. Move and copy ─────────────────────────────────────────────────────────

$filesystem->copy('notes/hello.txt', 'archive/hello.txt');
$filesystem->move('uploads/large.bin', 'archive/large.bin');

echo "\nAfter move/copy:\n";
echo "  notes/hello.txt exists: " . ($filesystem->file_exists('notes/hello.txt') ? 'yes' : 'no') . "\n";
echo "  archive/hello.txt exists: " . ($filesystem->file_exists('archive/hello.txt') ? 'yes' : 'no') . "\n";
echo "  uploads/large.bin exists: " . ($filesystem->file_exists('uploads/large.bin') ? 'yes' : 'no') . "\n";

// ── 6. File metadata ─────────────────────────────────────────────────────────

echo "\nFile metadata for archive/hello.txt:\n";
echo "  size:          " . $filesystem->file_size('archive/hello.txt') . " bytes\n";
echo "  mime type:     " . $filesystem->mime_type('archive/hello.txt') . "\n";
echo "  last modified: " . date('Y-m-d H:i:s', $filesystem->last_modified('archive/hello.txt')) . "\n";

// ── 7. Delete ────────────────────────────────────────────────────────────────

$filesystem->delete_directory('archive');
$filesystem->delete_directory('notes');
$filesystem->delete_directory('public');

echo "\nCleanup complete.\n";
