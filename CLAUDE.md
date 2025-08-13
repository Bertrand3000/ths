# PHPUnit Debugging - Projet TEHOU

## Contraintes Qualité
- **JAMAIS** utiliser `--no-verify` ou bypass des hooks
- **JAMAIS** désactiver des tests, les corriger
- **TOUJOURS** exécuter la suite complète après chaque fix
- **TOUJOURS** maintenir la couverture de test existante

## Patterns PHPUnit/Symfony Identifiés
### Configuration services test
- Container services disponible via `static::getContainer()`
- Environnement test activé par `APP_ENV=test` dans phpunit.xml
- Base données SQLite file-based plus stable que :memory:

### Dépendances injection
- Services privés accessibles en test via conteneur
- Paramètres configuration requis pour TehouBundle/Extension
- Lock factory nécessite configuration timeout

### Mocks vs services réels
- Tests services utilisent vrais services (integration tests)
- TestTools.php fournit factory pour entités de test
- Isolation via transactions BDD plutôt que mocks complets

### Problèmes base données test
- Configuration SQLite distincte dev/test
- Schéma doit être créé explicitement pour environnement test
- Pas de migrations automatiques en test

## Commandes Fréquentes
```bash
# Tests complets
./vendor/bin/phpunit

# Tests spécifiques
./vendor/bin/phpunit tests/Service/StatsServiceTest.php
./vendor/bin/phpunit tests/Controller/AdminControllerTest.php

# Debug verbose
./vendor/bin/phpunit --verbose --debug

# Stop premier échec
./vendor/bin/phpunit --stop-on-failure --testdox

# Tests avec profiling
./vendor/bin/phpunit --verbose

# Configuration base test
php bin/console doctrine:schema:create --env=test

# Debug configuration
php bin/console debug:container --parameters --env=test
```

## Commandes Projet Spécifiques
```bash
# Installation dépendances manquantes
composer require symfony/validator:5.4.*

# Vérification status base données
php bin/console doctrine:query:sql "SELECT name FROM sqlite_master WHERE type='table';" --env=test

# Tests par catégorie
./vendor/bin/phpunit tests/Service/ --testdox
./vendor/bin/phpunit tests/Controller/ --testdox

# Performance monitoring  
./vendor/bin/phpunit --verbose | grep -E "Time:|Memory:"
```

## Résolutions Types d'Erreurs

### Dépendances manquantes
```bash
# Identifier package manquant
php -r "echo class_exists('ClassPath') ? 'YES' : 'NO';"

# Installer via Composer
composer require package/name:version

# Vérifier installation
composer show package/name
```

### Configuration services manquants
```yaml
# config/packages/tehou.yaml
tehou:
    syslog:
        lock_timeout: 300
        batch_size: 1000
        max_errors: 100
```

### Tests isolation
```php
// Dans setUp()
$this->entityManager->beginTransaction();

// Dans tearDown()  
$this->entityManager->rollback();
```