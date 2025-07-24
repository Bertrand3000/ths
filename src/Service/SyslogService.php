<?php

namespace App\Service;

use App\Repository\ConfigRepository;
use App\Repository\NetworkSwitchRepository;
use App\Repository\PositionRepository;
use App\Repository\SystemeventsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service d'analyse des événements syslog avec gestion robuste des erreurs
 */
class SyslogService
{
    public const DERNIER_SYSLOG_ID_KEY = 'dernier_syslog_id';
    public const DERNIER_NETTOYAGE_SYSLOG_KEY = 'dernier_nettoyage_syslog';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ConfigRepository $configRepository,
        private readonly SystemeventsRepository $systemeventsRepository,
        private readonly PositionRepository $positionRepository,
        private readonly NetworkSwitchRepository $networkSwitchRepository,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Analyse les événements syslog par lots pour optimiser les performances.
     *
     * @return int Nombre total d'événements traités
     */
    public function analyzeSyslogEvents(): int
    {
        $batchSize = $this->parameterBag->get('tehou.syslog.batch_size');
        $maxProcessingTime = $this->parameterBag->get('tehou.syslog.max_processing_time');
        $dernierIdTraite = (int)($this->configRepository->find(self::DERNIER_SYSLOG_ID_KEY)?->getValeur() ?? 0);

        $startTime = microtime(true);
        $totalProcessed = 0;
        $errorCount = 0;

        do {
            if ($this->shouldStopProcessing($errorCount, $startTime, $maxProcessingTime)) {
                $this->logger->warning('Arrêt du traitement syslog - Circuit breaker activé', [
                    'error_count' => $errorCount,
                    'processing_time' => microtime(true) - $startTime,
                ]);
                break;
            }

            $events = $this->systemeventsRepository->findNewEvents($dernierIdTraite, $batchSize);
            if (empty($events)) {
                break;
            }

            $batchResult = $this->processBatch($events);
            $totalProcessed += $batchResult['processed'];
            $errorCount += $batchResult['errors'];

            $dernierIdTraite = $events[count($events) - 1]->getId();
            $this->updateDernierIdTraite($dernierIdTraite);

            $this->em->flush();
            $this->em->clear();

        } while (count($events) === $batchSize);

        $this->recordProcessingMetrics($totalProcessed, $errorCount, microtime(true) - $startTime);

        return $totalProcessed;
    }

    /**
     * Traite un lot d'événements.
     *
     * @param array $events
     * @return array ['processed' => int, 'errors' => int]
     */
    private function processBatch(array $events): array
    {
        $processed = 0;
        $errors = 0;

        foreach ($events as $event) {
            $processed++;
            try {
                $this->processSyslogMessage($event->getMessage() ?? '', $event->getSyslogtag() ?? '');
            } catch (\Exception $e) {
                $this->logger->critical('Erreur critique pendant le traitement d\'un événement syslog', [
                    'event_id' => $event->getId(),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors++;
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }

    /**
     * Met à jour la valeur de configuration du dernier ID traité.
     *
     * @param int $dernierIdTraite
     */
    private function updateDernierIdTraite(int $dernierIdTraite): void
    {
        $configDernierId = $this->configRepository->find(self::DERNIER_SYSLOG_ID_KEY);
        if (!$configDernierId) {
            $configDernierId = new \App\Entity\Config();
            $configDernierId->setCle(self::DERNIER_SYSLOG_ID_KEY);
            $this->em->persist($configDernierId);
        }
        $configDernierId->setValeur((string)$dernierIdTraite);
        $configDernierId->setDateMaj(new \DateTime());
    }

    /**
     * Traite un message syslog individuel pour mettre à jour la position.
     *
     * @param string $message Le message de l'événement syslog.
     * @param string $syslogTag Le tag syslog, utilisé pour identifier le switch.
     */
    private function processSyslogMessage(string $message, string $syslogTag): void
    {
        if ($connectionData = $this->parseConnectionMessage($message)) {
            $mac = $this->validateAndNormalizeMac($connectionData['mac']);
            if ($mac) {
                $this->updatePositionMac($syslogTag, $connectionData['port'], $mac);
            }
        } elseif ($disconnectionData = $this->parseDisconnectionMessage($message)) {
            $this->updatePositionMac($syslogTag, $disconnectionData['port'], null);
        } else {
            $this->logger->error('Message syslog non reconnu', [
                'message' => $message,
                'tag' => $syslogTag,
            ]);
        }
    }

    /**
     * Parse les messages de connexion avec support multi-formats.
     *
     * @param string $message Message syslog à analyser.
     * @return array|null ['port' => string, 'mac' => string] ou null si échec.
     */
    private function parseConnectionMessage(string $message): ?array
    {
        $patterns = $this->parameterBag->get('tehou.syslog.regex_patterns.connection');
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return [
                    'port' => $matches[1],
                    'mac' => $matches[2],
                ];
            }
        }
        return null;
    }

    /**
     * Parse les messages de déconnexion avec support multi-formats.
     *
     * @param string $message Message syslog à analyser.
     * @return array|null ['port' => string] ou null si échec.
     */
    private function parseDisconnectionMessage(string $message): ?array
    {
        $patterns = $this->parameterBag->get('tehou.syslog.regex_patterns.disconnection');
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return [
                    'port' => $matches[1],
                ];
            }
        }
        return null;
    }

    /**
     * Met à jour l'adresse MAC d'une position en fonction du switch et du port.
     *
     * @param string $switchName Nom du switch (depuis syslogtag).
     * @param string $portName Nom du port (ex: GigabitEthernet1/0/21).
     * @param string|null $mac Nouvelle adresse MAC, ou null pour la déconnexion.
     */
    private function updatePositionMac(string $switchName, string $portName, ?string $mac): void
    {
        $switch = $this->networkSwitchRepository->findOneBy(['nom' => $switchName]);
        if (!$switch) {
            $this->logger->warning('Switch non trouvé dans la base de données', [
                'switch_name' => $switchName,
            ]);
            return;
        }

        // Extrait le numéro de la prise du nom du port, ex: "GigabitEthernet1/0/21" -> "21"
        if (!preg_match('/(\d+)$/', $portName, $matches)) {
            return;
        }
        $prise = 'P' . $matches[1];

        $position = $this->positionRepository->findOneBy(['networkSwitch' => $switch, 'prise' => $prise]);

        if ($position) {
            $position->setMac($mac);
            $this->em->persist($position);
            $this->em->flush(); // Flush ici pour que les modifs soient prises en compte dans la même transaction
        }
    }

    /**
     * Nettoie les anciens événements syslog (si > 24h depuis dernier nettoyage).
     *
     * @return bool True si nettoyage effectué, false sinon
     * @throws \Exception
     */
    public function cleanupOldSyslogEvents(): bool
    {
        $dernierNettoyageConfig = $this->configRepository->find(self::DERNIER_NETTOYAGE_SYSLOG_KEY);
        $now = new \DateTime();

        if ($dernierNettoyageConfig) {
            $dernierNettoyageDate = new \DateTime($dernierNettoyageConfig->getValeur());
            if ($now->getTimestamp() - $dernierNettoyageDate->getTimestamp() < 24 * 3600) {
                return false; // Pas encore 24h
            }
        }

        $dernierIdLu = $this->configRepository->find(self::DERNIER_SYSLOG_ID_KEY)?->getValeur();
        if ($dernierIdLu === null) {
            return false; // Rien à nettoyer si on n'a jamais rien lu
        }

        $this->systemeventsRepository->deleteOldEvents((int)$dernierIdLu);

        if (!$dernierNettoyageConfig) {
            $dernierNettoyageConfig = new \App\Entity\Config();
            $dernierNettoyageConfig->setCle(self::DERNIER_NETTOYAGE_SYSLOG_KEY);
            $this->em->persist($dernierNettoyageConfig);
        }
        $dernierNettoyageConfig->setValeur($now->format('Y-m-d H:i:s'));
        $dernierNettoyageConfig->setDateMaj($now);

        $this->em->flush();

        return true;
    }

    /**
     * Normalise une adresse MAC en supprimant les séparateurs et en la mettant en minuscules.
     *
     * @param string $mac
     * @return string
     */
    private function normalizeMacAddress(string $mac): string
    {
        return strtolower(str_replace([':', '-', '.'], '', $mac));
    }

    /**
     * Valide et normalise une adresse MAC.
     *
     * @param string $mac Adresse MAC à valider.
     * @return string|null MAC normalisée ou null si invalide.
     */
    private function validateAndNormalizeMac(string $mac): ?string
    {
        $normalizedMac = preg_replace('/[^0-9a-fA-F]/', '', $mac);
        $normalizedMac = strtolower($normalizedMac);

        if (strlen($normalizedMac) !== 12) {
            $this->logger->warning('Adresse MAC de longueur invalide détectée', [
                'mac_original' => $mac,
                'mac_normalized' => $normalizedMac
            ]);
            return null;
        }

        $formattedMac = implode(':', str_split($normalizedMac, 2));

        if (!preg_match('/^([0-9a-f]{2}:){5}[0-9a-f]{2}$/', $formattedMac)) {
            $this->logger->warning('Adresse MAC invalide détectée', [
                'mac_original' => $mac,
                'mac_formatted' => $formattedMac
            ]);
            return null;
        }

        return $formattedMac;
    }

    /**
     * Détermine si le traitement doit être arrêté (circuit breaker).
     *
     * @param int $errorCount
     * @param float $startTime
     * @param int $maxTime
     * @return bool
     */
    private function shouldStopProcessing(int $errorCount, float $startTime, int $maxTime): bool
    {
        $maxErrors = $this->parameterBag->get('tehou.syslog.max_errors');
        $currentDuration = microtime(true) - $startTime;

        return $errorCount > $maxErrors || $currentDuration > $maxTime;
    }

    /**
     * Enregistre les métriques de traitement pour monitoring.
     *
     * @param int $processed
     * @param int $errors
     * @param float $duration
     */
    private function recordProcessingMetrics(int $processed, int $errors, float $duration): void
    {
        $this->logger->info('Traitement syslog terminé', [
            'events_processed' => $processed,
            'errors_encountered' => $errors,
            'processing_duration_seconds' => round($duration, 2),
            'events_per_second' => $processed > 0 ? round($processed / $duration, 2) : 0,
            'error_rate_percent' => $processed > 0 ? round(($errors / $processed) * 100, 2) : 0
        ]);
    }
}
