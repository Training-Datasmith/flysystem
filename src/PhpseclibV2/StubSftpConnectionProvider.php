<?php

declare(strict_types=1);

namespace League\Flysystem\PhpseclibV2;

use phpseclib\Net\SFTP;

/**
 * @deprecated The "League\Flysystem\PhpseclibV2\StubSftpConnectionProvider" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\StubSftpConnectionProvider" instead.
 */
class StubSftpConnectionProvider implements ConnectionProvider
{
    private ?\League\Flysystem\PhpseclibV2\SftpStub $connection = null;

    public function __construct(private string $host, private string $username, private ?string $password = null, private int $port = 22)
    {
    }

    public function provideConnection(): SFTP
    {
        if (! $this->connection instanceof SFTP) {
            $connection = new SftpStub($this->host, $this->port);
            $connection->login($this->username, $this->password);

            $this->connection = $connection;
        }

        return $this->connection;
    }
}
