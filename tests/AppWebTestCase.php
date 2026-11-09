<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Commande;
use App\Entity\Conditionnement;
use App\Entity\Depot;
use App\Entity\EmplacementStock;
use App\Entity\Grossiste;
use App\Entity\Produit;
use App\Entity\Stock;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base des tests fonctionnels : connexion par rôle, envoi de formulaires avec jeton CSRF,
 * lecture des messages flash et accès aux données de référence (AppFixtures).
 */
abstract class AppWebTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function loginAs(string $username): Utilisateur
    {
        $user = $this->em()->getRepository(Utilisateur::class)->findOneBy(['username' => $username]);
        $this->client->loginUser($user);

        return $user;
    }

    /**
     * Poste un formulaire de l'interface web avec un jeton CSRF valide (pris sur $pageFormulaire),
     * suit la redirection et renvoie le texte des messages flash affichés.
     */
    protected function poster(string $action, array $donnees = [], string $pageFormulaire = '/consignations'): string
    {
        $this->client->request('GET', $pageFormulaire);
        $jeton = $this->client->getCrawler()->filter('input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', $action, $donnees + ['_token' => $jeton]);
        $crawler = $this->client->getResponse()->isRedirection() ? $this->client->followRedirect() : $this->client->getCrawler();

        return implode("\n", $crawler->filter('.alert-text')->each(fn ($n) => trim($n->text())));
    }

    protected function produit(string $code): Produit
    {
        return $this->em()->getRepository(Produit::class)->findOneBy(['code' => $code]);
    }

    protected function carton(string $codeProduit): Conditionnement
    {
        return $this->em()->getRepository(Conditionnement::class)->findOneBy(['produit' => $this->produit($codeProduit), 'libelle' => 'Carton']);
    }

    protected function depot(): Depot
    {
        return $this->em()->getRepository(Depot::class)->findOneBy(['code' => AppFixtures::DEPOT]);
    }

    protected function grossiste(string $code): Grossiste
    {
        return $this->em()->getRepository(Grossiste::class)->findOneBy(['code' => $code]);
    }

    /** Stock disponible d'un produit (carton) sur un emplacement, relu en base. */
    protected function stockDisponible(EmplacementStock $emp, string $codeProduit): float
    {
        $this->em()->clear();

        return (float) $this->em()->createQueryBuilder()
            ->select('COALESCE(SUM(s.quantiteDisponible), 0)')
            ->from(Stock::class, 's')
            ->where('s.emplacement = :e AND s.produit = :p AND s.conditionnement = :c')
            ->setParameter('e', $emp->getId())
            ->setParameter('p', $this->produit($codeProduit)->getId())
            ->setParameter('c', $this->carton($codeProduit)->getId())
            ->getQuery()->getSingleScalarResult();
    }

    protected function emplacementDepot(): EmplacementStock
    {
        return $this->em()->getRepository(EmplacementStock::class)->findOneBy(['depot' => $this->depot(), 'typeEmplacement' => 'DEPOT']);
    }

    /** Crée une commande de cartons via le formulaire web et renvoie l'entité enregistrée. */
    protected function creerCommande(string $codeGrossiste, string $codeProduit, float $quantite): ?Commande
    {
        $this->poster('/commandes/nouveau', [
            'typeCommande' => 'GROSSISTE',
            'grossiste_id' => $this->grossiste($codeGrossiste)->getId(),
            'depot_id' => $this->depot()->getId(),
            'produit_ids' => [$this->produit($codeProduit)->getId()],
            'conditionnement_ids' => [$this->carton($codeProduit)->getId()],
            'quantites' => [$quantite],
            'prix_unitaires' => [0.01],
        ], '/commandes');

        $this->em()->clear();

        return $this->em()->getRepository(Commande::class)->findOneBy(['grossiste' => $this->grossiste($codeGrossiste)], ['id' => 'DESC']);
    }
}
