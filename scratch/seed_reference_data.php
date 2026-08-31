<?php

use App\Kernel;
use App\Entity\PaymentMethod;
use App\Entity\OrderStatus;
use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$pmRepo = $em->getRepository(PaymentMethod::class);
$osRepo = $em->getRepository(OrderStatus::class);

$pms = [
    ['name' => 'M-Pesa', 'code' => 'm_pesa', 'icon' => 'smartphone'],
    ['name' => 'Airtel Money', 'code' => 'airtel_money', 'icon' => 'smartphone'],
    ['name' => 'Orange Money', 'code' => 'orange_money', 'icon' => 'smartphone'],
    ['name' => 'Cash (Paiement à la livraison)', 'code' => 'cash', 'icon' => 'banknote'],
];

foreach ($pms as $pm) {
    if (!$pmRepo->findOneBy(['code' => $pm['code']])) {
        $p = new PaymentMethod();
        $p->setName($pm['name']);
        $p->setCode($pm['code']);
        $p->setIcon($pm['icon']);
        $p->setIsActive(true);
        $em->persist($p);
    }
}

$oss = [
    ['code' => 'received', 'label' => 'Commande reçue', 'color' => 'blue', 'position' => 1],
    ['code' => 'preparing', 'label' => 'En préparation', 'color' => 'amber', 'position' => 2],
    ['code' => 'shipping', 'label' => 'En livraison', 'color' => 'cyan', 'position' => 3],
    ['code' => 'delivered', 'label' => 'Livrée', 'color' => 'purple', 'position' => 4],
    ['code' => 'confirmed', 'label' => 'Confirmée', 'color' => 'emerald', 'position' => 5],
    ['code' => 'cancelled', 'label' => 'Annulée', 'color' => 'red', 'position' => 6],
];

foreach ($oss as $os) {
    if (!$osRepo->findOneBy(['code' => $os['code']])) {
        $o = new OrderStatus();
        $o->setCode($os['code']);
        $o->setLabel($os['label']);
        $o->setColor($os['color']);
        $o->setPosition($os['position']);
        $o->setIsActive(true);
        $em->persist($o);
    }
}

$em->flush();
echo "SEEDED_REFERENCE_DATA_SUCCESSFULLY\n";
