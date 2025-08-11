<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ApiControllerTest extends WebTestCase
{
    private $client;
    private $token;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->token = static::getContainer()->getParameter('tehou.api.token');
    }

    private function getAuthHeaders(): array
    {
        return [
            'HTTP_Authorization' => 'Bearer ' . $this->token,
            'CONTENT_TYPE' => 'application/json',
        ];
    }

    public function testApiEndpointWithoutTokenFails()
    {
        $this->client->request('POST', '/api/position', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['username' => '12345']));
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testApiEndpointWithInvalidTokenFails()
    {
        $this->client->request('POST', '/api/position', [], [], [
            'HTTP_Authorization' => 'Bearer INVALID_TOKEN',
            'CONTENT_TYPE' => 'application/json'
        ], json_encode(['username' => '12345']));
        $this->assertEquals(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testUpdatePositionWithMissingParametersFails()
    {
        $this->client->request('POST', '/api/position', [], [], $this->getAuthHeaders(), json_encode(['username' => '12345']));
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('error', $response['status']);
        $this->assertStringContainsString('Missing parameters', $response['message']);
    }

    public function testUpdatePositionSuccess()
    {
        // This test assumes an agent '00001' exists from fixtures or previous state
        $payload = ['username' => '00001', 'ip' => '55.153.4.10', 'mac' => 'AA:BB:CC:DD:EE:FF'];
        $this->client->request('POST', '/api/position', [], [], $this->getAuthHeaders(), json_encode($payload));
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('Position updated', $response['message']);
    }

    public function testLogoffSuccess()
    {
        $payload = ['username' => '00001'];
        $this->client->request('POST', '/api/logoff', [], [], $this->getAuthHeaders(), json_encode($payload));
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    public function testSleepSuccess()
    {
        $payload = ['username' => '00001'];
        $this->client->request('POST', '/api/sleep', [], [], $this->getAuthHeaders(), json_encode($payload));
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    public function testGetInventaireByPositionIdSuccess()
    {
        // This test assumes a position with ID 1 exists
        $this->client->request('GET', '/api/inventaire/get?position_id=1', [], [], $this->getAuthHeaders());
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertIsArray($response['data']['materiel']);
    }

    public function testGetInventaireNotFound()
    {
        $this->client->request('GET', '/api/inventaire/get?position_id=99999', [], [], $this->getAuthHeaders());
        $this->assertEquals(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    public function testSetInventaireSuccess()
    {
        $payload = [
            'position_id' => 1,
            'materiel' => [
                ['type' => 'dock', 'codebarre' => 'TEST-DOCK-001', 'special' => false],
                ['type' => 'clavier', 'codebarre' => 'TEST-KEYB-001', 'special' => true],
            ]
        ];
        $this->client->request('POST', '/api/inventaire/set', [], [], $this->getAuthHeaders(), json_encode($payload));
        $this->assertEquals(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('Inventory updated', $response['message']);

        // Verify the data was actually changed
        $this->client->request('GET', '/api/inventaire/get?position_id=1', [], [], $this->getAuthHeaders());
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $response['data']['materiel']);
        $this->assertEquals('TEST-DOCK-001', $response['data']['materiel'][0]['codebarre']);
    }
}
