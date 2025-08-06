<?php

namespace App\Controller;

use App\Form\AgentImportType;
use App\Service\AgentImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
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
}
