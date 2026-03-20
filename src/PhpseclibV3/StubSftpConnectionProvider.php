<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use phpseclib3\Net\SFTP;
class Stub_Sftp_Connection_Provider implements Connection_Provider
{
    /**
     * @var SftpStub|null
     */
    public $connection;
    public function __construct(private string $host, private string $username, private ?string $password = null, private int $port = 22)
    {
    }
    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
        }
    }
    public function provide_connection(): SFTP
    {
        if (!$this->connection instanceof SFTP || !$this->connection->is_connected()) {
            $connection = new Sftp_Stub($this->host, $this->port);
            $connection->login($this->username, $this->password);
            $this->connection = $connection;
        }
        return $this->connection;
    }
}