<?php

namespace App\Controller;

use App\Entity\Etage;
use App\Repository\EtageRepository;
use App\Service\ArchitectureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ArchitectureService $architectureService,
        private readonly EtageRepository $etageRepository
    ) {
    }

    #[Route('/dashboard', name: 'dashboard_index')]
    public function index(): Response
    {
        $sites = $this->architectureService->getSites();
        $etagesBySite = [];
        foreach ($sites as $site) {
            $etagesBySite[$site->getNom()] = $this->architectureService->getEtages($site);
        }

        return $this->render('dashboard/index.html.twig', [
            'etagesBySite' => $etagesBySite,
        ]);
    }

    #[Route('/dashboard/etage/{id}', name: 'dashboard_etage')]
    public function etage(int $id): Response
    {
        $etage = $this->etageRepository->find($id);
        if (!$etage) {
            throw new NotFoundHttpException('Étage non trouvé');
        }
        
        $services = $this->architectureService->getServices($etage);
        $servicesData = [];

        foreach ($services as $service) {
            $servicesData[] = [
                'service' => $service,
                'stats' => $this->architectureService->getServiceOccupancyStats($service),
                'boundingBox' => $this->architectureService->getServiceBoundingBox($service),
            ];
        }

        return $this->render('dashboard/etage.html.twig', [
            'etage' => $etage,
            'servicesData' => $servicesData,
        ]);
    }
}
