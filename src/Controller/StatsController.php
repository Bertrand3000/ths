<?php

namespace App\Controller;

use App\Service\StatsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur pour la section des statistiques.
 * Fournit les pages web et les endpoints de données pour les statistiques d'utilisation.
 */
#[Route('/stats')]
class StatsController extends AbstractController
{
    private StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Affiche la page d'accueil des statistiques avec une vue d'ensemble.
     */
    #[Route('/', name: 'stats_index', methods: ['GET'])]
    public function index(): Response
    {
        $realTimeStats = $this->statsService->getRealTimeStats();

        return $this->render('stats/index.html.twig', [
            'realTimeStats' => $realTimeStats,
        ]);
    }

    /**
     * Affiche le dashboard temps réel interactif.
     */
    #[Route('/dashboard', name: 'stats_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $realTimeStats = $this->statsService->getRealTimeStats();

        return $this->render('stats/dashboard.html.twig', [
            'realTimeStats' => $realTimeStats,
        ]);
    }

    /**
     * Affiche la page des rapports détaillés avec filtres.
     */
    #[Route('/reports', name: 'stats_reports', methods: ['GET'])]
    public function reports(Request $request): Response
    {
        $start = $request->query->get('start') ? new \DateTime($request->query->get('start')) : new \DateTime('-30 days');
        $end = $request->query->get('end') ? new \DateTime($request->query->get('end')) : new \DateTime();

        $occupancyHistory = $this->statsService->getOccupancyHistory($start, $end);
        $peakUsage = $this->statsService->getPeakUsageByTimeSlot($start, $end);

        return $this->render('stats/reports.html.twig', [
            'occupancyHistory' => $occupancyHistory,
            'peakUsage' => $peakUsage,
            'filters' => ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')],
        ]);
    }

    /**
     * Affiche la page pour l'export de données.
     */
    #[Route('/exports', name: 'stats_exports', methods: ['GET'])]
    public function exports(): Response
    {
        return $this->render('stats/exports.html.twig');
    }

    /**
     * Gère l'export CSV de l'historique d'occupation.
     */
    #[Route('/export/occupancy-history', name: 'stats_export_occupancy_history', methods: ['POST'])]
    public function exportOccupancyHistory(Request $request): Response
    {
        $start = new \DateTime($request->request->get('start', '-30 days'));
        $end = new \DateTime($request->request->get('end', 'now'));
        $granularity = $request->request->get('granularity', 'day');

        $data = $this->statsService->getOccupancyHistory($start, $end, $granularity);

        $csv = "periode,agents_uniques\n";
        foreach ($data as $row) {
            $csv .= "{$row['date_period']},{$row['unique_agents']}\n";
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $filename = "export_occupation_{$start->format('Ymd')}_{$end->format('Ymd')}.csv";
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
