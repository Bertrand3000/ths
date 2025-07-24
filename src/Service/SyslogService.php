<?php

namespace App\Service;

use App\Repository\ConfigRepository;
use App\Repository\NetworkSwitchRepository;
use App\Repository\PositionRepository;
use App\Repository\SystemeventsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service pour l'analyse des événements réseau syslog et la mise à jour des correspondances MAC/Position.
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
        private readonly NetworkSwitchRepository $networkSwitchRepository
    ) {
    }

    /**
     * Analyse les nouveaux événements syslog et met à jour les positions.
     *
     * @return int Nombre d'événements traités
     * @throws \Exception
     */
    public function analyzeSyslogEvents(): int
    {
        $dernierIdTraite = (int) ($this->configRepository->find(self::DERNIER_SYSLOG_ID_KEY)?->getValeur() ?? 0);
        $nouveauxEvenements = $this->systemeventsRepository->findNewEvents($dernierIdTraite);

        if (empty($nouveauxEvenements)) {
            return 0;
        }

        $this->em->beginTransaction();
        try {
            foreach ($nouveauxEvenements as $event) {
                $this->processSyslogMessage($event->getMessage() ?? '', $event->getSyslogtag() ?? '');
                $dernierIdTraite = $event->getId();
            }

            $configDernierId = $this->configRepository->find(self::DERNIER_SYSLOG_ID_KEY);
            if (!$configDernierId) {
                $configDernierId = new \App\Entity\Config();
                $configDernierId->setCle(self::DERNIER_SYSLOG_ID_KEY);
                $this->em->persist($configDernierId);
            }
            $configDernierId->setValeur((string)$dernierIdTraite);
            $configDernierId->setDateMaj(new \DateTime());

            $this->em->commit();
        } catch (\Exception $e) {
            $this->em->rollback();
            // Logger l'erreur, par exemple avec un service de log injecté
            throw $e;
        }

        return count($nouveauxEvenements);
    }

    /**
     * Traite un message syslog individuel pour mettre à jour la position.
     *
     * @param string $message Le message de l'événement syslog.
     * @param string $syslogTag Le tag syslog, utilisé pour identifier le switch.
     */
    private function processSyslogMessage(string $message, string $syslogTag): void
    {
        // Connexion: %%10LLDP/6/LLDP_CREATE_NEIGHBOR: ... port GigabitEthernet1/0/X ... MAC
        if (preg_match('/LLDP_CREATE_NEIGHBOR:.*?port (GigabitEthernet[\d\/]+).*?port ID is ([\w-]+)/', $message, $matches)) {
            $portName = $matches[1];
            $macRaw = str_replace('-', '', strtolower($matches[2]));
            $mac = implode(':', str_split($macRaw, 2));

            $this->updatePositionMac($syslogTag, $portName, $mac);
        }
        // Déconnexion: %%10IFNET/3/PHY_UPDOWN: ... GigabitEthernet1/0/X changed to down
        elseif (preg_match('/PHY_UPDOWN:.*?interface (GigabitEthernet[\d\/]+) changed to down/', $message, $matches)) {
            $portName = $matches[1];
            $this->updatePositionMac($syslogTag, $portName, null);
        }
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
            // Switch non trouvé, on pourrait logger cette information
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
}
