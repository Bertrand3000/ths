<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\AgentConnexion;
use App\Entity\AgentHistoriqueConnexion;
use App\Entity\AgentPosition;
use App\Entity\Enum\TypeConnexion;
use App\Repository\AgentConnexionRepository;
use App\Repository\AgentHistoriqueConnexionRepository;
use App\Repository\AgentPositionRepository;
use App\Repository\AgentRepository;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

class PositionService
{
    private const CONNECTION_TIMEOUT = '30 minutes';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgentRepository $agentRepository,
        private readonly AgentConnexionRepository $agentConnexionRepository,
        private readonly AgentPositionRepository $agentPositionRepository,
        private readonly AgentHistoriqueConnexionRepository $agentHistoriqueConnexionRepository,
        private readonly PositionRepository $positionRepository,
        private readonly SyslogService $syslogService,
        private readonly LoggerInterface $logger,
        private readonly LockFactory $lockFactory
    ) {
    }

    /**
     * Actualise la connexion et la position d'un agent.
     *
     * @param string $numeroAgent
     * @param string $ip
     * @param string $mac
     * @param string $status
     */
    public function actualiserAgent(string $numeroAgent, string $ip, string $mac, string $status = 'active'): void
    {
        $lock = $this->lockFactory->createLock('agent-'.$numeroAgent);

        if (!$lock->acquire()) {
            $this->logger->warning("Impossible d'acquérir le verrou pour l'agent $numeroAgent. Une autre opération est en cours.");
            return;
        }

        try {
            $typeConnexion = $this->determinerTypeConnexion($ip);

            if ($typeConnexion === null) {
                $this->logger->info("Connexion hors réseau RAMAGE pour l'agent $numeroAgent (IP: $ip). Traitement arrêté.");
                return;
            }

            $agent = $this->agentRepository->find($numeroAgent);
            if (!$agent) {
                $this->logger->warning("Agent non trouvé pour le numéro: $numeroAgent");
                return;
            }

            // Gérer agent_connexion (pour compatibilité client lourd)
            $this->updateAgentConnexion($agent, $typeConnexion, $ip, $mac, $status);

            $agentPosition = $this->agentPositionRepository->find($agent->getNumagent());
            $positionTrouvee = ($typeConnexion === TypeConnexion::SITE) ? $this->findPositionForSite($mac, $numeroAgent) : null;

            // Scénario 1: L'agent se connecte en Télétravail
            if ($typeConnexion === TypeConnexion::TELETRAVAIL) {
                if ($agentPosition) {
                    $this->finaliserHistorique($agentPosition, new \DateTime(), 'Télétravail');
                    $this->em->remove($agentPosition);
                }
            }
            // Scénario 2: L'agent est sur site et une position est trouvée
            elseif ($positionTrouvee) {
                if ($agentPosition && $agentPosition->getPosition() !== $positionTrouvee) {
                    // L'agent a changé de place
                    $this->finaliserHistorique($agentPosition, new \DateTime(), 'Changement de poste');
                    $this->em->remove($agentPosition); // On supprime l'ancienne pour en créer une nouvelle
                    $this->em->flush(); // On s'assure que la suppression est faite avant la création
                    $agentPosition = null; // Réinitialiser pour la création
                }

                if ($agentPosition === null) {
                    // Nouvelle position pour l'agent
                    $agentPosition = new AgentPosition();
                    $agentPosition->setAgent($agent);
                    $agentPosition->setPosition($positionTrouvee);
                    $agentPosition->setJour(new \DateTime());
                    $agentPosition->setDateconnexion(new \DateTime());
                    $this->creerHistorique($agentPosition);
                }

                $agentPosition->updateExpiration();
                $this->em->persist($agentPosition);
            }
            // Scénario 3: Agent sur site mais pas de position trouvée (ou connexion WIFI)
            else {
                // Si l'agent avait une position, on ne fait rien pour la conserver (cas du WIFI ou déconnexion temporaire)
                // Si la position expire, le cron de nettoyage s'en occupera.
                // On met juste à jour sa date d'expiration pour le maintenir actif
                if ($agentPosition) {
                    $agentPosition->updateExpiration();
                    $this->em->persist($agentPosition);
                }
            }

            $this->em->flush();
        } finally {
            $lock->release();
        }
    }

    private function updateAgentConnexion(Agent $agent, TypeConnexion $type, string $ip, string $mac, string $status): void
    {
        $connexion = $this->agentConnexionRepository->findOneBy(['agent' => $agent]) ?? new AgentConnexion();
        $connexion->setAgent($agent);
        $connexion->setType($type);
        $connexion->setIp($ip);
        $connexion->setMac($mac);
        $connexion->setStatus($status);
        if ($connexion->getId() === null) {
            $connexion->setDateconnexion(new \DateTime());
        }
        $connexion->setDateactualisation(new \DateTime());
        $this->em->persist($connexion);
    }

    private function findPositionForSite(string $mac, string $numeroAgent): ?\App\Entity\Position
    {
        try {
            $this->syslogService->analyzeSyslogEvents();
            return $this->positionRepository->findOneBy(['mac' => $mac]);
        } catch (\Exception $e) {
            $this->logger->critical(
                "Erreur critique lors de l'analyse des événements syslog pour l'agent {agent}: {message}",
                ['agent' => $numeroAgent, 'message' => $e->getMessage()]
            );
            return null;
        }
    }

    private function creerHistorique(AgentPosition $agentPosition): void
    {
        $historique = new AgentHistoriqueConnexion();
        $historique->setAgent($agentPosition->getAgent());
        $historique->setPosition($agentPosition->getPosition());
        $historique->setJour($agentPosition->getJour());
        $historique->setDateconnexion($agentPosition->getDateconnexion());
        $this->em->persist($historique);
    }

    private function finaliserHistorique(AgentPosition $agentPosition, \DateTimeInterface $dateDeconnexion, string $motif): void
    {
        $historique = $this->agentHistoriqueConnexionRepository->findOneBy([
            'agent' => $agentPosition->getAgent(),
            'datedeconnexion' => null
        ], ['dateconnexion' => 'DESC']);

        if ($historique) {
            $historique->setDatedeconnexion($dateDeconnexion);
            $this->em->persist($historique);
            $this->logger->info(
                "Historique finalisé pour l'agent {agent} à la position {position}. Motif: {motif}",
                [
                    'agent' => $agentPosition->getAgent()->getNumagent(),
                    'position' => $agentPosition->getPosition()->getId(),
                    'motif' => $motif
                ]
            );
        }
    }

    /**
     * Détermine le type de connexion en fonction de l'adresse IP.
     *
     * @param string $ip
     * @return TypeConnexion|null
     */
    private function determinerTypeConnexion(string $ip): ?TypeConnexion
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
             return null; // IP privée ou réservée, donc hors RAMAGE
        }

        $ipLong = ip2long($ip);

        // Réseaux Télétravail
        $teletravailRanges = [
            ['net' => '55.255.0.0', 'mask' => 16],
            ['net' => '55.254.0.0', 'mask' => 16],
            ['net' => '55.185.0.0', 'mask' => 16],
            ['net' => '55.184.0.0', 'mask' => 16],
        ];
        foreach ($teletravailRanges as $range) {
            if ($this->ipInRange($ipLong, $range['net'], $range['mask'])) {
                return TypeConnexion::TELETRAVAIL;
            }
        }

        // Réseaux Sur Site (non-wifi)
        $siteRanges = [
            ['net' => '55.153.4.1', 'mask' => 22],
            ['net' => '55.153.223.1', 'mask' => 24],
        ];
        foreach ($siteRanges as $range) {
            if ($this->ipInRange($ipLong, $range['net'], $range['mask'])) {
                return TypeConnexion::SITE;
            }
        }

        // Si c'est dans le réseau RAMAGE mais pas Télétravail ni Site, on considère WIFI
        if ($this->ipInRange($ipLong, '55.0.0.0', 8)) {
            return TypeConnexion::WIFI;
        }

        return null; // Hors réseau RAMAGE
    }

    /**
     * Vérifie si une adresse IP est dans une plage CIDR.
     */
    private function ipInRange(int $ipLong, string $netAddr, int $mask): bool
    {
        $netLong = ip2long($netAddr);
        $maskLong = -1 << (32 - $mask);
        return ($ipLong & $maskLong) === ($netLong & $maskLong);
    }

    /**
     * Déconnecte un agent.
     *
     * @param string $numeroAgent
     */
    public function deconnecterAgent(string $numeroAgent): void
    {
        $agent = $this->agentRepository->find($numeroAgent);
        if (!$agent) {
            $this->logger->warning("Tentative de déconnexion d'un agent non trouvé: $numeroAgent");
            return;
        }

        $connexion = $this->agentConnexionRepository->findOneBy(['agent' => $agent]);
        if ($connexion) {
            $connexion->setStatus('logout');
            $this->em->persist($connexion);
        }

        $position = $this->agentPositionRepository->find($agent->getNumagent());
        if ($position) {
            $this->finaliserHistorique($position, new \DateTime(), 'Déconnexion manuelle');
            $this->em->remove($position);
        }

        $this->em->flush();
        $this->logger->info("Agent $numeroAgent déconnecté.");
    }

    /**
     * Gère la mise en veille d'un agent.
     *
     * @param string $numeroAgent
     */
    public function veilleAgent(string $numeroAgent): void
    {
        $agent = $this->agentRepository->find($numeroAgent);
        if (!$agent) {
            $this->logger->warning("Tentative de mise en veille d'un agent non trouvé: $numeroAgent");
            return;
        }

        $connexion = $this->agentConnexionRepository->findOneBy(['agent' => $agent]);
        if ($connexion) {
            $connexion->setStatus('sleep');
            $this->em->persist($connexion);
            $this->em->flush();
            $this->logger->info("Agent $numeroAgent mis en veille.");
        } else {
            $this->logger->warning("Aucune connexion active trouvée pour l'agent $numeroAgent lors de la mise en veille.");
        }
    }


    /**
     * Nettoie les connexions et positions expirées.
     *
     * Cette fonction supprime les enregistrements de la table `agent_connexion` qui n'ont pas été
     * actualisés depuis plus de `CONNECTION_TIMEOUT`. Elle supprime également les
     * enregistrements `agent_position` associés.
     */
    /**
     * Nettoie les positions expirées.
     *
     * @return int Le nombre de positions nettoyées.
     */
    public function cleanExpiredPositions(): int
    {
        $expiredPositions = $this->agentPositionRepository->findExpiredPositions();
        $count = count($expiredPositions);

        if ($count === 0) {
            return 0;
        }

        $this->logger->info(sprintf('Nettoyage de %d positions expirées.', $count));

        foreach ($expiredPositions as $position) {
            $this->finaliserHistorique($position, $position->getDateexpiration(), 'Expiration automatique');
            $this->em->remove($position);
        }

        $this->em->flush();

        return $count;
    }
}
