<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeolocationService
{
    public function __construct(private HttpClientInterface $httpClient) {}

    public function getLocationFromIp(string $ip): ?array
    {
        try {
            $response = $this->httpClient->request('GET', "https://ipapi.co/{$ip}/json/");
            return $response->toArray();
        } catch (\Exception $e) {
            return null;
        }
    }
}