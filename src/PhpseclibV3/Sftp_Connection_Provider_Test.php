<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V3;

use function base64_decode;
use function class_exists;
use function explode;
use function getenv;
use function hash;
use function implode;
use function is_a;
use phpseclib3\Net\SFTP;
use Php_Unit\Framework\Test_Case;
use function sleep;
use function str_split;
use Throwable;
/**
 * @group sftp
 * @group sftp-connection
 * @group phpseclib3
 */
class Sftp_Connection_Provider_Test extends Test_Case
{
    public const KEX_ACCEPTED_BY_DEFAULT_OPENSSH_BUT_DISABLED_IN_EDDSA_ONLY = 'diffie-hellman-group14-sha256';
    public static function set_up_before_class(): void
    {
        if (!class_exists(SFTP::class)) {
            self::mark_test_incomplete('No phpseclib v3 installed');
        }
    }
    /**
     * @test
     */
    public function giving_up_after_5_connection_failures(): void
    {
        $this->expect_exception(Unable_To_Connect_To_Sftp_Host::class);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'timeout' => 10, 'connectivityChecker' => new Fixated_Connectivity_Checker(5)]);
        $provider->provide_connection();
    }
    /**
     * @test
     */
    public function trying_until_5_tries(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'timeout' => 10, 'connectivityChecker' => new Fixated_Connectivity_Checker(4)]);
        $connection = $provider->provide_connection();
        $same_connection = $provider->provide_connection();
        $this->assert_instance_of(SFTP::class, $connection);
        $this->assert_same($connection, $same_connection);
    }
    /**
     * @test
     */
    public function authenticating_with_a_private_key(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'bar', 'privateKey' => __DIR__ . '/../../test_files/sftp/id_rsa', 'passphrase' => 'secret', 'port' => 2222]);
        $connection = null;
        $this->run_with_retries(function () use (&$connection, $provider): void {
            $connection = $provider->provide_connection();
        });
        $this->assert_instance_of(SFTP::class, $connection);
    }
    /**
     * @test
     */
    public function authenticating_with_an_invalid_private_key(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'bar', 'privateKey' => __DIR__ . '/../../test_files/sftp/users.conf', 'port' => 2222]);
        $this->expect_exception(Unable_To_Load_Private_Key::class);
        $this->run_with_retries(fn(): \phpseclib3\Net\SFTP => $provider->provide_connection(), Unable_To_Load_Private_Key::class);
    }
    /**
     * @test
     */
    public function authenticating_with_an_ssh_agent(): void
    {
        if (getenv('COMPOSER_OPTS') === false) {
            $this->mark_test_skipped('Test is not run locally');
        }
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'bar', 'useAgent' => true, 'port' => 2222]);
        $connection = null;
        $this->run_with_retries(function () use ($provider, &$connection): void {
            $connection = $provider->provide_connection();
        });
        $this->assert_instance_of(SFTP::class, $connection);
    }
    /**
     * @test
     */
    public function failing_to_authenticating_with_an_ssh_agent(): void
    {
        $this->expect_exception(Unable_To_Authenticate::class);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'useAgent' => true, 'port' => 2222]);
        $provider->provide_connection();
    }
    /**
     * @test
     */
    public function authenticating_with_a_private_key_and_falling_back_to_password(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'privateKey' => __DIR__ . '/../../test_files/sftp/id_rsa', 'passphrase' => 'secret', 'port' => 2222]);
        $connection = null;
        $this->run_with_retries(function () use ($provider, &$connection): void {
            $connection = $provider->provide_connection();
        });
        $this->assert_instance_of(SFTP::class, $connection);
    }
    /**
     * @test
     */
    public function not_being_able_to_authenticate_with_a_private_key(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'privateKey' => __DIR__ . '/../../test_files/sftp/unknown.key', 'passphrase' => 'secret', 'port' => 2222]);
        $this->expect_exception_object(Unable_To_Authenticate::with_private_key());
        $this->run_with_retries(fn(): \phpseclib3\Net\SFTP => $provider->provide_connection(), Unable_To_Authenticate::class);
    }
    /**
     * @test
     */
    public function verifying_a_fingerprint(): void
    {
        $key = file_get_contents(__DIR__ . '/../../test_files/sftp/ssh_host_ed25519_key.pub');
        $finger_print = $this->compute_finger_print($key);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'hostFingerprint' => $finger_print]);
        $connection = null;
        $this->run_with_retries(function () use ($provider, &$connection): void {
            $connection = $provider->provide_connection();
        });
        $this->assert_instance_of(SFTP::class, $connection);
    }
    /**
     * @test
     */
    public function providing_an_invalid_fingerprint(): void
    {
        $this->expect_exception(Unable_To_Establish_Authenticity_Of_Host::class);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'hostFingerprint' => 'invalid:fingerprint']);
        $this->run_with_retries(fn(): \phpseclib3\Net\SFTP => $provider->provide_connection(), Unable_To_Establish_Authenticity_Of_Host::class);
    }
    /**
     * @test
     */
    public function providing_an_invalid_password(): void
    {
        $this->expect_exception(Unable_To_Authenticate::class);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'lol', 'port' => 2222]);
        $this->run_with_retries(fn(): \phpseclib3\Net\SFTP => $provider->provide_connection(), Unable_To_Authenticate::class);
    }
    /**
     * @test
     */
    public function retries_several_times_until_failure(): void
    {
        $connectivity_checker = new class implements Connectivity_Checker
        {
            /** @var int */
            public $calls = 0;
            public function is_connected(SFTP $connection): bool
            {
                ++$this->calls;
                return false;
            }
        };
        $max_tries = 2;
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'bar', 'privateKey' => __DIR__ . '/../../test_files/sftp/id_rsa', 'passphrase' => 'secret', 'port' => 8222, 'maxTries' => $max_tries, 'timeout' => 1, 'connectivityChecker' => $connectivity_checker]);
        $this->expect_exception(Unable_To_Connect_To_Sftp_Host::class);
        try {
            $provider->provide_connection();
        } finally {
            self::assert_same($max_tries + 1, $connectivity_checker->calls);
        }
    }
    /**
     * @test
     */
    public function authenticate_with_supported_preferred_kex_algorithm_succeeds(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'preferredAlgorithms' => ['kex' => [self::KEX_ACCEPTED_BY_DEFAULT_OPENSSH_BUT_DISABLED_IN_EDDSA_ONLY]]]);
        $this->run_with_retries(fn() => $this->assert_instance_of(SFTP::class, $provider->provide_connection()));
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2223, 'preferredAlgorithms' => ['kex' => ['curve25519-sha256']]]);
        $this->run_with_retries(fn() => $this->assert_instance_of(SFTP::class, $provider->provide_connection()));
    }
    /**
     * @test
     */
    public function authenticate_with_unsupported_preferred_kex_algorithm_failes(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2223, 'preferredAlgorithms' => ['kex' => [self::KEX_ACCEPTED_BY_DEFAULT_OPENSSH_BUT_DISABLED_IN_EDDSA_ONLY]]]);
        $this->expect_exception(Unable_To_Connect_To_Sftp_Host::class);
        $provider->provide_connection();
    }
    private function compute_finger_print(string $public_key): string
    {
        $content = explode(' ', $public_key, 3);
        $algo = $content[0] === 'ssh-rsa' ? 'md5' : 'sha512';
        return implode(':', str_split(hash($algo, base64_decode($content[1])), 2));
    }
    /**
     * @param class-string<Throwable>|null $expected
     *
     * @throws Throwable
     */
    public function run_with_retries(callable $scenario, ?string $expected = null): void
    {
        $tries = 0;
        start:
        try {
            $scenario();
        } catch (Throwable $exception) {
            if (($expected === null || is_a($exception, $expected) === false) && $tries < 10) {
                $tries++;
                sleep($tries);
                goto start;
            }
            throw $exception;
        }
    }
}