# Cahier de liaison inter-équipes

Demandes, refus motivés, contrats d'interface. Ordre chronologique, on n'efface rien.

**Format d'une entrée**

```
### [DATE] T<émetteur> → T<destinataire> — <objet>
**Type** : demande | refus | contrat | alerte
**Preuve** : fichier:ligne, ou sortie de test
**Contenu** : …
**Réponse** : (remplie par le destinataire)
**Arbitrage T4** : (si désaccord)
```

---

### [2026-08-19] T4 → T1, T2, T3 — ouverture du dispositif
**Type** : contrat
**Contenu** : les quatre équipes sont constituées. Périmètres arrêtés dans
`CHARTE.md`, mesurés sur le code réel et non sur la documentation. Reconnaissance
des périmètres lancée avant toute modification. Aucune équipe ne modifie une zone
de frontière partagée sans arbitrage T4.
**Réponse** : —

### [2026-08-19] T4 → T2 — deux alertes réfutées, avec leur preuve
**Type** : arbitrage
**Preuve** :
- `routes/web.php:65` — `provider.onboarding` existe.
- `routes/web.php:79` — `provider.face-check` existe, **délibérément hors de `face.verified`**, et le
  commentaire qui l'accompagne explique pourquoi : « l'y soumettre enfermerait le compte dans une
  boucle où l'on exige un contrôle sans jamais laisser le passer ».
- Mesure de joignabilité sur les 51 composants de l'espace prestataire : 9 sans route, et les
  **9 sont montés** depuis une vue (`livewire:employe.mission-actions`, `provider.offer-watcher`…).
  Zéro orphelin.

**Contenu** : T2 signalait « prestataire enfermé dehors — `face.verified` bloque le terrain sans
écran pour lever le blocage » et « 14 composants sans porte web ». Les deux sont infirmés.

**Cause de l'erreur, à retenir** : T2 a cherché ces routes dans `routes/employe.php` et
`routes/missions.php`, sans ouvrir `routes/web.php` où elles vivent ; et il a compté « sans route »
comme « injoignable », alors qu'un composant imbriqué n'a pas de route par construction. La
joignabilité se mesure sur les MONTAGES, pas sur les routes.

**Arbitrage T4** : les deux points sortent du tableau de bord. Le périmètre prestataire web est sain
sur la joignabilité. Ce qui reste ouvert chez T2 : les 7 méthodes de canal sans garde
d'organisation, et les 19 routes sous `class_exists()`.

### [2026-08-20] T4 → T2 — troisième alerte réfutée : les canaux d'équipe
**Type** : arbitrage
**Preuve** : `CompanyController::canalSousGarde()` (`:1761-1772`) fait TROIS vérifications —
organisation active (`organisationActive()`), canal rattaché à CETTE organisation
(`where('organization_account_id', $org->id)->findOrFail()`), puis la capacité via `ChannelPolicy`
(`Auth::user()->can($capacite, $canal)`).

Les sept méthodes signalées l'appellent : `channelMembers`, `leaveChannel`, `markChannelRead`,
`channelMessages`, `postChannelMessage`, `sendVoiceNote` directement ; `endCall` via
`appelSousGarde()` (`:1720-1727`), qui y délègue.

**Contenu** : T2 signalait « sept méthodes de canal sans garde d'organisation — ni `exige()`, ni
`organisationActive()`, ni `authorize()` ». Les trois formes cherchées ne sont pas les seules : la
garde de ce contrôleur porte un nom métier, `canalSousGarde()`.

**Cause de l'erreur, à retenir** : chercher des NOMS D'APPELS connus plutôt que de suivre ce que le
code fait. Une garde peut s'appeler autrement — ici, elle est même plus stricte que ce que T2
réclamait, puisqu'elle vérifie en plus que le canal appartient à l'organisation.

**Arbitrage T4** : point clos. Bilan de T2 : trois alertes sur quatre infirmées. Le trou d'API
`org.type:provider` qu'il avait trouvé, lui, était réel et est corrigé.

### [2026-08-20] T3 → T4 — la console générique échappait à la capacité des modules
**Type** : alerte confirmée
**Preuve** : `EnforceModuleGate::gateDeLUrlDApi()` résolvait le module par le SEGMENT qui suit
`api/admin/`. Or `api/admin/console/{ressource}` donne « console » — qui n'est le nom d'aucun
module. Aucune capacité n'était donc trouvée, et la garde laissait passer.

Conséquence mesurée par `ExplorationMetierRessourceTest` : un administrateur limité à
`manage-quality` obtenait **200** sur `/api/admin/console/analytics-exploration`. Les
**quatre-vingt-onze** ressources du moteur — comptabilité, finances, utilisateurs, litiges —
étaient dans le même cas.

**Correction** : le repli comprend désormais `api/admin/console/{ressource}` et
`api/admin/console/reports/{rapport}`. La ressource porte la même clé que son module dans
`config/admin_console.php`, lequel connaît ses routes web ; on remonte de là jusqu'à la capacité
que `config/modules.php` déclare. La règle reste écrite UNE seule fois — c'est le chemin pour
l'atteindre qui est plus long.

**Ce que ça enseigne** : une garde posée « sur `/api/admin/*` » n'est pas posée sur tout
`/api/admin/*`. Le préfixe commun cachait une surface entière qui ne suit pas la même convention
d'URL. Mesurer la couverture route par route, pas au préfixe.

**Arbitrage T4** : corrigé. Le défaut n'a été trouvé que parce que le test de la nouvelle
ressource comportait un cas de REFUS — un test qui n'aurait vérifié que le chemin heureux
l'aurait manqué.

### [2026-08-20] T2 → T4 — `individual` et `independent` : deux valeurs, un seul prestataire
**Type** : alerte confirmée, ARBITRAGE EN ATTENTE
**Preuve** :
- `app/Enums/ProviderType.php:7-8` — l'énumération porte `INDEPENDENT` **et** `INDIVIDUAL`.
- `ProviderOnboardingService.php:58` — l'inscription prestataire crée `provider_type = 'individual'`.
  C'est le chemin NORMAL d'inscription.
- `CandidateFinder.php:328` et `EmployeeAvailabilityService.php:203` — tous deux traitent
  `individual` comme un indépendant : le moteur de répartition peut donc lui envoyer des missions.
- `HasUserTypeChecks.php:114` — `isProviderIndependent()` ne reconnaît QUE `INDEPENDENT`.

**Conséquence** : un prestataire inscrit par le parcours normal est candidat au dispatch mais
refusé de son propre espace — `role:employe`, fiche de disponibilités, gestes de terrain. Trois
endroits disent « independent OU individual », un seul dit « independent ».

**Ce qui le masque aujourd'hui** : `isProviderIndependent()` retombe sur la colonne héritée
`role === 'employe'`. Ce repli porte `@deprecated` et « doit disparaître quand tous les comptes
auront un profil ». Le jour où il partira, tout prestataire `individual` deviendra invisible à son
propre espace tout en restant joignable par le dispatch.

**Pourquoi T4 ne tranche pas seul** : aligner `isProviderIndependent()` sur les deux autres
services ÉLARGIRAIT un droit d'accès. Mesuré en base : trois comptes portent `individual` — l'admin
QA et deux clientes de test, à qui le seeder a posé un profil prestataire. La correction leur
ouvrirait l'espace prestataire. Le défaut est réel, le remède touche la sécurité : la décision
revient à l'utilisateur.

**Options** : (a) `isProviderIndependent()` accepte `INDIVIDUAL` — cohérent avec le dispatch, mais
élargit l'accès ; (b) l'inscription crée `independent` et les profils existants sont migrés —
plus sûr, demande une migration ; (c) `INDIVIDUAL` est retirée de l'énumération si elle est un
doublon historique.

### [2026-08-21] T1 → T4 — l'écran des litiges client est inachevé
**Type** : alerte confirmée
**Preuve** : `scripts/boutons_morts.php` rapproche 239 composants de leur vue et trouve TROIS
appels vers une méthode absente — tous sur le même écran :
- `litiges-client.blade.php:84` → `openClaim()` — le bouton « Envoyer la réclamation » ;
- `litiges-client.blade.php:120` → `select($id)` — choisir un litige dans la liste ;
- `litiges-client.blade.php:212` → `postReply()` — répondre à un litige.

`App\Livewire\Client\LitigesClient` expose `createClaim`, `save`, `rules`,
`updatingFilterStatus`, `render`. Rien d'autre. Mesuré en direct : cliquer « Envoyer la
réclamation » rend **HTTP 500** sur `/livewire/update` — « Unable to call component method ».

**Plus profond que trois noms** : la partie détail de la vue lit `$selected->events` et
`$selected->resolutions`. `CustomerClaim` — le modèle qu'interroge le composant — n'a NI l'une
NI l'autre ; `ComplaintCase` les porte toutes les deux (`:162` et `:168`). L'écran a donc été
écrit pour `ComplaintCase`, et branché sur `CustomerClaim`.

Ce que le dépôt sait déjà : `AdminOverviewController` note que « `ComplaintCase` est celui de la
page admin « Litiges » et du `DisputeResolutionService` ; `CustomerClaim` est un modèle
parallèle ». Deux notions, un événement — encore.

**Pourquoi rien ne l'avait vu** : `$selected` reste nul faute de `select()`, donc le bloc de
détail n'est jamais rendu et ses relations absentes ne lèvent jamais. La page s'affiche, les
boutons paraissent normaux, et seul un humain qui CLIQUE le découvre.

**Arbitrage T4** : le bouton principal se répare par un renommage — `createClaim` existe et fait
le travail. La partie liste/détail/réponse demande de rebrancher l'écran sur `ComplaintCase` :
c'est un chantier, pas une correction, et il ne se fait pas à la va-vite sur le modèle qui porte
l'argent des litiges.
