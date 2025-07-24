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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

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
    private $lockFactory;
    private $lock;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->agentRepository = $this->createMock(AgentRepository::class);
        $this->agentConnexionRepository = $this->createMock(AgentConnexionRepository::class);
        $this->agentPositionRepository = $this->createMock(AgentPositionRepository::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);
        $this->syslogService = $this->createMock(SyslogService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock = $this->createMock(LockInterface::class);

        $this->lockFactory->method('createLock')->willReturn($this->lock);
        $this->lock->method('acquire')->willReturn(true);

        $this->positionService = new PositionService(
            $this->entityManager,
            $this->agentRepository,
            $this->agentConnexionRepository,
            $this->agentPositionRepository,
            $this->positionRepository,
            $this->syslogService,
            $this->logger,
            $this->lockFactory
        );
    }

    public function testActualiserAgentAcquiresAndReleasesLock()
    {
        $this->lock->expects($this->once())->method('acquire')->willReturn(true);
        $this->lock->expects($this->once())->method('release');

        $this->positionService->actualiserAgent('00001', '192.168.1.10', 'AA:BB:CC:DD:EE:FF');
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

    public function testActualiserAgentSiteSyslogFailure()
    {
        $agent = new Agent();
        $agent->setNumagent('00001');
        $this->agentRepository->method('find')->willReturn($agent);

        $this->syslogService->method('analyzeSyslogEvents')->will($this->throwException(new \Exception('Syslog service failed')));

        $this->logger->expects($this->once())->method('critical');
        $this->entityManager->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(AgentConnexion::class));
        $this->entityManager->expects($this->never())->method('remove');

        $this->positionService->actualiserAgent('00001', '55.153.4.50', 'AA:BB:CC:DD:EE:FF');
    }
}
