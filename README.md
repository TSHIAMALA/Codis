# CODIS — Colonne Distribution System (web)

Application Symfony 7.4 de gestion de la distribution CODIS : réseau commercial, commandes, consignations des DR, livraisons, paiements, production et stocks multi-sites. Elle expose aussi l'API JWT de l'application mobile (`../codis_mobil`).

Documentation fonctionnelle : [docs/ARCHITECTURE_ET_WORKFLOWS.md](docs/ARCHITECTURE_ET_WORKFLOWS.md) (circuits et impacts stock) et [docs/GUIDE_UTILISATION_ET_TESTS.md](docs/GUIDE_UTILISATION_ET_TESTS.md) (rôles, matrice des accès).

## Prérequis

- PHP 8.2+ (extensions `pdo_mysql`, `openssl`, `intl`), Composer 2
- MySQL 8 (WAMP convient). Le moteur par défaut du serveur peut rester MyISAM : Doctrine crée toutes les tables en InnoDB (`config/packages/doctrine.yaml`).

## Installation

```bash
composer install

# 1. Secrets locaux : jamais dans .env, toujours dans .env.local (non versionné)
php -r "echo 'APP_SECRET=' . bin2hex(random_bytes(16)) . PHP_EOL . 'JWT_PASSPHRASE=' . bin2hex(random_bytes(32)) . PHP_EOL;" > .env.local
#    puis ajouter DATABASE_URL dans .env.local si la base n'est pas mysql://root:@127.0.0.1:3306/db_codis

# 2. Clés JWT (chiffrées avec JWT_PASSPHRASE)
php bin/console lexik:jwt:generate-keypair
#    Sous Windows, si OpenSSL ne trouve pas sa configuration :
#    set OPENSSL_CONF=C:\wamp64\bin\php\php8.3.6\extras\ssl\openssl.cnf

# 3. Base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate   # doit afficher deux [OK]
```

Le premier compte administrateur se crée en base, ou en chargeant les données de démonstration (`php bin/console doctrine:fixtures:load`, qui **efface la base**, voir `src/DataFixtures/AppFixtures.php`).

## Mise en production

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction   # après une sauvegarde de la base
php bin/console asset-map:compile                             # OBLIGATOIRE : écrit public/assets/
php bin/console cache:clear --env=prod
```

Les styles et scripts (`assets/styles/*.css`, `assets/scripts/codis.js`) sont servis par AssetMapper avec un nom versionné (ex. `codis-lHFW-Cl.css`), ce qui permet au navigateur de les garder en cache. En dev, Symfony les sert à la volée. **En production, sans `asset-map:compile`, les pages s'afficheraient sans style.** Relancer la commande à chaque déploiement.

## Migrations

Toute évolution du schéma passe par une migration : `php bin/console make:migration`, à relire, puis `doctrine:migrations:migrate`. Ne pas utiliser `doctrine:schema:update --force`.

| Migration | Rôle |
| :--- | :--- |
| `Version20261101000000` | Schéma complet pour une installation neuve (sans effet sur une base existante) |
| `Version20261109130000` / `140000` | Phase 2 : stock DR, conditionnement des consignations, InnoDB, valeurs ENUM manquantes |
| `Version20261110000000` | Alignement d'une base existante sur les entités (types, 96 clés étrangères, index), sans perte de données |

Les migrations détectent l'état de la base : la même suite fonctionne sur une base neuve comme sur une base existante.

## Tests

```bash
composer test            # ou php bin/phpunit
```

- La base `db_codis_test` (suffixe `_test` imposé, la base de développement n'est jamais touchée) est recréée à chaque lancement : migrations puis `AppFixtures`.
- Chaque test s'exécute dans une transaction annulée (DAMA DoctrineTestBundle).
- `TESTS_SKIP_DB_RESET=1 php bin/phpunit` réutilise la base de test existante (plus rapide pendant le développement).
- Les clés JWT de test (`config/jwt/test/`) ne sont pas chiffrées et ne servent qu'aux tests.

Couverture actuelle : sécurité (accès par rôle, CSRF, connexion, demandes d'accès), commandes et livraisons (prix serveur, paiement préalable, stock FEFO, annulations, limite de crédit), consignations DR (double validation, clôture, rejet), moteur de stock, API mobile, et performance.

`PerformanceTest` vérifie que le nombre de requêtes SQL des listes ne dépend pas du volume de données (pas de requête N+1). Si ce test échoue après une modification, charger les relations affichées par jointure (`->leftJoin(...)` + `->addSelect(...)`) plutôt que de les laisser se charger ligne par ligne.

## Listes et pagination

Les listes qui grossissent avec l'activité sont paginées par `App\Pagination\Page` (25 éléments par défaut) et affichent `templates/_pagination.html.twig`, qui conserve les filtres de l'URL :

```php
$commandes = Page::depuisRequete($qb, $request);                         // QueryBuilder avec ses jointures
$livraisons = Page::depuisRequete($qb, $request, jointureCollection: true); // si une collection est jointe
```

```twig
{{ commandes.total }} commandes
{% for c in commandes %}...{% endfor %}
{{ include('_pagination.html.twig', {page: commandes}) }}
```

## Architecture

| Dossier | Contenu |
| :--- | :--- |
| `src/Controller` | Contrôleurs web (Twig) et `Api/` (JSON + JWT) : ils lisent la requête et délèguent aux services |
| `src/Service` | Règles métier : `StockManagerService` (seul point d'écriture du stock), `OrderManagerService`, `LivraisonService`, `ConsignationService`, `TarifResolver` (prix) |
| `src/Security` | `UserChecker` (comptes inactifs), voter des consignations, page d'accès refusé |
| `config/packages/workflow.yaml` | Cycles de vie autorisés (commande, livraison, consignation, production) |
| `config/packages/security.yaml` | Rôles, hiérarchie et règles d'accès |
