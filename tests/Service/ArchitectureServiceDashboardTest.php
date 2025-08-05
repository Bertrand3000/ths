<?php

namespace App\Tests\Service;

use App\Entity\AgentPosition;
use App\Entity\Position;
use App\Entity\Service;
use App\Repository\AgentPositionRepository;
use App\Service\ArchitectureService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ArchitectureServiceDashboardTest extends TestCase
{
    private ArchitectureService $architectureService;
    private AgentPositionRepository $agentPositionRepository;

    protected function setUp(): void
    {
        $this->agentPositionRepository = $this->createMock(AgentPositionRepository::class);

        $this->architectureService = new ArchitectureService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(\App\Repository\SiteRepository::class),
            $this->agentPositionRepository,
            '',
            ''
        );
    }

    public function testGetServiceBoundingBox()
    {
        $service = new Service();
        $p1 = (new Position())->setCoordx(100)->setCoordy(200);
        $p2 = (new Position())->setCoordx(150)->setCoordy(250);
        $service->addPosition($p1);
        $service->addPosition($p2);

        $bbox = $this->architectureService->getServiceBoundingBox($service);

        $this->assertNotNull($bbox);
        $this->assertEquals(90, $bbox['x']); // 100 - 10 padding
        $this->assertEquals(190, $bbox['y']); // 200 - 10 padding
        $this->assertEquals(70, $bbox['width']); // (150-100) + 2*10
        $this->assertEquals(70, $bbox['height']); // (250-200) + 2*10
    }

    public function testGetServiceBoundingBoxNoPositions()
    {
        $service = new Service();
        $bbox = $this->architectureService->getServiceBoundingBox($service);
        $this->assertNull($bbox);
    }

    private function createPositionWithId(int $id): Position
    {
        $position = new Position();
        $reflection = new \ReflectionClass($position);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($position, $id);

        return $position;
    }

    public function testGetServiceOccupancyStats()
    {
        $service = new Service();
        $p1 = $this->createPositionWithId(1);
        $p2 = $this->createPositionWithId(2);
        $p3 = $this->createPositionWithId(3);
        $service->addPosition($p1);
        $service->addPosition($p2);
        $service->addPosition($p3);

        $this->agentPositionRepository->method('count')
            ->with($this->equalTo(['position' => [1, 2, 3]]))
            ->willReturn(2);

        $stats = $this->architectureService->getServiceOccupancyStats($service);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['occupied']);
        $this->assertEquals(66.67, $stats['rate']);
        $this->assertEquals('orange', $stats['color']);
    }

    public function testGetServiceOccupancyStatsColors()
    {
        $service = new Service();
        for ($i = 1; $i <= 10; $i++) {
            $service->addPosition($this->createPositionWithId($i));
        }

        $this->agentPositionRepository->method('count')
            ->will($this->onConsecutiveCalls(4, 7, 9, 10));

        // Test Green (<50%)
        $statsGreen = $this->architectureService->getServiceOccupancyStats($service);
        $this->assertEquals('green', $statsGreen['color']);

        // Test Orange (>=50% and <80%)
        $statsOrange = $this->architectureService->getServiceOccupancyStats($service);
        $this->assertEquals('orange', $statsOrange['color']);

        // Test Red (>=80% and <100%)
        $statsRed = $this->architectureService->getServiceOccupancyStats($service);
        $this->assertEquals('red', $statsRed['color']);

        // Test Black (>=100%)
        $statsBlack = $this->architectureService->getServiceOccupancyStats($service);
        $this->assertEquals('black', $statsBlack['color']);
    }

    public function testGetServiceOccupancyStatsNoPositions()
    {
        $service = new Service();
        $stats = $this->architectureService->getServiceOccupancyStats($service);
        $this->assertEquals(0, $stats['total']);
        $this->assertEquals('grey', $stats['color']);
    }
}
