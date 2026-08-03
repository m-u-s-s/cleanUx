# Console d'administration mobile — Sous-projet C

> **Prérequis :** sous-projets A et B livrés. Le moteur à descripteurs sert 10 modules ; l'annuaire
> affiche `10 / 81`. Ce plan ajoute la profondeur là où le rendu générique ne suffit pas.

**But :** quatre domaines servis par des écrans natifs sur-mesure, et le mécanisme qui les fait
coexister avec le moteur sans qu'aucun des deux ne mente sur ce qu'il couvre.

**Architecture :** un module passe en `coverage: 'screen'` dans `config/admin_console.php` ;
l'annuaire mobile l'ouvre alors sur un écran nommé plutôt que sur la liste générique. Une table
de correspondance côté mobile fait le lien, et **un test refuse qu'un module déclaré `screen`
n'ait pas d'écran** — le jumeau mobile du garde-fou anti-mensonge du lot B.

## Contraintes globales

- Contraintes des lots A et B reprises : Expo SDK 56 documenté, commentaires en français
  expliquant le pourquoi, `phpstan analyse` complet (référence : 20 erreurs préexistantes sur
  `main`), contre-épreuve MySQL, jamais de battement de présence dans l'espace admin.
- **Un écran sur-mesure ne réimplémente pas plus de règle qu'un descripteur.** Il apporte une
  ERGONOMIE que le moteur ne sait pas produire — demander trois valeurs avant d'agir, naviguer un
  arbre, poser un geste sur une carte — pas une logique métier parallèle.
- **Tout ce qui est déjà servi par un descripteur le reste** tant que son écran n'est pas livré :
  la bascule `descriptor` → `screen` est le DERNIER geste d'une tâche, jamais le premier.

---

### Tâche 1 : le pont annuaire → écran, et son garde-fou

**Fichiers :**
- Créer : `mobile/provider/src/admin/nativeScreens.ts`
- Modifier : `mobile/provider/src/admin/AdminDirectoryScreen.tsx`
- Modifier : `mobile/provider/src/navigation/types.ts`
- Test : `mobile/provider/__tests__/admin/nativeScreens.test.ts`

**Interfaces produites :**
`NATIVE_ADMIN_SCREENS: Record<string, { screen: string }>` — clé de module → écran natif.

- [ ] **Étape 1 : écrire le test qui échoue**

```ts
// Le jumeau mobile du garde-fou anti-mensonge du serveur. La mémoire projet documente des écrans
// mobiles orphelins que ni tsc ni jest ne signalent : un module déclaré `screen` sans entrée ici
// s'ouvrirait sur la liste générique — donc sur un écran qui n'est pas celui qu'on a écrit, sans
// que rien ne le dise.
it('tout module déclaré `screen` a un écran natif', () => {
  const registre = lireRegistrePhp();
  const manquants = registre
    .filter((m) => m.coverage === 'screen')
    .filter((m) => !NATIVE_ADMIN_SCREENS[m.key]);

  expect(manquants.map((m) => m.key)).toEqual([]);
});

it('tout écran natif déclaré correspond à un module connu', () => {
  const cles = lireRegistrePhp().map((m) => m.key);
  const orphelins = Object.keys(NATIVE_ADMIN_SCREENS).filter((k) => !cles.includes(k));

  expect(orphelins).toEqual([]);
});

it('tout écran natif déclaré est enregistré dans la pile admin', () => {
  const source = fs.readFileSync('src/navigation/RootNavigator.tsx', 'utf8');

  for (const { screen } of Object.values(NATIVE_ADMIN_SCREENS)) {
    expect(source).toContain(`name="${screen}"`);
  }
});
```

`lireRegistrePhp()` lit `config/admin_console.php` et en extrait les triplets
`key` / `coverage` par expression régulière — le fichier est une liste de littéraux à une ligne
par module, ce qui rend l'extraction fiable sans interpréteur PHP.

- [ ] **Étape 2** : lancer, constater l'échec.
- [ ] **Étape 3** : écrire `nativeScreens.ts` (vide au départ) et brancher `AdminDirectoryScreen`
  pour qu'il consulte la table avant de retomber sur la liste générique.
- [ ] **Étape 4** : relancer, commit.

---

### Tâche 2 : Utilisateurs et rôles

**Ce que le moteur ne sait pas faire ici :** réinitialiser un mot de passe sans le saisir,
changer un rôle avec ses conséquences affichées, voir d'un coup les casquettes d'un compte
(client, prestataire, entreprise) qui vivent dans des profils liés.

**Fichiers :** `src/admin/screens/UsersScreen.tsx`, `UserDetailScreen.tsx`, hooks dédiés ;
côté serveur, les endpoints manquants sous `/api/admin/users`.

- [ ] Écrire les tests serveur : réinitialisation de mot de passe (jeton envoyé, pas de mot de
      passe rendu), changement de rôle refusé vers `super_admin`, casquettes servies.
- [ ] Lancer, constater l'échec ; écrire les endpoints ; relancer.
- [ ] Écrire les tests mobile : liste, détail avec casquettes, actions, confirmation destructive.
- [ ] Écrire les écrans ; relancer.
- [ ] Basculer `users` sur `screen`, vérifier le garde-fou de la tâche 1, commit.

---

### Tâche 3 : Prix, catalogue et métiers

**Ce que le moteur ne sait pas faire ici :** naviguer l'arbre SECTEUR → MÉTIER → QUESTIONS du
moteur de commande, et éditer une grille de prix par zone — deux dimensions, pas une liste.

- [ ] Tests serveur : lecture de l'arbre, édition d'un prix par métier et par zone, refus d'un
      prix négatif, versionnement du catalogue préservé.
- [ ] Écrire les endpoints ; relancer.
- [ ] Tests mobile : navigation de l'arbre, édition d'une cellule de grille, annulation.
- [ ] Écrire les écrans ; relancer.
- [ ] Basculer `catalog`, `trades` et `pricing` sur `screen`, commit.

---

### Tâche 4 : Missions, réservations et dispatch

**Ce que le moteur ne sait pas faire ici :** réassigner un prestataire depuis une liste de
candidats scorés, et déclencher un dispatch en voyant ce qu'il propose avant de valider.

- [ ] Tests serveur : candidats scorés servis pour une mission, réassignation déléguée au service
      de dispatch, refus de réassigner une mission terminée.
- [ ] Écrire les endpoints ; relancer.
- [ ] Tests mobile : liste, détail, choix d'un candidat, confirmation.
- [ ] Écrire les écrans ; relancer.
- [ ] Basculer `missions` et `bookings` sur `screen`, commit.

---

### Tâche 5 : les refus motivés des files de validation

**Ce que le lot B a délibérément laissé de côté :** tous les refus du domaine — litige, KYC, KYB,
approbation d'entreprise — exigent un motif écrit, et le moteur ne sait pas demander une valeur
avant d'agir. C'est la seule chose qui manque à ces quatre files pour être complètes.

- [ ] Tests mobile : une feuille de motif s'ouvre, le refus part avec, un motif trop court est
      refusé côté serveur et l'erreur se pose sur le champ.
- [ ] Écrire une feuille de motif réutilisable par les quatre files, branchée sur les endpoints
      de refus existants (`DisputeResolutionService::dismiss`, `KycVerificationService::rejectManually`,
      `BusinessOnboardingService::reject`, `EnterpriseBookingApprovalService::reject`).
- [ ] Relancer ; basculer les quatre modules sur `screen`, commit.

---

### Tâche 6 : portail du sous-projet C

- [ ] `vendor/bin/pint --test`
- [ ] `vendor/bin/phpstan analyse` complet — aucune erreur nouvelle par rapport aux 20 de `main`
- [ ] `php artisan test`
- [ ] `cd mobile/provider && npm run typecheck && npm test`
- [ ] Contre-épreuve MySQL des tests serveur nouveaux
- [ ] Vérifier l'annuaire : `19 / 81 modules disponibles` (10 descripteurs + 9 bascules en écran)

## Auto-revue du plan

- **Couverture.** Pont annuaire→écran et son garde-fou → tâche 1. Les quatre domaines profonds
  demandés → tâches 2 à 5. Portail → tâche 6.
- **Cohérence.** `NATIVE_ADMIN_SCREENS` est défini en tâche 1 et alimenté par les tâches 2 à 5.
  Les valeurs de `coverage` restent celles du lot A.
- **Risque assumé.** Les tâches 3 et 4 supposent que les endpoints admin correspondants n'existent
  pas — la reconnaissance du lot A l'a montré pour la plupart des domaines. Si l'un existe déjà,
  la tâche le consomme au lieu de le recréer, et le dit dans son commit.
