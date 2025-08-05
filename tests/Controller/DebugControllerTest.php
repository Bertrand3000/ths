<?php

namespace App\Tests\Controller;

use App\Service\ArchitectureService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DebugControllerTest extends WebTestCase
{
    private $client;
    private static $testToken = 'DEBUG_TOKEN_CHANGE_IN_PROD';
    private static $testAgentNum = '99999';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();

        // Ensure the database is initialized
        $architectureService = static::getContainer()->get(ArchitectureService::class);
        $architectureService->initialiser();
    }

    public function testEndpointsAreProtected()
    {
        $this->client->request('GET', '/api/debug/get-state');
        $this->assertResponseStatusCodeSame(401, 'Request without token should be unauthorized.');

        $this->client->request('GET', '/api/debug/get-state', [], [], ['HTTP_Authorization' => 'Bearer INVALID_TOKEN']);
        $this->assertResponseStatusCodeSame(403, 'Request with invalid token should be forbidden.');
    }

    public function testCreateAndRemoveTestAgent()
    {
        // First, remove the agent to ensure a clean state, in case a previous test failed.
        $this->client->request(
            'DELETE',
            '/api/debug/remove-test-agent/' . self::$testAgentNum,
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . self::$testToken]
        );
        $this->assertResponseStatusCodeSame(200);


        // Then, create the agent
        $this->client->request(
            'POST',
            '/api/debug/create-test-agent',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . self::$testToken,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode(['numagent' => self::$testAgentNum, 'nom' => 'Test', 'prenom' => 'Agent'])
        );

        $this->assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
        $this->assertEquals(self::$testAgentNum, $response['agent']['numagent']);

        // Finally, remove the agent
        $this->client->request(
            'DELETE',
            '/api/debug/remove-test-agent/' . self::$testAgentNum,
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . self::$testToken]
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }

    public function testSimulatePosition()
    {
        // Find an existing agent from the initialized data
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $agent = $entityManager->getRepository(\App\Entity\Agent::class)->findOneBy([]);
        $this->assertNotNull($agent, "No agent found in the database to run the test.");

        $this->client->request(
            'POST',
            '/api/debug/simulate-position',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . self::$testToken,
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'username' => $agent->getNumagent(),
                'mac' => '00:11:22:33:44:55',
                'ip' => '55.153.4.10' // Site IP from Cahier Technique
            ])
        );

        $this->assertResponseStatusCodeSame(200);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('success', $response['status']);
    }
}
