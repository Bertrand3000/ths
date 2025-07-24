<?php

namespace App\Tests\MessageHandler;

use App\Message\CleanConnections;
use App\MessageHandler\CleanConnectionsHandler;
use App\Service\PositionService;
use PHPUnit\Framework\TestCase;

class CleanConnectionsHandlerTest extends TestCase
{
    public function testInvoke()
    {
        $positionService = $this->createMock(PositionService::class);
        $positionService->expects($this->once())->method('nettoyerConnexions');

        $handler = new CleanConnectionsHandler($positionService);
        $handler(new CleanConnections());
    }
}
