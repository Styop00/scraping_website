<?php

namespace App\Services\Scraping;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class Client
{
    protected HttpClient $httpClient;

    public function __construct()
    {
        $this->httpClient = $this->client();
    }

    /**
     * @return HttpClient
     */
    private function client(): HttpClient
    {
        // set request options
        $options = [
            'headers'         => [
                'Accept' => '*/*',
            ],
            'base_uri'        => config('scrape.base_url'),
            'connect_timeout' => 1,
        ];

        return new HttpClient($options);
    }

    /**
     * @param array $data
     * @return string
     * @throws GuzzleException
     */
    public function scrape(array $data = []): string
    {
        try {
            $res = $this->httpClient->get('', [
                'query' => [
                    'token' => config('scrape.api_token'),
                    ...$data
                ]
            ]);
            return $res->getBody()->getContents();
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
        return '';
    }

}
