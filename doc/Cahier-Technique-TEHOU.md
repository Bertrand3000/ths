# Téhou - Cahier Technique de Développement

## 1. Vue d'ensemble du projet

### 1.1 Objectif
Application de géolocalisation automatique des agents dans les nouveaux locaux CPAM fonctionnant en mode Flex office.

### 1.2 Architecture générale
- **Client lourd Windows** : Agent de géolocalisation automatique + système d'alertes
- **Serveur centralisé** : API REST + Interface Web + Traitement données
- **Infrastructure réseau** : Utilisation des syslogs switches pour correspondance MAC/position

### 1.3 Stack technique
- **Client** : C++ (natif Windows)
- **Serveur** : PHP 8.0 / Symfony 5.4 / PostgreSQL
- **Frontend** : Symfony/Twig + Bootstrap + JavaScript/Ajax + Fabric.js
- **Déploiement** : Serveur LAMP classique

## 2. Architecture technique détaillée

### 2.1 Composants système

```
[Client Windows] → [API REST] → [Base PostgreSQL]
                      ↓
[Listener Syslog] → [Service Correspondance] → [Interface Web]
                      ↓
[Système Alertes] → [Interface Admin]
```

### 2.2 Flux de données principaux

1. **Géolocalisation** : Client → API position → Traitement → Base → Interface
2. **Correspondance** : Switches → Syslog → Parser → Table MAC/Switch
3. **Recherche** : Interface → API search → Base → Résultats
4. **Alertes** : Interface admin → Serveur → Push clients

## 3. Client lourd Windows

### 3.1 Fonctionnalités
- **Géolocalisation automatique** : Détection réseau RAMAGE + envoi trame position
- **Gestion alertes** : Émission (combinaison touches) + Réception (port TCP)
- **États système** : Détection veille/réveil Windows
- **Fonctionnement** : Service arrière-plan invisible

### 3.2 Spécifications techniques

#### 3.2.1 Détection réseau
```cpp
// Pseudo-code détection
bool isOnRamageNetwork() {
    string localIP = getCurrentIP();
    return isInRange(localIP, "55.0.0.0/8"); // Tout RAMAGE
}

bool isOnTeletravail() {
    string localIP = getCurrentIP();
    return isInRange(localIP, "55.255.0.0/16") || isInRange(localIP, "55.254.0.0/16") || isInRange(localIP, "55.185.0.0/16") || isInRange(localIP, "55.184.0.0/16");
}

bool isOnSiegeFlex() {
    string localIP = getCurrentIP();
    return isInRange(localIP, "55.153.4.1/22") /* CPAM Siège */ || isInRange(localIP, "55.153.223.1/24") /* ELSM */;
}
```

#### 3.2.2 Collecte informations
- **Username** : `%USERNAME%` ou GetUserName() API Windows
- **MAC Address** : Interface réseau active (priorité Ethernet)
- **IP Address** : Adresse IP locale
- **Timestamp** : UTC ISO 8601

#### 3.2.3 Format trame JSON
```json
{
    "username": "jdupont",
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "ip_address": "5.10.20.150",
    "timestamp": "2025-07-09T14:30:00Z",
    "status": "active|sleep|wake",
    "client_version": "1.0.0",
    "auth_token": "fixed_token_v1"
}
```

#### 3.2.4 Configuration client
```ini
[tehou_client]
server_url=https://tehou.cpam-somme.ramage
api_endpoint=/api/position
alert_port=9999
sync_interval=300
auth_token=CLIENT_TOKEN_FIXED_V1
```

#### 3.2.5 Gestion réception alertes
- **Port écoute** : TCP 9999 (configurable)
- **Format alerte reçue** :
```json
{
    "type": "urgent|info",
    "message": "Alerte sécurité - Évacuation immédiate",
    "timestamp": "2025-07-09T14:30:00Z",
    "display_duration": 30
}
```

#### 3.2.6 Envoi alerte depuis le client (touche Panic)
- **Combinaison émission** : Ctrl+Alt+F12 (exemple)
- **Format alerte envoyée au serveur** :
```json
(
    "username": "jdupont",
    "mac_address": "AA:BB:CC:DD:EE:FF",
    "ip_address": "5.10.20.150",
    "timestamp": "2025-07-09T14:30:00Z",
    "status": "panic",
    "client_version": "1.0.0",
    "auth_token": "fixed_token_v1"
}
```

### 3.3 Ressources système
- **CPU** : <2% en moyenne
- **RAM** : <50MB
- **Réseau** : <1KB/5min en fonctionnement normal

## 4. Serveur - Architecture

### 4.1 Composants Symfony

#### 4.1.1 Structure des contrôleurs
```
src/Controlleur/
├── ApiControlleur.php            # Appel par API (positions, alerte panique, etc)
│── DebugControlleur.php          # API de debug pour tests unitaires
│── AdminControlleur.php          # Web: partie administration
│── DashboardControlleur.php      # Web: partie statique (pour tablettes ou écrans de présentation)
│── SearchControlleur.php         # Web: partie dynamique (pour recherche active et consultation depuis ordinateur)
```

#### 4.1.2 Services métier
```
src/Service/
├── ArchitectureService.php             # Gestion de l'architecture fixe (CRUD complet)
├── StatsService.php                    # Calculs statistiques
├── PositionService.php                 # Lecture et actualisation des positions
└── SyslogService.php                   # Parseur syslog
```

### 4.2 API REST Endpoints

#### 4.2.1 Position (Client → Serveur)
```
POST /api/position                      # Envoyer à la connexion et toutes les X minutes
Content-Type: application/json
Authorization: Bearer CLIENT_TOKEN

Body: {trame JSON client}
Response: {"status": "ok", "position_id": 123}

POST /api/logoff                        # Envoyer à la déconnexion
POST /api/sleep                         # Envoyer à la mise en veille
```

#### 4.2.2 Alertes silencieuses
```
POST /api/send-alert                    # Émission alerte silencieuse
```

#### 4.2.3 Inventaire
```
GET /api/inventaire/get                 # Récupération de l'équipement à une position
POST /api/inventaire/set                # Modification de l'équipement à une position
```

#### 4.2.4 Debug/Test
```
POST /api/debug/simulate-position       # Simulation d'une actualisation (connexion ou action routinie)
POST /api/debug/simulate-logout         # Simulation d'une déconnexion
POST /api/debug/simulate-sleep          # Simulation d'une mise en veille
POST /api/debug/simulate-timeout        # Simulation d'une expiration du timeout (rend la dernière simulation de position trop ancienne)
GET /api/debug/get-state                # Retourne l'état général de l'application avec les places disponibles
```

## 5. Base de données SQLite / PostgreSQL

```sql
CREATE TABLE site (
    id INTEGER PRIMARY KEY,                                             -- Autoincrémenté
    nom VARCHAR(100) NOT NULL,
    flex BOOLEAN NOT NULL DEFAULT TRUE                                  -- Indique si les règles flex s'appliquent sur ce site
);

CREATE TABLE etage (
    id INTEGER PRIMARY KEY,                                             -- Autoincrémenté
    idsite INTEGER NOT NULL,
    nom VARCHAR(100) NOT NULL,
    arriereplan VARCHAR(200) NOT NULL,                                  -- Nom du fichier image représentant l'étage
    largeur INTEGER NOT NULL,                                           -- Largeur de l'image
    hauteur INTEGER NOT NULL,                                           -- Hauteur de l'image
    FOREIGN KEY (idsite) REFERENCES site(id) ON DELETE CASCADE
);
CREATE INDEX idx_etage_idsite ON etage(idsite);

CREATE TABLE service (
    id INTEGER PRIMARY KEY,                                             -- Autoincrémenté
    idetage INTEGER NOT NULL,
    nom VARCHAR(100) NOT NULL,
    FOREIGN KEY (idetage) REFERENCES etage(id) ON DELETE CASCADE
);
CREATE INDEX idx_service_idetage ON service(idetage);

CREATE TABLE position (
    id INTEGER PRIMARY KEY,                                             -- Autoincrémenté
    idetage INTEGER NOT NULL,
    idservice INTEGER NOT NULL,
    idswitch INTEGER NOT NULL,
    coordx INTEGER NOT NULL,
    coordy INTEGER NOT NULL,
    prise VARCHAR(10) NOT NULL,                                         -- Numéro de prise sur le switch
    mac VARCHAR(17) NULL,                                               -- Format MAC standard XX:XX:XX:XX:XX:XX
    type VARCHAR(13) NOT NULL,                                          -- Echange, Concentration, Bulle, Réunion, Formation
    sanctuaire BOOLEAN NOT NULL DEFAULT FALSE,                          -- Si TRUE, le poste ne peut être utilisé que par le service où il a été rattaché
    flex BOOLEAN NOT NULL DEFAULT TRUE,                                 -- Indique si le poste est concerné par Téhou ou non
    FOREIGN KEY (idetage) REFERENCES etage(id) ON DELETE CASCADE,
    FOREIGN KEY (idservice) REFERENCES service(id) ON DELETE CASCADE,
    FOREIGN KEY (idswitch) REFERENCES switch(id) ON DELETE CASCADE
);
CREATE INDEX idx_position_idetage ON position(idetage);
CREATE INDEX idx_position_idservice ON position(idservice);
CREATE INDEX idx_position_idswitch ON position(idswitch);

CREATE TABLE materiel (
    id INTEGER PRIMARY KEY,                                             -- Autoincrémenté
    idposition INTEGER NOT NULL,
    type VARCHAR(10),                                                   -- "dock", "moniteur", "clavier", "souris", "webcam", ...
    special BOOLEAN NOT NULL DEFAULT FALSE,                             -- Indique s'il s'agit d'un matériel "spécial" (plus puissant qu'un matériel standard flex)
    codebarre VARCHAR(20) NOT NULL UNIQUE,                              -- Scanné avec CLAFOUTIS
    FOREIGN KEY (idposition) REFERENCES position(id) ON DELETE CASCADE
);
CREATE INDEX idx_materiel_idposition ON materiel(idposition);

CREATE TABLE agent (
    numagent VARCHAR(5) PRIMARY KEY,
    idservice INTEGER NOT NULL,
    civilite VARCHAR(4) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    FOREIGN KEY (idservice) REFERENCES service(id) ON DELETE RESTRICT
);
CREATE INDEX idx_agent_idservice ON agent(idservice);

CREATE TABLE network_switch (
    id INTEGER PRIMARY KEY,                                             -- Autoincrémenté
    idetage INTEGER NOT NULL,
    nom VARCHAR(100) NOT NULL,
    coordx INTEGER NOT NULL,
    coordy INTEGER NOT NULL,
    nbprises INTEGER NOT NULL,
    FOREIGN KEY (idetage) REFERENCES etage(id) ON DELETE CASCADE
);
CREATE INDEX idx_switch_idetage ON switch(idetage);

CREATE TABLE agent_connexion (
    id INTEGER PRIMARY KEY,
    numagent VARCHAR(5) NOT NULL,
    type VARCHAR(15) NOT NULL,
    ip VARCHAR(15) NOT NULL,
    mac VARCHAR(17) NOT NULL,
    dateconnexion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dateactualisation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (numagent) REFERENCES agent(numagent) ON DELETE CASCADE
);
CREATE INDEX idx_agent_connexion_numagent ON agent_connexion(numagent);
CREATE INDEX idx_agent_connexion_type ON agent_connexion(type);

CREATE TABLE agent_position (
    numagent VARCHAR(5) PRIMARY KEY,
    idposition INTEGER NOT NULL,
    jour DATE DEFAULT CURRENT_DATE,
    dateconnexion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dateexpiration TIMESTAMP NULL,
    FOREIGN KEY (numagent) REFERENCES agent(numagent) ON DELETE CASCADE,
    FOREIGN KEY (idposition) REFERENCES position(id) ON DELETE CASCADE
);
CREATE INDEX idx_agent_position_idposition ON agent_position(idposition);
CREATE INDEX idx_agent_position_jour ON agent_position(jour);

CREATE TABLE agent_historique_connexion (
    id INTEGER NOT NULL PRIMARY KEY,
    numagent VARCHAR(5) NOT NULL,
    idposition INTEGER NOT NULL,
    jour DATE DEFAULT CURRENT_DATE,
    dateconnexion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    datedeconnexion TIMESTAMP NULL,
    FOREIGN KEY (numagent) REFERENCES agent(numagent) ON DELETE CASCADE,
    FOREIGN KEY (idposition) REFERENCES position(id) ON DELETE CASCADE
);
CREATE INDEX idx_agent_historique_numagent ON agent_historique_connexion(numagent);
CREATE INDEX idx_agent_historique_idposition ON agent_historique_connexion(idposition);
CREATE INDEX idx_agent_historique_jour ON agent_historique_connexion(jour);

CREATE TABLE systemevents (                                             -- Rempli par le syslog
    id INTEGER PRIMARY KEY,
    customerid INTEGER,
    receivedat TIMESTAMP,
    devicereportedtime TIMESTAMP,
    facility INTEGER,
    priority INTEGER,
    fromhost VARCHAR(60),
    message TEXT,                                                       -- A analyser pour détecter les connexions et déconnexions
    ntseverity INTEGER,
    importance INTEGER,
    eventsource VARCHAR(60),
    eventuser VARCHAR(60),
    eventcategory INTEGER,
    eventid INTEGER,
    eventbinarydata TEXT,
    maxavailable INTEGER,
    currusage INTEGER,
    minusage INTEGER,
    maxusage INTEGER,
    infounitid INTEGER,
    syslogtag VARCHAR(60),                                              -- A comparer avec le nom du switch
    eventlogtype VARCHAR(60),
    genericfilename VARCHAR(60),
    systemid INTEGER
);
CREATE INDEX idx_systemevents_receivedat ON systemevents(receivedat);
CREATE INDEX idx_systemevents_syslogtag ON systemevents(syslogtag);

CREATE TABLE systemeventsproperties (                                   -- Utilisé (ou pas) par le syslog
    id INTEGER PRIMARY KEY,                                             -- Auto-incrémenté compatible
    systemeventid INTEGER,
    paramname VARCHAR(255),
    paramvalue TEXT,
    FOREIGN KEY (systemeventid) REFERENCES systemevents(id) ON DELETE CASCADE
);

CREATE TABLE config (
    cle VARCHAR(50) PRIMARY KEY,                                        -- "dernier_syslog_id" ou "dernier_nettoyage_syslog"
    valeur VARCHAR(255) NOT NULL,
    date_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## 6. Service de correspondance MAC/Position

### 6.1 Trame Syslog attendue

#### 6.1.1 Trame de connexion

Exemple :
%%10LLDP/6/LLDP_CREATE_NEIGHBOR: Nearest bridge agent neighbor created on port GigabitEthernet1/0/28 (IfIndex 28), neighbor's chassis ID is P1180014125VS0, port ID is 2c58-b9f5-fa9e.

Important à récupérer : nom du port "Ethernet1/0/28"
Adresse Mac: "2c58-b9f5-fa9e"

#### 6.1.2 Trame de déconnexion

Exemple :
 %%10IFNET/3/PHY_UPDOWN: Physical state on the interface GigabitEthernet1/0/21 changed to down.

Important à récupérer : nom du port "Ethernet1/0/21"

### 6.2 Algorithme de correspondance

#### 6.2.1 Service principal
```php
class CorrespondanceService
{
    public function updateAgentPosition(array $clientData): ?int
    {
        // 1. Récupérer agent
        $agent = $this->findAgent($clientData['username']);

        // 2. Trouver correspondance MAC → Location
        $location = $this->findLocationByMac($clientData['mac_address']);

        if (!$location) {
            $this->logUnknownMac($clientData['mac_address']);
            return null;
        }

        // 3. Mettre à jour position
        return $this->updateCurrentPosition($agent, $location, $clientData);
    }

    private function findLocationByMac(string $macAddress): ?Location
    {
        // Jointure mac_locations + locations
        $macLocation = $this->macLocationRepository->findOneBy(['macAddress' => $macAddress]);

        if (!$macLocation) {
            return null;
        }

        return $this->locationRepository->findOneBy([
            'switchId' => $macLocation->getSwitchId(),
            'switchPort' => $macLocation->getSwitchPort()
        ]);
    }
}
```

#### 6.2.2 Gestion réservation journalière
```php
private function updateCurrentPosition(Agent $agent, Location $location, array $data): int
{
    $existingPosition = $this->positionRepository->findOneBy(['agent' => $agent]);

    if ($existingPosition) {
        // Même position → mise à jour timestamp
        if ($existingPosition->getLocation()->getId() === $location->getId()) {
            $existingPosition->setLastSeenAt(new DateTime());
            $existingPosition->setExpiresAt(new DateTime('+8 hours'));
        } else {
            // Nouvelle position → libérer ancienne, créer nouvelle
            $this->archivePosition($existingPosition);
            $existingPosition->setLocation($location);
            $existingPosition->setFirstSeenAt(new DateTime());
        }
    } else {
        // Première connexion de la journée
        $existingPosition = new AgentPosition();
        $existingPosition->setAgent($agent);
        $existingPosition->setLocation($location);
    }

    $existingPosition->setMacAddress($data['mac_address']);
    $existingPosition->setIpAddress($data['ip_address']);
    $existingPosition->setStatus($data['status']);
    $existingPosition->setLastSeenAt(new DateTime());
    $existingPosition->setExpiresAt(new DateTime('+8 hours'));

    $this->entityManager->persist($existingPosition);
    $this->entityManager->flush();

    return $existingPosition->getId();
}
```

## 7. Interfaces utilisateur

### 7.1 Niveaux d'accès

#### 7.1.1 Droits par profil
- **Basique** : Aucun accès web (client lourd uniquement)
- **Classique** : Recherche place libre + membres équipe
- **Admin** : Tout + gestion alertes info
- **Direction** : Tout + statistiques + alertes urgentes

#### 7.1.2 Contrôle d'accès Symfony
```php
// config/packages/security.yaml
security:
    providers:
        ldap_provider:
            ldap:
                service: Symfony\Component\Ldap\Ldap
                base_dn: 'ou=users,dc=cpam,dc=local'
                search_dn: 'cn=tehou,ou=services,dc=cpam,dc=local'
                search_password: '%env(LDAP_PASSWORD)%'
                default_roles: ROLE_USER_BASIC
                uid_key: sAMAccountName

    role_hierarchy:
        ROLE_USER_CLASSIC: ROLE_USER_BASIC
        ROLE_ADMIN: ROLE_USER_CLASSIC
        ROLE_DIRECTION: ROLE_ADMIN
```

### 7.2 Controllers principaux

#### 7.2.1 Recherche agents
```php
class SearchController extends AbstractController
{
    #[Route('/search/agent', name: 'search_agent')]
    #[IsGranted('ROLE_USER_CLASSIC')]
    public function searchAgent(Request $request): Response
    {
        $form = $this->createForm(AgentSearchType::class);
        $form->handleRequest($request);

        $result = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $username = $form->get('username')->getData();
            $result = $this->positionService->findAgentPosition($username);

            // Log recherche pour audit
            $this->auditService->logSearch($this->getUser(), 'agent', $username);
        }

        return $this->render('search/agent.html.twig', [
            'form' => $form,
            'result' => $result
        ]);
    }
}
```

#### 7.2.2 Recherche places libres
```php
#[Route('/search/free-desks', name: 'search_free_desks')]
#[IsGranted('ROLE_USER_CLASSIC')]
public function searchFreeDesks(Request $request): Response
{
    $user = $this->getUser();
    $userTeam = $user->getTeam();

    // Places libres dans l'équipe en priorité
    $teamDesks = $this->locationService->findFreeDesks($userTeam->getZone());

    // Places libres autres zones
    $otherDesks = $this->locationService->findFreeDesks(null, $userTeam->getZone());

    return $this->render('search/free_desks.html.twig', [
        'team_desks' => $teamDesks,
        'other_desks' => $otherDesks,
        'user_team' => $userTeam
    ]);
}
```

### 7.3 Templates Twig

#### 7.3.1 Layout principal
```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{% block title %}Téhou - Géolocalisation CPAM{% endblock %}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta http-equiv="refresh" content="60"> {# Auto-refresh 1min #}
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ path('dashboard') }}">Téhou</a>
            <div class="navbar-nav">
                {% if is_granted('ROLE_USER_CLASSIC') %}
                    <a class="nav-link" href="{{ path('search_agent') }}">Recherche agent</a>
                    <a class="nav-link" href="{{ path('search_free_desks') }}">Places libres</a>
                {% endif %}
                {% if is_granted('ROLE_ADMIN') %}
                    <a class="nav-link" href="{{ path('admin_dashboard') }}">Administration</a>
                {% endif %}
            </div>
        </div>
    </nav>

    <main class="container mt-4">
        {% block body %}{% endblock %}
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    {% block javascripts %}{% endblock %}
</body>
</html>
```

#### 7.3.2 Visualisation occupation
```twig
{# templates/dashboard/occupancy.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Occupation temps réel{% endblock %}

{% block body %}
<div class="row">
    {% for floor in floors %}
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5>Étage {{ floor.number }}</h5>
            </div>
            <div class="card-body">
                {% for zone in floor.zones %}
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><strong>{{ zone.name }}</strong></span>
                        <span class="badge bg-{% if zone.occupancy_rate > 80 %}danger{% elseif zone.occupancy_rate > 50 %}warning{% else %}success{% endif %}">
                            {{ zone.occupied_desks }}/{{ zone.total_desks }} ({{ zone.occupancy_rate }}%)
                        </span>
                    </div>
                    <div class="progress mt-1">
                        <div class="progress-bar bg-{% if zone.occupancy_rate > 80 %}danger{% elseif zone.occupancy_rate > 50 %}warning{% else %}success{% endif %}"
                             style="width: {{ zone.occupancy_rate }}%"></div>
                    </div>
                </div>
                {% endfor %}
            </div>
        </div>
    </div>
    {% endfor %}
</div>

<script>
// Auto-refresh via Ajax toutes les 30s
setInterval(function() {
    fetch('{{ path('api_occupancy_data') }}')
        .then(response => response.json())
        .then(data => updateOccupancyDisplay(data));
}, 30000);
</script>
{% endblock %}
```

## 8. Système d'alertes

### 8.1 Types d'alertes
- **Silencieuse** : Agent → Manager/Sécurité (urgence discrète)
- **Urgente** : Direction → Tous agents (évacuation, sécurité)
- **Info** : Admin → Agents ciblés (maintenance, information)

### 8.2 Mécanisme push

#### 8.2.1 Serveur d'alertes
```php
class AlertService
{
    public function broadcastAlert(Alert $alert, array $targetAgents): void
    {
        $message = [
            'type' => $alert->getType(),
            'message' => $alert->getMessage(),
            'timestamp' => $alert->getCreatedAt()->format('c'),
            'display_duration' => $alert->getDisplayDuration()
        ];

        foreach ($targetAgents as $agent) {
            $this->pushToClient($agent, $message);
        }
    }

    private function pushToClient(Agent $agent, array $message): void
    {
        $position = $this->positionService->getCurrentPosition($agent);
        if (!$position) return;

        $clientEndpoint = "tcp://{$position->getIpAddress()}:9999";

        try {
            $socket = stream_socket_client($clientEndpoint, $errno, $errstr, 5);
            if ($socket) {
                fwrite($socket, json_encode($message) . "\n");
                fclose($socket);
            }
        } catch (Exception $e) {
            $this->logger->warning("Failed to send alert to {$agent->getUsername()}: " . $e->getMessage());
        }
    }
}
```

#### 8.2.2 Interface émission alerte
```php
#[Route('/admin/alerts/broadcast', name: 'admin_alert_broadcast')]
#[IsGranted('ROLE_ADMIN')]
public function broadcastAlert(Request $request): Response
{
    $form = $this->createForm(AlertBroadcastType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $alert = new Alert();
        $alert->setType($form->get('type')->getData());
        $alert->setMessage($form->get('message')->getData());
        $alert->setDisplayDuration($form->get('duration')->getData());

        // Ciblage agents
        $targets = $this->getTargetAgents($form->get('target_type')->getData());

        $this->alertService->broadcastAlert($alert, $targets);

        $this->addFlash('success', 'Alerte diffusée à ' . count($targets) . ' agents');
    }

    return $this->render('admin/alert_broadcast.html.twig', ['form' => $form]);
}
```

## 9. Interface d'administration

### 9.1 Gestion des emplacements

#### 9.1.1 Upload et gestion des plans
```php
#[Route('/admin/plans/upload', name: 'admin_plan_upload')]
#[IsGranted('ROLE_ADMIN')]
public function uploadPlan(Request $request): Response
{
    $form = $this->createForm(PlanUploadType::class);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $file = $form->get('plan_file')->getData();
        $floor = $form->get('floor')->getData();
        $site = $form->get('site')->getData();

        // Validation format
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new BadRequestException('Format non supporté');
        }

        // Sauvegarde fichier
        $filename = sprintf('plan_site%d_floor%d.%s',
            $site->getId(),
            $floor,
            $file->guessExtension()
        );

        $file->move($this->getParameter('plans_directory'), $filename);

        // Mise à jour base
        $plan = new FloorPlan();
        $plan->setSite($site);
        $plan->setFloor($floor);
        $plan->setFilename($filename);

        $this->entityManager->persist($plan);
        $this->entityManager->flush();
    }

    return $this->render('admin/plan_upload.html.twig', ['form' => $form]);
}
```

#### 9.1.2 Mapping visuel positions
```javascript
// templates/admin/location_mapping.html.twig
class LocationMapper {
    constructor(planImageUrl, existingLocations) {
        this.canvas = document.getElementById('mapping-canvas');
        this.ctx = this.canvas.getContext('2d');
        this.locations = existingLocations;
        this.selectedSwitch = null;
        this.selectedPort = null;

        this.loadPlanImage(planImageUrl);
        this.setupEventListeners();
    }

    setupEventListeners() {
        this.canvas.addEventListener('click', (e) => {
            if (!this.selectedSwitch || !this.selectedPort) {
                alert('Sélectionnez d\'abord un switch et un port');
                return;
            }

            const rect = this.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            this.createLocation(x, y);
        });
    }

    createLocation(x, y) {
        const locationData = {
            switch_id: this.selectedSwitch,
            switch_port: this.selectedPort,
            coordinates_x: Math.round(x),
            coordinates_y: Math.round(y),
            position_type: document.getElementById('position-type').value,
            zone: document.getElementById('zone').value
        };

        fetch('/api/admin/locations', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(locationData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.addLocationMarker(x, y, data.location);
                this.resetSelection();
            }
        });
    }
}
```

### 9.2 Monitoring et debug

#### 9.2.1 Dashboard erreurs
```php
#[Route('/admin/debug', name: 'admin_debug')]
#[IsGranted('ROLE_ADMIN')]
public function debugDashboard(): Response
{
    $unknownMacs = $this->positionService->getUnknownMacs();
    $unmappedSwitches = $this->locationService->getUnmappedSwitches();
    $expiredPositions = $this->positionService->getExpiredPositions();

    return $this->render('admin/debug.html.twig', [
        'unknown_macs' => $unknownMacs,
        'unmapped_switches' => $unmappedSwitches,
        'expired_positions' => $expiredPositions
    ]);
}
```

#### 9.2.2 Logs et audit
```php
class AuditService
{
    public function logSearch(User $user, string $type, string $target): void
    {
        $log = new AuditLog();
        $log->setUser($user);
        $log->setAction('search');
        $log->setEntityType($type);
        $log->setEntityId($target);
        $log->setIpAddress($this->requestStack->getCurrentRequest()->getClientIp());

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
```

## 10. Statistiques et reporting

### 10.1 Service statistiques

#### 10.1.1 Calculs métier
```php
class StatisticsService
{
    public function getOccupancyStats(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $qb = $this->positionHistoryRepository->createQueryBuilder('ph')
            ->select([
                'DATE(ph.startedAt) as date',
                'l.zone',
                'COUNT(DISTINCT ph.agentId) as unique_agents',
                'AVG(ph.durationMinutes) as avg_duration',
                'MAX(hourly.peak_count) as peak_occupancy'
            ])
            ->join('ph.location', 'l')
            ->leftJoin(/* sous-requête comptage par heure */, 'hourly', 'ON ...')
            ->where('ph.startedAt BETWEEN :from AND :to')
            ->groupBy('DATE(ph.startedAt)', 'l.zone')
            ->setParameters(['from' => $from, 'to' => $to]);

        return $qb->getQuery()->getResult();
    }

    public function getFlexEfficiencyReport(): array
    {
        // Ratio agents présents / agents équipe par service
        // Identification services sous/sur-occupés
        // Recommandations réorganisation
    }
}
```

### 10.2 Interface statistiques

#### 10.2.1 Dashboard direction
```twig
{# templates/statistics/dashboard.html.twig #}
{% extends 'base.html.twig' %}

{% block body %}
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Agents présents</h5>
                <h2 class="text-primary">{{ stats.current_agents }}</h2>
                <small class="text-muted">sur {{ stats.total_agents }} agents</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5 class="card-title">Taux occupation</h5>
                <h2 class="text-success">{{ stats.occupancy_rate }}%</h2>
                <small class="text-muted">postes flex occupés</small>
            </div>
        </div>
    </div>
    <!-- Autres KPIs -->
</div>

<div class="row">
    <div class="col-md-8">
        <canvas id="occupancy-chart"></canvas>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Top services présents</div>
            <div class="card-body">
                {% for service in stats.top_services %}
                <div class="d-flex justify-content-between">
                    <span>{{ service.name }}</span>
                    <span class="badge bg-primary">{{ service.count }}</span>
                </div>
                {% endfor %}
            </div>
        </div>
    </div>
</div>
{% endblock %}
```

## 11. Configuration et déploiement

### 11.1 Variables d'environnement

#### 11.1.1 Fichier .env
```bash
# Base
DATABASE_URL="postgresql://tehou_user:password@localhost:5432/tehou_db"
APP_SECRET="your-secret-key"

# LDAP
LDAP_HOST="ldap.cpam.local"
LDAP_PASSWORD="service_password"

# Syslog
SYSLOG_LISTEN_PORT=514
SYSLOG_BIND_IP="0.0.0.0"

# Alertes
ALERT_CLIENT_PORT=9999
ALERT_TIMEOUT_SECONDS=30

# Sécurité
CLIENT_AUTH_TOKEN="CLIENT_TOKEN_FIXED_V1_CHANGE_IN_PROD"

# Fichiers
PLANS_DIRECTORY="/var/www/tehou/public/uploads/plans"
LOGS_DIRECTORY="/var/www/tehou/var/log"
```

### 11.2 Structure déploiement

#### 11.2.1 Arborescence serveur
```
/var/www/tehou/
├── bin/
├── config/
├── public/
│   ├── index.php
│   └── uploads/
│       └── plans/
├── src/
├── templates/
├── var/
│   ├── cache/
│   ├── log/
│   └── sessions/
├── vendor/
├── .env
└── composer.json
```

#### 11.2.2 Configuration Apache/Nginx
```apache
# /etc/apache2/sites-available/tehou.conf
<VirtualHost *:80>
    ServerName tehou.cpam.local
    DocumentRoot /var/www/tehou/public

    <Directory /var/www/tehou/public>
        AllowOverride All
        Require all granted

        # Sécurité uploads
        <Files "*.php">
            Require all denied
        </Files>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/tehou_error.log
    CustomLog ${APACHE_LOG_DIR}/tehou_access.log combined
</VirtualHost>
```

### 11.3 Tâches de maintenance

#### 11.3.1 Crons de nettoyage
```bash
# /etc/crontab
# Nettoyage positions expirées (toutes les heures)
0 * * * * www-data php /var/www/tehou/bin/console app:cleanup:positions

# Archivage historique (quotidien à 02h)
0 2 * * * www-data php /var/www/tehou/bin/console app:archive:positions

# Purge anciens logs (hebdomadaire)
0 3 * * 0 www-data find /var/www/tehou/var/log -name "*.log" -mtime +30 -delete

# Nettoyage MAC locations expirées (quotidien)
30 2 * * * www-data php /var/www/tehou/bin/console app:cleanup:mac-locations
```

#### 11.3.2 Commandes Symfony
```php
// src/Command/CleanupPositionsCommand.php
#[AsCommand('app:cleanup:positions')]
class CleanupPositionsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $expiredCount = $this->positionService->cleanupExpiredPositions();
        $output->writeln("Nettoyé {$expiredCount} positions expirées");

        return Command::SUCCESS;
    }
}
```

### 11.4 Monitoring système

#### 11.4.1 Healthcheck endpoint
```php
#[Route('/api/health', name: 'api_health')]
public function healthCheck(): JsonResponse
{
    $checks = [
        'database' => $this->checkDatabase(),
        'syslog_service' => $this->checkSyslogService(),
        'disk_space' => $this->checkDiskSpace(),
        'recent_positions' => $this->checkRecentPositions()
    ];

    $overall = array_reduce($checks, fn($carry, $check) => $carry && $check['status'], true);

    return new JsonResponse([
        'status' => $overall ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => date('c')
    ]);
}
```

## 12. Sécurité

### 12.1 Authentification et autorisation

#### 12.1.2 Validation tokens clients
```php
class ApiAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): bool
    {
        return $request->headers->has('Authorization') &&
               str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization'), 7);
        $expectedToken = $this->parameterBag->get('client_auth_token');

        if (!hash_equals($expectedToken, $token)) {
            throw new AuthenticationException('Token invalide');
        }

        return new SelfValidatingPassport(new UserBadge('client_api'));
    }
}
```

### 12.2 Protection données

#### 12.2.1 Chiffrement données sensibles
```php
class EncryptionService
{
    private string $key;

    public function __construct(string $encryptionKey)
    {
        $this->key = $encryptionKey;
    }

    public function encryptMacAddress(string $mac): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($mac, 'AES-256-CBC', $this->key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decryptMacAddress(string $encryptedMac): string
    {
        $data = base64_decode($encryptedMac);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, 0, $iv);
    }
}
```

### 12.3 Validation et sanitization

#### 12.3.1 Validators custom
```php
class MacAddressValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}

class IpRangeValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        $allowedRanges = ['5.0.0.0/8']; // Configuration depuis settings

        $isAllowed = false;
        foreach ($allowedRanges as $range) {
            if ($this->ipInRange($value, $range)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            $this->context->buildViolation('IP non autorisée')->addViolation();
        }
    }
}
```

## 13. Tests et validation

### 13.1 Tests unitaires

#### 13.1.1 Tests services métier
```php
class PositionServiceTest extends TestCase
{
    public function testUpdateAgentPosition(): void
    {
        $clientData = [
            'username' => 'jdupont',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '5.10.20.150',
            'status' => 'active'
        ];

        $positionId = $this->positionService->updateAgentPosition($clientData);

        $this->assertNotNull($positionId);

        $position = $this->positionRepository->find($positionId);
        $this->assertEquals('jdupont', $position->getAgent()->getUsername());
        $this->assertEquals('active', $position->getStatus());
    }

    public function testExpiredPositionCleanup(): void
    {
        // Créer position expirée
        $expiredPosition = $this->createExpiredPosition();

        $cleanedCount = $this->positionService->cleanupExpiredPositions();

        $this->assertEquals(1, $cleanedCount);
        $this->assertNull($this->positionRepository->find($expiredPosition->getId()));
    }
}
```

### 13.2 Tests d'intégration

#### 13.2.1 Tests API
```php
class ApiPositionTest extends WebTestCase
{
    public function testSubmitPosition(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/position', [], [], [
            'HTTP_Authorization' => 'Bearer CLIENT_TOKEN_FIXED_V1',
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'username' => 'testuser',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
            'ip_address' => '5.10.20.100',
            'status' => 'active'
        ]));

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('ok', $response['status']);
    }
}
```

## 14. Performance et optimisation

### 14.1 Optimisations base de données

#### 14.1.1 Index critiques
```sql
-- Performance requêtes fréquentes
CREATE INDEX CONCURRENTLY idx_agent_positions_expires_active
ON agent_positions(expires_at) WHERE expires_at > NOW();

CREATE INDEX CONCURRENTLY idx_position_history_stats
ON position_history(date_only, location_id)
INCLUDE (agent_id, duration_minutes);

-- Performance recherches
CREATE INDEX CONCURRENTLY idx_agents_username_lower
ON agents(LOWER(username));

CREATE INDEX CONCURRENTLY idx_locations_zone_type
ON locations(zone, position_type) WHERE is_reservable = true;
```

#### 14.1.2 Partitioning historique
```sql
-- Partition par mois pour position_history
CREATE TABLE position_history_y2025m01 PARTITION OF position_history
FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');

CREATE TABLE position_history_y2025m02 PARTITION OF position_history
FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');

-- Automatisation création partitions
CREATE OR REPLACE FUNCTION create_monthly_partition(table_name text, start_date date)
RETURNS void AS $$
DECLARE
    end_date date := start_date + INTERVAL '1 month';
    partition_name text := table_name || '_y' || EXTRACT(year FROM start_date) || 'm' || LPAD(EXTRACT(month FROM start_date)::text, 2, '0');
BEGIN
    EXECUTE format('CREATE TABLE %I PARTITION OF %I FOR VALUES FROM (%L) TO (%L)',
                   partition_name, table_name, start_date, end_date);
END;
$$ LANGUAGE plpgsql;
```

### 14.2 Cache application

#### 14.2.1 Cache Symfony
```php
// src/Service/CachedLocationService.php
class CachedLocationService
{
    public function __construct(
        private LocationService $locationService,
        private CacheItemPoolInterface $cache
    ) {}

    public function findFreeDesks(string $zone = null): array
    {
        $cacheKey = 'free_desks_' . ($zone ?? 'all');
        $item = $this->cache->getItem($cacheKey);

        if (!$item->isHit()) {
            $freeDesks = $this->locationService->findFreeDesks($zone);
            $item->set($freeDesks);
            $item->expiresAfter(300); // 5 minutes
            $this->cache->save($item);
        }

        return $item->get();
    }
}
```

## 15. Spécifications POC

### 15.1 Périmètre technique limité

#### 15.1.1 Fonctionnalités MVP
- ✅ Client lourd basique (détection + envoi trame)
- ✅ API position + correspondance MAC/Switch
- ✅ Interface recherche agent simple
- ✅ Interface visualisation occupation (tableau)
- ✅ Interface admin minimale (mapping positions)
- ❌ Système alertes (reporté)
- ❌ Statistiques avancées (reporté)
- ❌ Cartographie visuelle (reporté)

#### 15.1.2 Métriques POC
- **Fiabilité** : >90% détection correcte sur 10 postes
- **Performance** : Position mise à jour <5min, impact client <2% CPU
- **Robustesse** : 0 crash sur 1 mois, récupération auto après panne réseau
- **Utilisabilité** : Recherche agent <30s, satisfaction >7/10

### 15.2 Planning développement

#### 15.2.1 Phases (sur base 60 JH estimés)
- **Phase 1** (15 JH) : Client lourd + API basique
- **Phase 2** (15 JH) : Correspondance MAC/Switch + base données
- **Phase 3** (15 JH) : Interfaces web recherche + occupation
- **Phase 4** (10 JH) : Interface admin + déploiement POC
- **Phase 5** (5 JH) : Tests + ajustements

#### 15.2.2 Jalons validation
- **J+15** : Client fonctionnel + API
- **J+30** : Localisation précise opérationnelle
- **J+45** : Interfaces utilisateur complètes
- **J+60** : POC déployé service informatique

---

**Version** : 1.0
**Date** : Juillet 2025
**Contact** : Équipe développement Téhou
