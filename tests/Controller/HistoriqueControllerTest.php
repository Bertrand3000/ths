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
        $uniqueId = 'HST' . time() . '1';
        $agent = $this->testTools->createTestAgent($uniqueId);
        $position = $this->testTools->createTestPosition();
        $this->testTools->createTestHistorique($agent, $position);

        $this->client->request('GET', '/api/historique/agent/' . $uniqueId);

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
        // Vérifier les champs de base de l'historique
        $this->assertArrayHasKey('id', $response[0]);
        $this->assertArrayHasKey('dateconnexion', $response[0]);
        $this->assertArrayHasKey('agent', $response[0]);
        $this->assertArrayHasKey('position', $response[0]);
    }

    public function testGetHistoriqueByPosition(): void
    {
        $uniqueId = 'HST' . time() . '2';
        $agent = $this->testTools->createTestAgent($uniqueId);
        $position = $this->testTools->createTestPosition();
        $this->testTools->createTestHistorique($agent, $position);

        $this->client->request('GET', '/api/historique/position/' . $position->getId());

        $this->assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $response);
        // Vérifier les champs de base de l'historique
        $this->assertArrayHasKey('id', $response[0]);
        $this->assertArrayHasKey('dateconnexion', $response[0]);
        $this->assertArrayHasKey('agent', $response[0]);
        $this->assertArrayHasKey('position', $response[0]);
    }

    public function testGetHistoriqueByDates(): void
    {
        $uniqueId = 'HST' . time() . '3';
        $agent = $this->testTools->createTestAgent($uniqueId);
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
