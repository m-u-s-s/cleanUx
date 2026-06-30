# Assets du « Parcours d'une mission » (monde 3D)

Ce dossier alimente le scrollytelling 3D de la home (`resources/js/home-journey.js`).
Chaque fichier est **optionnel** : s'il manque, la scène utilise un placeholder
neutre (téléphone gris, billboard invisible) — rien ne casse. Déposez vos vrais
visuels ici et le rendu devient photoréaliste, sans toucher au code.

> Important — droits : n'utilisez que des images dont vous détenez la licence
> (vos propres captures d'app, photos sous licence/stock payé, ou assets CC0).
> Pour les personnes, prévoyez le droit à l'image.

## Fichiers attendus

Chemin servi : `/images/journey/<fichier>` (dossier `public/`).

### Captures de votre application (écran du téléphone 3D)
Format **JPG**, ratio **9:19.5** environ (ex. 1080×2240), < 300 Ko chacune.

| Fichier            | Écran à montrer                                  | Scène |
|--------------------|--------------------------------------------------|-------|
| `app-booking.jpg`  | Réservation d'une mission (choix service)        | 1     |
| `app-quote.jpg`    | Devis IA depuis photo (fourchette de prix)       | 2     |
| `app-map.jpg`      | Suivi temps réel / carte + ETA                   | 4     |
| `app-done.jpg`     | Mission validée / paiement libéré                | 5,7   |

### Cutouts photoréalistes (personnes & objets)
Format **PNG transparent** (détouré), hauteur ~1200–1600 px, < 400 Ko.

| Fichier        | Sujet                                             | Scènes  |
|----------------|---------------------------------------------------|---------|
| `provider.png` | Un prestataire (pro en tenue, souriant)           | 5,6,7,8 |
| `client.png`   | Un client / une cliente                            | 1,7,8   |
| `house.png`    | Une maison / une porte / une pièce à rénover       | 2,5     |
| `tools.png`    | Des outils (perceuse, pinceau, etc.)               | 6       |

## Conseils
- Fond **transparent** pour les cutouts (sinon un rectangle photo apparaît dans la 3D).
- Lumière neutre et cadrage vertical pour les personnes (elles sont posées comme
  des « billboards » qui font toujours face à la caméra).
- Optimisez (TinyPNG / squoosh) : ces assets se chargent sur la home.

## Évolution possible
Le téléphone est une primitive 3D (corps métal + écran = votre capture). Pour un
modèle de smartphone CC0 (.glb), on peut brancher un `GLTFLoader` plus tard et
remplacer `buildPhone()` sans rien changer d'autre. Idem pour des personnages 3D
animés (glTF skinné) à la place des billboards photo.
