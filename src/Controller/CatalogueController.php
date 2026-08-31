<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Entity\CategorieProduit;
use App\Entity\Marque;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/catalogue')]
class CatalogueController extends AbstractController
{
    #[Route('', name: 'app_catalogue_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $produits = $em->getRepository(Produit::class)->findAll();
        $categories = $em->getRepository(CategorieProduit::class)->findAll();
        $marques = $em->getRepository(Marque::class)->findAll();

        return $this->render('catalogue/index.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'marques' => $marques,
        ]);
    }

    #[Route('/categorie/nouveau', name: 'app_categorie_nouveau', methods: ['POST'])]
    public function nouveauCategorie(Request $request, EntityManagerInterface $em): Response
    {
        $libelle = trim($request->request->get('libelle', ''));
        $code = trim($request->request->get('code', ''));

        if (empty($libelle)) {
            $this->addFlash('danger', 'Veuillez renseigner le libellé de la catégorie.');
            return $this->redirectToRoute('app_catalogue_index');
        }

        if (empty($code)) {
            $code = 'CAT-' . strtoupper(substr(uniqid(), -5));
        }

        $cat = new CategorieProduit();
        $cat->setCode($code);
        $cat->setLibelle($libelle);
        $cat->setActif(true);

        $em->persist($cat);
        $em->flush();

        $this->addFlash('success', 'Catégorie de produit "' . $cat->getLibelle() . '" créée avec succès !');
        return $this->redirectToRoute('app_catalogue_index');
    }

    #[Route('/produit/nouveau', name: 'app_produit_nouveau', methods: ['POST'])]
    public function nouveauProduit(Request $request, EntityManagerInterface $em): Response
    {
        $nom = trim($request->request->get('nom', ''));
        $code = trim($request->request->get('code', ''));
        $description = trim($request->request->get('description', ''));
        $categorieId = $request->request->get('categorie_id');
        $marqueId = $request->request->get('marque_id');

        $categorie = $categorieId ? $em->getRepository(CategorieProduit::class)->find($categorieId) : null;
        $marque = $marqueId ? $em->getRepository(Marque::class)->find($marqueId) : null;

        if (empty($nom) || !$categorie || !$marque) {
            $this->addFlash('danger', 'Veuillez remplir les champs obligatoires (*) : nom, catégorie et marque.');
            return $this->redirectToRoute('app_catalogue_index');
        }

        if (empty($code)) {
            $code = 'PRD-' . strtoupper(substr(uniqid(), -5));
        }

        $p = new Produit();
        $p->setCode($code);
        $p->setNom($nom);
        $p->setDescription($description);
        $p->setCategorie($categorie);
        $p->setMarque($marque);
        $p->setActif(true);

        $em->persist($p);
        $em->flush();

        $this->addFlash('success', 'Produit "' . $p->getNom() . '" créé avec succès dans le catalogue !');
        return $this->redirectToRoute('app_catalogue_index');
    }
}
