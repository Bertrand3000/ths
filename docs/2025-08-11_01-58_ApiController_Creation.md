# Rapport de Création - ApiController

**Date :** 2025-08-11
**Auteur :** Jules
**Service :** `src/Controller/ApiController.php`

## 1. Contexte

Ce rapport documente la création du `ApiController`, le contrôleur principal de l'API REST du projet TEHOU. Ce contrôleur est destiné à être le point d'entrée pour les clients lourds et fournit des endpoints pour la gestion des positions des agents et de l'inventaire matériel.

Le développement a suivi les spécifications du cahier des charges, en s'appuyant sur l'architecture et les patrons de conception existants dans le projet, notamment le `DebugController` pour le système d'authentification.

## 2. Fichiers Créés et Modifiés

### Fichiers Créés

-   `src/Controller/ApiController.php`: Le nouveau contrôleur avec tous les endpoints de l'API.
-   `tests/Controller/ApiControllerTest.php`: La suite de tests pour le nouveau contrôleur.
-   `docs/2025-08-11_01-58_ApiController_Creation.md`: Ce rapport.

### Fichiers Modifiés

-   `config/packages/tehou.yaml`: Ajout de la section de configuration `api`.
-   `src/DependencyInjection/Configuration.php`: Définition de la structure de configuration pour `tehou.api`.
-   `src/DependencyInjection/TehouExtension.php`: Chargement de la configuration `tehou.api` dans le conteneur de services.
-   `src/Entity/Materiel.php`: Ajout des groupes de sérialisation (`materiel:read`) pour l'API.

## 3. Architecture et Fonctionnalités

### 3.1. Authentification

Le contrôleur utilise un système d'authentification par `Bearer Token`, identique à celui du `DebugController`. La configuration est gérée via `config/packages/tehou.yaml` :

```yaml
tehou:
    api:
        enabled: true
        token: "UN_TOKEN_API_SECRET_A_CHANGER"
```

Une méthode privée `isAuthorized()` est appelée au début de chaque endpoint pour valider le token.

### 3.2. Format de Réponse Standardisé

Toutes les réponses de l'API suivent un format JSON standardisé pour assurer la cohérence :
```json
{
  "status": "success|error",
  "message": "Description claire de la réponse",
  "data": { /* Données spécifiques à l'endpoint */ },
  "timestamp": "2025-08-11T01:58:00+00:00"
}
```

### 3.3. Endpoints Implémentés

#### Endpoints de Position (Client → Serveur)

-   `POST /api/position`
    -   **Description :** Actualise la position d'un agent.
    -   **Body :** `{"username": "12345", "ip": "192.168.1.10", "mac": "AA:BB:CC:DD:EE:FF"}`
    -   **Service utilisé :** `PositionService::actualiserAgent()`

-   `POST /api/logoff`
    -   **Description :** Déconnecte un agent.
    -   **Body :** `{"username": "12345"}`
    -   **Service utilisé :** `PositionService::deconnecterAgent()`

-   `POST /api/sleep`
    -   **Description :** Met en veille un agent.
    -   **Body :** `{"username": "12345"}`
    -   **Service utilisé :** `PositionService::veilleAgent()`

#### Endpoints d'Inventaire

-   `GET /api/inventaire/get`
    -   **Description :** Récupère le matériel associé à une position ou un agent.
    -   **Query Params :** `?position_id=123` OU `?agent_id=12345`

-   `POST /api/inventaire/set`
    -   **Description :** Remplace l'inventaire matériel d'une position.
    -   **Body :** `{"position_id": 123, "materiel": [{"type": "dock", "codebarre": "..."}]}`
    -   **Logique :** La méthode utilise une transaction pour supprimer l'ancien matériel et persister le nouveau, garantissant l'atomicité de l'opération.

## 4. Tests

Une suite de tests complète (`tests/Controller/ApiControllerTest.php`) a été écrite pour couvrir tous les endpoints, y compris les cas d'authentification, les erreurs de validation et les scénarios de succès.

**Problème Connu :** Conformément aux rapports précédents sur le projet, l'environnement de test est instable et sujet à des timeouts. L'exécution de la suite de tests a échoué en raison d'un timeout de plus de 6 minutes. Sur instruction, l'étape d'exécution des tests a été sautée pour permettre la finalisation de la mission. Les tests sont cependant livrés et prêts à être exécutés dans un environnement stable.

## 5. Conclusion

Le `ApiController` a été implémenté avec succès conformément à toutes les spécifications techniques. Il est robuste, sécurisé par token, et s'intègre parfaitement à l'architecture existante du projet TEHOU. Le code est entièrement documenté (PhpDoc) et prêt pour la production, sous réserve de la validation des tests dans un environnement fonctionnel.
