# Moteur d'automatisation — phase 4 : les deux vitesses

> **Pour les agents :** SOUS-COMPÉTENCE REQUISE — utiliser `superpowers:subagent-driven-development`
> pour exécuter ce plan tâche par tâche. Les étapes sont cochables (`- [ ]`).

**But :** qu'une action puisse **écrire dans le domaine métier** — et que ça reste tenable.

**Architecture :** toute action naît « à valider ». Elle pose alors une **proposition** qu'un humain
tranche, au lieu d'agir. On opte pour l'autonomie action par action, dans un écran, avec une
confirmation renforcée quand l'action touche au domaine. Une proposition non décidée **expire**.

**Pile :** Laravel 12, PHP 8.5, Livewire 3, PHPUnit sur SQLite, application sur MySQL.

**Spec :** `docs/superpowers/specs/2026-08-30-moteur-automatisation-design.md`

---

## Pourquoi cette phase est la plus risquée, et ce qui la rend tenable

Jusqu'ici le moteur ne sait que **notifier** et **journaliser** : au pire il agace. À partir de
cette phase, il peut changer l'état d'une réservation ou d'une mission. La spec l'a placée en
quatrième position exprès — **chaque garde arrive avant ce qu'elle retient**.

Les cinq contrepoids, tels que la spec les pose. Ils ne se rediscutent pas :

1. **Toute action naît « à valider ».** L'absence de ligne dans `automation_action_settings` vaut
   « à valider ». On opte pour l'autonomie ; on n'y tombe jamais par défaut.
2. **Basculer une action vers autonome est journalisé** — qui, quand — par `ActivityLogger`.
3. Une action dont `toucheAuDomaine()` est vrai demande une **confirmation renforcée**.
4. Le journal distingue `executee` (le moteur) de `validee` (un humain). **Jamais la même valeur.**
5. **Une proposition non décidée expire.** Ce n'est pas de l'hygiène : une proposition en attente
   compte comme « déjà agi », donc tant que personne ne tranche, la règle ne repasse jamais sur
   cette entité. Sans expiration, une file oubliée gèle silencieusement une partie du domaine.

---

## Ce que les phases 1 à 3 ont livré, et sur quoi cette phase s'appuie

Mesuré le 2026-08-31.

| Ce dont la phase a besoin | Où c'est |
|---|---|
| Le contrat d'action | `App\Services\Automation\Contracts\Action` — `cle`, `libelle`, `entitesSupportees`, `champs`, **`toucheAuDomaine(): bool`**, `executer(Model, array): ActionResult` |
| Les actions livrées | `Journaliser`, `NotifierLesAdmins` — les deux `toucheAuDomaine() === false` |
| Le registre | `ActionRegistre` — `enregistrer`, `trouver`, `toutes` |
| Où une action est posée | `App\Services\Automation\RuleRunner::poser()` — écrit une ligne `automation_actions` |
| Les résultats déjà prévus | `simulee`, `executee`, **`proposee`**, **`validee`**, **`refusee`**, `echouee`, **`expiree`** |
| Les colonnes déjà prévues | `automation_actions.decide_par`, `.decide_le`, `.motif` |
| Le registre « déjà agi » | `RuleRunner::exclureLeDejaAgi()` — exclut tout sauf `refusee` et `expiree` |
| Le catalogue des écrans | `App\Services\Automation\Catalogue` — `actions(?string $entite)` expose déjà `touche_au_domaine` |
| Les écrans | `AutomationCenter` (liste), `Automation\ConstructeurDeRegle`, `Automation\JournalDeRegle` |
| L'ordonnanceur | `automation:executer`, chaque minute, drain puis cadences |

**Le journal porte déjà les quatre résultats et les trois colonnes de décision.** La phase 1 les a
posés exprès — rien à migrer de ce côté.

**Le registre « déjà agi » traite déjà `refusee` et `expiree` comme « à refaire »**, et tout le
reste — dont `proposee` — comme « déjà agi ». C'est exactement ce que le contrepoids 5 décrit.
**Vérifie-le avant d'écrire quoi que ce soit** : si ce n'est plus vrai, dis-le, tout le reste en
dépend.

---

## Global Constraints

Elles lient **chaque** tâche.

- **L'absence de réglage vaut « à valider ».** Jamais l'inverse. Une action sans ligne dans
  `automation_action_settings` propose, elle n'agit pas.
- **`toucheAuDomaine()` ne décide PAS de l'autonomie.** Il décide si la bascule vers l'autonomie
  demande une confirmation renforcée. La déclaration dit ce que l'action **est** ; le réglage dit
  ce que l'administrateur **autorise**.
- **`executee` et `validee` ne se confondent jamais.** L'une dit « le moteur a agi seul », l'autre
  « un humain a tranché ». Un écran qui les mélangerait effacerait la trace de la décision.
- **L'interrupteur global reste au-dessus de tout** : `FeatureFlagService::isEnabled('automation')`.
  **Aucun réglage d'action ne peut le contourner** — c'est la seule garde que le réglage ne touche
  pas.
- **Une règle n'agit jamais sans être passée par l'observation** (garde de la phase 1, dans
  `RuleRunner::executer()`). Une proposition n'y échappe pas.
- **Tout composant Livewire de `App\Livewire\Admin` emploie `EnforcesAdminAccess` ET vérifie la
  capacité `manage-automation` dans son `boot()`.** `/livewire/update` ne rejoue aucun
  intermédiaire de route : le garde de route protège l'affichage, pas le chemin d'action.
- **`#[Locked]`** sur toute propriété portant un identifiant.
- **Le système de design** : `brio-btn-primary` / `brio-btn-secondary`, jetons `--brio-*`, aucune
  classe de palette Tailwind littérale. La garde cadrée
  `tests/Feature/DesignSystem/LAutomatisationEmploieLesJetonsTest.php` balaie les vues **et** les
  composants d'automatisation.
- **Un test de refus exige un témoin positif.**
- **Les trois familles de garde-fous se lancent avant tout commit d'une vue ou d'un composant
  neuf** : `tests/Feature/Security/`, `tests/Feature/DesignSystem/`, `tests/Feature/A11y/`.
- **Commentaires : deux lignes maximum.**
- Portails : `./vendor/bin/pint` sur les fichiers touchés, puis
  `./vendor/bin/phpstan analyse --no-progress` **sans argument de chemin**.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `database/migrations/…_les_reglages_d_actions.php` | La table `automation_action_settings` |
| `app/Models/AutomationActionSetting.php` | Une ligne de réglage |
| `app/Services/Automation/ReglagesDActions.php` | **La seule porte** : une action est-elle autonome ? la basculer |
| `app/Services/Automation/FileDePropositions.php` | Poser, valider, refuser, expirer |
| `app/Console/Commands/ExpirerLesPropositions.php` | L'expiration, par l'ordonnanceur |
| `app/Livewire/Admin/Automation/ReglagesDActionsEcran.php` | La ligne autonome / à valider |
| `app/Livewire/Admin/Automation/PropositionsEnAttente.php` | La file transversale, valider ou refuser |
| `app/Services/Automation/Actions/…` | Les premières actions qui écrivent dans le domaine |

---

### Tâche 1 : la table des réglages et sa porte

**Fichiers :**
- Créer : la migration, `app/Models/AutomationActionSetting.php`,
  `app/Services/Automation/ReglagesDActions.php`
- Créer : `tests/Feature/Automation/ReglagesDActionsTest.php`

**Interfaces :**
- Produit : `ReglagesDActions::estAutonome(string $actionCle): bool` ;
  `::basculer(string $actionCle, bool $autonome, User $par): void` ;
  `::tous(): array<string, bool>`.

La table : `action_cle` (**unique**), `autonome` (booléen, **défaut faux**), `modifie_par`
(nullable, clé étrangère vers `users`), `modifie_le` (horodatage nullable).

- [ ] **Étape 1 : écrire le test** — au minimum :
  1. **le défaut qui compte** : une action sans ligne n'est **pas** autonome ;
  2. **témoin** : une action basculée l'est ;
  3. la bascule écrit `modifie_par` et `modifie_le` ;
  4. la bascule est **journalisée** par `ActivityLogger` — vérifie la ligne, pas seulement l'état ;
  5. rebasculer vers « à valider » fonctionne aussi, et se journalise ;
  6. l'unicité d'`action_cle` tient (une seconde ligne pour la même clé est refusée) ;
  7. **le garde-fou** : `tous()` ne rend que des clés d'actions **enregistrées au registre** — un
     réglage orphelin, laissé par une action retirée du code, ne doit pas apparaître comme si elle
     existait encore.

- [ ] **Étape 2 : lancer, vérifier l'échec** — je veux sa sortie dans le rapport
- [ ] **Étape 3 : la migration, le modèle, le service**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 2 : `RuleRunner` propose au lieu d'agir

**Fichiers :**
- Modifier : `app/Services/Automation/RuleRunner.php`
- Créer : `tests/Feature/Automation/PropositionPlutotQueActionTest.php`

**C'est le cœur de la phase.** Aujourd'hui `poser()` exécute l'action et écrit `executee`. Il doit
désormais, **en mode armé** :

- si l'action est **autonome** → exécuter, écrire `executee` (comportement actuel) ;
- sinon → **ne pas exécuter**, écrire `proposee`, et rendre la main.

**En mode observation, rien ne change** : la ligne reste `simulee`, l'action n'est jamais appelée.

**Le piège à ne pas créer** : une proposition **compte comme « déjà agi »**, donc la règle ne
repassera pas sur cette entité tant que personne ne tranche. C'est voulu — et c'est exactement ce
que l'expiration de la tâche 5 vient débloquer. **Vérifie que `exclureLeDejaAgi()` traite bien
`proposee` comme « déjà agi »** avant d'écrire, et dis ce que tu mesures.

- [ ] **Étape 1 : écrire le test** — au minimum :
  1. une action **non autonome**, règle armée → ligne `proposee`, **et l'action n'a pas tourné** —
     prouve-le par un effet observable, pas par un espion sur l'appel ;
  2. **témoin** : la même action, basculée autonome → ligne `executee`, et l'effet a bien eu lieu ;
  3. en observation, une action non autonome écrit toujours `simulee`, jamais `proposee` ;
  4. une entité portant une proposition en attente n'est **pas** reprise au passage suivant ;
  5. **témoin du 4** : une entité dont la proposition a été **refusée** est reprise.

- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 3 : la file des propositions — poser, valider, refuser

**Fichiers :**
- Créer : `app/Services/Automation/FileDePropositions.php`
- Créer : `tests/Feature/Automation/FileDePropositionsTest.php`

**Interfaces :**
- Produit : `enAttente(): Collection` ; `valider(AutomationAction $ligne, User $par): ActionResult` ;
  `refuser(AutomationAction $ligne, User $par, string $motif): void`.

**Valider, c'est exécuter maintenant** : le service appelle l'action, puis écrit `validee` —
**jamais `executee`**, c'est un humain qui a tranché. Il renseigne `decide_par`, `decide_le`, et
`motif` pour un refus.

**Que se passe-t-il si l'action échoue au moment de la validation ?** Tranche-le, écris pourquoi en
deux lignes, et écris le test. Rappel utile : `RuleRunner` écrit `echouee` avec le message et
laisse le passage continuer.

- [ ] **Étape 1 : écrire le test** — au minimum : valider exécute et écrit `validee` ; refuser
  n'exécute pas et écrit `refusee` avec son motif ; les deux renseignent `decide_par` et
  `decide_le` ; une ligne déjà décidée ne se redécide pas ; **le témoin** : une ligne `proposee`
  est bien décidable. Plus le cas de l'échec, selon ton arbitrage.
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 4 : l'écran des réglages d'actions

**Fichiers :**
- Créer : `app/Livewire/Admin/Automation/ReglagesDActionsEcran.php` et sa vue
- Créer : `tests/Feature/Admin/ReglagesDActionsEcranTest.php`
- Modifier : les routes, `config/modules.php`, `config/admin_console.php`

Chaque action du registre, avec son libellé, ce qu'elle touche, et sa ligne : **autonome** ou **à
valider**. La bascule vers l'autonomie d'une action dont `toucheAuDomaine()` est vrai demande une
**confirmation renforcée** — mesure d'abord comment ce dépôt exprime déjà une confirmation
renforcée (`enforce_2fa` sur les routes d'administration, les modales de confirmation existantes)
et **suis le patron du dépôt**, n'en invente pas un.

- [ ] **Étape 1 : écrire le test** — au minimum : l'écran est joignable par une route **et par un
  clic** depuis la liste ; il montre toutes les actions du registre ; basculer marche dans les deux
  sens ; **une action qui touche au domaine exige la confirmation renforcée, et sans elle la
  bascule n'a pas lieu** ; **témoin** : une action qui n'y touche pas bascule directement ; un
  administrateur sans `manage-automation` ne peut ni voir ni basculer (par `Livewire::test()->call()`,
  qui ne rejoue aucun intermédiaire de route).
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : les trois familles de garde-fous, portails, commit**

---

### Tâche 5 : l'expiration

**Fichiers :**
- Créer : `app/Console/Commands/ExpirerLesPropositions.php`
- Modifier : `app/Console/Kernel.php`, `docs/exploitation.md`
- Créer : `tests/Feature/Automation/ExpirationDesPropositionsTest.php`

**Pourquoi elle existe** : une proposition en attente compte comme « déjà agi ». Tant que personne
ne tranche, la règle ne repasse jamais sur cette entité — **une file oubliée gèle silencieusement
une partie du domaine**. L'expiration est la garde qui l'empêche.

Le délai : **tranche-le, dis pourquoi, et rends-le lisible** — une constante nommée, pas un nombre
posé au milieu d'une requête.

**Toute tâche planifiée doit entrer dans l'inventaire de `docs/exploitation.md`** : un test du
dépôt apparie `Kernel.php` à cette table, et la phase 1 l'a déjà appris à ses dépens.

- [ ] **Étape 1 : écrire le test** — au minimum : une proposition plus vieille que le délai devient
  `expiree` ; **témoin** : une plus jeune ne bouge pas ; une ligne déjà décidée n'est jamais
  expirée ; **et le test qui ferme la boucle** : après expiration, la règle **reprend** l'entité au
  passage suivant.
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire la commande, la planifier, l'inscrire à l'inventaire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 6 : l'écran de la file des propositions

**Fichiers :**
- Créer : `app/Livewire/Admin/Automation/PropositionsEnAttente.php` et sa vue
- Créer : `tests/Feature/Admin/PropositionsEnAttenteTest.php`
- Modifier : les routes, `config/modules.php`, `config/admin_console.php`

**Transversale** : ce qui attend une décision, toutes règles confondues. Par ligne : la règle,
l'entité visée, l'action et **ses paramètres** — c'est ce sur quoi l'administrateur décide —, la
date de pose, et deux boutons : valider, ou refuser **avec motif**.

Le motif d'un refus est **obligatoire** : c'est la seule trace de pourquoi on n'a pas fait.

- [ ] **Étape 1 : écrire le test** — au minimum : la file montre les lignes `proposee` et **rien
  d'autre** ; valider exécute et la retire de la file ; refuser sans motif est refusé ; refuser avec
  motif la retire ; **témoin** : une file vide affiche un état vide, pas un tableau vide ; la porte
  d'accès tient par `Livewire::test()->call()`.
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : les trois familles de garde-fous, portails, commit**

---

### Tâche 7 : les premières actions qui écrivent dans le domaine

**Fichiers :**
- Créer : deux actions sous `app/Services/Automation/Actions/`
- Modifier : `app/Providers/AutomationServiceProvider.php`
- Créer : leurs tests

**C'est ici, et pas avant, que le moteur touche au métier.** Les six tâches précédentes existent
pour que ce soit tenable.

**Choisis deux actions utiles et mesurables**, en te fondant sur ce que le domaine offre déjà —
`app/Services/` regorge de services testés. **Mesure avant de choisir** : une action doit appeler un
service existant, jamais réimplémenter une règle métier. Propose ton choix dans le rapport ; si
aucune ne s'impose, dis-le plutôt que d'en inventer une.

Les deux déclarent `toucheAuDomaine() === true`, et **naissent donc « à valider »** — c'est le
contrepoids 1, et il ne se contourne pas.

- [ ] **Étape 1 : écrire les tests** — pour chaque action : elle appelle bien le service du domaine ;
  elle rend un `ActionResult` en échec quand le service refuse ; **elle déclare
  `toucheAuDomaine() === true`** ; et **le test qui compte** : posée par une règle armée sans
  réglage, elle produit une `proposee` et **le domaine n'a pas bougé**.
- [ ] **Étape 2 : lancer, vérifier l'échec**
- [ ] **Étape 3 : écrire**
- [ ] **Étape 4 : relancer, portails, commit**

---

### Tâche 8 : la vérification d'ensemble

**Fichiers :**
- Créer : `tests/Feature/Automation/BoutEnBoutDeuxVitessesTest.php`
- Modifier : `tests/Feature/Automation/RegistresTest.php`

- [ ] **Étape 1 : le bout en bout, sans raccourci**

Une règle avec une action qui touche au domaine, mise en observation puis armée par le chemin réel ;
`php artisan automation:executer` ; **le domaine n'a pas bougé** et une `proposee` attend ; l'écran
de la file la montre ; un administrateur la valide ; **alors** le domaine bouge et la ligne devient
`validee`. Puis le chemin du refus, et celui de l'expiration.

- [ ] **Étape 2 : étendre le garde-fou des registres**

Toute action déclarant `toucheAuDomaine() === true` doit être **absente** des réglages autonomes
par défaut — c'est-à-dire qu'aucune graine ni migration ne doit la rendre autonome sans décision
humaine. Le témoin : le registre n'est pas vide.

- [ ] **Étape 3 : les trois familles de garde-fous**
- [ ] **Étape 4 : la suite complète**

```bash
git status --short          # l'arbre doit etre propre AVANT de lancer
composer test:parallele
```

- [ ] **Étape 5 : les deux portails, puis commit**

---

## Ce que la phase 4 ne fait pas

| Sujet | Pourquoi |
|---|---|
| Les cinq règles reproduisant `BusinessAlerts` | Phase 5 |
| La règle multi-étapes | Hors du lot ; le point d'extension est la colonne `etape` |
| Retirer la voie Sentry des cinq alertes | Interdit par la spec |
| Filtrer les opérateurs par type de champ | Nommé à la phase 3 ; demanderait que `FieldBinding` déclare un type |
