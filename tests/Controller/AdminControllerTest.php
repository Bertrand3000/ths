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

        $filePath = self::$kernel->getProjectDir() . '/tests/fixtures/import_test.xlsx';
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
        $this->assertSelectorTextContains('.alert-success', '1'); // 1 created
        $this->assertSelectorTextContains('.alert-warning', '0'); // In a clean test DB, no agent is updated
        $this->assertSelectorTextContains('.alert-danger', '0'); // In a clean test DB, no agent is deleted initially
    }
}
