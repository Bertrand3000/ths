<?php

namespace App\Controller;

use App\Entity\Etage;
use App\Entity\Site;
use App\Form\AgentImportType;
use App\Form\EtageType;
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
            'sites' => $this->architectureService->getSites(),
            'etages' => $this->entityManager->getRepository(\App\Entity\Etage::class)->count([]),
            'services' => $this->entityManager->getRepository(\App\Entity\Service::class)->count([]),
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
        ]);
    }

    #[Route('/site/{id}', name: 'admin_site_show', methods: ['GET'])]
    public function siteShow(Site $site): Response
    {
        return $this->render('admin/site/show.html.twig', [
            'site' => $site,
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
    #[Route('/etages', name: 'admin_etage_index', methods: ['GET'])]
    public function etageIndex(Request $request): Response
    {
        $etages = $this->entityManager->getRepository(Etage::class)->findAll();

        return $this->render('admin/etage/index.html.twig', [
            'etages' => $etages,
        ]);
    }

    #[Route('/etage/new', name: 'admin_etage_new', methods: ['GET', 'POST'])]
    public function etageNew(Request $request): Response
    {
        $etage = new Etage();
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

        return $this->render('admin/etage/new.html.twig', [
            'etage' => $etage,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/etage/{id}', name: 'admin_etage_show', methods: ['GET'])]
    public function etageShow(Etage $etage): Response
    {
        return $this->render('admin/etage/show.html.twig', [
            'etage' => $etage,
        ]);
    }

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
        ]);
    }

    #[Route('/etage/{id}', name: 'admin_etage_delete', methods: ['POST'])]
    public function etageDelete(Request $request, Etage $etage): Response
    {
        if ($this->isCsrfTokenValid('delete'.$etage->getId(), $request->request->get('_token'))) {
            try {
                $this->architectureService->deleteEtage($etage->getId());
                $this->addFlash('success', 'L\'étage a été supprimé avec succès.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
                $this->addFlash('error', 'Impossible de supprimer cet étage car il contient des services.');
            }
        }

        return $this->redirectToRoute('admin_etage_index');
    }
    //endregion
}
