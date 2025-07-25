<?php

namespace App\Controller;

use App\Repository\AgentHistoriqueConnexionRepository;
use App\Repository\AgentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/historique')]
class HistoriqueController extends AbstractController
{
    public function __construct(
        private readonly AgentHistoriqueConnexionRepository $historiqueRepo,
        private readonly AgentRepository $agentRepo,
        private readonly SerializerInterface $serializer
    ) {
    }

    #[Route('/agent/{numagent}', name: 'api_historique_agent', methods: ['GET'])]
    public function getHistoriqueByAgent(string $numagent): JsonResponse
    {
        $agent = $this->agentRepo->find($numagent);
        if (!$agent) {
            return $this->json(['message' => 'Agent non trouvé'], 404);
        }

        $historique = $this->historiqueRepo->findBy(['agent' => $agent], ['dateconnexion' => 'DESC']);

        return $this->json($historique, 200, [], ['groups' => 'historique:read']);
    }

    #[Route('/position/{id}', name: 'api_historique_position', methods: ['GET'])]
    public function getHistoriqueByPosition(int $id): JsonResponse
    {
        $historique = $this->historiqueRepo->findBy(['position' => $id], ['dateconnexion' => 'DESC']);

        return $this->json($historique, 200, [], ['groups' => 'historique:read']);
    }

    #[Route('/dates', name: 'api_historique_dates', methods: ['GET'])]
    public function getHistoriqueByDates(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start');
        $endDate = $request->query->get('end');

        if (!$startDate || !$endDate) {
            return $this->json(['message' => 'Les paramètres "start" et "end" sont obligatoires.'], 400);
        }

        try {
            $startDateTime = new \DateTime($startDate);
            $endDateTime = new \DateTime($endDate);
        } catch (\Exception $e) {
            return $this->json(['message' => 'Format de date invalide. Utilisez le format YYYY-MM-DD.'], 400);
        }

        $historique = $this->historiqueRepo->findByDateRange($startDateTime, $endDateTime);

        return $this->json($historique, 200, [], ['groups' => 'historique:read']);
    }
}
