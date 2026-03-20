<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use function base64_decode;
use function implode;
use League\Flysystem\Filesystem_Exception;
use phpseclib3\Crypt\Common\Asymmetric_Key;
use phpseclib3\Crypt\Public_Key_Loader;
use phpseclib3\Exception\No_Key_Loaded_Exception;
use phpseclib3\Net\SFTP;
use phpseclib3\System\SSH\Agent;
use function str_split;
use Throwable;
class Sftp_Connection_Provider implements Connection_Provider
{
    private ?\phpseclib3\Net\SFTP $connection = null;
    private \League\Flysystem\Phpseclib_V3\Connectivity_Checker $connectivity_checker;
    public function __construct(private string $host, private string $username, private ?string $password = null, private ?string $private_key = null, private ?string $passphrase = null, private int $port = 22, private bool $use_agent = false, private int $timeout = 10, private int $max_tries = 4, private ?string $host_fingerprint = null, ?Connectivity_Checker $connectivity_checker = null, private array $preferred_algorithms = [], private bool $disable_stat_cache = true)
    {
        $this->connectivity_checker = $connectivity_checker ?? new Simple_Connectivity_Checker();
    }
    public function provide_connection(): SFTP
    {
        $tries = 0;
        start:
        $tries++;
        try {
            $connection = $this->connection instanceof SFTP ? $this->connection : $this->setup_connection();
        } catch (Throwable $exception) {
            if ($tries <= $this->max_tries) {
                goto start;
            }
            if ($exception instanceof Filesystem_Exception) {
                throw $exception;
            }
            throw Unable_To_Connect_To_Sftp_Host::at_hostname($this->host, $exception);
        }
        if (!$this->connectivity_checker->is_connected($connection)) {
            $connection->disconnect();
            $this->connection = null;
            if ($tries <= $this->max_tries) {
                goto start;
            }
            throw Unable_To_Connect_To_Sftp_Host::at_hostname($this->host);
        }
        return $this->connection = $connection;
    }
    public function disconnect(): void
    {
        if ($this->connection) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }
    private function setup_connection(): SFTP
    {
        $connection = new SFTP($this->host, $this->port, $this->timeout);
        $connection->set_preferred_algorithms($this->preferred_algorithms);
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
        $algo = $content[0] === 'ssh-rsa' ? 'md5' : 'sha512';
        return implode(':', str_split(hash($algo, base64_decode($content[1])), 2));
    }
    private function authenticate(SFTP $connection): void
    {
        if ($this->private_key !== null) {
            $this->authenticate_with_private_key($connection);
        } elseif ($this->use_agent) {
            $this->authenticate_with_agent($connection);
        } else {
            $this->authenticate_with_username_and_password($connection);
        }
    }
    private function authenticate_with_username_and_password(SFTP $connection): void
    {
        if (!$connection->login($this->username, $this->password)) {
            throw Unable_To_Authenticate::with_password($connection->get_last_error());
        }
    }
    public static function from_array(array $options): Sftp_Connection_Provider
    {
        return new Sftp_Connection_Provider($options['host'], $options['username'], $options['password'] ?? null, $options['privateKey'] ?? null, $options['passphrase'] ?? null, $options['port'] ?? 22, $options['useAgent'] ?? false, $options['timeout'] ?? 10, $options['maxTries'] ?? 4, $options['hostFingerprint'] ?? null, $options['connectivityChecker'] ?? null, $options['preferredAlgorithms'] ?? []);
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
        throw Unable_To_Authenticate::with_private_key($connection->get_last_error());
    }
    private function load_private_key(): Asymmetric_Key
    {
        if (!str_starts_with($this->private_key, '---') && !str_starts_with($this->private_key, 'PuTTY') && is_file($this->private_key)) {
            $this->private_key = file_get_contents($this->private_key);
        }
        try {
            if ($this->passphrase !== null) {
                return Public_Key_Loader::load($this->private_key, $this->passphrase);
            }
            return Public_Key_Loader::load($this->private_key);
        } catch (No_Key_Loaded_Exception $exception) {
            throw new Unable_To_Load_Private_Key(null, $exception);
        }
    }
    private function authenticate_with_agent(SFTP $connection): void
    {
        $agent = new Agent();
        if (!$connection->login($this->username, $agent)) {
            throw Unable_To_Authenticate::with_ssh_agent($connection->get_last_error());
        }
    }
}