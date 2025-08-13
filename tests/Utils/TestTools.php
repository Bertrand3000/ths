<?php

namespace App\Tests\Utils;

use App\Entity\Agent;
use App\Entity\AgentHistoriqueConnexion;
use App\Entity\Position;
use App\Entity\Service;
use App\Entity\Etage;
use App\Entity\Site;
use App\Entity\NetworkSwitch;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;

class TestTools
{
    private $entityManager;

    public function __construct(ContainerInterface $container)
    {
        $this->entityManager = $container->get('doctrine')->getManager();
    }

    public function createTestAgent(string $numagent, bool $flush = true): Agent
    {
        $service = $this->createTestService(false);
        $agent = new Agent();
        $agent->setNumagent($numagent);
        $agent->setNom('Test');
        $agent->setPrenom('Agent');
        $agent->setCivilite('M.');
        $agent->setService($service);

        $this->entityManager->persist($agent);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $agent;
    }

    public function createTestPosition(bool $flush = true): Position
    {
        $service = $this->createTestService(false);
        $etage = $service->getEtage();
        $switch = $this->createTestSwitch($etage, false);

        $position = new Position();
        $position->setEtage($etage);
        $position->setService($service);
        $position->setNetworkSwitch($switch);
        $position->setCoordx(10);
        $position->setCoordy(20);
        $position->setPrise('A01');
        $position->setMac($this->generateRandomMac()); // MAC address unique pour tests
        $position->setType('Echange');

        $this->entityManager->persist($position);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $position;
    }

    public function createTestHistorique(Agent $agent, Position $position, ?\DateTimeInterface $date = null, bool $flush = true): AgentHistoriqueConnexion
    {
        $historique = new AgentHistoriqueConnexion();
        $historique->setAgent($agent);
        $historique->setPosition($position);
        $historique->setJour($date ?? new \DateTime());
        $historique->setDateconnexion($date ?? new \DateTime());

        $this->entityManager->persist($historique);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $historique;
    }

    private function createTestService(bool $flush = true): Service
    {
        $etage = $this->createTestEtage(false);
        $service = new Service();
        $service->setNom('Service Test');
        $service->setEtage($etage);

        $this->entityManager->persist($service);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $service;
    }

    private function createTestEtage(bool $flush = true): Etage
    {
        $site = $this->createTestSite(false);
        $etage = new Etage();
        $etage->setNom('Etage Test');
        $etage->setSite($site);
        $etage->setArriereplan('test.jpg');
        $etage->setLargeur(100);
        $etage->setHauteur(100);

        $this->entityManager->persist($etage);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $etage;
    }

    private function createTestSite(bool $flush = true): Site
    {
        $site = new Site();
        $site->setNom('Site Test');
        $site->setFlex(true);

        $this->entityManager->persist($site);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $site;
    }

    private function createTestSwitch(Etage $etage, bool $flush = true): NetworkSwitch
    {
        $switch = new NetworkSwitch();
        $switch->setNom('Switch Test');
        $switch->setEtage($etage);
        $switch->setCoordx(5);
        $switch->setCoordy(5);
        $switch->setNbprises(24);

        $this->entityManager->persist($switch);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $switch;
    }

    private function generateRandomMac(): string
    {
        $mac = [];
        for ($i = 0; $i < 6; $i++) {
            $mac[] = sprintf('%02X', rand(0, 255));
        }
        return implode(':', $mac);
    }
}
