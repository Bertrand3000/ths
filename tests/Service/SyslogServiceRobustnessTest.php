<?php

namespace App\Tests\Service;

use App\Service\SyslogService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\ConfigRepository;
use App\Repository\SystemeventsRepository;
use App\Repository\PositionRepository;
use App\Repository\NetworkSwitchRepository;

class SyslogServiceRobustnessTest extends TestCase
{
    private SyslogService $syslogService;
    private $logger;
    private $parameterBag;
    private $systemeventsRepository;
    private $networkSwitchRepository;
    private $positionRepository;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $configRepository = $this->createMock(ConfigRepository::class);
        $this->systemeventsRepository = $this->createMock(SystemeventsRepository::class);
        $this->positionRepository = $this->createMock(PositionRepository::class);
        $this->networkSwitchRepository = $this->createMock(NetworkSwitchRepository::class);
        $lockFactory = $this->createMock(\Symfony\Component\Lock\LockFactory::class);
        $lock = $this->createMock(\Symfony\Component\Lock\LockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lockFactory->method('createLock')->willReturn($lock);

        $this->syslogService = new SyslogService(
            $entityManager,
            $configRepository,
            $this->systemeventsRepository,
            $this->positionRepository,
            $this->networkSwitchRepository,
            $this->logger,
            $this->parameterBag,
            $lockFactory
        );
    }

    /**
     * Helper to call private methods on the service.
     */
    private function invokePrivateMethod(string $methodName, array $parameters)
    {
        $reflection = new \ReflectionClass($this->syslogService);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($this->syslogService, $parameters);
    }

    /**
     * Test la validation des adresses MAC
     */
    public function testMacAddressValidation(): void
    {
        $testCases = [
            ['2c58-b9f5-fa9e', '2c:58:b9:f5:fa:9e'], // Format switch
            ['2c:58:b9:f5:fa:9e', '2c:58:b9:f5:fa:9e'], // Format standard
            ['2c58.b9f5.fa9e', '2c:58:b9:f5:fa:9e'], // Format avec points
            ['INVALID_MAC', null], // Invalide
            ['', null], // Vide
            ['2c:58:b9:f5:fa', null], // Trop court
            ['2c:58:b9:f5:fa:9e:ab', null], // Trop long
            ['GG:HH:II:JJ:KK:LL', null], // Caractères non hexadécimaux
        ];

        foreach ($testCases as [$input, $expected]) {
            $result = $this->invokePrivateMethod('validateAndNormalizeMac', [$input ?? '']);
            $this->assertEquals($expected, $result, "Failed on input: " . ($input ?? 'null'));
        }
    }

    /**
     * Test la gestion des messages malformés
     */
    public function testMalformedMessageHandling(): void
    {
        $malformedEvents = [
            'Message complètement invalide',
            '%%INVALID_FORMAT: données corrompues',
            'Message avec caractères spéciaux: éàçù%$#@',
            null,
            ''
        ];

        $this->parameterBag->method('get')->willReturnMap([
            ['tehou.syslog.batch_size', 100],
            ['tehou.syslog.max_processing_time', 300],
            ['tehou.syslog.max_errors', 100],
            ['tehou.syslog.lock_timeout', 300],
            ['tehou.syslog.regex_patterns.connection', []],
            ['tehou.syslog.regex_patterns.disconnection', []],
        ]);

        $this->systemeventsRepository->method('findNewEvents')->willReturn($this->createMockEvents(count($malformedEvents), $malformedEvents));

        // On s'attend à ce que les erreurs soient logguées, mais que le service ne plante pas.
        $this->logger->expects($this->exactly(count($malformedEvents)))->method('warning');

        $result = $this->syslogService->analyzeSyslogEvents();
        $this->assertIsInt($result);
        $this->assertEquals(0, $result);
    }

    /**
     * Test les performances avec un gros volume
     */
    public function testHighVolumeProcessing(): void
    {
        $eventCount = 5000;

        // Mocking ParameterBag
        $this->parameterBag->method('get')->willReturnMap([
            ['tehou.syslog.batch_size', 1000],
            ['tehou.syslog.max_processing_time', 300],
            ['tehou.syslog.max_errors', 100],
            ['tehou.syslog.lock_timeout', 300],
            ['tehou.syslog.regex_patterns.connection', ['/port\s+(\w+\/\d+\/\d+).*port\s+ID\s+is\s+([\w-]+)/']],
            ['tehou.syslog.regex_patterns.disconnection', []],
        ]);

        $this->systemeventsRepository->method('findNewEvents')
             ->will($this->onConsecutiveCalls(
                 $this->createMockEvents(1000, [], 1),
                 $this->createMockEvents(1000, [], 1001),
                 $this->createMockEvents(1000, [], 2001),
                 $this->createMockEvents(1000, [], 3001),
                 $this->createMockEvents(1000, [], 4001),
                 []
             ));

        $this->networkSwitchRepository->method('findOneBy')->willReturn($this->createMock(\App\Entity\NetworkSwitch::class));
        $this->positionRepository->method('findOneBy')->willReturn($this->createMock(\App\Entity\Position::class));

        $startTime = microtime(true);
        $result = $this->syslogService->analyzeSyslogEvents();
        $endTime = microtime(true);

        $this->assertLessThan(60, $endTime - $startTime, 'Traitement trop lent');
        $this->assertEquals($eventCount, $result);
    }

    private function createMockEvents(int $count, array $messages = [], int $startId = 1): array
    {
        $events = [];
        for ($i = 0; $i < $count; $i++) {
            $event = $this->createMock(\App\Entity\Systemevents::class);
            $event->method('getId')->willReturn($startId + $i);
            $message = $messages[$i] ?? '%%10LLDP/6/LLDP_CREATE_NEIGHBOR: Nearest bridge agent neighbor created on port GigabitEthernet1/0/1, port ID is 1122-3344-5566.';
            $event->method('getMessage')->willReturn($message);
            $event->method('getSyslogtag')->willReturn('Switch-Test');
            $events[] = $event;
        }
        return $events;
    }
}
