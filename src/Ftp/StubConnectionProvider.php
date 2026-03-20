<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

class Stub_Connection_Provider implements Connection_Provider
{
    public mixed $connection;
    public function __construct(private Connection_Provider $provider)
    {
    }
    public function create_connection(Ftp_Connection_Options $options)
    {
        return $this->connection = $this->provider->create_connection($options);
    }
}