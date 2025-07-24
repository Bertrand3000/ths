# Rapport de Développement - SyslogService

**Date :** 2025-07-24
**Auteur :** Jules
**Service :** `src/Service/SyslogService.php`

## 1. Architecture et Contexte

Le `SyslogService` a été développé pour répondre au besoin de correspondance automatique entre les adresses MAC des équipements connectés au réseau et les positions physiques (bureaux) dans le cadre du projet TEHOU.

Ce service s'intègre dans l'architecture Symfony 5.4 existante et a pour responsabilités :
-   **L'analyse des journaux (syslogs)** envoyés par les commutateurs réseau (switches).
-   **La mise à jour en temps réel** de la table `position` de la base de données avec les informations de connexion et de déconnexion.
-   **Le nettoyage périodique** des anciens journaux pour maintenir la performance de la base de données.

Le service s'appuie sur les entités `Systemevents`, `Config`, `Position`, et `NetworkSwitch` et leurs repositories respectifs.

## 2. Choix Techniques

### 2.1. Parsing des messages

Pour extraire les informations pertinentes des messages syslog, des **expressions régulières (regex)** ont été utilisées. Cette approche offre un bon compromis entre performance et flexibilité pour traiter les formats de messages spécifiques des switches.

-   **Connexion (LLDP_CREATE_NEIGHBOR) :** La regex capture le nom du port et l'adresse MAC du périphérique connecté.
-   **Déconnexion (PHY_UPDOWN) :** La regex capture le nom du port qui vient de passer à l'état "down".

L'adresse MAC est ensuite normalisée au format standard `XX:XX:XX:XX:XX:XX`.

### 2.2. Gestion de l'état

La gestion de l'état du traitement est assurée par la table `config`, qui stocke deux informations clés :

-   `dernier_syslog_id` : Permet au service de ne traiter que les nouveaux événements à chaque exécution, évitant ainsi les doublons et les traitements inutiles.
-   `dernier_nettoyage_syslog` : Stocke le timestamp du dernier nettoyage pour s'assurer que cette opération n'est effectuée qu'une fois toutes les 24 heures.

### 2.3. Transactions de Base de Données

Toutes les opérations d'écriture dans la fonction `analyzeSyslogEvents` (mise à jour des positions et de la configuration) sont encapsulées dans une **transaction Doctrine**. Cela garantit l'atomicité de l'opération : si une erreur survient, toutes les modifications sont annulées (`rollback`), assurant ainsi la cohérence de la base de données.

## 3. Tests Réalisés

Des tests unitaires complets ont été développés pour valider le comportement du service en isolation.

-   **Fichier de test :** `tests/Service/SyslogServiceTest.php`
-   **Méthodologie :** Les dépendances (`EntityManager`, repositories) ont été remplacées par des **mocks** pour simuler leur comportement et isoler la logique du service.
-   **Cas de tests couverts :**
    1.  **Analyse d'un événement de connexion :** Vérifie que le message est correctement parsé et que l'adresse MAC est bien affectée à la bonne position.
    2.  **Analyse d'un événement de déconnexion :** Vérifie que l'adresse MAC est bien mise à `NULL` sur la position correspondante.
    3.  **Nettoyage non effectué :** Valide que la fonction de nettoyage ne s'exécute pas si moins de 24 heures se sont écoulées.
    4.  **Nettoyage effectué :** Valide que la fonction de nettoyage supprime les anciens événements lorsque les conditions sont remplies.
-   **Résultat :** Tous les tests passent avec succès, assurant la fiabilité et la robustesse du service.

## 4. Points d'Attention et Améliorations Possibles

-   **Gestion des Erreurs :** Pour l'instant, les erreurs (ex: un switch non trouvé dans la base de données à partir de son `syslogtag`) sont ignorées silencieusement. Une amélioration future pourrait être d'injecter un service de logging (comme Monolog) pour tracer ces cas et faciliter le diagnostic.
-   **Performance :** Si le volume de syslogs devient très important, le traitement de tous les nouveaux événements en une seule fois pourrait devenir un goulot d'étranglement. Une approche par lots (`batch processing`) pourrait être envisagée.
-   **Robustesse des Regex :** Les expressions régulières sont spécifiques aux formats de message fournis. Si de nouveaux modèles de switches avec des formats de log différents sont ajoutés, il faudra rendre le système de parsing plus flexible, potentiellement via une stratégie de "parsers" interchangeables.
