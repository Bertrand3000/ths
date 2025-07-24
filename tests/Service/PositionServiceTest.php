<?php

namespace App\Tests\Service;

use App\Entity\Agent;
use App\Entity\AgentConnexion;
use App\Entity\AgentPosition;
use App\Entity\Enum\TypeConnexion;
use App\Entity\Position;
use App\Repository\AgentConnexionRepository;
use App\Repository\AgentPositionRepository;
use App\Repository\AgentRepository;
use App\Repository\PositionRepository;
use App\Service\PositionService;
use App\Service\SyslogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PositionServiceTest extends TestCase
{
    private PositionService $positionService;
    private $entityManager;
    private $agentRepository;
    private $agentConnexionRepository;
    private $agentPositionRepository;
    private $positionRepository;
    private $syslogService;
    private $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->agentRepository = $this->createMock(AgentRepository::class);
        $this->agentConnexionRepository = $this->createMock(AgentConnexionRepository::class);
        $this->agentPositionRepository = $this->createMock(AgentPositionRepository::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);
        $this->syslogService = $this->createMock(SyslogService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->positionService = new PositionService(
            $this->entityManager,
            $this->agentRepository,
            $this->agentConnexionRepository,
            $this->agentPositionRepository,
            $this->positionRepository,
            $this->syslogService,
            $this->logger
        );
    }

    public function testActualiserAgentHorsReseauRamage()
    {
        $this->entityManager->expects($this->never())->method('persist');
        $this->positionService->actualiserAgent('00001', '192.168.1.10', 'AA:BB:CC:DD:EE:FF');
    }

    public function testActualiserAgentTeletravail()
    {
        $agent = new Agent();
        $agent->setNumagent('00001');
        $this->agentRepository->method('find')->willReturn($agent);

        $this->agentConnexionRepository->method('findOneBy')->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->callback(function (AgentConnexion $connexion) {
                return $connexion->getType() === TypeConnexion::TELETRAVAIL;
            }));

        $this->positionService->actualiserAgent('00001', '55.255.10.20', 'AA:BB:CC:DD:EE:FF');
    }

    public function testActualiserAgentSite()
    {
        $agent = new Agent();
        $agent->setNumagent('00001');
        $this->agentRepository->method('find')->willReturn($agent);

        $position = new Position();
        $this->positionRepository->method('findOneBy')->willReturn($position);

        $this->entityManager->expects($this->exactly(2))->method('persist');

        $this->positionService->actualiserAgent('00001', '55.153.4.50', 'AA:BB:CC:DD:EE:FF');
    }

    public function testActualiserAgentWifi()
    {
        $agent = new Agent();
        $agent->setNumagent('00001');
        $this->agentRepository->method('find')->willReturn($agent);

        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(AgentConnexion::class));

        $this->entityManager->expects($this->never())->method('remove');

        $this->positionService->actualiserAgent('00001', '55.10.0.1', 'AA:BB:CC:DD:EE:FF');
    }

    public function testDeconnecterAgent()
    {
        $agent = new Agent();
        $agent->setNumagent('00001');
        $this->agentRepository->method('find')->willReturn($agent);

        $connexion = new AgentConnexion();
        $this->agentConnexionRepository->method('findOneBy')->willReturn($connexion);

        $position = new AgentPosition();
        $this->agentPositionRepository->method('find')->willReturn($position);

        $this->entityManager->expects($this->exactly(2))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        $this->positionService->deconnecterAgent('00001');
    }

    public function testNettoyerConnexions()
    {
        $agent = new Agent();
        $agent->setNumagent('00001');
        $connexion = new AgentConnexion();
        $connexion->setAgent($agent);

        $this->agentConnexionRepository->method('findExpiredConnections')->willReturn([$connexion]);

        $position = new AgentPosition();
        $this->agentPositionRepository->method('find')->willReturn($position);

        $this->entityManager->expects($this->exactly(2))->method('remove');
        $this->entityManager->expects($this->once())->method('flush');

        // We need to use reflection to test the private method
        $reflection = new \ReflectionClass(PositionService::class);
        $method = $reflection->getMethod('nettoyerConnexions');
        $method->setAccessible(true);
        $method->invoke($this->positionService);
    }
}
