<?php

declare(strict_types=1);

namespace League\Flysystem\Tests\Security;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\In_Memory_Filesystem_Adapter;
use League\Flysystem\Path_Traversal_Detected;
use PHPUnit\Framework\TestCase;

/**
 * Security tests verifying that path traversal sequences are rejected before
 * they reach the underlying adapter.
 *
 * Path traversal (also known as directory traversal) is a vulnerability that
 * allows an attacker to access files outside the intended storage root by
 * including sequences like `../` in file paths.
 *
 * Flysystem prevents this by normalising all paths via Path_Normalizer before
 * delegating to the adapter. These tests document that guarantee.
 */
class Path_Traversal_Test extends TestCase
{
    private Filesystem $fs;

    protected function setUp(): void
    {
        $this->fs = new Filesystem(new In_Memory_Filesystem_Adapter());
        // Plant a legitimate file
        $this->fs->write('safe/file.txt', 'safe content');
    }

    /**
     * A classical `../` traversal in a read path must be rejected.
     */
    public function test_read_with_traversal_sequence_is_rejected(): void
    {
        $this->expectException(Path_Traversal_Detected::class);
        $this->fs->read('safe/../../../etc/passwd');
    }

    /**
     * A `../` traversal in a write path must be rejected.
     */
    public function test_write_with_traversal_sequence_is_rejected(): void
    {
        $this->expectException(Path_Traversal_Detected::class);
        $this->fs->write('../outside-root/evil.txt', 'malicious content');
    }

    /**
     * A `../` traversal in a delete path must be rejected.
     */
    public function test_delete_with_traversal_sequence_is_rejected(): void
    {
        $this->expectException(Path_Traversal_Detected::class);
        $this->fs->delete('safe/../../etc/shadow');
    }

    /**
     * URL-encoded traversal sequences must also be rejected.
     *
     * An attacker may encode `../` as `%2e%2e%2f` or `%2E%2E/` to bypass
     * naive string matching.
     */
    public function test_url_encoded_traversal_is_rejected(): void
    {
        $this->expectException(Path_Traversal_Detected::class);
        $this->fs->read('safe/%2e%2e/secret.txt');
    }

    /**
     * A Windows-style `..\\` separator must be treated as traversal.
     */
    public function test_windows_style_traversal_is_rejected(): void
    {
        $this->expectException(Path_Traversal_Detected::class);
        $this->fs->read('safe\\..\\.\\secret.txt');
    }

    /**
     * Paths that only appear to traverse but resolve inside the root must work.
     *
     * e.g. `folder/../folder/file.txt` resolves to `folder/file.txt`.
     */
    public function test_redundant_traversal_that_stays_in_root_is_allowed(): void
    {
        $this->fs->write('folder/file.txt', 'contents');
        // safe/../safe/file.txt → safe/file.txt — still within root
        $this->fs->write('safe/nested/../file.txt', 'overwritten');
        $this->assertSame('overwritten', $this->fs->read('safe/file.txt'));
    }

    /**
     * A leading `/` (absolute path attempt) must not escape the root.
     */
    public function test_absolute_path_is_treated_as_relative(): void
    {
        $this->fs->write('/absolute/path.txt', 'content');
        // Should be stored at 'absolute/path.txt', not at the filesystem root
        $this->assertTrue($this->fs->file_exists('absolute/path.txt'));
    }

    /**
     * Listing a directory using traversal in the path must be rejected.
     */
    public function test_list_contents_with_traversal_is_rejected(): void
    {
        $this->expectException(Path_Traversal_Detected::class);
        iterator_to_array($this->fs->list_contents('../'));
    }
}
