<?php

namespace App\DataFixtures;

use App\Entity\AgentCommercial;
use App\Entity\CategorieProduit;
use App\Entity\Conditionnement;
use App\Entity\Depot;
use App\Entity\Detailant;
use App\Entity\EmplacementStock;
use App\Entity\Grossiste;
use App\Entity\Lot;
use App\Entity\Marque;
use App\Entity\Pays;
use App\Entity\Produit;
use App\Entity\Region;
use App\Entity\Role;
use App\Entity\Stock;
use App\Entity\Tarif;
use App\Entity\Usine;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de référence des tests automatisés (chargé dans db_codis_test).
 *
 * Stock initial au dépôt « DEP-TEST » :
 *  - Produit A, Carton : 30 (lot ancien, péremption J+30) + 100 (lot récent, J+365) = 130
 *  - Produit B, Carton : 20 (sans tarif : toute commande de B doit être refusée)
 * Tarifs du Produit A, Carton : 450 $ (grossiste, région Kinshasa), 500 $ (grossiste, national), 480 $ (détaillant, Kinshasa)
 */
class AppFixtures extends Fixture
{
    public const MOT_DE_PASSE = 'MotDePasseTest1!';

    public const ADMIN = 'test_admin';
    public const DELEGUE = 'test_delegue';
    public const DR = 'test_dr';
    public const SUPERVISEUR = 'test_superviseur';
    public const ASM = 'test_asm';
    public const GESTIONNAIRE = 'test_depot';
    public const CAISSIER = 'test_caisse';
    public const PARTENAIRE = 'test_partenaire';
    public const EN_ATTENTE = 'test_en_attente';

    public const DEPOT = 'DEP-TEST';
    public const PRODUIT_A = 'PA';
    public const PRODUIT_B = 'PB';
    public const DISTRIBUTEUR_AGREE = 'GRO-AGREE';
    public const GROSSISTE_SECTEUR = 'GRO-SECTEUR';
    public const DETAILLANT = 'DET-TEST';

    public function __construct(private UserPasswordHasherInterface $hasher) {}

    public function load(ObjectManager $om): void
    {
        $roles = [];
        foreach ([
            'SUPER_ADMIN' => 'Administrateur général', 'ADMIN_PAYS' => 'Administrateur pays', 'RESPONSABLE_USINE' => 'Responsable usine',
            'GESTIONNAIRE_DEPOT' => 'Gestionnaire dépôt', 'DELEGUE' => 'Délégué commercial', 'AGENT_COMMERCIAL' => 'Agent commercial',
            'GROSSISTE_USER' => 'Utilisateur grossiste', 'CAISSIER' => 'Caissier',
        ] as $code => $libelle) {
            $roles[$code] = (new Role())->setCode($code)->setLibelle($libelle);
            $om->persist($roles[$code]);
        }

        $pays = (new Pays())->setCode('CD')->setNom('RDC Test')->setDevise('USD')->setActif(true);
        $kin = (new Region())->setPays($pays)->setCode('KIN')->setNom('Kinshasa')->setActif(true);
        $kat = (new Region())->setPays($pays)->setCode('KAT')->setNom('Katanga')->setActif(true);
        array_map([$om, 'persist'], [$pays, $kin, $kat]);

        $depot = (new Depot())->setPays($pays)->setRegion($kin)->setCode(self::DEPOT)->setNom('Dépôt Test Kinshasa')->setTypeDepot('CENTRAL')->setActif(true);
        $empDepot = (new EmplacementStock())->setTypeEmplacement('DEPOT')->setDepot($depot)->setNom('Stock Dépôt Test')->setActif(true);
        $usine = (new Usine())->setPays($pays)->setRegion($kin)->setCode('USI-TEST')->setNom('Usine Test')->setActif(true);
        $empUsine = (new EmplacementStock())->setTypeEmplacement('USINE')->setUsine($usine)->setNom('Stock Usine Test')->setActif(true);
        array_map([$om, 'persist'], [$depot, $empDepot, $usine, $empUsine]);

        $marque = (new Marque())->setCode('MQT')->setNom('Marque Test')->setActif(true);
        $categorie = (new CategorieProduit())->setCode('CAT')->setLibelle('Catégorie Test')->setActif(true);
        $produitA = (new Produit())->setMarque($marque)->setCategorie($categorie)->setCode(self::PRODUIT_A)->setNom('Produit A')->setActif(true);
        $produitB = (new Produit())->setMarque($marque)->setCategorie($categorie)->setCode(self::PRODUIT_B)->setNom('Produit B')->setActif(true);
        $cartonA = (new Conditionnement())->setProduit($produitA)->setLibelle('Carton')->setNiveau(3)->setUniteReference('PAQUET')->setQuantiteConversion('50');
        $paquetA = (new Conditionnement())->setProduit($produitA)->setLibelle('Paquet')->setNiveau(2)->setUniteReference('UNITE')->setQuantiteConversion('20');
        $cartonB = (new Conditionnement())->setProduit($produitB)->setLibelle('Carton')->setNiveau(3)->setUniteReference('PAQUET')->setQuantiteConversion('50');
        array_map([$om, 'persist'], [$marque, $categorie, $produitA, $produitB, $cartonA, $paquetA, $cartonB]);

        $lotAncien = (new Lot())->setProduit($produitA)->setUsine($usine)->setNumeroLot('LOT-A-ANCIEN')->setDateFabrication(new \DateTime('-60 days'))->setDateExpiration(new \DateTime('+30 days'))->setStatut('ACTIF');
        $lotRecent = (new Lot())->setProduit($produitA)->setUsine($usine)->setNumeroLot('LOT-A-RECENT')->setDateFabrication(new \DateTime('-5 days'))->setDateExpiration(new \DateTime('+365 days'))->setStatut('ACTIF');
        $lotB = (new Lot())->setProduit($produitB)->setUsine($usine)->setNumeroLot('LOT-B')->setDateFabrication(new \DateTime('-5 days'))->setDateExpiration(new \DateTime('+365 days'))->setStatut('ACTIF');
        array_map([$om, 'persist'], [$lotAncien, $lotRecent, $lotB]);

        // Le lot récent est créé en premier : la consommation FEFO ne doit pas dépendre de l'ordre d'insertion
        foreach ([[$lotRecent, $cartonA, $produitA, '100'], [$lotAncien, $cartonA, $produitA, '30'], [$lotB, $cartonB, $produitB, '20']] as [$lot, $cond, $produit, $qte]) {
            $stock = (new Stock())->setEmplacement($empDepot)->setProduit($produit)->setLot($lot)->setConditionnement($cond)
                ->setQuantiteDisponible($qte)->setQuantiteReservee('0')->setQuantiteTransit('0');
            $om->persist($stock);
        }

        foreach ([[$kin, 'GROSSISTE', '450'], [null, 'GROSSISTE', '500'], [$kin, 'DETAILLANT', '480']] as [$region, $type, $prix]) {
            $om->persist((new Tarif())->setPays($pays)->setRegion($region)->setProduit($produitA)->setConditionnement($cartonA)->setTypeClient($type)->setPrix($prix)->setActif(true));
        }

        $users = [];
        foreach ([
            self::ADMIN => 'SUPER_ADMIN', self::DELEGUE => 'DELEGUE', self::DR => 'AGENT_COMMERCIAL', self::SUPERVISEUR => 'AGENT_COMMERCIAL',
            self::ASM => 'AGENT_COMMERCIAL', self::GESTIONNAIRE => 'GESTIONNAIRE_DEPOT', self::CAISSIER => 'CAISSIER',
            self::PARTENAIRE => 'GROSSISTE_USER', self::EN_ATTENTE => null,
        ] as $username => $role) {
            $u = (new Utilisateur())->setNom(strtoupper($username))->setPrenom('Test')->setUsername($username)->setEmail($username . '@test.codis')->setPays($pays)->setActif($role !== null);
            $u->setPassword($this->hasher->hashPassword($u, self::MOT_DE_PASSE));
            if ($role) {
                $u->addRoleEntity($roles[$role]);
            }
            $users[$username] = $u;
            $om->persist($u);
        }

        $asm = (new AgentCommercial())->setUtilisateur($users[self::ASM])->setPays($pays)->setRegion($kin)->setTypeAgent('ASM')->setActif(true);
        $superviseur = (new AgentCommercial())->setUtilisateur($users[self::SUPERVISEUR])->setPays($pays)->setRegion($kin)->setTypeAgent('SUPERVISEUR')->setSuperviseur($asm)->setActif(true);
        $dr = (new AgentCommercial())->setUtilisateur($users[self::DR])->setPays($pays)->setRegion($kin)->setTypeAgent('DR')->setSuperviseur($superviseur)->setActif(true);
        array_map([$om, 'persist'], [$asm, $superviseur, $dr]);

        $om->persist((new Grossiste())->setPays($pays)->setRegion($kin)->setCode(self::DISTRIBUTEUR_AGREE)->setRaisonSociale('Distributeur Agréé Test')->setLimiteCredit('0')->setTypeDistribution('DISTRIBUTEUR_AGREE')->setStatut('ACTIF'));
        $om->persist((new Grossiste())->setPays($pays)->setRegion($kin)->setDr($dr)->setCode(self::GROSSISTE_SECTEUR)->setRaisonSociale('Grossiste Secteur Test')->setLimiteCredit('1000')->setTypeDistribution('GROSSISTE_SECTEUR')->setStatut('ACTIF'));
        $om->persist((new Detailant())->setPays($pays)->setRegion($kin)->setCode(self::DETAILLANT)->setNom('Boutique Test')->setStatut('ACTIF'));

        $om->flush();
    }
}
