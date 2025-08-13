<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\AgentConnexion;
use App\Entity\AgentHistoriqueConnexion;
use App\Entity\AgentPosition;
use App\Entity\Etage;
use App\Entity\Materiel;
use App\Entity\NetworkSwitch;
use App\Entity\Position;
use App\Entity\Service;
use App\Entity\Site;
use App\Repository\AgentPositionRepository;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ArchitectureService
{
    private string $nomsFile;
    private string $prenomsFile;

    public array $sites = [
        ['nom' => 'Siège', 'flex' => true],
        ['nom' => 'Abbeville', 'flex' => false],
    ];

    public array $etagesSiege = [
        ['nom' => 'Rez-de-jardin', 'niveau' => -1],
        ['nom' => 'Rez-de-chaussée', 'niveau' => 0],
        ['nom' => 'Étage 1', 'niveau' => 1],
        ['nom' => 'Étage 2', 'niveau' => 2],
        ['nom' => 'Étage 3', 'niveau' => 3],
        ['nom' => 'Étage 4', 'niveau' => 4],
        ['nom' => 'Étage 5', 'niveau' => 5],
    ];

    public array $etagesAbbeville = [
        ['nom' => 'Rez-de-chaussée', 'niveau' => 0],
        ['nom' => 'Étage 1', 'niveau' => 1],
        ['nom' => 'Étage 2', 'niveau' => 2],
    ];

    public int $servicesPerEtage = 2;
    public int $switchesPerEtageSiege = 4;
    public int $prisesPerSwitch = 5;
    public int $positionsPerSwitch = 3;
    public int $agentsCount = 100;

    public array $positionTypes = ['Echange', 'Concentration', 'Bulle', 'Réunion', 'Formation'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SiteRepository $siteRepository,
        private readonly AgentPositionRepository $agentPositionRepository,
        ParameterBagInterface $parameterBag = null
    ) {
        // Valeurs par défaut (ajustez le chemin selon votre structure)
        $projectDir = $parameterBag?->get('kernel.project_dir') ?? __DIR__ . '/../../';
        $this->nomsFile = $projectDir . '/src/Data/noms.txt';
        $this->prenomsFile = $projectDir . '/src/Data/prenoms.txt';
    }

    /**
     * Calcule le rectangle englobant pour un service sur un étage donné.
     * @param Service $service
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public function getServiceBoundingBox(Service $service): ?array
    {
        $positions = $service->getPositions()->toArray();

        if (empty($positions)) {
            return null;
        }

        $minX = PHP_INT_MAX;
        $minY = PHP_INT_MAX;
        $maxX = PHP_INT_MIN;
        $maxY = PHP_INT_MIN;

        foreach ($positions as $position) {
            $minX = min($minX, $position->getCoordx());
            $minY = min($minY, $position->getCoordy());
            $maxX = max($maxX, $position->getCoordx());
            $maxY = max($maxY, $position->getCoordy());
        }

        $padding = 10; // Marge de 10 pixels

        return [
            'x' => $minX - $padding,
            'y' => $minY - $padding,
            'width' => ($maxX - $minX) + (2 * $padding),
            'height' => ($maxY - $minY) + (2 * $padding),
        ];
    }

    /**
     * Calcule le taux d'occupation d'un service.
     * @param Service $service
     * @return array{total: int, occupied: int, rate: float, color: string}
     */
    public function getServiceOccupancyStats(Service $service): array
    {
        $positions = $service->getPositions()->toArray();
        $total = count($positions);

        if ($total === 0) {
            return ['total' => 0, 'occupied' => 0, 'rate' => 0.0, 'color' => 'grey'];
        }

        $positionIds = array_map(fn($p) => $p->getId(), $positions);
        $occupiedCount = $this->agentPositionRepository->count(['position' => $positionIds]);

        $rate = ($occupiedCount / $total) * 100;

        if ($rate >= 100) {
            $color = 'black';
        } elseif ($rate >= 80) {
            $color = 'red';
        } elseif ($rate >= 50) {
            $color = 'orange';
        } else {
            $color = 'green';
        }

        return [
            'total' => $total,
            'occupied' => $occupiedCount,
            'rate' => round($rate, 2),
            'color' => $color,
        ];
    }

    /**
     * Initialise l'architecture de base de l'application si la base de données est vide.
     * Cette méthode crée les sites, étages, services, switches, positions, matériel et agents.
     * Elle est conçue pour ne s'exécuter qu'une seule fois.
     */
    public function initialiser(): void
    {
        if ($this->siteRepository->count([]) > 0) {
            return;
        }

        try {
            $this->createSites();
            $this->em->flush();

            $this->createAgents();
            $this->em->flush();
        } catch (\Exception $e) {
            // Ignore silently
        }
    }

    /**
     * Crée les sites et toutes les entités dépendantes (étages, services, etc.).
     */
    private function createSites(): void
    {
        foreach ($this->sites as $siteData) {
            $site = new Site();
            $site->setNom($siteData['nom']);
            $site->setFlex($siteData['flex']);
            $this->em->persist($site);

            $etagesData = ($siteData['nom'] === 'Siège') ? $this->etagesSiege : $this->etagesAbbeville;
            $this->createEtages($site, $etagesData);
        }
    }

    /**
     * Crée les étages pour un site donné.
     *
     * @param Site $site Le site parent.
     * @param array $etagesData Les données des étages à créer.
     */
    private function createEtages(Site $site, array $etagesData): void
    {
        foreach ($etagesData as $etageData) {
            $etage = new Etage();
            $etage->setSite($site);
            $etage->setNom($etageData['nom']);
            $etage->setArriereplan('background_' . rand(1, 10) . '.jpg');
            $etage->setLargeur(1000);
            $etage->setHauteur(1000);
            $this->em->persist($etage);

            $this->createServices($etage);
            $this->em->flush(); // Flush pour que les services soient disponibles

            if ($site->getNom() === 'Siège') {
                $this->createSwitches($etage);
            }
        }
    }

    /**
     * Crée les services pour un étage donné.
     *
     * @param Etage $etage L'étage parent.
     */
    private function createServices(Etage $etage): void
    {
        for ($i = 0; $i < $this->servicesPerEtage; $i++) {
            $service = new Service();
            $service->setEtage($etage);
            $service->setNom('Service ' . $etage->getNom() . ' ' . ($i + 1));
            $this->em->persist($service);
        }
    }

    /**
     * Crée les switches pour un étage donné.
     *
     * @param Etage $etage L'étage parent.
     */
    private function createSwitches(Etage $etage): void
    {
        for ($i = 0; $i < $this->switchesPerEtageSiege; $i++) {
            $switch = new NetworkSwitch();
            $switch->setEtage($etage);
            $switch->setNom('GigabitEthernet1/0/' . ($i + 1));
            $switch->setNbprises($this->prisesPerSwitch);
            $switch->setCoordx(rand(0, 999));
            $switch->setCoordy(rand(0, 999));
            $this->em->persist($switch);

            $this->createPositions($switch);
        }
    }

    /**
     * Crée les positions pour un switch donné.
     *
     * @param NetworkSwitch $switch Le switch parent.
     */
    private function createPositions(NetworkSwitch $switch): void
    {
        $services = $switch->getEtage()->getServices()->toArray();
        for ($i = 0; $i < $this->positionsPerSwitch; $i++) {
            $position = new Position();
            $position->setNetworkSwitch($switch);
            $position->setEtage($switch->getEtage());
            $position->setService($services[array_rand($services)]);
            $position->setType($this->positionTypes[array_rand($this->positionTypes)]);
            $position->setFlex(true);
            $position->setSanctuaire(false);
            $position->setCoordx(rand(0, 999));
            $position->setCoordy(rand(0, 999));
            $position->setPrise('P' . ($i + 1));
            $position->setMac($this->generateRandomMac());
            $this->em->persist($position);

            $this->createMateriels($position);
        }
    }

    /**
     * Crée le matériel pour une position donnée.
     *
     * @param Position $position La position parente.
     */
    private function createMateriels(Position $position): void
    {
        $types = ['dock', 'écran', 'écran'];
        foreach ($types as $type) {
            $materiel = new Materiel();
            $materiel->setPosition($position);
            $materiel->setType($type);
            $materiel->setCodebarre($this->generateRandomBarcode());
            $this->em->persist($materiel);
        }
    }

    /**
     * Crée les agents et les répartit dans les services.
     */
    private function createAgents(): void
    {
        $services = $this->em->getRepository(Service::class)->findAll();
        if (empty($services)) {
            return;
        }

        $noms = file($this->nomsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $prenoms = file($this->prenomsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        for ($i = 0; $i < $this->agentsCount; $i++) {
            $agent = new Agent();
            $agent->setService($services[array_rand($services)]);
            $agent->setNom($noms[array_rand($noms)]);
            $agent->setPrenom($prenoms[array_rand($prenoms)]);
            $agent->setCivilite(rand(0, 1) ? 'M.' : 'Mme');
            $agent->setNumagent(str_pad((string)rand(1000, 99999), 5, '0', STR_PAD_LEFT));
            $this->em->persist($agent);
        }
    }

    /**
     * Génère une adresse MAC aléatoire.
     *
     * @return string
     */
    private function generateRandomMac(): string
    {
        return implode(':', str_split(str_pad(dechex(mt_rand(0, 0xFFFFFFFFFFFF)), 12, '0', STR_PAD_LEFT), 2));
    }

    /**
     * Génère un code-barres aléatoire.
     *
     * @return string
     */
    private function generateRandomBarcode(): string
    {
        return sprintf(
            '%02d-%02d-%03d-%04d',
            rand(10, 99),
            rand(10, 99),
            rand(100, 999),
            rand(1000, 9999)
        );
    }

    //region Lecture
    /**
     * Retourne tous les sites.
     * @return Site[]
     */
    public function getSites(): array
    {
        return $this->em->getRepository(Site::class)->findAll();
    }

    /**
     * Retourne le site principal (ou premier site).
     * @return Site|null
     */
    public function getSitePrincipal(): ?Site
    {
        return $this->em->getRepository(Site::class)->findOneBy([]);
    }

    /**
     * Retourne tous les étages d'un site.
     * @param Site $site
     * @return Etage[]
     */
    public function getEtages(Site $site): array
    {
        return $this->em->getRepository(Etage::class)->findBy(['site' => $site]);
    }

    /**
     * Retourne tous les services d'un étage.
     * @param Etage $etage
     * @return Service[]
     */
    public function getServices(Etage $etage): array
    {
        return $this->em->getRepository(Service::class)->findBy(['etage' => $etage]);
    }

    /**
     * Retourne tous les switches d'un étage.
     * @param Etage $etage
     * @return NetworkSwitch[]
     */
    public function getSwitches(Etage $etage): array
    {
        return $this->em->getRepository(NetworkSwitch::class)->findBy(['etage' => $etage]);
    }

    /**
     * Retourne toutes les positions d'un étage.
     * @param Etage $etage
     * @return Position[]
     */
    public function getPositions(Etage $etage): array
    {
        return $this->em->getRepository(Position::class)->findBy(['etage' => $etage]);
    }
    //endregion

    //region CRUD Site
    /**
     * Crée un nouveau site.
     * @param array $data
     * @return Site
     */
    public function addSite(array $data): Site
    {
        $site = new Site();
        $site->setNom($data['nom']);
        $site->setFlex($data['flex']);

        $this->em->persist($site);
        $this->em->flush();

        return $site;
    }

    /**
     * Met à jour un site existant.
     * @param int $id
     * @param array $data
     * @return Site
     */
    public function updateSite(int $id, array $data): Site
    {
        $site = $this->em->getRepository(Site::class)->find($id);

        if (!$site) {
            throw new \InvalidArgumentException("Site with id $id does not exist!");
        }

        $site->setNom($data['nom']);
        $site->setFlex($data['flex']);

        $this->em->flush();

        return $site;
    }

    /**
     * Supprime un site.
     * @param int $id
     * @return bool
     */
    public function deleteSite(int $id): bool
    {
        $site = $this->em->getRepository(Site::class)->find($id);

        if (!$site) {
            throw new \InvalidArgumentException("Site with id $id does not exist!");
        }

        $this->em->remove($site);
        $this->em->flush();

        return true;
    }
    //endregion

    //region CRUD Etage
    /**
     * Crée un nouvel étage.
     * @param array $data
     * @return Etage
     */
    public function addEtage(array $data): Etage
    {
        $site = $this->em->getRepository(Site::class)->find($data['site_id']);
        if (!$site) {
            throw new \InvalidArgumentException("Site with id {$data['site_id']} does not exist!");
        }

        $etage = new Etage();
        $etage->setSite($site);
        $etage->setNom($data['nom']);
        $etage->setArriereplan($data['arriereplan']);
        $etage->setLargeur($data['largeur']);
        $etage->setHauteur($data['hauteur']);

        $this->em->persist($etage);
        $this->em->flush();

        return $etage;
    }

    /**
     * Met à jour un étage existant.
     * @param int $id
     * @param array $data
     * @return Etage
     */
    public function updateEtage(int $id, array $data): Etage
    {
        $etage = $this->em->getRepository(Etage::class)->find($id);

        if (!$etage) {
            throw new \InvalidArgumentException("Etage with id $id does not exist!");
        }

        if (isset($data['site_id'])) {
            $site = $this->em->getRepository(Site::class)->find($data['site_id']);
            if (!$site) {
                throw new \InvalidArgumentException("Site with id {$data['site_id']} does not exist!");
            }
            $etage->setSite($site);
        }

        $etage->setNom($data['nom']);
        $etage->setArriereplan($data['arriereplan']);
        $etage->setLargeur($data['largeur']);
        $etage->setHauteur($data['hauteur']);

        $this->em->flush();

        return $etage;
    }

    /**
     * Supprime un étage.
     * @param int $id
     * @return bool
     */
    public function deleteEtage(int $id): bool
    {
        $etage = $this->em->getRepository(Etage::class)->find($id);

        if (!$etage) {
            throw new \InvalidArgumentException("Etage with id $id does not exist!");
        }

        $this->em->remove($etage);
        $this->em->flush();

        return true;
    }
    //endregion

    //region CRUD Service
    /**
     * Crée un nouveau service.
     * @param array $data
     * @return Service
     */
    public function addService(array $data): Service
    {
        $etage = $this->em->getRepository(Etage::class)->find($data['etage_id']);
        if (!$etage) {
            throw new \InvalidArgumentException("Etage with id {$data['etage_id']} does not exist!");
        }

        $service = new Service();
        $service->setEtage($etage);
        $service->setNom($data['nom']);

        $this->em->persist($service);
        $this->em->flush();

        return $service;
    }

    /**
     * Met à jour un service existant.
     * @param int $id
     * @param array $data
     * @return Service
     */
    public function updateService(int $id, array $data): Service
    {
        $service = $this->em->getRepository(Service::class)->find($id);

        if (!$service) {
            throw new \InvalidArgumentException("Service with id $id does not exist!");
        }

        if (isset($data['etage_id'])) {
            $etage = $this->em->getRepository(Etage::class)->find($data['etage_id']);
            if (!$etage) {
                throw new \InvalidArgumentException("Etage with id {$data['etage_id']} does not exist!");
            }
            $service->setEtage($etage);
        }

        $service->setNom($data['nom']);

        $this->em->flush();

        return $service;
    }

    /**
     * Supprime un service.
     * @param int $id
     * @return bool
     */
    public function deleteService(int $id): bool
    {
        $service = $this->em->getRepository(Service::class)->find($id);

        if (!$service) {
            throw new \InvalidArgumentException("Service with id $id does not exist!");
        }

        $this->em->remove($service);
        $this->em->flush();

        return true;
    }
    //endregion

    //region CRUD Switch
    /**
     * Crée un nouveau switch.
     * @param array $data
     * @return NetworkSwitch
     */
    public function addSwitch(array $data): NetworkSwitch
    {
        $etage = $this->em->getRepository(Etage::class)->find($data['etage_id']);
        if (!$etage) {
            throw new \InvalidArgumentException("Etage with id {$data['etage_id']} does not exist!");
        }

        $switch = new NetworkSwitch();
        $switch->setEtage($etage);
        $switch->setNom($data['nom']);
        $switch->setNbprises($data['nbprises']);
        $switch->setCoordx($data['coordx']);
        $switch->setCoordy($data['coordy']);

        $this->em->persist($switch);
        $this->em->flush();

        return $switch;
    }

    /**
     * Met à jour un switch existant.
     * @param int $id
     * @param array $data
     * @return NetworkSwitch
     */
    public function updateSwitch(int $id, array $data): NetworkSwitch
    {
        $switch = $this->em->getRepository(NetworkSwitch::class)->find($id);

        if (!$switch) {
            throw new \InvalidArgumentException("Switch with id $id does not exist!");
        }

        if (isset($data['etage_id'])) {
            $etage = $this->em->getRepository(Etage::class)->find($data['etage_id']);
            if (!$etage) {
                throw new \InvalidArgumentException("Etage with id {$data['etage_id']} does not exist!");
            }
            $switch->setEtage($etage);
        }

        $switch->setNom($data['nom']);
        $switch->setNbprises($data['nbprises']);
        $switch->setCoordx($data['coordx']);
        $switch->setCoordy($data['coordy']);

        $this->em->flush();

        return $switch;
    }

    /**
     * Supprime un switch.
     * @param int $id
     * @return bool
     */
    public function deleteSwitch(int $id): bool
    {
        $switch = $this->em->getRepository(NetworkSwitch::class)->find($id);

        if (!$switch) {
            throw new \InvalidArgumentException("Switch with id $id does not exist!");
        }

        $this->em->remove($switch);
        $this->em->flush();

        return true;
    }
    //endregion

    //region CRUD Position
    /**
     * Crée une nouvelle position.
     * @param array $data
     * @return Position
     */
    public function addPosition(array $data): Position
    {
        $etage = $this->em->getRepository(Etage::class)->find($data['etage_id']);
        if (!$etage) {
            throw new \InvalidArgumentException("Etage with id {$data['etage_id']} does not exist!");
        }

        $position = new Position();
        $position->setEtage($etage);

        if (!empty($data['service_id'])) {
            $service = $this->em->getRepository(Service::class)->find($data['service_id']);
            if (!$service) {
                throw new \InvalidArgumentException("Service with id {$data['service_id']} does not exist!");
            }
            $position->setService($service);
        }

        if (!empty($data['switch_id'])) {
            $switch = $this->em->getRepository(NetworkSwitch::class)->find($data['switch_id']);
            if (!$switch) {
                throw new \InvalidArgumentException("Switch with id {$data['switch_id']} does not exist!");
            }
            $position->setNetworkSwitch($switch);
        }
        $position->setCoordx($data['coordx']);
        $position->setCoordy($data['coordy']);
        $position->setPrise($data['prise']);
        $position->setMac($data['mac'] ?? null);
        $position->setType($data['type']);
        $position->setSanctuaire($data['sanctuaire']);
        $position->setFlex($data['flex']);

        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }

    /**
     * Met à jour une position existante.
     * @param int $id
     * @param array $data
     * @return Position
     */
    public function updatePosition(int $id, array $data): Position
    {
        $position = $this->em->getRepository(Position::class)->find($id);

        if (!$position) {
            throw new \InvalidArgumentException("Position with id $id does not exist!");
        }

        if (isset($data['etage_id'])) {
            $etage = $this->em->getRepository(Etage::class)->find($data['etage_id']);
            if (!$etage) {
                throw new \InvalidArgumentException("Etage with id {$data['etage_id']} does not exist!");
            }
            $position->setEtage($etage);
        }

        if (array_key_exists('service_id', $data)) {
            $service = null;
            if ($data['service_id'] !== null) {
                $service = $this->em->getRepository(Service::class)->find($data['service_id']);
                if (!$service) {
                    throw new \InvalidArgumentException("Service with id {$data['service_id']} does not exist!");
                }
            }
            $position->setService($service);
        }

        if (array_key_exists('switch_id', $data)) {
            $switch = null;
            if ($data['switch_id'] !== null) {
                $switch = $this->em->getRepository(NetworkSwitch::class)->find($data['switch_id']);
                if (!$switch) {
                    throw new \InvalidArgumentException("Switch with id {$data['switch_id']} does not exist!");
                }
            }
            $position->setNetworkSwitch($switch);
        }

        $position->setCoordx($data['coordx']);
        $position->setCoordy($data['coordy']);
        $position->setPrise($data['prise']);
        $position->setMac($data['mac'] ?? null);
        $position->setType($data['type']);
        $position->setSanctuaire($data['sanctuaire']);
        $position->setFlex($data['flex']);

        $this->em->flush();

        return $position;
    }

    /**
     * Supprime une position.
     * @param int $id
     * @return bool
     */
    public function deletePosition(int $id): bool
    {
        $position = $this->em->getRepository(Position::class)->find($id);

        if (!$position) {
            throw new \InvalidArgumentException("Position with id $id does not exist!");
        }

        $this->em->remove($position);
        $this->em->flush();

        return true;
    }
    //endregion

    //region CRUD Agent
    /**
     * Crée un nouvel agent.
     * @param array $data
     * @return Agent
     */
    public function addAgent(array $data): Agent
    {
        if (empty($data['numagent']) || empty($data['nom']) || empty($data['prenom']) || empty($data['service_id'])) {
            throw new \InvalidArgumentException("Missing required data for new agent (numagent, nom, prenom, service_id).");
        }

        $service = $this->em->getRepository(Service::class)->find($data['service_id']);
        if (!$service) {
            throw new \InvalidArgumentException("Service with id {$data['service_id']} does not exist!");
        }

        if ($this->em->getRepository(Agent::class)->find($data['numagent'])) {
            throw new \InvalidArgumentException("Agent with numagent {$data['numagent']} already exists.");
        }

        $agent = new Agent();
        $agent->setNumagent($data['numagent']);
        $agent->setNom($data['nom']);
        $agent->setPrenom($data['prenom']);
        $agent->setService($service);
        $agent->setCivilite($data['civilite'] ?? 'M.');

        $this->em->persist($agent);
        $this->em->flush();

        return $agent;
    }

    /**
     * Supprime un agent.
     * @param string $numagent
     * @return bool
     */
    public function deleteAgent(string $numagent): bool
    {
        $agent = $this->em->getRepository(Agent::class)->find($numagent);

        if (!$agent) {
            // It's not an error to try to delete something that doesn't exist
            return true;
        }

        // Before deleting agent, we need to delete dependencies
        $agentPosition = $this->em->getRepository(AgentPosition::class)->find($numagent);
        if ($agentPosition) {
            $this->em->remove($agentPosition);
        }

        $agentConnexions = $this->em->getRepository(AgentConnexion::class)->findBy(['agent' => $agent]);
        foreach ($agentConnexions as $connexion) {
            $this->em->remove($connexion);
        }

        $this->em->remove($agent);
        $this->em->flush();

        return true;
    }

    /**
     * Met à jour un agent existant.
     * @param string $numagent
     * @param array $data
     * @return Agent
     */
    public function updateAgent(string $numagent, array $data): Agent
    {
        $agent = $this->em->getRepository(Agent::class)->find($numagent);

        if (!$agent) {
            throw new \InvalidArgumentException("Agent with numagent $numagent does not exist!");
        }

        if (!empty($data['service_id'])) {
            $service = $this->em->getRepository(Service::class)->find($data['service_id']);
            if (!$service) {
                throw new \InvalidArgumentException("Service with id {$data['service_id']} does not exist!");
            }
            $agent->setService($service);
        }

        if (!empty($data['nom'])) {
            $agent->setNom($data['nom']);
        }
        if (!empty($data['prenom'])) {
            $agent->setPrenom($data['prenom']);
        }
        if (!empty($data['civilite'])) {
            $agent->setCivilite($data['civilite']);
        }

        $this->em->flush();

        return $agent;
    }
    //endregion

    //region Test Data
    /**
     * Crée une position de test simple.
     * @return Position
     */
    public function createTestPosition(): Position
    {
        $etage = $this->em->getRepository(Etage::class)->findOneBy([]);
        if (!$etage) {
            throw new \RuntimeException("Aucun étage trouvé pour créer une position de test.");
        }

        $service = $this->em->getRepository(Service::class)->findOneBy(['etage' => $etage]);
        if (!$service) {
            throw new \RuntimeException("Aucun service trouvé pour créer une position de test.");
        }

        $switch = $this->em->getRepository(NetworkSwitch::class)->findOneBy(['etage' => $etage]);
        if (!$switch) {
            throw new \RuntimeException("Aucun switch trouvé pour créer une position de test.");
        }

        $position = new Position();
        $position->setEtage($etage);
        $position->setService($service);
        $position->setNetworkSwitch($switch);
        $position->setCoordx(rand(1,100));
        $position->setCoordy(rand(1,100));
        $position->setPrise('T'.rand(1,100));
        $position->setMac($this->generateRandomMac());
        $position->setType('Echange');
        $position->setSanctuaire(false);
        $position->setFlex(true);

        $this->em->persist($position);
        $this->em->flush();

        return $position;
    }
    //endregion

    /**
     * Trouve le service de la position la plus proche des coordonnées données.
     *
     * @param Etage $etage
     * @param int $x
     * @param int $y
     * @return Service|null
     */
    public function findNearestService(Etage $etage, int $x, int $y): ?\App\Entity\Service
    {
        $positions = $etage->getPositions();
        if ($positions->isEmpty()) {
            return null;
        }

        $closestPosition = null;
        $minDistanceSq = PHP_INT_MAX;

        foreach ($positions as $position) {
            // Ne considère que les positions qui ont un service
            if ($position->getService() === null) {
                continue;
            }

            $distSq = pow($position->getCoordx() - $x, 2) + pow($position->getCoordy() - $y, 2);
            if ($distSq < $minDistanceSq) {
                $minDistanceSq = $distSq;
                $closestPosition = $position;
            }
        }

        // Optionnel: ajouter un seuil de distance maximale
        // if ($minDistanceSq > (100*100)) { // ex: 100 pixels
        //     return null;
        // }

        return $closestPosition ? $closestPosition->getService() : null;
    }
}
