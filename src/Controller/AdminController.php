<?php

namespace App\Controller;

use App\Entity\Etage;
use App\Entity\Service;
use App\Entity\Site;
use App\Form\AgentImportType;
use App\Form\EtageType;
use App\Form\ServiceType;
use App\Form\SiteType;
use App\Service\AgentImportService;
use App\Service\ArchitectureService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly ArchitectureService $architectureService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/', name: 'admin_index')]
    public function index(): Response
    {
        $stats = [
            'sites' => $this->entityManager->getRepository(Site::class)->count([]),
            'etages' => $this->entityManager->getRepository(Etage::class)->count([]),
            'services' => $this->entityManager->getRepository(Service::class)->count([]),
        ];

        return $this->render('admin/index.html.twig', [
            'stats' => $stats,
        ]);
    }

    /**
     * Affiche et traite le formulaire d'importation d'agents.
     *
     * @param Request $request
     * @param AgentImportService $agentImportService
     * @return Response
     */
    #[Route('/import/agents', name: 'admin_import_agents', methods: ['GET', 'POST'])]
    public function importAgents(Request $request, AgentImportService $agentImportService): Response
    {
        $form = $this->createForm(AgentImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $xlsFile */
            $xlsFile = $form->get('xls_file')->getData();

            if ($xlsFile) {
                $originalFilename = pathinfo($xlsFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name in the URL
                $safeFilename = transliterator_transliterate('Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()', $originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$xlsFile->guessExtension();

                try {
                    // Move the file to the directory where brochures are stored
                    $xlsFile->move(
                        $this->getParameter('kernel.project_dir').'/var/uploads',
                        $newFilename
                    );

                    $report = $agentImportService->importAgentsFromXls(
                        $this->getParameter('kernel.project_dir').'/var/uploads/'.$newFilename
                    );

                    return $this->render('admin/import_report.html.twig', [
                        'report' => $report,
                    ]);

                } catch (\Exception $e) {
                    $this->addFlash('error', "Une erreur est survenue lors de l'importation : " . $e->getMessage());
                }
            }
        }

        return $this->render('admin/import_agents.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    //region Site CRUD
    #[Route('/sites', name: 'admin_site_index', methods: ['GET'])]
    public function siteIndex(Request $request): Response
    {
        $searchForm = $this->createForm(\App\Form\AdminSearchType::class);
        $searchForm->handleRequest($request);

        $q = $searchForm->isSubmitted() && $searchForm->isValid() ? $searchForm->get('q')->getData() : null;
        $sort = $request->query->get('sort', 's.nom');
        $direction = $request->query->get('direction', 'asc');

        $sites = $this->entityManager->getRepository(Site::class)->search($q, $sort, $direction);

        return $this->render('admin/site/index.html.twig', [
            'sites' => $sites,
            'search_form' => $searchForm->createView(),
        ]);
    }

    #[Route('/site/new', name: 'admin_site_new', methods: ['GET', 'POST'])]
    public function siteNew(Request $request): Response
    {
        $site = new Site();
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->architectureService->addSite([
                    'nom' => $site->getNom(),
                    'flex' => $site->isFlex(),
                ]);
                $this->addFlash('success', 'Le site a été créé avec succès.');
                return $this->redirectToRoute('admin_site_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/site/new.html.twig', [
            'site' => $site,
            'form' => $form->createView(),
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => 'Nouveau'],
            ],
        ]);
    }

    #[Route('/site/{id}', name: 'admin_site_show', methods: ['GET'])]
    public function siteShow(Site $site): Response
    {
        return $this->render('admin/site/show.html.twig', [
            'site' => $site,
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $site->getNom()],
            ],
        ]);
    }

    #[Route('/site/{id}/edit', name: 'admin_site_edit', methods: ['GET', 'POST'])]
    public function siteEdit(Request $request, Site $site): Response
    {
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->architectureService->updateSite($site->getId(), [
                    'nom' => $site->getNom(),
                    'flex' => $site->isFlex(),
                ]);
                $this->addFlash('success', 'Le site a été modifié avec succès.');
                return $this->redirectToRoute('admin_site_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/site/edit.html.twig', [
            'site' => $site,
            'form' => $form->createView(),
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $site->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $site->getId()])],
                ['label' => 'Modifier'],
            ],
        ]);
    }

    #[Route('/site/{id}', name: 'admin_site_delete', methods: ['POST'])]
    public function siteDelete(Request $request, Site $site): Response
    {
        if ($this->isCsrfTokenValid('delete'.$site->getId(), $request->request->get('_token'))) {
            try {
                $this->architectureService->deleteSite($site->getId());
                $this->addFlash('success', 'Le site a été supprimé avec succès.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Impossible de supprimer ce site car il contient des étages.');
            }
        }

        return $this->redirectToRoute('admin_site_index');
    }
    //endregion

    //region Etage CRUD
    /**
     * Affiche la liste des étages avec pagination, recherche, tri et filtre.
     */
    #[Route('/etages', name: 'admin_etage_index', methods: ['GET'])]
    public function etageIndex(Request $request): Response
    {
        $searchForm = $this->createForm(\App\Form\AdminSearchType::class);
        $searchForm->handleRequest($request);

        $q = $searchForm->isSubmitted() && $searchForm->isValid() ? $searchForm->get('q')->getData() : null;
        $siteId = $request->query->getInt('site_id');
        $sort = $request->query->get('sort', 'e.nom');
        $direction = $request->query->get('direction', 'asc');
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $allEtages = $this->entityManager->getRepository(Etage::class)->search($q, $sort, $direction, $siteId);
        $etages = array_slice($allEtages, ($page - 1) * $limit, $limit);
        $maxPage = ceil(count($allEtages) / $limit);


        return $this->render('admin/etage/index.html.twig', [
            'etages' => $etages,
            'search_form' => $searchForm->createView(),
            'sites' => $this->entityManager->getRepository(Site::class)->findAll(),
            'current_site_id' => $siteId,
            'page' => $page,
            'maxPage' => $maxPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Crée un nouvel étage, potentiellement pré-associé à un site.
     */
    #[Route('/etage/new', name: 'admin_etage_new', methods: ['GET', 'POST'])]
    public function etageNew(Request $request): Response
    {
        $etage = new Etage();
        $site = null;
        if ($siteId = $request->query->getInt('site_id')) {
            $site = $this->entityManager->getRepository(Site::class)->find($siteId);
            if ($site) {
                $etage->setSite($site);
            }
        }
        $form = $this->createForm(EtageType::class, $etage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->architectureService->addEtage([
                    'nom' => $etage->getNom(),
                    'site_id' => $etage->getSite()->getId(),
                    'arriereplan' => $etage->getArriereplan() ?? 'default.png',
                    'largeur' => $etage->getLargeur() ?? 1920,
                    'hauteur' => $etage->getHauteur() ?? 1080,
                ]);
                $this->addFlash('success', 'L\'étage a été créé avec succès.');
                return $this->redirectToRoute('admin_etage_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $breadcrumbs = [
            ['label' => 'Étages', 'url' => $this->generateUrl('admin_etage_index')],
        ];
        if ($site) {
            $breadcrumbs = [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $site->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $site->getId()])],
                ['label' => 'Nouvel étage'],
            ];
        } else {
            $breadcrumbs[] = ['label' => 'Nouvel étage'];
        }

        return $this->render('admin/etage/new.html.twig', [
            'etage' => $etage,
            'form' => $form->createView(),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Affiche les détails d'un étage et ses services.
     */
    #[Route('/etage/{id}', name: 'admin_etage_show', methods: ['GET'])]
    public function etageShow(Etage $etage): Response
    {
        return $this->render('admin/etage/show.html.twig', [
            'etage' => $etage,
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $etage->getSite()->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $etage->getSite()->getId()])],
                ['label' => $etage->getNom()],
            ],
        ]);
    }

    /**
     * Modifie un étage existant.
     */
    #[Route('/etage/{id}/edit', name: 'admin_etage_edit', methods: ['GET', 'POST'])]
    public function etageEdit(Request $request, Etage $etage): Response
    {
        $form = $this->createForm(EtageType::class, $etage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->architectureService->updateEtage($etage->getId(), [
                    'nom' => $etage->getNom(),
                    'site_id' => $etage->getSite()->getId(),
                    'arriereplan' => $etage->getArriereplan(),
                    'largeur' => $etage->getLargeur(),
                    'hauteur' => $etage->getHauteur(),
                ]);
                $this->addFlash('success', 'L\'étage a été modifié avec succès.');
                return $this->redirectToRoute('admin_etage_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/etage/edit.html.twig', [
            'etage' => $etage,
            'form' => $form->createView(),
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $etage->getSite()->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $etage->getSite()->getId()])],
                ['label' => $etage->getNom(), 'url' => $this->generateUrl('admin_etage_show', ['id' => $etage->getId()])],
                ['label' => 'Modifier'],
            ],
        ]);
    }

    /**
     * Supprime un étage, avec une vérification des services enfants.
     */
    #[Route('/etage/{id}', name: 'admin_etage_delete', methods: ['POST'])]
    public function etageDelete(Request $request, Etage $etage): Response
    {
        if ($this->isCsrfTokenValid('delete'.$etage->getId(), $request->request->get('_token'))) {
            if ($etage->getServices()->count() > 0) {
                $this->addFlash('error', 'Impossible de supprimer cet étage car il contient des services. Veuillez d\'abord supprimer les services associés.');
                return $this->redirectToRoute('admin_etage_show', ['id' => $etage->getId()]);
            }

            try {
                $this->architectureService->deleteEtage($etage->getId());
                $this->addFlash('success', 'L\'étage a été supprimé avec succès.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_etage_index');
    }
    //endregion

    //region Service CRUD
    /**
     * Affiche la liste des services avec pagination, recherche, tri et filtre.
     */
    #[Route('/services', name: 'admin_service_index', methods: ['GET'])]
    public function serviceIndex(Request $request): Response
    {
        $searchForm = $this->createForm(\App\Form\AdminSearchType::class);
        $searchForm->handleRequest($request);

        $q = $searchForm->isSubmitted() && $searchForm->isValid() ? $searchForm->get('q')->getData() : null;
        $siteId = $request->query->getInt('site_id');
        $etageId = $request->query->getInt('etage_id');
        $sort = $request->query->get('sort', 's.nom');
        $direction = $request->query->get('direction', 'asc');
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        $allServices = $this->entityManager->getRepository(Service::class)->search($q, $sort, $direction, $etageId, $siteId);
        $services = array_slice($allServices, ($page - 1) * $limit, $limit);
        $maxPage = ceil(count($allServices) / $limit);

        return $this->render('admin/service/index.html.twig', [
            'services' => $services,
            'search_form' => $searchForm->createView(),
            'sites' => $this->entityManager->getRepository(Site::class)->findAll(),
            'etages' => $this->entityManager->getRepository(Etage::class)->findAll(), // Could be optimized
            'current_site_id' => $siteId,
            'current_etage_id' => $etageId,
            'page' => $page,
            'maxPage' => $maxPage,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    /**
     * Crée un nouveau service, potentiellement pré-associé à un étage.
     */
    #[Route('/service/new', name: 'admin_service_new', methods: ['GET', 'POST'])]
    public function serviceNew(Request $request): Response
    {
        $service = new Service();
        $etage = null;
        if ($etageId = $request->query->getInt('etage_id')) {
            $etage = $this->entityManager->getRepository(Etage::class)->find($etageId);
            if ($etage) {
                $service->setEtage($etage);
            }
        }
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->architectureService->addService([
                    'nom' => $service->getNom(),
                    'etage_id' => $service->getEtage()->getId(),
                ]);
                $this->addFlash('success', 'Le service a été créé avec succès.');
                return $this->redirectToRoute('admin_service_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        $breadcrumbs = [['label' => 'Services', 'url' => $this->generateUrl('admin_service_index')]];
        if ($etage) {
             $breadcrumbs = [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $etage->getSite()->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $etage->getSite()->getId()])],
                ['label' => $etage->getNom(), 'url' => $this->generateUrl('admin_etage_show', ['id' => $etage->getId()])],
                ['label' => 'Nouveau service'],
            ];
        } else {
            $breadcrumbs[] = ['label' => 'Nouveau service'];
        }

        return $this->render('admin/service/new.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * Affiche les détails d'un service.
     */
    #[Route('/service/{id}', name: 'admin_service_show', methods: ['GET'])]
    public function serviceShow(Service $service): Response
    {
        return $this->render('admin/service/show.html.twig', [
            'service' => $service,
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $service->getEtage()->getSite()->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $service->getEtage()->getSite()->getId()])],
                ['label' => $service->getEtage()->getNom(), 'url' => $this->generateUrl('admin_etage_show', ['id' => $service->getEtage()->getId()])],
                ['label' => $service->getNom()],
            ],
        ]);
    }

    /**
     * Modifie un service existant.
     */
    #[Route('/service/{id}/edit', name: 'admin_service_edit', methods: ['GET', 'POST'])]
    public function serviceEdit(Request $request, Service $service): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->architectureService->updateService($service->getId(), [
                    'nom' => $service->getNom(),
                    'etage_id' => $service->getEtage()->getId(),
                ]);
                $this->addFlash('success', 'Le service a été modifié avec succès.');
                return $this->redirectToRoute('admin_service_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
            'breadcrumbs' => [
                ['label' => 'Sites', 'url' => $this->generateUrl('admin_site_index')],
                ['label' => $service->getEtage()->getSite()->getNom(), 'url' => $this->generateUrl('admin_site_show', ['id' => $service->getEtage()->getSite()->getId()])],
                ['label' => $service->getEtage()->getNom(), 'url' => $this->generateUrl('admin_etage_show', ['id' => $service->getEtage()->getId()])],
                ['label' => $service->getNom(), 'url' => $this->generateUrl('admin_service_show', ['id' => $service->getId()])],
                ['label' => 'Modifier'],
            ],
        ]);
    }

    /**
     * Supprime un service.
     */
    #[Route('/service/{id}', name: 'admin_service_delete', methods: ['POST'])]
    public function serviceDelete(Request $request, Service $service): Response
    {
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            try {
                $this->architectureService->deleteService($service->getId());
                $this->addFlash('success', 'Le service a été supprimé avec succès.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Impossible de supprimer ce service car il est utilisé.');
            }
        }

        return $this->redirectToRoute('admin_service_index');
    }
    //endregion
}
