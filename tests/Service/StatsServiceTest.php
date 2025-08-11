<?php

namespace App\Tests\Service;

use App\Repository\AgentHistoriqueConnexionRepository;
use App\Repository\AgentPositionRepository;
use App\Repository\EtageRepository;
use App\Repository\PositionRepository;
use App\Repository\ServiceRepository;
use App\Repository\SiteRepository;
use App\Service\ArchitectureService;
use App\Service\StatsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class StatsServiceTest extends TestCase
{
    private $entityManager;
    private $agentPositionRepository;
    private $agentHistoriqueConnexionRepository;
    private $siteRepository;
    private $etageRepository;
    private $serviceRepository;
    private $positionRepository;
    private $architectureService;
    private StatsService $statsService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->agentPositionRepository = $this->createMock(AgentPositionRepository::class);
        $this->agentHistoriqueConnexionRepository = $this->createMock(AgentHistoriqueConnexionRepository::class);
        $this->siteRepository = $this->createMock(SiteRepository::class);
        $this->etageRepository = $this->createMock(EtageRepository::class);
        $this->serviceRepository = $this->createMock(ServiceRepository::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);
        $this->architectureService = $this->createMock(ArchitectureService::class);

        $this->statsService = new StatsService(
            $this->entityManager,
            $this->agentPositionRepository,
            $this->agentHistoriqueConnexionRepository,
            $this->siteRepository,
            $this->etageRepository,
            $this->serviceRepository,
            $this->positionRepository,
            $this->architectureService
        );
    }

    public function testGetAgentsPresentsCount()
    {
        $this->agentPositionRepository->expects($this->once())
            ->method('count')
            ->with([])
            ->willReturn(42);

        $this->assertEquals(42, $this->statsService->getAgentsPresentsCount());
    }

    public function testGetTotalPostesFlexCount()
    {
        $this->positionRepository->expects($this->once())
            ->method('count')
            ->with(['flex' => true])
            ->willReturn(100);

        $this->assertEquals(100, $this->statsService->getTotalPostesFlexCount());
    }

    public function testGetRealTimeStats()
    {
        $this->agentPositionRepository->expects($this->once())
            ->method('count')
            ->willReturn(25);

        $this->positionRepository->expects($this->once())
            ->method('count')
            ->with(['flex' => true])
            ->willReturn(100);

        $this->serviceRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $stats = $this->statsService->getRealTimeStats();

        $this->assertIsArray($stats);
        $this->assertEquals(25, $stats['agentsPresents']);
        $this->assertEquals(100, $stats['totalPostesFlex']);
        $this->assertEquals(75, $stats['postesLibres']);
        $this->assertEquals(25.0, $stats['tauxOccupationGlobal']);
    }

    // Due to the complexity of mocking the QueryBuilder and native SQL connections
    // in a pure unit test, we will test the historical methods via the controller test (functional test)
    // which will provide a more realistic testing environment.
    // However, we can add a simple test for getAgentPresenceHistory.

    public function testGetAgentPresenceHistory()
    {
        $numagent = '12345';
        $history = [
            ['id' => 1, 'numagent' => $numagent],
            ['id' => 2, 'numagent' => $numagent]
        ];

        $this->agentHistoriqueConnexionRepository->expects($this->once())
            ->method('findBy')
            ->with(['numagent' => $numagent], ['dateconnexion' => 'DESC'])
            ->willReturn($history);

        $result = $this->statsService->getAgentPresenceHistory($numagent);
        $this->assertCount(2, $result);
    }
}
