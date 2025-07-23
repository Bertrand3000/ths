<?php

namespace App\Tests\Service;

use App\Repository\SiteRepository;
use App\Service\ArchitectureService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ArchitectureServiceTest extends TestCase
{
    public function testInitialiserDoesNotRunWhenSiteIsNotEmpty(): void
    {
        $siteRepository = $this->createMock(SiteRepository::class);
        $siteRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $service = new ArchitectureService($em, $siteRepository, '', '');
        $service->initialiser();
    }

    public function testInitialiserCreatesAllEntities(): void
    {
        $siteRepository = $this->createMock(SiteRepository::class);
        $siteRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        // We expect persist to be called many times, so we use any()
        $em->expects($this->any())->method('persist');
        // We expect flush to be called twice
        $em->expects($this->exactly(2))->method('flush');

        $service = new ArchitectureService(
            $em,
            $siteRepository,
            __DIR__ . '/../../src/Data/noms.txt',
            __DIR__ . '/../../src/Data/prenoms.txt'
        );

        // Reduce the number of entities for tests
        $service->agentsCount = 1;
        $service->servicesPerEtage = 1;
        $service->switchesPerEtageSiege = 1;
        $service->positionsPerSwitch = 1;
        $service->sites = [['nom' => 'Test Site', 'flex' => true]];
        $service->etagesSiege = [['nom' => 'Test Etage', 'niveau' => 1]];


        $service->initialiser();
    }
}
