# Protocole — comment les quatre sessions travaillent ensemble

## 1. Le sens de circulation

```
 Utilisateur
      │  indications
      ▼
 ┌─────────────────────────────────────────┐
 │ T4 — COMITÉ DE PILOTAGE                 │
 │ découpe en lots · distribue · arbitre   │
 └───┬───────────────┬───────────────┬─────┘
     │ directive     │ directive     │ directive
     ▼               ▼               ▼
 ┌───────┐       ┌───────┐       ┌───────┐
 │  T1   │◄─────►│  T2   │◄─────►│  T3   │   revue croisée
 │CLIENT │       │PROVID.│       │ ADMIN │   (elles se corrigent
 └───┬───┘       └───┬───┘       └───┬───┘    entre elles)
     │ livrable      │ livrable      │ livrable
     └───────────────┴───────────────┘
                     ▼
            T4 : ✅ approuvé  ou  ❌ renvoyé
```

**L'utilisateur ne parle qu'à T4.** T1, T2, T3 ne reçoivent jamais d'ordre direct.

## 2. La revue croisée — personne ne valide son propre travail

Tout livrable d'une équipe est relu par **au moins une autre équipe**, choisie
selon la frontière touchée :

| L'équipe qui produit | Est relue par | Sur quoi |
|---|---|---|
| T1 client | T2 | tout ce qui devient une mission côté provider |
| T2 provider | T1 | tout ce que le client voit du travail en cours |
| T1 ou T2 | T3 | tout ce qui dépend d'un tarif, d'un drapeau, d'un catalogue |
| T3 admin | T1 **et** T2 | tout ce qui ouvre ou ferme une capacité aux deux publics |

**Le relecteur peut REFUSER.** Un refus s'écrit dans le cahier de liaison avec :
le fichier et la ligne, ce que ça casse, et le test qui le prouve. Un refus sans
preuve n'est pas un refus — T4 le rejette.

**Un désaccord entre deux équipes se tranche par le test qui départage**, jamais
par l'autorité. Si le test n'existe pas, on l'écrit avant de discuter.

## 3. La boucle — elle ne s'arrête pas avant 100 %

Chaque lot tourne ainsi, sans annoncer chaque tour :

1. **Mesure** — l'équipe lit le code réel de son périmètre, cite fichier:ligne.
2. **Correction** — elle corrige, avec le test qui prouve l'ancien comportement
   écrit AVANT la modification si le doute existe.
3. **Revue croisée** — l'équipe désignée relit à froid et cherche la régression.
4. **Verdict T4** — ✅, ou ❌ avec ce qui manque, nommément.
5. **Si ❌ : retour à l'étape 1.** Pas de « c'est globalement bon ».

Un lot n'est clos que lorsque **toutes** ses cases sont cochées, sur **web ET natif**.

## 4. Les critères de sortie — ce que « 100 % » veut dire ici

Un lot est fini quand, et seulement quand :

- [ ] Le chemin est **joignable** : un utilisateur réel du rôle concerné peut y
      arriver depuis un écran existant (le module complet et injoignable est la
      famille de défauts dominante de ce dépôt).
- [ ] Le comportement est **identique sur web et sur natif**, ou l'écart est
      documenté et voulu.
- [ ] Les gardes existent **des deux côtés** : web ET API (une garde posée sur le
      web seulement est un trou d'authentification).
- [ ] Chaque test de refus a son **témoin positif**.
- [ ] La suite qui couvre le lot est verte, et sa sortie est rapportée telle quelle.
- [ ] Aucune couleur ni espacement en dur ; mode sombre traité ; mouvement réduit
      et lecteur d'écran respectés.
- [ ] Pint et PHPStan passent (Pint est une porte dure de la CI sur ce dépôt).

## 5. Les fichiers de travail partagés

| Fichier | Qui écrit | Quoi |
|---|---|---|
| `TABLEAU-DE-BORD.md` | T4 seule | état de chaque lot, verdicts |
| `CAHIER-DE-LIAISON.md` | les quatre | demandes, refus motivés, contrats d'interface |
| `perimetres/T*.md` | l'équipe propriétaire | la carte de son périmètre |
| `lots/LOT-*.md` | T4 pose, l'équipe remplit | la directive et son exécution |

**Règle d'écriture** : une équipe n'écrit jamais dans le fichier d'une autre. Elle
passe par le cahier de liaison, et T4 tranche.
