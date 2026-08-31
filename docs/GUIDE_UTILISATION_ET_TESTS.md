# Guide d'Utilisation & Procédures de Test CODIS

Ce document constitue le guide de prise en main et de test pas à pas de l'ensemble des modules de l'application CODIS.

---

## 🔑 1. Identifiants d'Accès Administration

- **URL de l'application** : `http://localhost:803`
- **Page de connexion** : `http://localhost:803/login`
- **Identifiants Admin par défaut** :
  - **Email / Login** : `admin@codis.com` *(ou username `admin`)*
  - **Mot de Passe** : `adminpassword`

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
