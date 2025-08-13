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
        $this->em->createQuery('DELETE FROM App\Entity\Site')->execute();

        // Créer des données de base
        $site = new \App\Entity\Site();
        $site->setNom('Site Test')->setFlex(true);
        $this->em->persist($site);

        $etage = new Etage();
        $etage->setSite($site)->setNom('Test Etage')->setLargeur(100)->setHauteur(100)->setArriereplan('test.jpg');
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
            ->setNom('A Supprimer')
            ->setPrenom('Agent')
            ->setCivilite('Mme')
            ->setService($serviceA);
        $this->em->persist($agentToDelete);

        $this->em->flush();
    }

    public function testImportAgentsFromXls(): void
    {
        // Créer un fichier Excel temporaire pour le test
        $filePath = sys_get_temp_dir() . '/test_import_agents.xlsx';
        $this->createTestExcelFile($filePath);

        $report = $this->importService->importAgentsFromXls($filePath);

        // Vérifier le rapport
        $this->assertEquals(1, $report['created']);
        $this->assertEquals(1, $report['updated']);
        $this->assertEquals(1, $report['deleted']);
        $this->assertContains('Service B', $report['created_services']);
        $this->assertCount(0, $report['errors'], 'Les lignes avec numagent vide sont ignorées silencieusement.');

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

    private function createTestExcelFile(string $filePath): void
    {
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($filePath);
        
        // Header
        $headerCells = [];
        foreach (['NumAgent', 'Civilité', 'Prénom', 'Nom', 'Email', 'Service'] as $value) {
            $headerCells[] = \OpenSpout\Common\Entity\Cell::fromValue($value);
        }
        $writer->addRow(new \OpenSpout\Common\Entity\Row($headerCells));
        
        // Data rows
        $dataSets = [
            ['11111', 'M.', 'Nouveau', 'Agent', 'agent@test.com', 'Service A'],
            ['22222', 'Mme', 'Agent', 'Modifié', 'modifie@test.com', 'Service B'],
            ['', 'M.', 'Sans', 'NumAgent', 'invalide@test.com', 'Service C'] // Ligne invalide
        ];
        
        foreach ($dataSets as $dataSet) {
            $cells = [];
            foreach ($dataSet as $value) {
                $cells[] = \OpenSpout\Common\Entity\Cell::fromValue($value);
            }
            $writer->addRow(new \OpenSpout\Common\Entity\Row($cells));
        }
        
        $writer->close();
    }
}
