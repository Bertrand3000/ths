<?php

namespace App\MessageHandler;

use App\Message\CleanConnections;
use App\Service\PositionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CleanConnectionsHandler
{
    public function __construct(private readonly PositionService $positionService)
    {
    }

    public function __invoke(CleanConnections $message): void
    {
        $this->positionService->cleanExpiredPositions();
    }
}
