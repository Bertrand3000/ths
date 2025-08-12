<?php

namespace App\Controller;

use App\Service\ArchitectureService;
use App\Service\PositionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/debug')]
class DebugController extends AbstractController
{
    private bool $debugEnabled = true;
    private string $debugToken = 'DEBUG_TOKEN_CHANGE_IN_PROD';

    public function __construct(
        private readonly PositionService $positionService,
        private readonly ArchitectureService $architectureService,
        private readonly EntityManagerInterface $em,
        ParameterBagInterface $parameterBag = null
    ) {
        if ($parameterBag) {
            $this->debugEnabled = $parameterBag->get('tehou.debug.enabled', true);
            $this->debugToken = $parameterBag->get('tehou.debug.token', 'DEBUG_TOKEN_CHANGE_IN_PROD');
        }
    }

    /**
     * Vérifie si l'utilisateur est autorisé à utiliser les endpoints de debug.
     *
     * @param Request $request
     * @return JsonResponse|null
     */
    private function isAuthorized(Request $request): ?JsonResponse
    {
        if (!$this->debugEnabled) {
            return $this->json(['message' => 'Debug mode is not enabled.'], 403);
        }

        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader) {
            return $this->json(['message' => 'Authorization header is missing.'], 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);
        if ($token !== $this->debugToken) {
            return $this->json(['message' => 'Invalid debug token.'], 403);
        }

        return null;
    }

    // Endpoints will be added here in the next steps.

    #[Route('/simulate-position', name: 'api_debug_simulate_position', methods: ['POST'])]
    public function simulatePosition(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $mac = $data['mac'] ?? null;
        $ip = $data['ip'] ?? null;
        $status = $data['status'] ?? 'active';

        if (!$username || !$mac || !$ip) {
            return $this->json(['message' => 'Missing parameters: username, mac, ip are required.'], 400);
        }

        try {
            $this->positionService->actualiserAgent($username, $ip, $mac, $status);
            return $this->json(['status' => 'success', 'message' => "Position simulated for agent $username."]);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/simulate-logout', name: 'api_debug_simulate_logout', methods: ['POST'])]
    public function simulateLogout(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;

        if (!$username) {
            return $this->json(['message' => 'Missing parameter: username is required.'], 400);
        }

        $this->positionService->deconnecterAgent($username);

        return $this->json(['status' => 'success', 'message' => "Logout simulated for agent $username."]);
    }

    #[Route('/simulate-sleep', name: 'api_debug_simulate_sleep', methods: ['POST'])]
    public function simulateSleep(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;

        if (!$username) {
            return $this->json(['message' => 'Missing parameter: username is required.'], 400);
        }

        $this->positionService->veilleAgent($username);

        return $this->json(['status' => 'success', 'message' => "Sleep simulated for agent $username."]);
    }

    #[Route('/simulate-timeout', name: 'api_debug_simulate_timeout', methods: ['POST'])]
    public function simulateTimeout(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $cleanedCount = $this->positionService->cleanExpiredPositions();

        return $this->json([
            'status' => 'success',
            'message' => "$cleanedCount expired positions cleaned up."
        ]);
    }

    #[Route('/get-state', name: 'api_debug_get_state', methods: ['GET'])]
    public function getState(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $sites = $this->architectureService->getSites();
        $response = ['sites' => []];

        foreach ($sites as $site) {
            $siteData = [
                'id' => $site->getId(),
                'nom' => $site->getNom(),
                'etages' => [],
            ];

            $etages = $this->architectureService->getEtages($site);
            foreach ($etages as $etage) {
                $etageData = [
                    'id' => $etage->getId(),
                    'nom' => $etage->getNom(),
                    'stats' => ['total' => 0, 'occupied' => 0, 'rate' => 0.0],
                    'services' => [],
                ];

                $services = $this->architectureService->getServices($etage);
                foreach ($services as $service) {
                    $positionsInService = $this->em->getRepository(\App\Entity\Position::class)->findBy(['service' => $service]);
                    $totalPositionsService = count($positionsInService);
                    $occupiedPositionsService = 0;

                    $serviceData = [
                        'id' => $service->getId(),
                        'nom' => $service->getNom(),
                        'stats' => ['total' => $totalPositionsService, 'occupied' => 0, 'rate' => 0.0],
                        'positions' => [],
                    ];

                    foreach ($positionsInService as $position) {
                        $agentPosition = $this->em->getRepository(\App\Entity\AgentPosition::class)->findOneBy(['position' => $position]);
                        $positionData = [
                            'id' => $position->getId(),
                            'status' => 'libre',
                            'agent' => null,
                        ];

                        if ($agentPosition) {
                            $occupiedPositionsService++;
                            $positionData['status'] = 'occupee';
                            $positionData['agent'] = [
                                'numagent' => $agentPosition->getAgent()->getNumagent(),
                                'nom' => $agentPosition->getAgent()->getNom(),
                                'prenom' => $agentPosition->getAgent()->getPrenom(),
                            ];
                        }
                        $serviceData['positions'][] = $positionData;
                    }

                    $serviceData['stats']['occupied'] = $occupiedPositionsService;
                    if ($totalPositionsService > 0) {
                        $serviceData['stats']['rate'] = round(($occupiedPositionsService / $totalPositionsService) * 100, 2);
                    }

                    $etageData['stats']['total'] += $totalPositionsService;
                    $etageData['stats']['occupied'] += $occupiedPositionsService;
                    $etageData['services'][] = $serviceData;
                }

                if ($etageData['stats']['total'] > 0) {
                    $etageData['stats']['rate'] = round(($etageData['stats']['occupied'] / $etageData['stats']['total']) * 100, 2);
                }
                $siteData['etages'][] = $etageData;
            }
            $response['sites'][] = $siteData;
        }

        return $this->json($response);
    }

    #[Route('/create-test-agent', name: 'api_debug_create_test_agent', methods: ['POST'])]
    public function createTestAgent(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['numagent']) || empty($data['nom']) || empty($data['prenom'])) {
             return $this->json(['message' => 'Missing parameters: numagent, nom, prenom are required.'], 400);
        }

        // Find a random service to assign the agent to
        $services = $this->em->getRepository(\App\Entity\Service::class)->findAll();
        if (empty($services)) {
            return $this->json(['message' => 'No services found in the database. Cannot create agent.'], 500);
        }
        $data['service_id'] = $services[array_rand($services)]->getId();


        try {
            $agent = $this->architectureService->addAgent($data);
            return $this->json([
                'status' => 'success',
                'message' => "Test agent {$agent->getNumagent()} created.",
                'agent' => [
                    'numagent' => $agent->getNumagent(),
                    'nom' => $agent->getNom(),
                    'prenom' => $agent->getPrenom(),
                    'service_id' => $agent->getService()->getId(),
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/remove-test-agent/{numagent}', name: 'api_debug_remove_test_agent', methods: ['DELETE'])]
    public function removeTestAgent(Request $request, string $numagent): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $this->architectureService->deleteAgent($numagent);

        return $this->json([
            'status' => 'success',
            'message' => "Agent $numagent and related data removed."
        ]);
    }

    #[Route('/create-test-position', name: 'api_debug_create_test_position', methods: ['POST'])]
    public function createTestPosition(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        try {
            $position = $this->architectureService->createTestPosition();
            return $this->json([
                'status' => 'success',
                'message' => 'Test position created.',
                'position' => [
                    'id' => $position->getId(),
                    'mac' => $position->getMac(),
                    'prise' => $position->getPrise(),
                    'etage' => $position->getEtage()->getNom(),
                    'service' => $position->getService()->getNom(),
                ]
            ], 201);
        } catch (\RuntimeException $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/list-test-data', name: 'api_debug_list_test_data', methods: ['GET'])]
    public function listTestData(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $agents = $this->em->getRepository(\App\Entity\Agent::class)->findAll();
        $positions = $this->em->getRepository(\App\Entity\Position::class)->findAll();

        return $this->json([
            'agents' => array_map(fn($a) => ['numagent' => $a->getNumagent(), 'nom' => $a->getNom()], $agents),
            'positions' => array_map(fn($p) => ['id' => $p->getId(), 'mac' => $p->getMac()], $positions),
        ]);
    }
}
