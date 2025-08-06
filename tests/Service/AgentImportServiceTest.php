<?php

namespace App\Tests\Service;

use App\Entity\Agent;
use App\Entity\Etage;
use App\Entity\Service;
use App\Service\AgentImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AgentImportServiceTest extends KernelTestCase
{
    private ?EntityManagerInterface $em;
    private ?AgentImportService $importService;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->importService = $container->get(AgentImportService::class);

        // Nettoyer la base de données avant chaque test
        $this->em->createQuery('DELETE FROM App\Entity\Agent')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Service')->execute();
        $this->em->createQuery('DELETE FROM App\Entity\Etage')->execute();

        // Créer des données de base
        $etage = new Etage();
        $etage->setNom('Test Etage')->setLargeur(100)->setHauteur(100)->setArriereplan('test.jpg');
        $this->em->persist($etage);

        $serviceA = new Service();
        $serviceA->setNom('Service A')->setEtage($etage);
        $this->em->persist($serviceA);

        // Agent à mettre à jour
        $agentToUpdate = new Agent();
        $agentToUpdate->setNumagent('22222')
            ->setNom('AncienNom')
            ->setPrenom('AncienPrenom')
            ->setCivilite('M.')
            ->setService($serviceA);
        $this->em->persist($agentToUpdate);

        // Agent à supprimer
        $agentToDelete = new Agent();
        $agentToDelete->setNumagent('33333')
            ->setNom('A','Supprimer')
            ->setPrenom('Agent')
            ->setCivilite('Mme')
            ->setService($serviceA);
        $this->em->persist($agentToDelete);

        $this->em->flush();
    }

    public function testImportAgentsFromXls(): void
    {
        $filePath = self::$kernel->getProjectDir() . '/tests/fixtures/import_test.xlsx';

        $report = $this->importService->importAgentsFromXls($filePath);

        // Vérifier le rapport
        $this->assertEquals(1, $report['created']);
        $this->assertEquals(1, $report['updated']);
        $this->assertEquals(1, $report['deleted']);
        $this->assertContains('Service B', $report['created_services']);
        $this->assertCount(1, $report['errors'], 'Il devrait y avoir une erreur pour la ligne invalide.');

        // Vérifier l'agent créé
        $createdAgent = $this->em->getRepository(Agent::class)->find('11111');
        $this->assertNotNull($createdAgent);
        $this->assertEquals('Nouveau', $createdAgent->getPrenom());
        $this->assertEquals('Service A', $createdAgent->getService()->getNom());

        // Vérifier l'agent mis à jour
        $updatedAgent = $this->em->getRepository(Agent::class)->find('22222');
        $this->assertNotNull($updatedAgent);
        $this->assertEquals('Modifié', $updatedAgent->getNom());
        $this->assertEquals('Service B', $updatedAgent->getService()->getNom());

        // Vérifier l'agent supprimé
        $deletedAgent = $this->em->getRepository(Agent::class)->find('33333');
        $this->assertNull($deletedAgent);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        $this->em = null; // avoid memory leaks
    }
}
