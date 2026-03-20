<?php

declare (strict_types=1);
namespace League\Flysystem\Web_Dav;

use Sabre\DAV\Client;
class Url_Prefixing_Client_Stub extends Client
{
    /**
     * @param string $url
     */
    public function prop_find($url, array $properties, $depth = 0): array
    {
        $response = parent::prop_find($url, $properties, $depth);
        if ($depth === 0) {
            return $response;
        }
        $formatted = [];
        foreach ($response as $path => $object) {
            $formatted['https://domain.tld/' . ltrim($path, '/')] = $object;
        }
        return $formatted;
    }
}