<?php

namespace App\Tests\Controller;

use App\Service\ArchitectureService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SearchControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = static::getContainer();

        // Initialise la base de données de test
        $architectureService = $container->get(ArchitectureService::class);
        $architectureService->initialiser();
    }

    public function testSearchIndexPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Système de Recherche TEHOU');
    }

    public function testSearchAgentPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search/agent');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rechercher un Agent');
    }

    public function testSearchPlacesLibresPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search/places-libres');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rechercher des Places Libres');
    }

    public function testSearchServicePageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search/service');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Consulter un Service');
    }

    public function testSearchEtagePageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/search/etage');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Consulter un Étage');
    }

    public function testSearchAgentByName(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/search/agent');

        $form = $crawler->selectButton('Rechercher')->form();
        // Let's find an agent to search for
        $agentRepo = static::getContainer()->get(\App\Repository\AgentRepository::class);
        $agent = $agentRepo->findOneBy([]);

        if (!$agent) {
            $this->markTestSkipped('No agent found in the database to test with.');
        }

        $form['agent_search[search_term]'] = $agent->getNom();
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Résultats pour');
        $this->assertSelectorTextContains('td', $agent->getNom());
    }

    public function testSearchPlacesLibresWithFilter(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/search/places-libres');

        $form = $crawler->selectButton('Filtrer')->form();
        $etageRepo = static::getContainer()->get(\App\Repository\EtageRepository::class);
        $etage = $etageRepo->findOneBy([]);

        $form['places_libres_search[etage]']->setValue($etage->getId());
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Places Disponibles');
    }

    public function testSearchService(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/search/service');

        $form = $crawler->selectButton('Consulter')->form();
        $serviceRepo = static::getContainer()->get(\App\Repository\ServiceRepository::class);
        $service = $serviceRepo->findOneBy([]);

        $form['service_search[service]']->setValue($service->getId());
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Service :');
    }

    public function testSearchEtage(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/search/etage');

        $form = $crawler->selectButton('Consulter')->form();
        $etageRepo = static::getContainer()->get(\App\Repository\EtageRepository::class);
        $etage = $etageRepo->findOneBy([]);

        $form['etage_search[etage]']->setValue($etage->getId());
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Étage :');
    }
}
