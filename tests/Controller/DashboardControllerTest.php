<?php

namespace App\Tests\Controller;

use App\Entity\Etage;
use App\Service\ArchitectureService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    public function testDashboardIndex()
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Sélectionnez un étage');
    }

    public function testDashboardEtage()
    {
        $client = static::createClient();

        // Ensure the database is initialized for the test environment
        $architectureService = static::getContainer()->get(ArchitectureService::class);
        $architectureService->initialiser();

        // Find an existing Etage to test with
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $etageRepository = $entityManager->getRepository(Etage::class);
        $etage = $etageRepository->findOneBy([]);

        if (!$etage) {
            $this->markTestSkipped('No Etage found in the database to test.');
        }

        $client->request('GET', '/dashboard/etage/' . $etage->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dashboard');
        $this->assertSelectorTextContains('h1', $etage->getNom());
    }

    public function testDashboardEtageNotFound()
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard/etage/99999'); // An ID that likely doesn't exist

        $this->assertResponseStatusCodeSame(404);
    }
}
