<?php

namespace Greenhat616\ExpressApiProvider\Client;

use GuzzleHttp\Client;

class Requests
{
    private Client $client;
    function __construct(string $baseUrl)
    {
        $this->client = new Client([
            "base_uri" => $baseUrl,
            "timeout" => 5.0,
            "user_agent" =>
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36",
        ]);
    }

    function get(string $url, array $query = [])
    {
        return $this->client->get($url, ["query" => $query]);
    }

    function post(
        string $url,
        array $query = [],
        array $form = []
    ): \Psr\Http\Message\ResponseInterface {
        return $this->client->post($url, [
            "query" => $query,
            "headers" => [
                "Content-Type" => "application/x-www-form-urlencoded",
            ],
            "body" => http_build_query($form),
        ]);
    }

    public function getClient (): Client
    {
        return $this->client;
    }
}
