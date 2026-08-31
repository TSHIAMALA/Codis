<?php

use App\Kernel;
use App\Entity\Tarif;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Entity\Region;
use App\Entity\Pays;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

$em = $kernel->getContainer()->get('doctrine')->getManager();

$produits = $em->getRepository(Produit::class)->findAll();
$regions = $em->getRepository(Region::class)->findAll();
$pays = $em->getRepository(Pays::class)->findOneBy([]);

// Base pricing table
$grille = [
    'Équateur Rouge' => [
        'Carton' => [
            'Kinshasa' => ['grossiste' => 450.00, 'detaillant' => 480.00],
            'Haut-Katanga' => ['grossiste' => 475.00, 'detaillant' => 510.00],
            'Nord-Kivu' => ['grossiste' => 490.00, 'detaillant' => 525.00],
            'Brazzaville' => ['grossiste' => 460.00, 'detaillant' => 495.00],
        ],
        'Paquet' => [
            'Kinshasa' => ['grossiste' => 9.20, 'detaillant' => 10.00],
            'Haut-Katanga' => ['grossiste' => 9.70, 'detaillant' => 10.50],
            'Nord-Kivu' => ['grossiste' => 10.00, 'detaillant' => 11.00],
            'Brazzaville' => ['grossiste' => 9.50, 'detaillant' => 10.20],
        ],
    ],
    'Équateur Or' => [
        'Carton' => [
            'Kinshasa' => ['grossiste' => 500.00, 'detaillant' => 535.00],
            'Haut-Katanga' => ['grossiste' => 530.00, 'detaillant' => 565.00],
            'Nord-Kivu' => ['grossiste' => 545.00, 'detaillant' => 580.00],
            'Brazzaville' => ['grossiste' => 515.00, 'detaillant' => 550.00],
        ],
        'Paquet' => [
            'Kinshasa' => ['grossiste' => 10.20, 'detaillant' => 11.00],
            'Haut-Katanga' => ['grossiste' => 10.80, 'detaillant' => 11.80],
            'Nord-Kivu' => ['grossiste' => 11.20, 'detaillant' => 12.00],
            'Brazzaville' => ['grossiste' => 10.50, 'detaillant' => 11.50],
        ],
    ],
    'Équateur Bleu' => [
        'Carton' => [
            'Kinshasa' => ['grossiste' => 420.00, 'detaillant' => 450.00],
            'Haut-Katanga' => ['grossiste' => 445.00, 'detaillant' => 480.00],
            'Nord-Kivu' => ['grossiste' => 460.00, 'detaillant' => 495.00],
            'Brazzaville' => ['grossiste' => 435.00, 'detaillant' => 465.00],
        ],
        'Paquet' => [
            'Kinshasa' => ['grossiste' => 8.60, 'detaillant' => 9.50],
            'Haut-Katanga' => ['grossiste' => 9.10, 'detaillant' => 10.00],
            'Nord-Kivu' => ['grossiste' => 9.40, 'detaillant' => 10.30],
            'Brazzaville' => ['grossiste' => 8.90, 'detaillant' => 9.80],
        ],
    ],
];

foreach ($produits as $p) {
    $pNom = $p->getNom();
    if (!isset($grille[$pNom])) continue;

    foreach ($p->getConditionnements() as $cond) {
        $cLib = $cond->getLibelle();
        if (!isset($grille[$pNom][$cLib])) continue;

        foreach ($regions as $r) {
            $rNom = $r->getNom();
            if (!isset($grille[$pNom][$cLib][$rNom])) continue;

            $prices = $grille[$pNom][$cLib][$rNom];

            // Grossiste
            $tG = $em->getRepository(Tarif::class)->findOneBy([
                'produit' => $p,
                'conditionnement' => $cond,
                'region' => $r,
                'typeClient' => 'GROSSISTE',
            ]);
            if (!$tG) {
                $tG = new Tarif();
                $tG->setProduit($p);
                $tG->setConditionnement($cond);
                $tG->setRegion($r);
                $tG->setPays($r->getPays() ?: $pays);
                $tG->setTypeClient('GROSSISTE');
            }
            $tG->setPrix((string) $prices['grossiste']);
            $tG->setActif(true);
            $em->persist($tG);

            // Detaillant
            $tD = $em->getRepository(Tarif::class)->findOneBy([
                'produit' => $p,
                'conditionnement' => $cond,
                'region' => $r,
                'typeClient' => 'DETAILLANT',
            ]);
            if (!$tD) {
                $tD = new Tarif();
                $tD->setProduit($p);
                $tD->setConditionnement($cond);
                $tD->setRegion($r);
                $tD->setPays($r->getPays() ?: $pays);
                $tD->setTypeClient('DETAILLANT');
            }
            $tD->setPrix((string) $prices['detaillant']);
            $tD->setActif(true);
            $em->persist($tD);
        }
    }
}

$em->flush();
echo "TARIFS_SEEDED_SUCCESSFULLY\n";
