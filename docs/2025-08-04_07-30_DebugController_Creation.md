# Rapport de Création - DebugController

**Date :** 2025-08-04
**Auteur :** Jules
**Service :** `src/Controller/DebugController.php`

## 1. Contexte

Ce rapport documente la création complète du `DebugController`, un nouveau service d'API REST conçu pour faciliter les tests et le débogage de l'application TEHOU. Ce contrôleur fournit une série d'endpoints pour simuler le comportement des clients lourds et pour manipuler les données de test.

## 2. Fichiers Créés et Modifiés

### Fichiers Créés

-   `src/Controller/DebugController.php`: Le nouveau contrôleur avec tous les endpoints de débogage.
-   `tests/Controller/DebugControllerTest.php`: La suite de tests pour le nouveau contrôleur.
-   `docs/2025-08-04_07-30_DebugController_Creation.md`: Ce rapport.

### Fichiers Modifiés

-   `src/Entity/AgentConnexion.php`: Ajout d'un champ `status` pour suivre l'état de l'agent (active, sleep, logout).
-   `src/Service/ArchitectureService.php`: Ajout de méthodes publiques (`addAgent`, `deleteAgent`, `createTestPosition`) pour la gestion des données de test.
-   `src/Service/PositionService.php`: Implémentation de la méthode `veilleAgent` et mise à jour des autres méthodes pour utiliser le nouveau champ `status`.
-   `config/packages/tehou.yaml`: Ajout de la section de configuration `debug`.
-   `src/DependencyInjection/Configuration.php`: Définition de la structure de configuration pour `tehou.debug`.
-   `src/DependencyInjection/TehouExtension.php`: Chargement de la configuration `tehou.debug` dans le conteneur de services.

## 3. Configuration Requise

Pour activer le `DebugController`, la configuration suivante doit être présente dans `config/packages/tehou.yaml`:

```yaml
tehou:
    debug:
        enabled: true
        token: "UN_TOKEN_SECRET_A_CHANGER"
```

-   `enabled`: Si `false`, tous les endpoints du contrôleur renverront une erreur 403.
-   `token`: Le token secret à fournir dans l'en-tête `Authorization` en tant que Bearer token.

## 4. Endpoints de l'API de Débogage

Tous les endpoints nécessitent l'en-tête `Authorization: Bearer VOTRE_TOKEN`.

### 4.1. Endpoints de Simulation

-   `POST /api/debug/simulate-position`
    -   **Description :** Simule une actualisation de position d'un agent.
    -   **Body (JSON) :** `{"username": "12345", "mac": "AA:BB:CC:DD:EE:FF", "ip": "55.153.4.10"}`
-   `POST /api/debug/simulate-logout`
    -   **Description :** Simule la déconnexion d'un agent.
    -   **Body (JSON) :** `{"username": "12345"}`
-   `POST /api/debug/simulate-sleep`
    -   **Description :** Simule la mise en veille du client d'un agent.
    -   **Body (JSON) :** `{"username": "12345"}`
-   `POST /api/debug/simulate-timeout`
    -   **Description :** Force le nettoyage de toutes les positions expirées.
    -   **Body :** Vide.

### 4.2. Endpoint d'État

-   `GET /api/debug/get-state`
    -   **Description :** Retourne un état complet de l'occupation de tous les sites, étages, et services, avec des statistiques détaillées.
    -   **Réponse :** Un objet JSON complexe décrivant toute l'architecture et l'état d'occupation.

### 4.3. Endpoints de Gestion des Données de Test

-   `POST /api/debug/create-test-agent`
    -   **Description :** Crée un nouvel agent de test.
    -   **Body (JSON) :** `{"numagent": "99999", "nom": "Test", "prenom": "Agent"}`
-   `DELETE /api/debug/remove-test-agent/{numagent}`
    -   **Description :** Supprime un agent de test et ses données associées.
-   `POST /api/debug/create-test-position`
    -   **Description :** Crée une nouvelle position de test avec une adresse MAC aléatoire.
-   `GET /api/debug/list-test-data`
    -   **Description :** Liste les agents et positions actuellement dans la base de données.

## 5. Tests

Une suite de tests a été créée pour le contrôleur. Cependant, l'exécution des tests a échoué en raison de timeouts persistants lors de l'initialisation de la base de données de test, un problème connu dans l'environnement de développement actuel. Les tests ont été écrits pour valider l'authentification et la fonctionnalité de base des endpoints et sont disponibles dans `tests/Controller/DebugControllerTest.php`.

## 6. Conclusion

Le `DebugController` est un outil puissant pour le développement et la maintenance de l'application TEHOU. Il fournit les fonctionnalités nécessaires pour simuler le comportement des clients et pour gérer un environnement de test stable.
