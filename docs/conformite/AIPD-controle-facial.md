# AIPD — Vérification faciale des prestataires

**Analyse d'impact relative à la protection des données** (RGPD, art. 35)
Traitement : contrôle d'identité par comparaison faciale des prestataires de la plateforme CleanUx.

| | |
|---|---|
| Version | 1.0 |
| Rédigée le | 2026-08-16 |
| Version du consentement couverte | `1.0` (`FACE_CHECK_CONSENT_VERSION`) |
| Statut | **PROJET — non signé.** Voir § 12. |

> **CE DOCUMENT N'EST PAS ENCORE UNE AIPD VALIDE.** Il en contient la matière technique complète —
> ce que le code fait réellement, mesuré dans le code et pas dans une intention — mais l'AIPD n'est
> opposable qu'une fois **signée par le responsable du traitement** après avis du **DPO**, et après
> renseignement des champs marqués **⟦À COMPLÉTER⟧**. Trois de ces champs (identité du responsable,
> avis du DPO, consultation éventuelle de l'APD) ne peuvent pas être remplis par l'équipe technique.
>
> **Tant que ce document n'est pas signé, le module ne doit pas être activé en production.**
> Il est livré éteint pour tout métier qui ne coche pas `requires_face_check` ; les deux métiers
> cochés par défaut (garde d'enfants, gardiennage) ne doivent l'être en production qu'après cette
> signature.

---

## 1. Pourquoi une AIPD est obligatoire ici

L'article 35 §3 impose une AIPD notamment en cas de « traitement à grande échelle de catégories
particulières de données ». Un gabarit facial est une **donnée biométrique aux fins d'identifier une
personne physique de manière unique** : article 4 §14, donc catégorie particulière au titre de
l'article 9 §1.

Les lignes directrices WP248 du G29 retiennent neuf critères ; ce traitement en coche quatre, et
deux suffisent à rendre l'AIPD obligatoire :

1. données sensibles (art. 9) ;
2. évaluation systématique de personnes ;
3. décision produisant un effet sur la personne (suspension de l'accès au travail) ;
4. usage d'une technologie innovante.

**Conclusion : AIPD obligatoire, préalablement à la mise en service.**

---

## 2. Responsable du traitement et acteurs

| Rôle | Identité |
|---|---|
| Responsable du traitement | ⟦À COMPLÉTER : dénomination légale, n° BCE, siège⟧ |
| Délégué à la protection des données | ⟦À COMPLÉTER : nom, e-mail⟧ |
| Sous-traitant (comparaison faciale) | Onfido — **uniquement si `FACE_CHECK_PROVIDER=onfido`**. Par défaut, `mock` : aucune donnée ne sort de l'infrastructure. |
| Sous-traitant (hébergement) | ⟦À COMPLÉTER : hébergeur, localisation des serveurs⟧ |
| Personnes concernées | Prestataires (indépendants et salariés de sociétés prestataires) exerçant un métier coché `requires_face_check`. |

**Contrat de sous-traitance (art. 28)** : ⟦À COMPLÉTER — un DPA signé avec Onfido est requis AVANT
de basculer `FACE_CHECK_PROVIDER` sur `onfido`. Tant que le bouchon est actif, aucun transfert n'a
lieu.⟧

---

## 3. Description du traitement

### 3.1 Finalité

Vérifier que la personne qui se présente chez un client est bien celle qui a été contrôlée à
l'inscription. La fraude visée est **le prêt ou la location de compte** : une personne inscrite,
contrôlée et assurée cède son accès à une autre, qui intervient sans qu'aucune vérification n'ait
porté sur elle. C'est le risque documenté publiquement par Bolt (« tenant drivers », après un cas
mortel d'usurpation en Afrique du Sud) et par les plateformes de livraison britanniques en 2025.

**Finalité unique.** Le module ne sert ni au pointage, ni au contrôle du temps de travail, ni à la
mesure de performance, ni à l'authentification (le mot de passe et la 2FA restent les seuls
facteurs d'accès au compte). Toute réutilisation à une autre fin serait un traitement distinct
exigeant sa propre base légale.

### 3.2 Opérations

| # | Opération | Quand | Donnée |
|---|---|---|---|
| 1 | Enrôlement d'un visage de référence | Une fois, avant la première intervention | Photographie du visage |
| 2 | Appariement automatique visage ↔ portrait de la pièce d'identité | Après l'enrôlement, en tâche de fond | Les deux images, un score |
| 3 | Contrôle périodique par selfie en direct | Avant de passer en ligne / d'accepter / de partir chez un client | Photographie du visage, résultat de vivacité |
| 4 | Revue humaine | Sur mismatch, échec répété, signalement | Consultation des images par un administrateur |

### 3.3 Cadence — et pourquoi elle est aléatoire

Au plus un contrôle par 24 h, au moins un tous les 3 jours ; **le moment exact est tiré au sort par
le serveur** (`random_int`) au moment du contrôle précédent, et **n'est renvoyé par aucune réponse
d'API**.

Ce choix sert la **minimisation** autant que l'efficacité : une cadence prévisible obligerait à
contrôler beaucoup plus souvent pour obtenir la même assurance. On traite moins de données
biométriques, pas plus, en rendant le moment imprévisible.

Contrôles supplémentaires hors cadence : nouvel appareil, échecs récents, abandons répétés,
forçage administrateur.

---

## 4. Bases légales

### 4.1 Article 6 — licéité

**Article 6 §1 b) — exécution du contrat**, pour la relation avec le prestataire : la plateforme
s'engage contractuellement, envers les clients, sur l'identité des intervenants.
Subsidiairement **6 §1 f) — intérêt légitime** (sécurité des clients et des tiers).

### 4.2 Article 9 — levée de l'interdiction

**Article 9 §2 a) — consentement explicite.**

**Nous documentons ici la fragilité de cette base**, plutôt que de la passer sous silence : le CEPD
rappelle qu'un consentement donné dans une relation de dépendance économique n'est pas librement
donné. Le refus a ici une conséquence directe — l'impossibilité d'exercer les métiers concernés.

Quatre mesures atténuent cette fragilité, et elles sont **implémentées, pas promises** :

1. **Le périmètre est étroit et justifié.** Seuls les métiers où la personne se retrouve seule au
   domicile d'un client, y compris auprès de mineurs, sont concernés — le même critère que celui
   qui impose déjà un extrait de casier judiciaire (`config/onboarding_documents.php`). Un
   prestataire refusant le contrôle conserve l'accès à tous les autres métiers de la plateforme.
2. **Le consentement est granulaire, horodaté et versionné** (`consent_given_at`,
   `consent_version`). Un changement du texte impose une nouvelle version, et un consentement
   ancien ne couvre pas le nouveau texte.
3. **Le retrait est effectif et immédiat** : `POST /provider/face-check/consent/withdraw` supprime
   le fichier du visage de référence du disque, pas seulement la ligne en base.
4. **Aucune décision automatisée définitive** : voir § 7.

> ⟦AVIS DU DPO REQUIS : confirmer que 9 §2 a) est retenu, ou lui préférer une autre condition.
> Si le DPO estime le consentement non librement donné, le module doit être suspendu, pas
> reformulé.⟧

---

## 5. Données traitées

| Donnée | Nature | Où | Protection |
|---|---|---|---|
| Visage de référence | Biométrique (art. 9) | Disque `private`, hors racine web | **Chiffré** avec la clé applicative (`FaceImageStore`) |
| Selfies de contrôle | Biométrique (art. 9) | Idem | Idem, **purgés au bout de 30 jours** |
| Score de similarité, verdict, vivacité | Donnée dérivée, non biométrique | Base | — |
| Empreinte SHA-256 de l'image de référence | Intégrité | Base | — |
| Empreinte SHA-256 de l'adresse IP | Sécurité | Base | Hachée, jamais en clair |
| Nom du jeton d'appareil | Sécurité | Base | — |
| Identifiant Onfido | Identifiant tiers | Base | Chiffré (`metadata`) |

**Ne sont PAS traités** : aucun gabarit biométrique n'est calculé ou stocké par la plateforme
elle-même ; aucune donnée de santé, d'origine, ni d'opinion n'est déduite ; aucune reconnaissance
faciale « un contre plusieurs » (identification dans une foule) n'est effectuée — le module compare
**une** image à **une** référence connue.

---

## 6. Destinataires, transferts et durées

**Destinataires**
- Les administrateurs de la plateforme, via URL signée valable **10 minutes**, avec contrôle
  d'administrateur en dur **et journalisation de chaque consultation**
  (`face_check.reference_viewed` / `selfie_viewed`).
- **Ni le client, ni la société prestataire employeuse ne voient jamais les images.** L'espace
  société affiche l'état de conformité, pas les visages.
- Onfido, uniquement si le fournisseur réel est activé.

**Transferts hors UE** : aucun avec le fournisseur par défaut. Avec Onfido, `face_check.onfido.base_url`
pointe par défaut sur `api.eu.onfido.com` — **région UE**. ⟦À COMPLÉTER si une autre région est
retenue : mécanisme de transfert au titre du chapitre V.⟧

**Durées de conservation**

| Donnée | Durée | Mécanisme |
|---|---|---|
| Visage de référence | Durée de vie du compte | Supprimé au retrait du consentement et à l'effacement RGPD |
| Selfies de contrôle | **30 jours** (réglable) | `RetentionPolicyService` — supprime **le fichier**, puis vide la colonne |
| Verdict, score, horodatage | Durée de vie du compte | Ne porte aucun visage ; sert à expliquer une décision a posteriori |
| Incidents | Durée de vie du compte | Idem |

---

## 7. Décision automatisée (art. 22)

Le module refuse automatiquement l'accès aux missions. **Ce n'est pas une décision au sens de
l'article 22 §1 sans intervention humaine**, pour trois raisons vérifiables dans le code :

1. **Aucun blocage définitif n'est prononcé par la machine.** Le blocage est un état réversible, et
   **seul un administrateur peut le lever** (`FaceCheckService::unblock()`). Ni le temps qui passe,
   ni un contrôle réussi, ni un signalement ne le lèvent.
2. **Un humain est systématiquement saisi** : tout blocage et tout appariement douteux ouvrent un
   incident et notifient les administrateurs.
3. **Le droit d'obtenir une intervention humaine et de contester est exercé par un bouton** — « ça
   ne marche pas » — présent sur les deux surfaces, y compris quand le compte est bloqué.

La personne est **informée de son blocage et de son motif** par notification
(`FaceCheckBlockedNotification`), et de sa levée (`FaceCheckUnblockedNotification`).

> ⟦AVIS DU DPO REQUIS : confirmer la qualification. Si l'article 22 est jugé applicable, les
> garanties du § 3 sont déjà en place ; c'est la documentation qui doit être complétée.⟧

---

## 8. Nécessité et proportionnalité

**Alternatives écartées, et pourquoi :**

| Alternative | Pourquoi écartée |
|---|---|
| Contrôle d'identité une seule fois à l'inscription | C'est l'état actuel, et c'est précisément ce qui laisse passer le prêt de compte : le contrôle porte sur l'inscrit, jamais sur celui qui se présente. |
| Code à usage unique envoyé par SMS | Se transmet avec le compte. Prouve la possession d'un téléphone, pas l'identité d'une personne. |
| Contrôle systématique à chaque mission | Bien plus de données biométriques traitées, pour une assurance équivalente. Contraire à la minimisation. |
| Contrôle humain de tous les dossiers | Non tenable à l'échelle, et n'apporte rien pour un contrôle qui doit avoir lieu au moment où le prestataire part. |
| Empreinte digitale | Aussi sensible, plus intrusive, exige un matériel que la plateforme ne contrôle pas. |

**Minimisation appliquée** : une seule image de référence conservée ; les selfies de contrôle
purgés à 30 jours ; la comparaison faite « un contre un » ; le périmètre restreint à deux métiers ;
la cadence aléatoire qui réduit le nombre de contrôles nécessaires.

---

## 9. Risques pour les personnes, et mesures

| Risque | Gravité | Vraisemblance | Mesures en place |
|---|---|---|---|
| Fuite d'images de visages | Élevée | Faible | Disque privé hors racine web ; **chiffrement au repos** avec la clé applicative ; URL signées 10 min ; aucune mise en cache (`no-store`) ; consultation journalisée |
| Faux négatif : un prestataire honnête est bloqué | Élevée (perte de revenus) | Moyenne | 3 essais par contrôle ; 2 contrôles échoués avant blocage ; un appariement **non concluant** (PDF, scan de travers) ne bloque PAS ; revue humaine ; bouton de signalement ; notification expliquant le motif |
| Détournement de finalité (pointage, surveillance) | Élevée | Faible | Finalité unique documentée ici ; aucune donnée de présence dérivée du module ; l'API n'expose ni l'échéance ni l'historique à l'employeur |
| Accès abusif d'un administrateur | Moyenne | Faible | Permission dédiée `manage-face-check` ; URL signées courtes ; **chaque consultation journalisée** avec l'identité de l'administrateur |
| Conservation excessive | Moyenne | Faible | Purge automatisée quotidienne, **testée** (elle vérifie la disparition du fichier, pas seulement de la colonne) |
| Biais de l'algorithme selon le phototype | Élevée | **Non mesurée** | ⟦RISQUE RÉSIDUEL — voir § 11⟧ |
| Contournement par photo d'écran | Moyenne | Moyenne | Détection de vivacité **obligatoire** ; tout échec de vivacité ouvre un incident critique dès la première fois |

---

## 10. Droits des personnes

| Droit | Comment il s'exerce |
|---|---|
| Information (13/14) | Texte de consentement affiché avant toute capture, servi par le serveur, identique sur les deux surfaces |
| Accès et portabilité (15/20) | Export RGPD : section `face_checks` — **métadonnées uniquement**. Les images ne sont pas incluses : réexpédier un visage exploitable dans un JSON transitant par un e-mail créerait un risque supérieur au droit exercé. ⟦À VALIDER par le DPO ; en cas d'avis contraire, prévoir une remise sécurisée sur demande.⟧ |
| Rectification (16) | Ré-enrôlement possible à tout moment |
| Effacement (17) | Suppression **réelle des fichiers** par `DataErasureService`, sans anonymisation : un gabarit anonymisé n'existe pas |
| Retrait du consentement (7 §3) | Un appel d'API ; conséquence annoncée avant le geste |
| Opposition / intervention humaine | Bouton de signalement, accessible même compte bloqué |

---

## 11. Risque résiduel

**Le biais différentiel selon le phototype, l'âge et le genre n'est pas mesuré.**

C'est le risque résiduel principal, et il est honnête de l'écrire : la littérature (NIST FRVT)
documente des écarts de taux de faux rejet selon les groupes démographiques, et un faux rejet a ici
une conséquence économique directe. La plateforme ne dispose d'aucune mesure propre.

**Mesures compensatoires en place :** aucun blocage sur un seul échec ; revue humaine systématique ;
voie de contestation accessible.

**Mesures à prendre avant activation en production :**
- ⟦obtenir du fournisseur retenu ses taux de faux rejet par groupe démographique⟧ ;
- ⟦mettre en place un suivi du taux d'échec, et le relire mensuellement — un écart durable est un
  signal de biais, pas de fraude⟧.

---

## 12. Conclusion et signatures

Le traitement est **nécessaire et proportionné** au regard du risque visé, à la condition que son
périmètre reste celui décrit au § 3 : les métiers où une personne se retrouve seule au domicile
d'un client.

**Conditions à lever avant activation en production :**

- [ ] Champs ⟦À COMPLÉTER⟧ renseignés (§ 2, § 6)
- [ ] Avis du DPO sur la base légale (§ 4.2) et sur la qualification art. 22 (§ 7)
- [ ] DPA signé avec le fournisseur **si** `FACE_CHECK_PROVIDER=onfido`
- [ ] Taux de faux rejet par groupe démographique obtenus (§ 11)
- [ ] Texte de consentement relu par un conseil juridique (`lang/*/face_check.php`, clé `consent.text`)
- [ ] Inscription au registre des activités de traitement (art. 30)
- [ ] Consultation préalable de l'APD si le risque résiduel est jugé élevé (art. 36)

| | Nom | Date | Signature |
|---|---|---|---|
| Responsable du traitement | ⟦ ⟧ | | |
| DPO (avis) | ⟦ ⟧ | | |

---

## Annexe — où le vérifier dans le code

| Affirmation | Fichier |
|---|---|
| Chiffrement au repos des images | `app/Services/FaceCheck/FaceImageStore.php` |
| Cadence aléatoire, jamais exposée | `app/Services/FaceCheck/FaceCheckScheduler.php` |
| Levée du blocage réservée à un admin | `app/Services/FaceCheck/FaceCheckService::unblock()` |
| Purge des selfies (fichier compris) | `app/Services/Gdpr/RetentionPolicyService::purgeFaceCheckSelfies()` |
| Effacement réel | `app/Services/Gdpr/DataErasureService::eraseBiometrics()` |
| Export sans images | `app/Services/Gdpr/DataExportService::collectFaceChecks()` |
| Journalisation des consultations | `app/Http/Controllers/Admin/FaceCheckImageController.php` |
| Texte de consentement, source unique | `lang/{fr,nl,en}/face_check.php` → `consent.text` |
| Preuves automatisées | `tests/Feature/FaceCheck/RgpdDuControleFacialTest.php` |
