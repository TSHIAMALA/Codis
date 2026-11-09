<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Base de test reconstruite à chaque lancement : migrations (comme en production) puis jeu de données de référence.
// Chaque test s'exécute ensuite dans une transaction annulée (DAMA DoctrineTestBundle).
// TESTS_SKIP_DB_RESET=1 permet de réutiliser la base existante pour aller plus vite.
if (!($_SERVER['TESTS_SKIP_DB_RESET'] ?? $_ENV['TESTS_SKIP_DB_RESET'] ?? false)) {
    $console = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/console');
    foreach ([
        'doctrine:database:drop --force --if-exists',
        'doctrine:database:create',
        'doctrine:migrations:migrate --no-interaction',
        'doctrine:fixtures:load --no-interaction',
    ] as $commande) {
        exec("$console $commande --env=test --quiet 2>&1", $sortie, $code);
        if ($code !== 0) {
            fwrite(STDERR, "Échec de « $commande » :\n" . implode("\n", $sortie) . "\n");
            exit(1);
        }
    }
}
