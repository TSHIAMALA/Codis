<?php

namespace App\Service;

use App\Entity\Conditionnement;
use App\Entity\Detailant;
use App\Entity\Grossiste;
use App\Entity\Produit;
use App\Entity\Region;
use App\Entity\Tarif;
use App\Entity\ZoneCommerciale;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Source unique du prix de vente. Priorité : Zone > Région > Tarif national > Tarif de référence.
 * Les prix envoyés par le navigateur ou l'app mobile ne sont jamais utilisés.
 */
class TarifResolver
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @return array{prix: float, source: string, region?: string, zone?: string, devise: string}|null
     *         null si aucun tarif actif n'est défini pour ce produit/conditionnement/type de client.
     */
    public function resoudre(
        Produit $produit,
        Conditionnement $conditionnement,
        string $typeClient,
        ?Grossiste $grossiste = null,
        ?Detailant $detailant = null,
        ?Region $region = null,
        ?ZoneCommerciale $zone = null,
    ): ?array {
        // Si le client est spécifié, sa région et sa zone priment
        if ($grossiste) {
            $region = $grossiste->getRegion() ?? $region;
            $zone = $grossiste->getZone() ?? $zone;
        } elseif ($detailant) {
            $region = $detailant->getRegion() ?? $region;
            $zone = $detailant->getZone() ?? $zone;
        }

        $base = [
            'produit' => $produit,
            'conditionnement' => $conditionnement,
            'typeClient' => $typeClient,
            'actif' => true,
        ];
        $repo = $this->em->getRepository(Tarif::class);

        if ($zone && $t = $repo->findOneBy($base + ['zone' => $zone])) {
            return ['prix' => (float) $t->getPrix(), 'source' => 'Tarif Zone : ' . $zone->getNom(), 'zone' => $zone->getNom(), 'devise' => '$'];
        }

        if ($region && $t = $repo->findOneBy($base + ['region' => $region, 'zone' => null])) {
            return ['prix' => (float) $t->getPrix(), 'source' => 'Tarif Région : ' . $region->getNom(), 'region' => $region->getNom(), 'devise' => '$'];
        }

        if ($t = $repo->findOneBy($base + ['region' => null, 'zone' => null])) {
            return ['prix' => (float) $t->getPrix(), 'source' => 'Tarif National', 'region' => 'Nationale', 'devise' => '$'];
        }

        // Aucun tarif national : on retient le tarif actif le plus ancien (comportement historique)
        if ($t = $repo->findOneBy($base, ['id' => 'ASC'])) {
            return ['prix' => (float) $t->getPrix(), 'source' => 'Tarif de Référence', 'region' => $t->getRegion()?->getNom() ?? 'Nationale', 'devise' => '$'];
        }

        return null;
    }
}
