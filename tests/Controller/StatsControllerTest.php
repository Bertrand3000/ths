<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatsControllerTest extends WebTestCase
{
    public function testStatsIndexPageIsSuccessful()
    {
        $client = static::createClient();
        $client->request('GET', '/stats/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', "Vue d'ensemble");
    }

    public function testStatsDashboardPageIsSuccessful()
    {
        $client = static::createClient();
        $client->request('GET', '/stats/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Dashboard Temps Réel');
    }

    public function testStatsReportsPageIsSuccessful()
    {
        $client = static::createClient();
        $client->request('GET', '/stats/reports');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Rapports Historiques');
    }

    public function testStatsExportsPageIsSuccessful()
    {
        $client = static::createClient();
        $client->request('GET', '/stats/exports');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Exports de Données');
    }

    public function testStatsExportCsvIsSuccessful()
    {
        $client = static::createClient();
        $client->request('POST', '/stats/export/occupancy-history', [
            'start' => '2023-01-01',
            'end' => '2023-01-31',
            'granularity' => 'day'
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertTrue(
            $client->getResponse()->headers->contains(
                'Content-Type',
                'text/csv'
            )
        );
        $this->assertStringContainsString(
            'attachment; filename="export_occupation_20230101_20230131.csv"',
            $client->getResponse()->headers->get('Content-Disposition')
        );
    }
}
