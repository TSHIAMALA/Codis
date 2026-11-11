<?php

namespace App\Security;

use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Matrice « rôle × module » affichée dans Administration → Rôles & Permissions.
 *
 * Chaque action liste les rôles Symfony qui l'autorisent (l'un OU l'autre suffit) ; la hiérarchie
 * (security.yaml) est appliquée pour savoir ce que chaque rôle obtient. Les rôles d'accès aux modules
 * reprennent les attributs #[IsGranted] des contrôleurs : MatriceAccesTest vérifie qu'ils restent alignés.
 */
class MatriceAcces
{
    /**
     * @var list<array{module: string, icone: string, controleur: class-string, actions: list<array{libelle: string, roles: list<string>, note?: string}>}>
     */
    public const MODULES = [
        ['module' => 'Vue Générale', 'icone' => 'fa-chart-pie', 'controleur' => \App\Controller\DashboardController::class, 'actions' => [
            ['libelle' => 'Consulter le tableau de bord', 'roles' => ['ROLE_STAFF']],
        ]],
        ['module' => 'Réseau commercial', 'icone' => 'fa-network-wired', 'controleur' => \App\Controller\CommercialController::class, 'actions' => [
            ['libelle' => 'Gérer grossistes, détaillants et agents', 'roles' => ['ROLE_DELEGUE']],
        ]],
        ['module' => 'Grille des tarifs', 'icone' => 'fa-tags', 'controleur' => \App\Controller\TarifController::class, 'actions' => [
            ['libelle' => 'Gérer les tarifs', 'roles' => ['ROLE_ADMIN_PAYS']],
        ]],
        ['module' => 'Consignations DR', 'icone' => 'fa-clipboard-check', 'controleur' => \App\Controller\ConsignationController::class, 'actions' => [
            ['libelle' => 'Consulter', 'roles' => ['ROLE_STAFF']],
            ['libelle' => 'Demander un prélèvement', 'roles' => ['ROLE_AGENT_COMMERCIAL']],
            ['libelle' => 'Valider N1 / N2', 'roles' => ['ROLE_ADMIN_PAYS'], 'note' => 'Aussi le Superviseur du DR (N1) et un ASM (N1 ou N2), selon la fiche agent ; le valideur N2 doit être différent du N1.'],
            ['libelle' => 'Clôturer une tournée', 'roles' => ['ROLE_ADMIN_PAYS', 'ROLE_GESTIONNAIRE_DEPOT'], 'note' => 'Aussi le DR concerné et sa hiérarchie (Superviseur, ASM).'],
        ]],
        ['module' => 'Commandes clients', 'icone' => 'fa-file-invoice-dollar', 'controleur' => \App\Controller\CommandeController::class, 'actions' => [
            ['libelle' => 'Créer et modifier', 'roles' => ['ROLE_AGENT_COMMERCIAL']],
            ['libelle' => 'Valider, générer le BL, annuler', 'roles' => ['ROLE_DELEGUE']],
        ]],
        ['module' => 'Livraisons & transit', 'icone' => 'fa-truck-fast', 'controleur' => \App\Controller\LivraisonController::class, 'actions' => [
            ['libelle' => 'Expédier et confirmer', 'roles' => ['ROLE_GESTIONNAIRE_DEPOT']],
        ]],
        ['module' => 'Paiements & crédits', 'icone' => 'fa-credit-card', 'controleur' => \App\Controller\PaiementController::class, 'actions' => [
            ['libelle' => 'Encaisser et suivre les crédits', 'roles' => ['ROLE_CAISSIER']],
        ]],
        ['module' => 'Catalogue & produits', 'icone' => 'fa-boxes-packing', 'controleur' => \App\Controller\CatalogueController::class, 'actions' => [
            ['libelle' => 'Consulter', 'roles' => ['ROLE_STAFF']],
            ['libelle' => 'Créer produits et catégories', 'roles' => ['ROLE_ADMIN_PAYS']],
        ]],
        ['module' => 'Production & usines', 'icone' => 'fa-industry', 'controleur' => \App\Controller\ProductionController::class, 'actions' => [
            ['libelle' => 'Gérer les ordres de fabrication', 'roles' => ['ROLE_RESPONSABLE_USINE']],
        ]],
        ['module' => 'Stocks', 'icone' => 'fa-cubes', 'controleur' => \App\Controller\StockController::class, 'actions' => [
            ['libelle' => 'Consulter', 'roles' => ['ROLE_STAFF']],
            ['libelle' => 'Expédier un transfert', 'roles' => ['ROLE_RESPONSABLE_USINE', 'ROLE_GESTIONNAIRE_DEPOT']],
            ['libelle' => 'Réceptionner un transfert', 'roles' => ['ROLE_GESTIONNAIRE_DEPOT']],
        ]],
        ['module' => 'Utilisateurs & rôles', 'icone' => 'fa-users-gear', 'controleur' => \App\Controller\UtilisateurController::class, 'actions' => [
            ['libelle' => 'Gérer les comptes', 'roles' => ['ROLE_ADMIN_PAYS'], 'note' => 'Seul un Administrateur général peut gérer un autre Administrateur général.'],
        ]],
        ['module' => 'Audit & traçabilité', 'icone' => 'fa-clock-rotate-left', 'controleur' => \App\Controller\AuditController::class, 'actions' => [
            ['libelle' => 'Consulter le journal', 'roles' => ['ROLE_SUPER_ADMIN']],
        ]],
    ];

    public function __construct(private RoleHierarchyInterface $roleHierarchy) {}

    /**
     * Pour un code de rôle CODIS (ex. CAISSIER) : par module, les actions autorisées.
     *
     * @return array<string, array{niveau: 'complet'|'partiel'|'aucun', actions: list<string>}>
     */
    public function droitsDuRole(string $code): array
    {
        $atteignables = $this->roleHierarchy->getReachableRoleNames([self::roleSymfony($code)]);
        $droits = [];
        foreach (self::MODULES as $m) {
            $autorisees = [];
            foreach ($m['actions'] as $a) {
                if (array_intersect($a['roles'], $atteignables)) {
                    $autorisees[] = $a['libelle'];
                }
            }
            $droits[$m['module']] = [
                'niveau' => match (\count($autorisees)) {
                    0 => 'aucun',
                    \count($m['actions']) => 'complet',
                    default => 'partiel',
                },
                'actions' => $autorisees,
            ];
        }

        return $droits;
    }

    /** Même convention que Utilisateur::getRoles() : le code en base peut omettre le préfixe ROLE_. */
    public static function roleSymfony(string $code): string
    {
        return str_starts_with($code, 'ROLE_') ? $code : 'ROLE_' . $code;
    }

    /** Un rôle sans ROLE_STAFF n'ouvre aucun écran web (ex. utilisateur grossiste : application mobile uniquement). */
    public function aAccesWeb(string $code): bool
    {
        return \in_array('ROLE_STAFF', $this->roleHierarchy->getReachableRoleNames([self::roleSymfony($code)]), true);
    }
}
