<?php

namespace App\Controller;

use App\Form\AgentSearchType;
use App\Form\EtageSearchType;
use App\Form\PlacesLibresSearchType;
use App\Form\ServiceSearchType;
use App\Repository\AgentConnexionRepository;
use App\Repository\AgentPositionRepository;
use App\Repository\AgentRepository;
use App\Repository\PositionRepository;
use App\Service\ArchitectureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/search')]
class SearchController extends AbstractController
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
        private readonly AgentPositionRepository $agentPositionRepository,
        private readonly AgentConnexionRepository $agentConnexionRepository,
        private readonly ArchitectureService $architectureService
    ) {
    }

    #[Route('', name: 'search_index')]
    public function index(): Response
    {
        return $this->render('search/index.html.twig');
    }

    #[Route('/agent', name: 'search_agent', methods: ['GET', 'POST'])]
    public function searchAgent(Request $request): Response
    {
        $form = $this->createForm(AgentSearchType::class);
        $form->handleRequest($request);

        $results = [];
        $searchTerm = '';

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $searchTerm = $data['search_term'] ?? $data['num_agent'];
            $agents = [];

            if (!empty($data['num_agent'])) {
                $agent = $this->agentRepository->find($data['num_agent']);
                if ($agent) {
                    $agents[] = $agent;
                }
            } elseif (!empty($data['search_term'])) {
                $agents = $this->agentRepository->searchByName($data['search_term']);
            }

            foreach ($agents as $agent) {
                $position = $this->agentPositionRepository->find($agent->getNumagent());
                $connexion = $this->agentConnexionRepository->findOneBy(['agent' => $agent]);

                $status = 'Absent';
                $location = 'N/A';

                if ($connexion) {
                    if ($connexion->getType()->value === 'TELETRAVAIL') {
                        $status = 'En télétravail';
                    }
                }

                if ($position) {
                    $status = 'Présent';
                    $location = sprintf(
                        '%s > %s > %s',
                        $position->getPosition()->getEtage()->getNom(),
                        $position->getPosition()->getService()->getNom(),
                        $position->getPosition()->getPrise()
                    );
                }

                $results[] = [
                    'agent' => $agent,
                    'status' => $status,
                    'location' => $location,
                ];
            }
        }

        return $this->render('search/agent.html.twig', [
            'form' => $form->createView(),
            'results' => $results,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/places-libres', name: 'search_places_libres', methods: ['GET', 'POST'])]
    public function searchPlacesLibres(Request $request, PositionRepository $positionRepository): Response
    {
        $form = $this->createForm(PlacesLibresSearchType::class);
        $form->handleRequest($request);

        $etage = null;
        $service = null;
        $type = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $etage = $data['etage'];
            $service = $data['service'];
            $type = $data['type'];
        }

        $positions = $positionRepository->findAvailablePositionsFiltered($etage, $service, $type);

        return $this->render('search/places_libres.html.twig', [
            'form' => $form->createView(),
            'positions' => $positions,
        ]);
    }

    #[Route('/service', name: 'search_service', methods: ['GET', 'POST'])]
    public function searchService(Request $request): Response
    {
        $form = $this->createForm(ServiceSearchType::class);
        $form->handleRequest($request);

        $service = null;
        $stats = [
            'total' => 0,
            'present' => 0,
            'absent' => 0,
        ];
        $presentAgents = [];
        $absentAgents = [];

        if ($form->isSubmitted() && $form->isValid()) {
            $service = $form->get('service')->getData();
            if ($service) {
                $allAgents = $this->agentRepository->findByService($service);
                $stats['total'] = count($allAgents);

                foreach ($allAgents as $agent) {
                    $position = $this->agentPositionRepository->find($agent->getNumagent());
                    if ($position) {
                        $presentAgents[] = [
                            'agent' => $agent,
                            'location' => sprintf(
                                '%s > %s',
                                $position->getPosition()->getEtage()->getNom(),
                                $position->getPosition()->getPrise()
                            )
                        ];
                    } else {
                        $absentAgents[] = $agent;
                    }
                }
                $stats['present'] = count($presentAgents);
                $stats['absent'] = count($absentAgents);
            }
        }

        return $this->render('search/service.html.twig', [
            'form' => $form->createView(),
            'service' => $service,
            'stats' => $stats,
            'presentAgents' => $presentAgents,
            'absentAgents' => $absentAgents,
        ]);
    }

    #[Route('/etage', name: 'search_etage', methods: ['GET', 'POST'])]
    public function searchEtage(Request $request): Response
    {
        $form = $this->createForm(EtageSearchType::class);
        $form->handleRequest($request);

        $etage = null;
        $servicesData = [];
        $globalStats = ['total' => 0, 'occupied' => 0, 'rate' => 0];

        if ($form->isSubmitted() && $form->isValid()) {
            $etage = $form->get('etage')->getData();
            if ($etage) {
                $services = $this->architectureService->getServices($etage);
                foreach ($services as $service) {
                    $stats = $this->architectureService->getServiceOccupancyStats($service);
                    $servicesData[] = [
                        'service' => $service,
                        'stats' => $stats,
                    ];
                    $globalStats['total'] += $stats['total'];
                    $globalStats['occupied'] += $stats['occupied'];
                }

                if ($globalStats['total'] > 0) {
                    $globalStats['rate'] = round(($globalStats['occupied'] / $globalStats['total']) * 100, 2);
                }
            }
        }

        return $this->render('search/etage.html.twig', [
            'form' => $form->createView(),
            'etage' => $etage,
            'servicesData' => $servicesData,
            'globalStats' => $globalStats,
        ]);
    }
}
