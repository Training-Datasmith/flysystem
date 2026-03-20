<?php

declare (strict_types=1);
namespace League\Flysystem\Adapter_Test_Utilities;

use Guzzle_Http\Client;
/**
 * This class provides a client for the HTTP API provided by the proxy that simulates network issues.
 *
 * @see https://github.com/shopify/toxiproxy#http-api
 *
 * @phpstan-type RegisteredProxies 'ftp'|'sftp'|'ftpd'
 * @phpstan-type StreamDirection 'upstream'|'downstream'
 * @phpstan-type Type 'latency'|'bandwidth'|'slow_close'|'timeout'|'reset_peer'|'slicer'|'limit_data'
 * @phpstan-type Attributes array{latency?: int, jitter?: int, rate?: int, delay?: int}
 * @phpstan-type Toxic array{name?: string, type: Type, stream?: StreamDirection, toxicity?: float, attributes: Attributes}
 */
final class Toxiproxy_Management
{
    /** @var Client */
    private $api_client;
    public function __construct(Client $api_client)
    {
        $this->api_client = $api_client;
    }
    public static function for_server(string $api_uri = 'http://localhost:8474'): self
    {
        return new self(new Client(['base_uri' => $api_uri, 'base_url' => $api_uri]));
    }
    public function remove_all_toxics(): void
    {
        $this->api_client->post('/reset');
    }
    /**
     * Simulates a peer reset on the client->server direction.
     *
     * @param RegisteredProxies $proxyName
     */
    public function reset_peer_on_request(string $proxy_name, int $timeout_in_milliseconds): void
    {
        $configuration = ['type' => 'reset_peer', 'stream' => 'upstream', 'attributes' => ['timeout' => $timeout_in_milliseconds]];
        $this->add_toxic($proxy_name, $configuration);
    }
    /**
     * Registers a network toxic for the given proxy.
     *
     * @param RegisteredProxies $proxyName
     * @param Toxic $configuration
     */
    private function add_toxic(string $proxy_name, array $configuration): void
    {
        $this->api_client->post('/proxies/' . $proxy_name . '/toxics', ['json' => $configuration]);
    }
}