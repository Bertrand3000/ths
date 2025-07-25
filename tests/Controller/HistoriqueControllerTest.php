<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Tests\Utils\TestTools;

class HistoriqueControllerTest extends WebTestCase
{
    private $client;
    private $testTools;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->testTools = new TestTools($this->client->getContainer());
    }

    public function testGetHistoriqueByAgent(): void
    {
        $agent = $this->testTools->createTestAgent('12345');
        $position = $this->testTools->createTestPosition();
        $this->testTools->createTestHistorique($agent, $position);

        $this->client->request('GET', '/api/historique/agent/12345');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
        $this->assertEquals('12345', $response[0]['agent']['numagent']);
    }

    public function testGetHistoriqueByPosition(): void
    {
        $agent = $this->testTools->createTestAgent('54321');
        $position = $this->testTools->createTestPosition();
        $this->testTools->createTestHistorique($agent, $position);

        $this->client->request('GET', '/api/historique/position/' . $position->getId());

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
        $this->assertEquals($position->getId(), $response[0]['position']['id']);
    }

    public function testGetHistoriqueByDates(): void
    {
        $agent = $this->testTools->createTestAgent('98765');
        $position = $this->testTools->createTestPosition();
        $this->testTools->createTestHistorique($agent, $position, new \DateTime('2025-07-26'));

        $this->client->request('GET', '/api/historique/dates?start=2025-07-26&end=2025-07-27');

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
    }

    public function testGetHistoriqueByDatesInvalid(): void
    {
        $this->client->request('GET', '/api/historique/dates?start=invalid-date&end=2025-07-27');
        $this->assertResponseStatusCodeSame(400);
    }
}
