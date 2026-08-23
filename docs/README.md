# Documentation Brio

Neuf pages. Chacune couvre un sujet en entier — vous n'avez pas à en ouvrir trois pour
comprendre une chose.

## Par où commencer

**Vous découvrez le dépôt** → [Démarrer](demarrer.md), puis [Architecture](architecture.md).

**Vous devez corriger un défaut** → [Domaine](domaine.md) pour le vocabulaire, puis
[Parcours](parcours.md) pour situer le code dans la chaîne.

**Vous intégrez l'API** → [API](api.md).

**Vous mettez en production** → [Exploitation](exploitation.md).

## Les neuf pages

| Page | Répond à |
|---|---|
| [Démarrer](demarrer.md) | Comment j'installe et je lance ? Que dois-je voir ? |
| [Architecture](architecture.md) | Qui décide quoi ? Pourquoi c'est fait ainsi ? |
| [Domaine](domaine.md) | Que veut dire « métier », « zone », « mission » ici ? |
| [Parcours](parcours.md) | Que se passe-t-il entre le clic et le paiement ? |
| [API](api.md) | Comment j'authentifie et j'appelle ? |
| [Données](donnees.md) | Où vit quoi ? Comment je migre sans casser ? |
| [Tests](tests.md) | Comment je lance ? Comment j'écris un test utile ? |
| [Exploitation](exploitation.md) | Comment je déploie et je surveille ? |
| [Conventions](conventions.md) | Comment j'écris du code accepté ici ? |

## Ce que cette documentation n'est pas

Elle ne raconte pas l'histoire du projet. Les décisions passées, les défauts corrigés et leur
raison vivent dans **l'historique Git** : chaque message de commit explique ce qui n'allait pas,
comment on l'a mesuré et pourquoi la correction prend cette forme.

```bash
git log --oneline                       # la chronologie
git log --grep="dispatch" -p            # tout ce qui a touché la répartition
git blame app/Services/…/Fichier.php    # qui a écrit cette ligne, et dans quel commit
```

Cherchez le pourquoi dans Git. Cherchez le quoi ici.

## Tenir cette documentation à jour

Ces pages décrivent un état **mesuré**, pas un état souhaité. Quand vous changez la plateforme :

1. Vérifiez si une page devient fausse — un chiffre, un nom de table, un chemin.
2. Corrigez-la dans le même commit que le code.
3. Ne créez pas de dixième page. Complétez celle qui traite déjà le sujet.

Une page qui vieillit ment. Mieux vaut neuf pages justes que cinquante approximatives.
