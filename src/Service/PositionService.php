<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\AgentConnexion;
use App\Entity\AgentPosition;
use App\Entity\Enum\TypeConnexion;
use App\Repository\AgentConnexionRepository;
use App\Repository\AgentPositionRepository;
use App\Repository\AgentRepository;
use App\Repository\PositionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PositionService
{
    private const CONNECTION_TIMEOUT = '30 minutes';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AgentRepository $agentRepository,
        private readonly AgentConnexionRepository $agentConnexionRepository,
        private readonly AgentPositionRepository $agentPositionRepository,
        private readonly PositionRepository $positionRepository,
        private readonly SyslogService $syslogService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Actualise la connexion et la position d'un agent.
     *
     * @param string $numeroAgent
     * @param string $ip
     * @param string $mac
     */
    public function actualiserAgent(string $numeroAgent, string $ip, string $mac): void
    {
        $this->nettoyerConnexions();

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

        // Gérer agent_connexion
        $connexion = $this->agentConnexionRepository->findOneBy(['agent' => $agent]) ?? new AgentConnexion();
        $connexion->setAgent($agent);
        $connexion->setType($typeConnexion);
        $connexion->setIp($ip);
        $connexion->setMac($mac);
        if ($connexion->getId() === null) {
            $connexion->setDateconnexion(new \DateTime());
        }
        $connexion->setDateactualisation(new \DateTime());
        $this->em->persist($connexion);

        // Gérer agent_position
        $position = $this->agentPositionRepository->find($agent->getNumagent());

        switch ($typeConnexion) {
            case TypeConnexion::TELETRAVAIL:
                if ($position) {
                    $this->em->remove($position);
                }
                break;

            case TypeConnexion::SITE:
                // Pour le mode SITE, nous devons trouver la position via le syslog
                $this->syslogService->analyzeSyslogEvents();
                // La position devrait maintenant être mise à jour avec la bonne MAC
                // Nous devons trouver la position par MAC
                $positionTrouvee = $this->positionRepository->findOneBy(['mac' => $mac]);

                if ($positionTrouvee) {
                    if ($position === null) {
                        $position = new AgentPosition();
                        $position->setAgent($agent);
                        $position->setJour(new \DateTime());
                        $position->setDateconnexion(new \DateTime());
                    }
                    $position->setPosition($positionTrouvee);
                    $this->em->persist($position);
                } else {
                    $this->logger->warning("Impossible de trouver une position pour la MAC $mac pour l'agent $numeroAgent");
                    // Si on ne trouve pas de position, on supprime l'ancienne au cas où
                    if ($position) {
                        $this->em->remove($position);
                    }
                }
                break;

            case TypeConnexion::WIFI:
                // Ne rien faire sur la position
                break;
        }

        $this->em->flush();
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
            $this->em->remove($connexion);
        }

        $position = $this->agentPositionRepository->find($agent->getNumagent());
        if ($position) {
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
        // TODO: Implémenter la logique de mise en veille.
        // Pour l'instant, cette fonction ne fait rien.
        $this->logger->info("Mise en veille de l'agent $numeroAgent. Aucune action effectuée pour le moment.");
    }


    /**
     * Nettoie les connexions et positions expirées.
     *
     * Cette fonction supprime les enregistrements de la table `agent_connexion` qui n'ont pas été
     * actualisés depuis plus de `CONNECTION_TIMEOUT`. Elle supprime également les
     * enregistrements `agent_position` associés.
     */
    private function nettoyerConnexions(): void
    {
        $timeout = new \DateTime();
        $timeout->modify('-' . self::CONNECTION_TIMEOUT);

        $expiredConnexions = $this->agentConnexionRepository->findExpiredConnections($timeout);

        if (count($expiredConnexions) === 0) {
            return;
        }

        $this->logger->info(sprintf('Nettoyage de %d connexions expirées.', count($expiredConnexions)));

        foreach ($expiredConnexions as $connexion) {
            $agent = $connexion->getAgent();
            if ($agent) {
                $position = $this->agentPositionRepository->find($agent->getNumagent());
                if ($position) {
                    $this->em->remove($position);
                }
            }
            $this->em->remove($connexion);
        }

        $this->em->flush();
    }
}
