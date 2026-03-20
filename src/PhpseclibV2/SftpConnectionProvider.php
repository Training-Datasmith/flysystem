<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use phpseclib\System\SSH\Agent;
use Throwable;
/**
 * @deprecated The "League\Flysystem\PhpseclibV2\SftpConnectionProvider" class is deprecated since Flysystem 3.0, use "League\Flysystem\PhpseclibV3\SftpConnectionProvider" instead.
 */
class Sftp_Connection_Provider implements Connection_Provider
{
    private ?\phpseclib\Net\SFTP $connection = null;
    private \League\Flysystem\Phpseclib_V2\Connectivity_Checker $connectivity_checker;
    public function __construct(private string $host, private string $username, private ?string $password = null, private ?string $private_key = null, private ?string $passphrase = null, private int $port = 22, private bool $use_agent = false, private int $timeout = 10, private int $max_tries = 4, private ?string $host_fingerprint = null, ?Connectivity_Checker $connectivity_checker = null, private bool $disable_stat_cache = true)
    {
        $this->connectivity_checker = $connectivity_checker ?? new Simple_Connectivity_Checker();
    }
    public function provide_connection(): SFTP
    {
        $tries = 0;
        start:
        $connection = $this->connection instanceof SFTP ? $this->connection : $this->setup_connection();
        if (!$this->connectivity_checker->is_connected($connection)) {
            $connection->disconnect();
            $this->connection = null;
            if ($tries < $this->max_tries) {
                $tries++;
                goto start;
            }
            throw Unable_To_Connect_To_Sftp_Host::at_hostname($this->host);
        }
        return $this->connection = $connection;
    }
    private function setup_connection(): SFTP
    {
        $connection = new SFTP($this->host, $this->port, $this->timeout);
        $this->disable_stat_cache && $connection->disable_stat_cache();
        try {
            $this->check_fingerprint($connection);
            $this->authenticate($connection);
        } catch (Throwable $exception) {
            $connection->disconnect();
            throw $exception;
        }
        return $connection;
    }
    private function check_fingerprint(SFTP $connection): void
    {
        if (!$this->host_fingerprint) {
            return;
        }
        $public_key = $connection->get_server_public_host_key();
        if ($public_key === false) {
            throw Unable_To_Establish_Authenticity_Of_Host::because_the_authenticity_cant_be_established($this->host);
        }
        $fingerprint = $this->get_fingerprint_from_public_key($public_key);
        if (0 !== strcasecmp($this->host_fingerprint, $fingerprint)) {
            throw Unable_To_Establish_Authenticity_Of_Host::because_the_authenticity_cant_be_established($this->host);
        }
    }
    private function get_fingerprint_from_public_key(string $public_key): string
    {
        $content = explode(' ', $public_key, 3);
        return implode(':', str_split(md5(base64_decode($content[1])), 2));
    }
    private function authenticate(SFTP $connection): void
    {
        if ($this->private_key !== null) {
            $this->authenticate_with_private_key($connection);
        } elseif ($this->use_agent) {
            $this->authenticate_with_agent($connection);
        } elseif (!$connection->login($this->username, $this->password)) {
            throw Unable_To_Authenticate::with_password();
        }
    }
    public static function from_array(array $options): Sftp_Connection_Provider
    {
        return new Sftp_Connection_Provider($options['host'], $options['username'], $options['password'] ?? null, $options['privateKey'] ?? null, $options['passphrase'] ?? null, $options['port'] ?? 22, $options['useAgent'] ?? false, $options['timeout'] ?? 10, $options['maxTries'] ?? 4, $options['hostFingerprint'] ?? null, $options['connectivityChecker'] ?? null);
    }
    private function authenticate_with_private_key(SFTP $connection): void
    {
        $private_key = $this->load_private_key();
        if ($connection->login($this->username, $private_key)) {
            return;
        }
        if ($this->password !== null && $connection->login($this->username, $this->password)) {
            return;
        }
        throw Unable_To_Authenticate::with_private_key();
    }
    private function load_private_key(): RSA
    {
        if (!str_starts_with($this->private_key, '---') && is_file($this->private_key)) {
            $this->private_key = file_get_contents($this->private_key);
        }
        $key = new RSA();
        if ($this->passphrase !== null) {
            $key->set_password($this->passphrase);
        }
        if (!$key->load_key($this->private_key)) {
            throw new Unable_To_Load_Private_Key();
        }
        return $key;
    }
    private function authenticate_with_agent(SFTP $connection): void
    {
        $agent = new Agent();
        if (!$connection->login($this->username, $agent)) {
            throw Unable_To_Authenticate::with_ssh_agent();
        }
    }
}