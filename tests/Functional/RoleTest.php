<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Role;
use App\Security\MatriceAcces;
use App\Tests\AppWebTestCase;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RoleTest extends AppWebTestCase
{
    public function testAdministrateurVoitLaMatrice(): void
    {
        $this->loginAs(AppFixtures::ADMIN);
        $this->client->request('GET', '/roles');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.card-table-title', 'Rôles du système');
        self::assertSelectorExists('table.matrice-acces');
        self::assertSelectorTextContains('table.matrice-acces', 'Paiements & crédits');
    }

    public function testCaissierNaPasAccesAuxRoles(): void
    {
        $this->loginAs(AppFixtures::CAISSIER);
        $this->client->request('GET', '/roles');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministrateurGeneralRenommeUnRole(): void
    {
        $this->loginAs(AppFixtures::ADMIN);
        $role = $this->em()->getRepository(Role::class)->findOneBy(['code' => 'CAISSIER']);

        $messages = $this->poster('/roles/' . $role->getId() . '/libelle', ['libelle' => 'Caissier principal'], '/roles');

        self::assertStringContainsString('renommé', $messages);
        $this->em()->clear();
        self::assertSame('Caissier principal', $this->em()->getRepository(Role::class)->find($role->getId())->getLibelle());
    }

    public function testDroitsCalculesAvecLaHierarchie(): void
    {
        $matrice = static::getContainer()->get(MatriceAcces::class);

        $caissier = $matrice->droitsDuRole('CAISSIER');
        self::assertSame('complet', $caissier['Paiements & crédits']['niveau']);
        self::assertSame('aucun', $caissier['Livraisons & transit']['niveau']);
        self::assertSame('partiel', $caissier['Stocks']['niveau']);

        // ADMIN_PAYS hérite des rôles métier, mais pas de l'audit
        $adminPays = $matrice->droitsDuRole('ADMIN_PAYS');
        self::assertSame('complet', $adminPays['Livraisons & transit']['niveau']);
        self::assertSame('aucun', $adminPays['Audit & traçabilité']['niveau']);

        self::assertFalse($matrice->aAccesWeb('GROSSISTE_USER'));
    }

    /** La matrice affichée doit suivre les #[IsGranted] d'accès des contrôleurs : sinon elle ment. */
    public function testMatriceAligneeSurLesControleurs(): void
    {
        foreach (MatriceAcces::MODULES as $m) {
            $classe = new \ReflectionClass($m['controleur']);
            $attributs = $classe->getAttributes(IsGranted::class);
            if (!$attributs) {
                // Contrôle posé méthode par méthode : on prend celui de la page d'accueil du module (route '')
                foreach ($classe->getMethods() as $methode) {
                    foreach ($methode->getAttributes(Route::class) as $route) {
                        if (($route->getArguments()[0] ?? $route->getArguments()['path'] ?? null) === '') {
                            $attributs = $methode->getAttributes(IsGranted::class);
                        }
                    }
                }
            }

            self::assertNotEmpty($attributs, $m['module'] . ' : aucun #[IsGranted] trouvé');
            self::assertSame($m['actions'][0]['roles'][0], $attributs[0]->getArguments()[0], $m['module'] . ' : rôle d\'accès différent du contrôleur');
        }
    }
}
