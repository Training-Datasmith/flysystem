<?php

declare (strict_types=1);
namespace League\Flysystem\Phpseclib_V2;

use function class_exists;
use phpseclib\Net\SFTP;
use Php_Unit\Framework\Test_Case;
/**
 * @group sftp
 * @group sftp-connection
 * @group legacy
 */
class Sftp_Connection_Provider_Test extends Test_Case
{
    protected function set_up(): void
    {
        if (!class_exists('phpseclib\Net\SFTP')) {
            self::mark_test_skipped('PHPSecLib V2 is not installed');
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
        $connection = $provider->provide_connection();
        $this->assert_instance_of(SFTP::class, $connection);
    }
    /**
     * @test
     */
    public function authenticating_with_an_invalid_private_key(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'bar', 'privateKey' => __DIR__ . '/../../test_files/sftp/users.conf', 'port' => 2222]);
        $this->expect_exception(Unable_To_Load_Private_Key::class);
        $provider->provide_connection();
    }
    /**
     * @test
     */
    public function authenticating_with_an_ssh_agent(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'bar', 'useAgent' => true, 'port' => 2222]);
        $connection = $provider->provide_connection();
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
        $connection = $provider->provide_connection();
        $this->assert_instance_of(SFTP::class, $connection);
    }
    /**
     * @test
     */
    public function not_being_able_to_authenticate_with_a_private_key(): void
    {
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'privateKey' => __DIR__ . '/../../test_files/sftp/unknown.key', 'passphrase' => 'secret', 'port' => 2222]);
        $this->expect_exception_object(Unable_To_Authenticate::with_private_key());
        $provider->provide_connection();
    }
    /**
     * @test
     */
    public function verifying_a_fingerprint(): void
    {
        $key = file_get_contents(__DIR__ . '/../../test_files/sftp/ssh_host_rsa_key.pub');
        $finger_print = $this->compute_finger_print($key);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'hostFingerprint' => $finger_print]);
        $another_connection = $provider->provide_connection();
        $this->assert_instance_of(SFTP::class, $another_connection);
    }
    /**
     * @test
     */
    public function providing_an_invalid_fingerprint(): void
    {
        $this->expect_exception(Unable_To_Establish_Authenticity_Of_Host::class);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'pass', 'port' => 2222, 'hostFingerprint' => 'invalid:fingerprint']);
        $provider->provide_connection();
    }
    /**
     * @test
     */
    public function providing_an_invalid_password(): void
    {
        $this->expect_exception(Unable_To_Authenticate::class);
        $provider = Sftp_Connection_Provider::from_array(['host' => 'localhost', 'username' => 'foo', 'password' => 'lol', 'port' => 2222]);
        $provider->provide_connection();
    }
    private function compute_finger_print(string $public_key): string
    {
        $content = explode(' ', $public_key, 3);
        return implode(':', str_split(md5(base64_decode($content[1])), 2));
    }
}