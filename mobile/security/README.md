# Correctifs de dependances mobiles

Appliques par `npm ci` / `npm install` via `patch-package --error-on-fail`.
Ne pas utiliser `--ignore-scripts` pour produire un build. Apres une installation
exceptionnelle sans scripts, executer `npm run postinstall`, puis
`npm run test:security`. La CI execute ces regressions apres l'installation propre.

## Decodage des URL

`query-string@7.1.3` conserve son API CommonJS utilisee par React Navigation.
Son decodeur est verrouille sur le correctif officiel `decode-uri-component@0.5.0`
pour [GHSA-vcc3-ghjq-m6fr](https://github.com/advisories/GHSA-vcc3-ghjq-m6fr).
Le patch adapte seulement l'import a l'export ESM par defaut. Jest transforme
ce paquet dans les deux applications ; Metro assure la transformation du bundle.
L'outillage Node doit accepter `require(ESM)` (Node 22 recent, comme dans la CI).

Les tests couvrent les parametres multiples, espaces, UTF-8, encodage aller-retour,
queries malformees et les configurations reelles de liens profonds des deux apps.

Retirer l'override, le patch et l'exception Jest seulement apres une mise a jour
de React Navigation qui embarque un decodeur corrige compatible, puis rejouer
les tests et les exports Metro Android/iOS.

## Lecture des images

Au 12 septembre 2026, aucune version corrigee de `image-size` n'est publiee pour
[ICNS](https://github.com/advisories/GHSA-w3rx-r6r6-pgpr) et
[JXL/HEIF](https://github.com/advisories/GHSA-5p2g-fcmc-qvqq).
Le patch de `image-size@1.2.1` desactive par defaut les parseurs `icns`, `heif`,
`jxl` et `jxl-stream`. HEIF inclut HEIC/AVIF : ces formats ne doivent pas servir
d'assets mobiles tant que cette protection reste necessaire. Les assets actuels
testes sont en PNG. Une extension PNG ne permet pas de contourner le blocage,
qui porte sur le type detecte depuis les octets. Le patch est dans la bibliotheque,
donc egalement charge dans les processus workers de Metro.

Il s'agit d'une **mitigation**, pas d'un correctif amont : les quatre alertes npm
elevees restent visibles. Ne pas les masquer et ne pas appeler `disableTypes([])`.
Les tests passent par le vrai lecteur d'assets Metro et executent les entrees
hostiles dans des sous-processus avec delai maximal. Le detecteur est encore
execute avant le refus de type ; les tests ne prouvent pas l'absence de toute
autre entree hostile. Reevaluer cette protection a chaque mise a jour du paquet.

## Verification

Les deux jobs de CI exportent aussi les bundles Android/iOS avec Hermes. Cela
ne compile pas les projets natifs Gradle/Xcode et ne remplace pas les essais
sur appareils. Les variables de verification utilisent des domaines de test ;
aucun bundle de cet audit n'est a distribuer en production.

Le test `environment.test.cjs` utilise le vrai preset Babel Expo et execute la
configuration compilee dans un contexte sans variables runtime. Il protege
l'integration des variables publiques, qui doivent etre referencees explicitement
avec `process.env.EXPO_PUBLIC_NOM`, jamais via une cle dynamique.

Depuis `mobile/` :

```sh
npm ci
npm run test:security
npm test --workspace client -- --runInBand
npm test --workspace provider -- --runInBand
npm run typecheck --workspace client
npm run typecheck --workspace provider
npm audit
```
