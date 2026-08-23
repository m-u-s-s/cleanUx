# Contribuer

Les règles complètes sont dans [docs/conventions.md](docs/conventions.md) : nommage, style,
commentaires, messages de commit, ce qu'un relecteur cherche.

## Avant de proposer

```bash
vendor/bin/pint                                  # style — porte dure de la CI
vendor/bin/phpstan analyse --memory-limit=2G     # analyse statique, sans argument de chemin
composer test:parallele                          # la suite complète, ~13 min
```

Les trois doivent passer.

## L'essentiel en cinq points

1. **Mesurez avant de corriger.** Ni la documentation ni les commentaires ne font foi. Lisez le
   code, les migrations, les routes — et citez le fichier et la ligne.
2. **Le métier vit dans un service.** Un contrôleur valide, appelle, rend.
3. **Un commentaire tient en deux lignes** et dit ce que fait le code. Le pourquoi appartient au
   message de commit.
4. **Un test de refus exige un témoin positif**, sans quoi il passe au vert en mesurant une panne.
5. **Dites ce qui n'a pas été fait.** Un test qui échoue se rapporte avec sa sortie ; une partie
   laissée de côté se nomme, avec son motif.
