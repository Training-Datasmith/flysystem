<?php

declare (strict_types=1);
namespace League\Flysystem;

interface Checksum_Provider
{
    /**
     * @return string MD5 hash of the file contents
     *
     * @throws UnableToProvideChecksum
     * @throws ChecksumAlgoIsNotSupported
     */
    public function checksum(string $path, Config $config): string;
}