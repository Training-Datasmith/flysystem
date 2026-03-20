<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use function error_clear_last;
use function error_get_last;
use const FTP_USEPASVADDRESS;
class Ftp_Connection_Provider implements Connection_Provider
{
    /**
     * @return resource
     *
     * @throws FtpConnectionException
     */
    public function create_connection(Ftp_Connection_Options $options)
    {
        $connection = $this->create_connection_resource($options->host(), $options->port(), $options->timeout(), $options->ssl());
        try {
            $this->authenticate($options, $connection);
            $this->enable_utf8mode($options, $connection);
            $this->ignore_passive_address($options, $connection);
            $this->make_connection_passive($options, $connection);
        } catch (Ftp_Connection_Exception $exception) {
            @ftp_close($connection);
            throw $exception;
        }
        return $connection;
    }
    /**
     * @return resource
     */
    private function create_connection_resource(string $host, int $port, int $timeout, bool $ssl)
    {
        error_clear_last();
        $connection = $ssl ? @ftp_ssl_connect($host, $port, $timeout) : @ftp_connect($host, $port, $timeout);
        if ($connection === false) {
            throw Unable_To_Connect_To_Ftp_Host::for_host($host, $port, $ssl, error_get_last()['message'] ?? '');
        }
        return $connection;
    }
    /**
     * @param resource $connection
     */
    private function authenticate(Ftp_Connection_Options $options, $connection): void
    {
        if (!@ftp_login($connection, $options->username(), $options->password())) {
            throw new Unable_To_Authenticate();
        }
    }
    /**
     * @param resource $connection
     */
    private function enable_utf8mode(Ftp_Connection_Options $options, $connection): void
    {
        if (!$options->utf8()) {
            return;
        }
        $response = @ftp_raw($connection, 'OPTS UTF8 ON');
        if (!in_array(substr($response[0], 0, 3), ['200', '202'])) {
            throw new Unable_To_Enable_Utf8mode('Could not set UTF-8 mode for connection: ' . $options->host() . '::' . $options->port());
        }
    }
    /**
     * @param resource $connection
     */
    private function ignore_passive_address(Ftp_Connection_Options $options, $connection): void
    {
        $ignore_passive_address = $options->ignore_passive_address();
        if (!is_bool($ignore_passive_address) || !defined('FTP_USEPASVADDRESS')) {
            return;
        }
        if (!@ftp_set_option($connection, FTP_USEPASVADDRESS, !$ignore_passive_address)) {
            throw Unable_To_Set_Ftp_Option::while_setting_option('FTP_USEPASVADDRESS');
        }
    }
    /**
     * @param resource $connection
     */
    private function make_connection_passive(Ftp_Connection_Options $options, $connection): void
    {
        if (!@ftp_pasv($connection, $options->passive())) {
            throw new Unable_To_Make_Connection_Passive('Could not set passive mode for connection: ' . $options->host() . '::' . $options->port());
        }
    }
}