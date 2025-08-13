<?php

namespace App\Service;

use App\Repository\AgentHistoriqueConnexionRepository;
use App\Repository\AgentPositionRepository;
use App\Repository\EtageRepository;
use App\Repository\PositionRepository;
use App\Repository\ServiceRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour le calcul des statistiques avancées de l'application TEHOU.
 *
 * Ce service centralise toute la logique métier pour l'agrégation et l'analyse
 * des données d'occupation, de présence et d'utilisation du flex office.
 * Il est conçu pour être performant et fournir des données fiables
 * au StatsController et aux autres parties de l'application.
 */
class StatsService
{
    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var AgentPositionRepository
     */
    private AgentPositionRepository $agentPositionRepository;

    /**
     * @var AgentHistoriqueConnexionRepository
     */
    private AgentHistoriqueConnexionRepository $agentHistoriqueConnexionRepository;

    /**
     * @var SiteRepository
     */
    private SiteRepository $siteRepository;

    /**
     * @var EtageRepository
     */
    private EtageRepository $etageRepository;

    /**
     * @var ServiceRepository
     */
    private ServiceRepository $serviceRepository;

    /**
     * @var PositionRepository
     */
    private PositionRepository $positionRepository;

    /**
     * @var ArchitectureService
     */
    private ArchitectureService $architectureService;

    /**
     * Constructeur de StatsService.
     *
     * @param EntityManagerInterface $entityManager
     * @param AgentPositionRepository $agentPositionRepository
     * @param AgentHistoriqueConnexionRepository $agentHistoriqueConnexionRepository
     * @param SiteRepository $siteRepository
     * @param EtageRepository $etageRepository
     * @param ServiceRepository $serviceRepository
     * @param PositionRepository $positionRepository
     * @param ArchitectureService $architectureService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        AgentPositionRepository $agentPositionRepository,
        AgentHistoriqueConnexionRepository $agentHistoriqueConnexionRepository,
        SiteRepository $siteRepository,
        EtageRepository $etageRepository,
        ServiceRepository $serviceRepository,
        PositionRepository $positionRepository,
        ArchitectureService $architectureService
    ) {
        $this->entityManager = $entityManager;
        $this->agentPositionRepository = $agentPositionRepository;
        $this->agentHistoriqueConnexionRepository = $agentHistoriqueConnexionRepository;
        $this->siteRepository = $siteRepository;
        $this->etageRepository = $etageRepository;
        $this->serviceRepository = $serviceRepository;
        $this->positionRepository = $positionRepository;
        $this->architectureService = $architectureService;
    }

    // Les méthodes de calcul statistique seront ajoutées ici.

    /**
     * Récupère un résumé des statistiques en temps réel.
     *
     * @return array
     */
    public function getRealTimeStats(): array
    {
        $totalPostes = $this->getTotalPostesFlexCount();
        $agentsPresents = $this->getAgentsPresentsCount();
        $tauxOccupationGlobal = ($totalPostes > 0) ? ($agentsPresents / $totalPostes) * 100 : 0;

        return [
            'agentsPresents' => $agentsPresents,
            'totalPostesFlex' => $totalPostes,
            'postesLibres' => $totalPostes - $agentsPresents,
            'tauxOccupationGlobal' => round($tauxOccupationGlobal, 2),
            'statsParService' => $this->getOccupancyByService(),
        ];
    }

    /**
     * Calcule le nombre total d'agents actuellement connectés et positionnés.
     *
     * @return int
     */
    public function getAgentsPresentsCount(): int
    {
        // Compte le nombre d'entrées uniques dans agent_position
        return $this->agentPositionRepository->count([]);
    }

    /**
     * Calcule le nombre total de postes de type "flex".
     *
     * @return int
     */
    public function getTotalPostesFlexCount(): int
    {
        return $this->positionRepository->count(['flex' => true]);
    }

    /**
     * Récupère les statistiques d'occupation pour chaque service.
     * Réutilise la logique de ArchitectureService.
     *
     * @return array
     */
    public function getOccupancyByService(): array
    {
        $stats = [];
        $services = $this->serviceRepository->findAll();

        foreach ($services as $service) {
            $stats[] = $this->architectureService->getServiceOccupancyStats($service);
        }

        return $stats;
    }

    /**
     * Récupère l'historique d'occupation pour une période et une granularité données.
     *
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @param string $granularity 'day', 'week', 'month'
     * @return array
     */
    public function getOccupancyHistory(\DateTimeInterface $start, \DateTimeInterface $end, string $granularity = 'day'): array
    {
        $qb = $this->agentHistoriqueConnexionRepository->createQueryBuilder('h');

        // Simplified date formatting for compatibility
        // TODO: Implement proper date formatting based on granularity
        
        $qb->select('h.jour as date_period, COUNT(DISTINCT h.agent) as unique_agents')
            ->where('h.jour BETWEEN :start AND :end')
            ->groupBy('date_period')
            ->orderBy('date_period', 'ASC')
            ->setParameter('start', $start->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'));

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère l'historique de présence complet pour un agent donné.
     *
     * @param string $numagent
     * @return array
     */
    public function getAgentPresenceHistory(string $numagent): array
    {
        return $this->agentHistoriqueConnexionRepository->findBy(['numagent' => $numagent], ['dateconnexion' => 'DESC']);
    }

    /**
     * Calcule les pics d'occupation par heure pour une période donnée.
     *
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return array
     */
    public function getPeakUsageByTimeSlot(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $conn = $this->entityManager->getConnection();
        $sql = '
            SELECT
                strftime(\'%H\', dateconnexion) as hour,
                COUNT(DISTINCT numagent) as agent_count
            FROM agent_historique_connexion
            WHERE date(jour) BETWEEN :start AND :end
            GROUP BY hour
            ORDER BY hour ASC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);

        return $result->fetchAllAssociative();
    }
}
