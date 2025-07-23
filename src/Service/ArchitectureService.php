<?php

namespace App\Service;

use App\Entity\Agent;
use App\Entity\Etage;
use App\Entity\Materiel;
use App\Entity\NetworkSwitch;
use App\Entity\Position;
use App\Entity\Service;
use App\Entity\Site;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ArchitectureService
{
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
        #[Autowire('%kernel.project_dir%/src/Data/noms.txt')] private readonly string $nomsFile,
        #[Autowire('%kernel.project_dir%/src/Data/prenoms.txt')] private readonly string $prenomsFile
    ) {
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
}
