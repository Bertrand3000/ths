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
use Psr\Log\LoggerInterface;

/**
 * Contrôleur principal pour l'API REST de TEHOU.
 * Fournit les endpoints pour les clients lourds.
 */
#[Route('/api')]
class ApiController extends AbstractController
{
    /**
     * Constructeur du contrôleur de l'API.
     *
     * @param PositionService $positionService Service pour la gestion des positions.
     * @param ArchitectureService $architectureService Service pour la gestion de l'architecture.
     * @param EntityManagerInterface $em Le gestionnaire d'entités.
     * @param LoggerInterface $logger Le service de logging.
     * @param bool $apiEnabled Indique si l'API est activée.
     * @param string $apiToken Le token d'authentification pour l'API.
     */
    public function __construct(
        private readonly PositionService $positionService,
        private readonly ArchitectureService $architectureService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        #[Autowire('%tehou.api.enabled%')] private readonly bool $apiEnabled,
        #[Autowire('%tehou.api.token%')] private readonly string $apiToken
    ) {
    }

    /**
     * Vérifie si la requête est autorisée à accéder à l'API.
     *
     * @param Request $request La requête HTTP.
     * @return JsonResponse|null Une réponse JSON d'erreur si non autorisé, sinon null.
     */
    private function isAuthorized(Request $request): ?JsonResponse
    {
        if (!$this->apiEnabled) {
            return $this->apiResponse('error', 'API access is disabled.', null, 403);
        }

        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader) {
            return $this->apiResponse('error', 'Authorization header is missing.', null, 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);
        if (!hash_equals($this->apiToken, $token)) {
            return $this->apiResponse('error', 'Invalid API token.', null, 403);
        }

        return null;
    }

    /**
     * Crée une réponse JSON standardisée.
     *
     * @param string $status Le statut de la réponse ('success' ou 'error').
     * @param string $message Un message descriptif.
     * @param mixed|null $data Les données à inclure dans la réponse.
     * @param int $httpCode Le code de statut HTTP.
     * @return JsonResponse
     */
    private function apiResponse(string $status, string $message, mixed $data = null, int $httpCode = 200): JsonResponse
    {
        $response = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'timestamp' => (new \DateTime())->format('c'),
        ];

        return $this->json($response, $httpCode);
    }

    /**
     * Actualise la position d'un agent.
     * C'est l'endpoint principal utilisé par le client lourd.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/position', name: 'api_position_update', methods: ['POST'])]
    public function updatePosition(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        try {
            $data = $request->toArray();
            $username = $data['username'] ?? null;
            $ip = $data['ip'] ?? null;
            $mac = $data['mac'] ?? null;

            if (!$username || !$ip || !$mac) {
                return $this->apiResponse('error', 'Missing parameters. Required: username, ip, mac.', null, 400);
            }

            $this->positionService->actualiserAgent($username, $ip, $mac);

            return $this->apiResponse('success', 'Position updated', ['agent' => $username]);
        } catch (\JsonException $e) {
            return $this->apiResponse('error', 'Invalid JSON body.', null, 400);
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse('error', $e->getMessage(), null, 404);
        } catch (\Exception $e) {
            $this->logger->error('API Error on /position: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->apiResponse('error', 'An unexpected error occurred.', null, 500);
        }
    }

    /**
     * Gère la déconnexion d'un agent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/logoff', name: 'api_position_logoff', methods: ['POST'])]
    public function logoff(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        try {
            $data = $request->toArray();
            $username = $data['username'] ?? null;

            if (!$username) {
                return $this->apiResponse('error', 'Missing parameter. Required: username.', null, 400);
            }

            $this->positionService->deconnecterAgent($username);

            return $this->apiResponse('success', 'Agent disconnected', ['agent' => $username]);
        } catch (\JsonException $e) {
            return $this->apiResponse('error', 'Invalid JSON body.', null, 400);
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse('error', $e->getMessage(), null, 404);
        } catch (\Exception $e) {
            $this->logger->error('API Error on /logoff: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->apiResponse('error', 'An unexpected error occurred.', null, 500);
        }
    }

    /**
     * Gère la mise en veille d'un agent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/sleep', name: 'api_position_sleep', methods: ['POST'])]
    public function sleep(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        try {
            $data = $request->toArray();
            $username = $data['username'] ?? null;

            if (!$username) {
                return $this->apiResponse('error', 'Missing parameter. Required: username.', null, 400);
            }

            $this->positionService->veilleAgent($username);

            return $this->apiResponse('success', 'Agent set to sleep', ['agent' => $username]);
        } catch (\JsonException $e) {
            return $this->apiResponse('error', 'Invalid JSON body.', null, 400);
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse('error', $e->getMessage(), null, 404);
        } catch (\Exception $e) {
            $this->logger->error('API Error on /sleep: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->apiResponse('error', 'An unexpected error occurred.', null, 500);
        }
    }

    /**
     * Récupère la liste du matériel pour une position ou un agent donné.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/inventaire/get', name: 'api_inventaire_get', methods: ['GET'])]
    public function getInventaire(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        $positionId = $request->query->get('position_id');
        $agentId = $request->query->get('agent_id');

        if (!$positionId && !$agentId) {
            return $this->apiResponse('error', 'Missing parameter. Required: position_id or agent_id.', null, 400);
        }

        try {
            $position = null;
            if ($positionId) {
                $position = $this->em->getRepository(\App\Entity\Position::class)->find($positionId);
            } elseif ($agentId) {
                $agentPosition = $this->em->getRepository(\App\Entity\AgentPosition::class)->find($agentId);
                if ($agentPosition) {
                    $position = $agentPosition->getPosition();
                }
            }

            if (!$position) {
                return $this->apiResponse('error', 'Position not found.', null, 404);
            }

            $materiels = $position->getMateriels();

            return $this->apiResponse(
                'success',
                'Inventory retrieved.',
                ['materiel' => $this->json($materiels, 200, [], ['groups' => 'materiel:read'])->getData()]
            );

        } catch (\Exception $e) {
            $this->logger->error('API Error on /inventaire/get: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->apiResponse('error', 'An unexpected error occurred.', null, 500);
        }
    }

    /**
     * Met à jour l'inventaire matériel d'une position.
     *
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/inventaire/set', name: 'api_inventaire_set', methods: ['POST'])]
    public function setInventaire(Request $request): JsonResponse
    {
        if ($authError = $this->isAuthorized($request)) {
            return $authError;
        }

        try {
            $data = $request->toArray();
            $positionId = $data['position_id'] ?? null;
            $materielsData = $data['materiel'] ?? null;

            if (!$positionId || !is_array($materielsData)) {
                return $this->apiResponse('error', 'Missing parameters. Required: position_id, materiel (array).', null, 400);
            }

            $position = $this->em->getRepository(\App\Entity\Position::class)->find($positionId);
            if (!$position) {
                return $this->apiResponse('error', "Position with id $positionId not found.", null, 404);
            }

            $this->em->transactional(function ($em) use ($position, $materielsData) {
                // Supprimer l'ancien matériel
                foreach ($position->getMateriels() as $materiel) {
                    $em->remove($materiel);
                }
                $em->flush();

                // Ajouter le nouveau matériel
                foreach ($materielsData as $materielData) {
                    if (empty($materielData['type']) || empty($materielData['codebarre'])) {
                        throw new \InvalidArgumentException('Each materiel item must have a type and a codebarre.');
                    }
                    $materiel = new \App\Entity\Materiel();
                    $materiel->setPosition($position);
                    $materiel->setType($materielData['type']);
                    $materiel->setCodebarre($materielData['codebarre']);
                    $materiel->setSpecial($materielData['special'] ?? false);
                    $em->persist($materiel);
                }
            });

            return $this->apiResponse('success', 'Inventory updated', ['position_id' => $positionId]);

        } catch (\JsonException $e) {
            return $this->apiResponse('error', 'Invalid JSON body.', null, 400);
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse('error', $e->getMessage(), null, 400);
        } catch (\Exception $e) {
            $this->logger->error('API Error on /inventaire/set: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return $this->apiResponse('error', 'An unexpected error occurred.', null, 500);
        }
    }
}
