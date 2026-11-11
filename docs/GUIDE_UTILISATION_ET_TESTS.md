# Guide d'Utilisation & Procédures de Test CODIS

Ce document constitue le guide de prise en main et de test pas à pas de l'ensemble des modules de l'application CODIS.

---

## 🔑 1. Accès & Rôles

- **URL de l'application** : `http://localhost/CODIS/codis_web/public/index.php`
- **Page de connexion** : `.../login`. Toutes les pages (et toute l'API sauf `/api/login`) exigent une connexion.
- **Compte administrateur** : `admin@codis.com`. Le mot de passe n'est jamais écrit dans la documentation ; demandez-le à l'administrateur.
- **Nouveaux utilisateurs** : « Demander un accès » depuis la page de connexion. Le compte reste inactif jusqu'à ce qu'un administrateur l'accepte et lui attribue un rôle (*Administration ➔ Utilisateurs & Accès*).
- **Secrets** (`APP_SECRET`, `JWT_PASSPHRASE`) : uniquement dans `.env.local` (non versionné).

### Matrice des accès

| Rôle | Modules accessibles |
| :--- | :--- |
| **Administrateur général** (`SUPER_ADMIN`) | Tout, y compris Audit et la gestion des autres administrateurs généraux |
| **Administrateur pays** (`ADMIN_PAYS`) | Tout sauf Audit ; grille tarifaire, utilisateurs, validations N1/N2 des consignations |
| **Délégué commercial** (`DELEGUE`) | Réseau commercial, commandes (validation, annulation), consignations |
| **Agent commercial** (`AGENT_COMMERCIAL`) | Commandes (création), consignations (demande), catalogue, stocks (lecture) |
| **Gestionnaire dépôt** (`GESTIONNAIRE_DEPOT`) | Livraisons, clôture des consignations, transferts (expédition et réception), catalogue, stocks |
| **Caissier** (`CAISSIER`) | Paiements & crédits, catalogue, stocks |
| **Responsable usine** (`RESPONSABLE_USINE`) | Production, transferts (expédition depuis l'usine), catalogue, stocks |
| **Utilisateur grossiste** (`GROSSISTE_USER`) | Pas d'accès à l'interface web (application mobile uniquement) |

**Consignations DR** : le niveau 1 est validé par le Superviseur du DR (ou un ASM), le niveau 2 par un ASM *différent* du valideur N1. Les fonctions (ASM, Superviseur, DR, Cyclo) et le supérieur hiérarchique se définissent sur la fiche Agent (*Réseau commercial ➔ Agents*) : un DR est rattaché à un Superviseur, un Superviseur à un ASM, un Cyclo à un DR.

**Prix** : le prix de chaque ligne de commande est toujours calculé côté serveur à partir de la grille tarifaire (zone, puis région, puis national). Une commande dont un produit n'a pas de tarif actif est refusée.

---

## 🧪 2. Procédures de Test Pas à Pas

### Scenario A : Test de Production & Alimentation du Stock Usine
1. Allez dans **Industrie & Stocks** ➔ **Production & Usines** (`/production`).
2. Cliquez sur **`+ Créer Ordre de Fabrication`**.
3. Choisissez une Usine, un Produit, et saisissez une Quantité (ex: `5000`).
4. Cliquez sur **Créer Ordre de Fabrication** (le statut est `BROUILLON`).
5. Cliquez sur **`Valider`** sur la ligne créée.
6. *Vérification* : Le statut passe à `VALIDEE` et les 5000 unités apparaissent dans les stocks usine (`/stocks`).

---

### Scenario B : Test de Distribution Directe (Prélèvement DR, Double Validation, Clôture Journée)
1. Allez dans **Ventes & Logistique** ➔ **Consignations & Tournées DR** (`/consignations`).
2. Cliquez sur **`+ Nouvelle Demande Prélèvement`**.
3. Sélectionnez le DR (*Patrick ILUNGA*), le Dépôt Source, le Produit et mettez la Quantité (ex: `150`).
4. Cliquez sur **Soumettre pour Validation**.
5. Cliquez sur **`Valider Superviseur`** (Approbation Niveau 1).
6. Cliquez sur **`Valider ASM`** (Approbation Niveau 2 ➔ le stock sort du dépôt vers le DR).
7. Cliquez sur **`Clôture Journée`** :
   - Saisissez le Cash encaisse (ex: `1200.00 $`).
   - Saisissez les invendus (ex: `20` unités).
8. *Vérification* : Le statut passe à `CLOTUREE`, le cash est justifié et les 20 unités invendues sont automatiquement réintégrées dans le stock du dépôt.

---

### Scenario C : Test de Distribution Indirecte (Paiement Caisse Obligatoire)
1. Allez dans **Ventes & Logistique** ➔ **Livraisons & Transit** (`/livraisons`).
2. Tentez de cliquer sur **`Expédier`** pour un Bon de Livraison d'un Distributeur Agréé non réglé.
3. *Vérification* : Le système bloque l'expédition et affiche un message d'alerte indiquant que le versement préalable à la caisse est obligatoire.
4. Allez dans **Paiements & Crédits** (`/paiements`), cliquez sur **`Enregistrer un Règlement`** et validez le paiement de la commande.
5. Retournez sur `/livraisons` et cliquez sur **`Expédier`** : la livraison est débloquée et s'exécute avec succès !

---

### Scenario D : Consultation de la Synthèse des Stocks & Carte Régionale
1. Allez sur le **Tableau de Bord** (`/dashboard`) pour observer la **Répartition des Stocks par Région** (progress bars %).
2. Allez dans **Industrie & Stocks** ➔ **Synthèse par Produit** (`/stocks?tab=synthese`) pour voir le stock consolidé cumulé par produit sur tous les sites.
