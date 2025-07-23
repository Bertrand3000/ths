# Rapport d'Implémentation : Fonctions CRUD pour ArchitectureService

**Date :** 2025-07-23
**Auteur :** Jules
**Version :** 1.0

## 1. Résumé des fonctionnalités ajoutées

Ce document détaille l'ajout de fonctionnalités de lecture et de
maintenance (CRUD - Create, Read, Update, Delete) au sein du service
`ArchitectureService`. Ces nouvelles fonctions permettent une gestion
programmatique complète des entités structurelles de l'application TEHOU :

-   `Site`
-   `Etage`
-   `Service`
-   `NetworkSwitch`
-   `Position`

L'objectif est de fournir une API de service robuste et testée pour toutes les opérations de base sur ces entités, en respectant les conventions Symfony et les contraintes de la base de données.

## 2. Détails Techniques de l'Implémentation

### 2.1. Service `ArchitectureService`

Le service existant `src/Service/ArchitectureService.php` a été étendu pour inclure les méthodes suivantes.

#### 2.1.1. Fonctions de Lecture (Read)

Des méthodes simples ont été ajoutées pour récupérer les entités et leurs relations :

-   `getSites(): array` : Retourne tous les sites.
-   `getSitePrincipal(): ?Site` : Retourne le premier site trouvé.
-   `getEtages(Site $site): array` : Retourne les étages d'un site.
-   `getServices(Etage $etage): array` : Retourne les services d'un étage.
-   `getSwitches(Etage $etage): array` : Retourne les switches d'un étage.
-   `getPositions(Etage $etage): array` : Retourne les positions d'un étage.

#### 2.1.2. Fonctions de Création (Create)

Chaque fonction `addXxx(array $data)` prend un tableau de données, valide les clés étrangères nécessaires, crée une nouvelle instance de l'entité, la persiste et la retourne.

Exemple : `addSite(array $data): Site`

#### 2.1.3. Fonctions de Mise à jour (Update)

Chaque fonction `updateXxx(int $id, array $data)` recherche l'entité par son ID. Si elle est trouvée, ses propriétés sont mises à jour à partir du tableau de données. La gestion des relations (ex: changer un étage de site) est supportée.

Exemple : `updateEtage(int $id, array $data): Etage`

#### 2.1.4. Fonctions de Suppression (Delete)

Chaque fonction `deleteXxx(int $id)` recherche l'entité par son ID et la supprime. Les contraintes de clés étrangères (`ON DELETE CASCADE`) définies dans le schéma de la base de données assurent l'intégrité référentielle.

Exemple : `deletePosition(int $id): bool`

### 2.2. Gestion des Erreurs

Pour les opérations de mise à jour et de suppression, si une entité n'est pas trouvée par son ID, une exception `\InvalidArgumentException` est levée avec un message explicite. Il en va de même lors de la création si une entité parente (ex: `Site` pour un `Etage`) n'est pas trouvée.

### 2.3. Conventions et Bonnes Pratiques

-   **Types Stricts :** Le code utilise les déclarations de types PHP 8.1.
-   **Documentation :** Toutes les nouvelles méthodes sont documentées avec des blocs PhpDoc complets en français.
-   **Injection de Dépendances :** Le service continue d'utiliser l'injection de dépendances pour `EntityManagerInterface`.

## 3. Résultats des Tests Unitaires

Des tests unitaires complets ont été créés pour valider l'ensemble des nouvelles fonctionnalités.

-   **Fichier de Test :** `tests/Service/ArchitectureServiceCrudTest.php`
-   **Méthodologie :** Utilisation de mocks pour `EntityManagerInterface` et les Repositories afin d'isoler la logique du service de la base de données.
-   **Couverture :**
    -   Toutes les fonctions de lecture (`getXxx`).
    -   Toutes les fonctions CRUD pour chaque entité.
    -   Validation des appels aux méthodes de l'EntityManager (`persist`, `flush`, `remove`, `find`).
-   **Résultat :**
    -   **21 tests** ont été exécutés.
    -   **31 assertions** ont été validées.
    -   **Statut : OK (100% de succès)**.

## 4. Points d'Attention pour la Maintenance

-   **Validation des Données :** La validation des données d'entrée (ex: type, longueur, format) n'est pas dans le périmètre de ce service. Elle doit être effectuée en amont, par exemple dans les Contrôleurs ou les Formulaires Symfony, avant d'appeler les méthodes du service.
-   **Contraintes de Suppression :** La logique de suppression repose sur les contraintes `ON DELETE` de la base de données. Si un `DELETE RESTRICT` est rencontré (comme sur `agent.idservice`), la suppression échouera au niveau de la base de données et lèvera une `ForeignKeyConstraintViolationException`. Ce comportement est attendu et doit être géré dans le code appelant (ex: un `try-catch` dans le contrôleur).

## 5. Instructions d'Utilisation des Nouvelles Fonctions

Le service `ArchitectureService` peut être injecté dans n'importe quel autre service ou contrôleur Symfony.

### Exemple : Créer un nouvel étage dans un contrôleur

```php
use App\Service\ArchitectureService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminEtageController extends AbstractController
{
    private ArchitectureService $architectureService;

    public function __construct(ArchitectureService $architectureService)
    {
        $this->architectureService = $architectureService;
    }

    /**
     * @Route("/admin/etage/new", name="admin_etage_new", methods={"POST"})
     */
    public function new(Request $request): Response
    {
        $data = [
            'nom' => $request->request->get('nom'),
            'site_id' => (int)$request->request->get('site_id'),
            'arriereplan' => 'default.png',
            'largeur' => 1920,
            'hauteur' => 1080,
        ];

        try {
            $etage = $this->architectureService->addEtage($data);
            $this->addFlash('success', "L'étage '{$etage->getNom()}' a été créé avec succès.");
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_etage_index');
    }
}
```
