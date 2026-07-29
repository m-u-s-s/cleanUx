# Analyse concurrentielle — Inscription côté provider (chauffeur) : Uber & Heetch

> Compte-rendu détaillé, étape par étape, des parcours d'inscription chauffeur d'Uber et de Heetch :
> informations demandées, vérifications effectuées, et **comment** elles sont effectuées.
> Rédigé le 2026-07-29 en préparation de la refonte du register mobile provider de cleanUx.

---

## 1. Uber — parcours d'inscription chauffeur

### Étape 1 — Création du compte (≈ 2 minutes)

| Info demandée | Détail |
|---|---|
| Nom, prénom | Saisie libre |
| Adresse email | Vérifiée par lien de confirmation |
| Numéro de téléphone | **Vérifié immédiatement par code OTP SMS** |
| Ville d'exercice | Détermine les exigences réglementaires du marché local |
| Mot de passe | Création du compte "driver" (distinct du compte passager) |

Le compte est créé **avant** toute vérification lourde : Uber capture le lead d'abord, vérifie ensuite.
Le chauffeur atterrit immédiatement dans un tableau de bord qui liste les documents manquants.

### Étape 2 — Informations légales et réglementaires (selon le pays)

**France (VTC) :**
- Carte professionnelle VTC (obligatoire, non négociable) — délivrée après examen, inscrite au **registre REVTC**.
- Extrait Kbis ou avis de situation SIRENE (auto-entrepreneur/société).
- Extrait de casier judiciaire (bulletin n°3) **de moins de 3 mois**.
- Permis B depuis plus de 3 ans.

**USA :**
- Social Security Number (SSN) — sert de clé pour le background check.
- Permis valide ≥ 1 an (3 ans si moins de 25 ans).

### Étape 3 — Upload des documents (in-app, photo par caméra)

Documents demandés (France) :

1. Pièce d'identité (CNI, passeport ou titre de séjour)
2. Permis de conduire recto/verso
3. Carte VTC recto/verso
4. Carte grise du véhicule
5. Attestation d'assurance du véhicule avec mention « transport de personnes à titre onéreux »
6. Attestation RC Professionnelle
7. Kbis / avis SIRENE
8. RIB (versement des gains)
9. Photo de profil — contraintes affichées : visage entièrement visible, pas de lunettes de soleil, fond neutre

**Mécanique UX clé** : chaque document a son propre **statut individuel** visible dans l'app
(`en attente` / `en cours de vérification` / `approuvé` / `rejeté + raison précise`).
Un rejet notifie in-app avec le motif exact et permet le **re-upload immédiat**.

### Étape 4 — Vérifications effectuées, et comment

| Vérification | Comment elle est effectuée | Délai |
|---|---|---|
| **Revue documentaire** | Combinaison OCR automatique + revue humaine. Contrôle de lisibilité, validité, cohérence des noms entre documents. | 1 à 3 jours ouvrés |
| **Carte VTC** | Croisement avec le registre officiel **REVTC** (France). | Inclus dans la revue |
| **Background check** | Sous-traité à **Checkr** (USA) : vérification d'identité (SSN trace), antécédents routiers (Motor Vehicle Record), casier judiciaire multi-juridictions. Encadré par le FCRA. En France : casier B3 + registres officiels. | 3 à 10 jours ouvrés (médiane ~5) |
| **Monitoring continu** | Le background check n'est pas un one-shot : Checkr surveille en continu les nouveaux délits et alerte Uber (re-vérification annuelle + alertes temps réel). | Permanent |
| **Real-Time ID Check** (biométrie) | Selfie demandé au chauffeur, comparé à la photo de profil vérifiée via reconnaissance faciale (**Microsoft Face API** : détection de visage puis comparaison de *faceprints* biométriques). Déclenché à l'inscription **puis aléatoirement en cours d'activité**, y compris avant de se mettre en ligne. Mismatch → blocage temporaire du compte pendant investigation. | Temps réel (secondes) |
| **Inspection véhicule** | Selon le marché : approbation sur documents seuls, ou inspection physique en garage agréé / centre "Greenlight". | Variable |

### Étape 5 — Activation

- Durée totale du funnel : **5 à 14 jours** entre la soumission et la première course.
- L'app affiche en permanence l'état d'avancement du dossier ; notifications push à chaque changement de statut.
- Le compte reste utilisable en **mode restreint** pendant l'examen (accès au dashboard, aux ressources de formation, au suivi du dossier — pas aux courses).

---

## 2. Heetch — parcours d'inscription chauffeur (France / Belgique)

### Étape 1 — Formulaire court (web `join.heetch.com` ou app)

| Info demandée | Détail |
|---|---|
| Ville d'exercice | Détermine le marché et les exigences |
| Nom, prénom | Saisie libre |
| Email | Compte professionnel |
| Téléphone | Support de l'OTP |
| **Question filtrante** : « Avez-vous déjà la carte VTC ? » | Qualifie le lead d'entrée de jeu : sans carte VTC, réorientation vers les formations partenaires au lieu d'un dossier voué à l'échec |

### Étape 2 — Vérification téléphone + mot de passe

- Un **code de validation SMS (OTP)** est envoyé ; sa saisie confirme l'inscription.
- Création du mot de passe du compte professionnel.

### Étape 3 — Upload des documents

L'app/le site guide le chauffeur, document par document, avec une checklist :

1. Pièce d'identité en cours de validité (CNI, passeport, titre de séjour)
2. Permis de conduire **recto/verso** (permis B ≥ 3 ans)
3. Photo de profil claire
4. Carte VTC **recto/verso**
5. Extrait Kbis ou avis de situation SIRENE
6. Attestation d'assurance RC Professionnelle
7. Attestation d'assurance du véhicule **avec mention transport de personnes**
8. Carte grise du véhicule
9. IBAN (versement des gains ; pour les gestionnaires de flotte : IBAN de la société)

### Étape 4 — Vérification et activation

| Vérification | Comment elle est effectuée | Délai |
|---|---|---|
| **Contrôle de conformité documentaire** | **Revue manuelle par l'équipe Heetch** : validité, lisibilité, cohérence, dates d'expiration, mention « transport de personnes » sur l'assurance. | 24 à 48 h ouvrées |
| **Carte VTC** | Vérification de validité (prérequis non négociable, casier vierge requis). | Inclus |
| **Immatriculation société** | Contrôle du Kbis / avis SIRENE contre les registres officiels. | Inclus |
| **Activation** | Compte professionnel activé **sous 48 h** si le dossier est complet ; sinon relance ciblée sur les pièces manquantes/refusées. | < 48 h |

### Étape 5 — Contrôles continus post-activation

- **Contrôles d'identité aléatoires** en cours d'activité : le chauffeur connecté peut se voir demander
  en temps réel des documents officiels ou des éléments de profil.
- Documents à date d'expiration (assurance, carte VTC) re-demandés à échéance ; compte suspendu si non renouvelés.

---

## 3. Tableau comparatif synthétique

| Dimension | Uber | Heetch |
|---|---|---|
| Compte initial | Nom, email, tel + OTP, ville, mot de passe | Ville, nom, email, tel + OTP, mot de passe |
| Question filtrante métier | Non (dashboard liste les manques) | **Oui** (« avez-vous la carte VTC ? ») |
| Nombre de documents | ~9 | ~9 |
| Vérification documentaire | OCR + humain, 1-3 j, statut par document + motif de rejet | Manuelle par l'équipe, 24-48 h |
| Background check | **Checkr** (USA), FCRA, monitoring continu | Casier vierge requis (déclaratif + carte VTC comme proxy) |
| Biométrie | **Real-Time ID Check** — selfie vs faceprint (Microsoft Face API), à l'inscription + aléatoire | Contrôles d'identité aléatoires (documents/profil temps réel) |
| Registres officiels | REVTC, SIRENE | SIRENE, registre VTC |
| Paiement | RIB à l'inscription | IBAN à l'inscription |
| Délai d'activation | 5-14 jours | **< 48 h** |
| Mode pendant examen | Compte restreint + suivi du dossier | Compte en attente + relances ciblées |

## 4. Les 7 patterns communs à retenir pour cleanUx

1. **Funnel progressif** : compte minimal en < 2 min (nom/tel/email + OTP), les documents viennent *après* la création du compte — jamais avant. On capture le lead, puis on vérifie.
2. **OTP SMS en première étape** : le téléphone est vérifié avant tout le reste — c'est l'identifiant opérationnel du provider.
3. **Checklist de documents avec statut unitaire** : chaque pièce a son cycle de vie (`pending → in_review → approved/rejected + motif`), re-upload immédiat en cas de rejet, photo prise in-app à la caméra.
4. **Vérification d'identité biométrique** : selfie + liveness comparé au document d'identité (et re-déclenché aléatoirement après activation).
5. **Croisement avec les registres officiels** : les numéros déclarés (SIRET/Kbis, carte pro) sont vérifiés contre les sources autoritatives, pas seulement collectés.
6. **Mode restreint pendant l'examen** : le compte est utilisable immédiatement (suivi de dossier, formation, profil) — l'activation commerciale est asynchrone, notifiée par push.
7. **Re-vérification continue** : l'inscription n'est pas un événement mais un état — monitoring, expirations de documents, contrôles aléatoires.

---

## Sources

- [Gridwise — How to Become an Uber Driver in 2026](https://gridwise.io/blog/how-to-become-an-uber-driver)
- [Gridwise — Uber Background Check](https://gridwise.io/blog/uber-background-check)
- [Insurance Navy — Uber Driver Requirements 2026](https://www.insurancenavy.com/uber-driver-requirements/)
- [GigMoneyTips — Uber Driver Requirements 2026](https://gigmoneytips.com/uber-driver-requirements/)
- [Uber Engineering — Real-Time ID Check](https://www.uber.com/blog/real-time-id-check/)
- [Uber — How does Uber verify my identity?](https://help.uber.com/en/driving-and-delivering/article/how-does-uber-verify-my-identity?nodeId=aa821486-c8d1-42b7-b784-2fc24eb85f93)
- [Uber Newsroom — Background Checks and Safety](https://www.uber.com/us/en/newsroom/background-checks/)
- [Uber France — Vérification d'identité en temps réel](https://www.uber.com/fr/blog/verification-didentite-en-temps-reel-la-nouvelle-fonctionnalite-pour-lutter-contre-le-partage-de-compte)
- [BVTC — Inscription Uber VTC : toutes les étapes](https://bvtc.fr/bible-du-vtc/exercice-metier-vtc/uber-vtc-inscription/)
- [Legalstart — Devenir chauffeur Heetch](https://www.legalstart.fr/fiches-pratiques/chauffeur-vtc-transport/devenir-chauffeur-heetch/)
- [Captain Contrat — Devenir chauffeur Heetch](https://www.captaincontrat.com/exercer-un-metier/entreprise-de-transport/devenir-chauffeur-heetch-comment-faire)
- [Heetch Support — Que faut-il faire pour s'inscrire chez Heetch ?](https://support.heetch.com/fr/fr/driver/qqwf-sur-l-inscription/m2ng-que-faut-il-faire-pour-s-inscrire-chez-heetch)
- [Heetch Support — Comment faire vérifier mes documents ?](https://support.heetch.com/fr/fr/driver/zqvl-sur-mon-compte/fg2-comment-faire-verifier-mes-documents)
- [HeetchPro — La sécurité sur Heetch](https://www.heetchpro.com/articles/securite-appli-priorite-2021-evolution)
- [Propulse by CA — Devenir chauffeur Heetch, guide 2026](https://propulsebyca.fr/idees-business/vtc/heetch)
