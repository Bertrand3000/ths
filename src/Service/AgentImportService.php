<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Etage;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use OpenSpout\Reader\XLSX\Reader;

class AgentImportService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ArchitectureService $architectureService
    ) {
    }

    /**
     * Importe les agents depuis un fichier XLS/XLSX.
     *
     * @param string $filePath Chemin vers le fichier XLS.
     * @return array Rapport d'importation.
     * @throws \Exception
     */
    public function importAgentsFromXls(string $filePath): array
    {
        $report = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'created_services' => [],
            'errors' => [],
        ];

        $reader = new Reader();
        $reader->open($filePath);

        $agentsInFile = [];
        $firstSheet = null;

        foreach ($reader->getSheetIterator() as $sheet) {
            $firstSheet = $sheet;
            break;
        }

        if (!$firstSheet) {
            throw new \Exception("Le fichier XLS ne contient aucune feuille.");
        }

        $this->em->beginTransaction();
        try {
            $isFirstRow = true;
            foreach ($firstSheet->getRowIterator() as $row) {
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }

                $cells = $row->getCells();
                $rowData = [];
                foreach ($cells as $cell) {
                    $rowData[] = $cell->getValue();
                }

                // Ignorer les lignes vides
                if (empty(array_filter($rowData))) {
                    continue;
                }

                try {
                    $numAgent = $this->formatAgentNumber($rowData[0] ?? '');
                    if (empty($numAgent)) {
                        continue;
                    }
                    $agentsInFile[] = $numAgent;

                    $serviceName = trim($rowData[5] ?? 'Non défini');
                    $service = $this->getOrCreateService($serviceName, $report);

                    $agentData = [
                        'numagent' => $numAgent,
                        'civilite' => trim($rowData[1] ?? ''),
                        'prenom' => trim($rowData[2] ?? ''),
                        'nom' => trim($rowData[3] ?? ''),
                        'service_id' => $service->getId(),
                    ];

                    $existingAgent = $this->em->getRepository(Agent::class)->find($numAgent);

                    if ($existingAgent) {
                        $this->architectureService->updateAgent($numAgent, $agentData);
                        $report['updated']++;
                    } else {
                        $this->architectureService->addAgent($agentData);
                        $report['created']++;
                    }
                } catch (\Exception $e) {
                    $report['errors'][] = "Erreur à la ligne : " . implode(', ', $rowData) . " - " . $e->getMessage();
                }
            }

            // Supprimer les agents non présents dans le fichier
            $report['deleted'] = $this->deleteAgentsNotInFile($agentsInFile);

            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e; // Rethrow after rollback
        } finally {
            $reader->close();
        }

        return $report;
    }

    /**
     * Formate le numéro d'agent sur 5 chiffres avec des zéros à gauche.
     *
     * @param mixed $number
     * @return string
     */
    private function formatAgentNumber($number): string
    {
        $number = trim((string)$number);
        if (!is_numeric($number)) {
            return '';
        }
        return str_pad((string)(int)$number, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Récupère un service par son nom, ou le crée s'il n'existe pas.
     *
     * @param string $serviceName
     * @param array $report
     * @return Service
     * @throws \Exception
     */
    private function getOrCreateService(string $serviceName, array &$report): Service
    {
        $service = $this->em->getRepository(Service::class)->findOneBy(['nom' => $serviceName]);

        if (!$service) {
            // Si le service n'existe pas, le créer dans le premier étage trouvé
            $etage = $this->em->getRepository(Etage::class)->findOneBy([]);
            if (!$etage) {
                throw new \Exception("Impossible de créer le service '$serviceName' car aucun étage n'existe dans la base de données.");
            }

            $service = $this->architectureService->addService([
                'nom' => $serviceName,
                'etage_id' => $etage->getId(),
            ]);

            $report['created_services'][] = $serviceName;
        }

        return $service;
    }

    /**
     * Supprime les agents de la base de données qui ne sont pas dans la liste fournie.
     *
     * @param array $agentsInFile
     * @return int
     */
    private function deleteAgentsNotInFile(array $agentsInFile): int
    {
        $allAgentsInDb = $this->em->getRepository(Agent::class)->findAll();
        $deletedCount = 0;

        foreach ($allAgentsInDb as $agentInDb) {
            if (!in_array($agentInDb->getNumagent(), $agentsInFile)) {
                $this->architectureService->deleteAgent($agentInDb->getNumagent());
                $deletedCount++;
            }
        }

        return $deletedCount;
    }
}
