<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AdminControllerTest extends WebTestCase
{
    public function testImportAgentsPageIsSuccessful(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/import/agents');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Importer des Agents');
    }

    public function testImportAgentsFormSubmission(): void
    {
        $client = static::createClient();

        // Créer un fichier Excel temporaire pour le test
        $filePath = sys_get_temp_dir() . '/admin_test_import.xlsx';
        $this->createTestExcelFile($filePath);
        
        $uploadedFile = new UploadedFile(
            $filePath,
            'import_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true // Mark it as a test file
        );

        $client->request('GET', '/admin/import/agents');
        $client->submitForm('Importer', [
            'agent_import[xls_file]' => $uploadedFile,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "Rapport d'Importation des Agents");
        // Vérifier que les éléments de rapport sont présents
        $this->assertSelectorExists('.alert-success'); // Section des créations
        $this->assertSelectorExists('.alert-warning'); // Section des mises à jour
        $this->assertSelectorExists('.alert-danger'); // Section des suppressions
    }

    private function createTestExcelFile(string $filePath): void
    {
        // Créer les données de test nécessaires d'abord
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        
        // Créer un site
        $site = new \App\Entity\Site();
        $site->setNom('Site Test')->setFlex(true);
        $entityManager->persist($site);
        
        // Créer un étage  
        $etage = new \App\Entity\Etage();
        $etage->setSite($site)->setNom('Etage Test')
              ->setLargeur(1000)->setHauteur(1000)
              ->setArriereplan('test.jpg');
        $entityManager->persist($etage);
        
        // Créer un service
        $service = new \App\Entity\Service();
        $service->setEtage($etage)->setNom('Service Test');
        $entityManager->persist($service);
        
        $entityManager->flush();

        // Maintenant créer le fichier Excel
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($filePath);
        
        // Header
        $headerCells = [];
        foreach (['NumAgent', 'Civilité', 'Prénom', 'Nom', 'Email', 'Service'] as $value) {
            $headerCells[] = \OpenSpout\Common\Entity\Cell::fromValue($value);
        }
        $writer->addRow(new \OpenSpout\Common\Entity\Row($headerCells));
        
        // Une seule ligne de données pour simplifier avec un numéro unique
        $uniqueId = 'ADM' . time();
        $cells = [];
        foreach ([$uniqueId, 'M.', 'Test', 'Agent', 'test@example.com', 'Service Test'] as $value) {
            $cells[] = \OpenSpout\Common\Entity\Cell::fromValue($value);
        }
        $writer->addRow(new \OpenSpout\Common\Entity\Row($cells));
        
        $writer->close();
    }
}
