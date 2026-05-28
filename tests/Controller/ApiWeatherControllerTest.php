<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiWeatherControllerTest extends WebTestCase
{
    public function testApiWeatherDefaultLocation(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/weather');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('location', $data);
        $this->assertArrayHasKey('name', $data['location']);
        $this->assertArrayHasKey('latitude', $data['location']);
        $this->assertArrayHasKey('longitude', $data['location']);
        $this->assertArrayHasKey('timezone', $data['location']);
        $this->assertArrayHasKey('generatedAt', $data);
        $this->assertArrayHasKey('current', $data);
        $this->assertIsArray($data['current']);
        $this->assertArrayHasKey('forecast', $data);
        $this->assertIsArray($data['forecast']);
        $this->assertArrayHasKey('hourly', $data);
        $this->assertIsArray($data['hourly']);
    }

    public function testApiWeatherWithLocation(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/weather/paris');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('Paris', $data['location']['name']);
    }

    public function testApiWeatherInvalidLocation(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/weather/zzznotarealplace');
        $this->assertResponseStatusCodeSame(404);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $data);
    }
}
