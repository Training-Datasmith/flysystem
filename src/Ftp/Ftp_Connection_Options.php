<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

use const FTP_BINARY;
class Ftp_Connection_Options
{
    public function __construct(private string $host, private string $root, private string $username, private string $password, private int $port = 21, private bool $ssl = false, private int $timeout = 90, private bool $utf8 = false, private bool $passive = true, private int $transfer_mode = FTP_BINARY, private ?string $system_type = null, private ?bool $ignore_passive_address = null, private bool $enable_timestamps_on_unix_listings = false, private bool $recurse_manually = false, private ?bool $use_raw_list_options = null)
    {
    }
    public function host(): string
    {
        return $this->host;
    }
    public function root(): string
    {
        return $this->root;
    }
    public function username(): string
    {
        return $this->username;
    }
    public function password(): string
    {
        return $this->password;
    }
    public function port(): int
    {
        return $this->port;
    }
    public function ssl(): bool
    {
        return $this->ssl;
    }
    public function timeout(): int
    {
        return $this->timeout;
    }
    public function utf8(): bool
    {
        return $this->utf8;
    }
    public function passive(): bool
    {
        return $this->passive;
    }
    public function transfer_mode(): int
    {
        return $this->transfer_mode;
    }
    public function system_type(): ?string
    {
        return $this->system_type;
    }
    public function ignore_passive_address(): ?bool
    {
        return $this->ignore_passive_address;
    }
    public function timestamps_on_unix_listings_enabled(): bool
    {
        return $this->enable_timestamps_on_unix_listings;
    }
    public function recurse_manually(): bool
    {
        return $this->recurse_manually;
    }
    public function use_raw_list_options(): ?bool
    {
        return $this->use_raw_list_options;
    }
    public static function from_array(array $options): Ftp_Connection_Options
    {
        return new Ftp_Connection_Options($options['host'] ?? 'invalid://host-not-set', $options['root'] ?? '', $options['username'] ?? 'invalid://username-not-set', $options['password'] ?? 'invalid://password-not-set', $options['port'] ?? 21, $options['ssl'] ?? false, $options['timeout'] ?? 90, $options['utf8'] ?? false, $options['passive'] ?? true, $options['transferMode'] ?? FTP_BINARY, $options['systemType'] ?? null, $options['ignorePassiveAddress'] ?? null, $options['timestampsOnUnixListingsEnabled'] ?? false, $options['recurseManually'] ?? true, $options['useRawListOptions'] ?? null);
    }
}