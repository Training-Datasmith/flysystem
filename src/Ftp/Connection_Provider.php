<?php

declare (strict_types=1);
namespace League\Flysystem\Ftp;

interface Connection_Provider
{
    /**
     * @return resource
     */
    public function create_connection(Ftp_Connection_Options $options);
}