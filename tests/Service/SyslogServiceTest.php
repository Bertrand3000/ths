<?php

namespace App\Tests\Service;

use App\Entity\Config;
use App\Entity\NetworkSwitch;
use App\Entity\Position;
use App\Entity\Systemevents;
use App\Repository\ConfigRepository;
use App\Repository\NetworkSwitchRepository;
use App\Repository\PositionRepository;
use App\Repository\SystemeventsRepository;
use App\Service\SyslogService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SyslogServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ConfigRepository $configRepository;
    private SystemeventsRepository $systemeventsRepository;
    private PositionRepository $positionRepository;
    private NetworkSwitchRepository $networkSwitchRepository;
    private SyslogService $syslogService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->configRepository = $this->createMock(ConfigRepository::class);
        $this->systemeventsRepository = $this->createMock(SystemeventsRepository::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);
        $this->networkSwitchRepository = $this->createMock(NetworkSwitchRepository::class);

        $this->syslogService = new SyslogService(
            $this->em,
            $this->configRepository,
            $this->systemeventsRepository,
            $this->positionRepository,
            $this->networkSwitchRepository
        );
    }

    private function setEntityId(object $entity, int $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }

    public function testAnalyzeSyslogEventsForConnection()
    {
        $event = new Systemevents();
        $this->setEntityId($event, 124);
        $event->setSyslogtag('Switch-A');
        $event->setMessage('%%10LLDP/6/LLDP_CREATE_NEIGHBOR: Nearest bridge agent neighbor created on port GigabitEthernet1/0/28 (IfIndex 28), neighbor\'s chassis ID is P1180014125VS0, port ID is 2c58-b9f5-fa9e.');

        $this->systemeventsRepository->method('findNewEvents')->willReturn([$event]);

        $switch = new NetworkSwitch();
        $this->networkSwitchRepository->method('findOneBy')->with(['nom' => 'Switch-A'])->willReturn($switch);

        $position = new Position();
        $this->positionRepository->method('findOneBy')->with(['networkSwitch' => $switch, 'prise' => 'P28'])->willReturn($position);

        $this->configRepository->method('find')->willReturn(null);

        $this->em->expects($this->once())->method('beginTransaction');
        $this->em->expects($this->once())->method('commit');
        $this->em->expects($this->exactly(2))->method('persist'); // Persist for position and config

        $processedCount = $this->syslogService->analyzeSyslogEvents();

        $this->assertEquals(1, $processedCount);
        $this->assertEquals('2c:58:b9:f5:fa:9e', $position->getMac());
    }

    public function testAnalyzeSyslogEventsForDisconnection()
    {
        $event = new Systemevents();
        $this->setEntityId($event, 125);
        $event->setSyslogtag('Switch-B');
        $event->setMessage('%%10IFNET/3/PHY_UPDOWN: Physical state on the interface GigabitEthernet1/0/21 changed to down.');

        $this->systemeventsRepository->method('findNewEvents')->willReturn([$event]);

        $switch = new NetworkSwitch();
        $this->networkSwitchRepository->method('findOneBy')->with(['nom' => 'Switch-B'])->willReturn($switch);

        $position = new Position();
        $position->setMac('aa:bb:cc:dd:ee:ff'); // Pre-existing MAC
        $this->positionRepository->method('findOneBy')->with(['networkSwitch' => $switch, 'prise' => 'P21'])->willReturn($position);

        $this->configRepository->method('find')->willReturn(null);

        $processedCount = $this->syslogService->analyzeSyslogEvents();

        $this->assertEquals(1, $processedCount);
        $this->assertNull($position->getMac());
    }

    public function testCleanupOldSyslogEventsDoesNothingIfTooRecent()
    {
        $config = new Config();
        $config->setCle(SyslogService::DERNIER_NETTOYAGE_SYSLOG_KEY);
        $config->setValeur((new \DateTime())->format('Y-m-d H:i:s'));

        $this->configRepository->method('find')->with(SyslogService::DERNIER_NETTOYAGE_SYSLOG_KEY)->willReturn($config);

        $this->systemeventsRepository->expects($this->never())->method('deleteOldEvents');

        $result = $this->syslogService->cleanupOldSyslogEvents();
        $this->assertFalse($result);
    }

    public function testCleanupOldSyslogEventsPerformsCleanupWhenNeeded()
    {
        // Arrange
        $lastCleanupConfig = new Config();
        $lastCleanupConfig->setValeur((new \DateTime('-2 days'))->format('Y-m-d H:i:s'));
        $lastIdConfig = new Config();
        $lastIdConfig->setValeur('500');

        $this->configRepository->method('find')
            ->willReturnCallback(
                fn(string $key) => match ($key) {
                    SyslogService::DERNIER_NETTOYAGE_SYSLOG_KEY => $lastCleanupConfig,
                    SyslogService::DERNIER_SYSLOG_ID_KEY        => $lastIdConfig,
                    default                                     => null,
                }
            );

        $this->systemeventsRepository->expects($this->once())
            ->method('deleteOldEvents')
            ->with(500);

        $this->em->expects($this->once())->method('flush');

        // Act
        $result = $this->syslogService->cleanupOldSyslogEvents();

        // Assert
        $this->assertTrue($result);
    }
}
