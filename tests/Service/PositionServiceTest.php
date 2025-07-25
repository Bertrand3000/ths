<?php

namespace App\Tests\Service;

use App\Entity\Agent;
use App\Entity\AgentHistoriqueConnexion;
use App\Entity\AgentPosition;
use App\Entity\Position;
use App\Repository\AgentHistoriqueConnexionRepository;
use App\Service\PositionService;
use App\Tests\Utils\TestTools;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PositionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PositionService $positionService;
    private TestTools $testTools;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();
        $this->positionService = $container->get(PositionService::class);
        $this->testTools = new TestTools($container);

        // Clean up database before each test
        $this->entityManager->createQuery('DELETE FROM App\Entity\AgentHistoriqueConnexion')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AgentPosition')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AgentConnexion')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Agent')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Position')->execute();
    }

    public function testActualiserCreeNouvellePositionEtHistorique(): void
    {
        $agent = $this->testTools->createTestAgent('11111');
        $position = $this->testTools->createTestPosition();
        $mac = $position->getMac();

        $this->positionService->actualiserAgent($agent->getNumagent(), '55.153.4.10', $mac);

        $agentPosition = $this->entityManager->getRepository(AgentPosition::class)->find($agent->getNumagent());
        $this->assertNotNull($agentPosition);
        $this->assertNotNull($agentPosition->getDateexpiration());

        $historique = $this->entityManager->getRepository(AgentHistoriqueConnexion::class)->findOneBy(['agent' => $agent]);
        $this->assertNotNull($historique);
        $this->assertNull($historique->getDatedeconnexion());
    }

    public function testActualiserMetAJourExpiration(): void
    {
        $agent = $this->testTools->createTestAgent('22222');
        $position = $this->testTools->createTestPosition();
        $mac = $position->getMac();

        $this->positionService->actualiserAgent($agent->getNumagent(), '55.153.4.10', $mac);
        $agentPosition = $this->entityManager->getRepository(AgentPosition::class)->find($agent->getNumagent());
        $firstExpiration = $agentPosition->getDateexpiration();

        $this->positionService->actualiserAgent($agent->getNumagent(), '55.153.4.10', $mac);
        $this->entityManager->refresh($agentPosition);
        $secondExpiration = $agentPosition->getDateexpiration();

        $this->assertNotEquals($firstExpiration, $secondExpiration);
    }

    public function testChangementDePositionFinaliseAncienHistorique(): void
    {
        $agent = $this->testTools->createTestAgent('33333');
        $position1 = $this->testTools->createTestPosition();
        $position2 = $this->testTools->createTestPosition();

        // Connexion à la première position
        $this->positionService->actualiserAgent($agent->getNumagent(), '55.153.4.11', $position1->getMac());

        // Connexion à la deuxième position
        $this->positionService->actualiserAgent($agent->getNumagent(), '55.153.4.12', $position2->getMac());

        $historiques = $this->entityManager->getRepository(AgentHistoriqueConnexion::class)->findBy(['agent' => $agent], ['dateconnexion' => 'ASC']);
        $this->assertCount(2, $historiques);
        $this->assertNotNull($historiques[0]->getDatedeconnexion());
        $this->assertNull($historiques[1]->getDatedeconnexion());
    }

    public function testCleanExpiredPositions(): void
    {
        $agent = $this->testTools->createTestAgent('44444');
        $position = $this->testTools->createTestPosition();
        $this->positionService->actualiserAgent($agent->getNumagent(), '55.153.4.13', $position->getMac());

        // Rendre la position expirée
        $agentPosition = $this->entityManager->getRepository(AgentPosition::class)->find($agent->getNumagent());
        $agentPosition->setDateexpiration(new \DateTime('-1 second'));
        $this->entityManager->flush();

        $cleanedCount = $this->positionService->cleanExpiredPositions();
        $this->assertEquals(1, $cleanedCount);

        $agentPosition = $this->entityManager->getRepository(AgentPosition::class)->find($agent->getNumagent());
        $this->assertNull($agentPosition);

        $historique = $this->entityManager->getRepository(AgentHistoriqueConnexion::class)->findOneBy(['agent' => $agent]);
        $this->assertNotNull($historique->getDatedeconnexion());
    }
}
