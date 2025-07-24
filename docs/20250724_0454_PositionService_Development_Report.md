# Rapport de Développement - PositionService

**Date :** 2025-07-24
**Auteur :** Jules
**Service :** `src/Service/PositionService.php`

## 1. Contexte

Le `PositionService` a été développé pour centraliser la logique métier liée à la gestion des connexions et des positions des agents. Il est le point d'entrée pour toutes les mises à jour provenant des clients lourds et est responsable de la cohérence des tables `agent_connexion` et `agent_position`.

## 2. Fonctionnalités Implémentées

### 2.1. `actualiserAgent(string $numeroAgent, string $ip, string $mac)`

Cette fonction publique est le cœur du service. Elle exécute la logique suivante :
1.  **Nettoyage :** Appelle une fonction privée pour supprimer les connexions expirées.
2.  **Détermination du type de connexion :** Analyse l'adresse IP pour la classifier en `TELETRAVAIL`, `SITE`, `WIFI`, ou hors réseau.
3.  **Gestion de `agent_connexion` :** Crée ou met à jour un enregistrement `agent_connexion` pour chaque appel, en stockant le type de connexion, l'IP, et la MAC.
4.  **Gestion de `agent_position` :**
    -   **TELETRAVAIL :** Supprime toute position existante.
    -   **SITE :** Appelle le `SyslogService` pour s'assurer que la table `position` est à jour, puis recherche la position correspondante à l'adresse MAC de l'agent pour créer ou mettre à jour l'enregistrement `agent_position`.
    -   **WIFI :** Ne modifie pas l'enregistrement `agent_position` existant.
    -   **Hors Réseau :** Ne fait rien.

### 2.2. `deconnecterAgent(string $numeroAgent)`

Cette fonction supprime simplement les enregistrements `agent_connexion` et `agent_position` pour un agent donné.

### 2.3. `veilleAgent(string $numeroAgent)`

Cette fonction est un placeholder pour une future implémentation et ne contient actuellement aucune logique.

### 2.4. `nettoyerConnexions()`

Cette fonction privée est appelée par `actualiserAgent`. Elle supprime les `agent_connexion` dont le champ `dateactualisation` est plus ancien qu'un timeout défini (actuellement 30 minutes) et supprime également les `agent_position` associés.

## 3. Choix Techniques

-   **Détermination du type de connexion :** La logique de détermination du type de connexion est basée sur les plages d'adresses IP définies dans le `Cahier-Technique-TEHOU.md`.
-   **Dépendance à `SyslogService` :** Pour les connexions sur site, le service s'appuie sur `SyslogService` pour obtenir la correspondance MAC/position, conformément aux spécifications.
-   **Timeout :** Un timeout de 30 minutes a été choisi pour le nettoyage des connexions. Cette valeur est stockée dans une constante de classe et peut être facilement modifiée.

## 4. Tests

Des tests unitaires complets ont été développés dans `tests/Service/PositionServiceTest.php`. Les tests couvrent tous les cas de figure pour la fonction `actualiserAgent`, ainsi que les fonctions `deconnecterAgent` et `nettoyerConnexions`. Les dépendances du service sont mockées pour assurer des tests rapides et isolés. Tous les tests passent avec succès.

## 5. Conclusion

Le `PositionService` a été implémenté conformément aux spécifications. Il fournit une interface claire et robuste pour gérer la logique de positionnement des agents et est prêt à être utilisé par les contrôleurs de l'application.
