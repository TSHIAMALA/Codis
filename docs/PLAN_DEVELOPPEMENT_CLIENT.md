# Analyse de Couverture & Plan d'Adaptation CODIS (Cahier des Charges Trésor Tshims)

Ce document présente l'évaluation de la couverture actuelle du système CODIS par rapport au fonctionnement réel décrit par M. Trésor Tshims (Distribution Directe vs Indirecte, Hiérarchie ASM/DR/Cyclo, Consignation et Paiement préalable).

---

## 📊 Bilan de Couverture Actuel

L'application couvre actuellement **100% des fonctionnalités métiers requises** par le cahier des charges client.

| Axe du Cahier des Charges | Statut de Couverture | Implémentation réalisée |
| :--- | :---: | :--- |
| **1. Deux Canaux (Direct vs Indirect)** | 🟢 100% Couvert | Structuration explicite **Directe (Propre)** vs **Indirecte (Distributeur Agréé)** |
| **2. Hiérarchie Directe (ASM -> Superviseur -> DR -> Cyclo)** | 🟢 100% Couvert | Rôles et liens hiérarchiques exacts : **ASM**, **Superviseur des Ventes**, **DR**, **Cyclos (Ambulants)** |
| **3. Demande de Prélèvement & Consignation (DR)** | 🟢 100% Couvert | Module **Demande de Prélèvement** avec **double validation (Superviseur -> ASM)** |
| **4. Clôture de Journée DR (Cash & Invendus)** | 🟢 100% Couvert | Module **Clôture & Justification de Journée** (Cash encaissé + Réintégration automatique des invendus au dépôt) |
| **5. Distribution Indirecte (Paiement Préalable)** | 🟢 100% Couvert | Règle stricte : **Encaissement Caisse Obligatoire AVANT génération du Bon de Livraison** pour les Distributeurs Agréés |

---

## 🛠️ Modèle de Données & Architecture Implémentée

### Component 1 : Hiérarchie Commerciale Directe & Indirecte

#### Entité [AgentCommercial.php](file:///c:/wamp64/www/CODIS/codis_web/src/Entity/AgentCommercial.php)
- **Rôles métiers** : `ASM`, `SUPERVISEUR`, `DR`, `CYCLO`.
- **Hiérarchie directe** : Lien `superviseur_id` permettant le rattachement direct de l'agent à son supérieur hiérarchique.

#### Entité [Grossiste.php](file:///c:/wamp64/www/CODIS/codis_web/src/Entity/Grossiste.php)
- **Type de distribution** : `DISTRIBUTEUR_AGREE` (Indirecte) vs `GROSSISTE_SECTEUR` (Directe / Passif).
- **Rattachement DR** : Lien `dr_id` rattachant le grossiste au DR de son secteur.

---

### Component 2 : Workflow Distribution Directe (Consignation & Clôture Journée)

#### Entités [DemandeConsignation.php](file:///c:/wamp64/www/CODIS/codis_web/src/Entity/DemandeConsignation.php) & [DemandeConsignationLigne.php](file:///c:/wamp64/www/CODIS/codis_web/src/Entity/DemandeConsignationLigne.php)
- **Statuts du Workflow** :
  1. `SOUMISE` : Demande initiée par le DR.
  2. `VALIDEE_SUPERVISEUR` : Approbation Niveau 1 par le Superviseur des Ventes.
  3. `VALIDEE_ASM` : Approbation Niveau 2 par l'ASM (déclenche la sortie de stock du dépôt vers le DR).
  4. `CLOTUREE` : Justification du Cash encaissé et réintégration automatique des invendus au stock dépôt.

---

### Component 3 : Workflow Distribution Indirecte (Règle Paiement Cash Préalable)

#### Controller [LivraisonController.php](file:///c:/wamp64/www/CODIS/codis_web/src/Controller/LivraisonController.php)
- Verrouillage automatique : Pour tout Distributeur Agréé, le Bon de Livraison et l'expédition sont bloqués si la caisse n'a pas validé le paiement intégral préalable de la commande.
