# « Brio — Le Film » : les prompts, plan par plan

Tout ce qu'il faut pour **refaire un plan sans refaire les treize autres**.

Les prompts sont en anglais : les modèles de génération y sont nettement plus précis, et aucun de
ces mots n'atterrit à l'écran. Le français ne vit qu'à deux endroits — les écrans de téléphone
(plans 05, 06, 07, 12) et les légendes HTML, qui ne sont pas dans l'image.

---

## 0. La chaîne, en trois gestes

```
1. fiches de casting  → gpt_image_2, 16:9, 2k, quality:high      (6,5 crédits pièce)
2. images-clés 01..14 → gpt_image_2, 16:9, 2k, quality:high      (6,5 crédits pièce)
                        + medias:[{role:'image', value:<job de casting>}] dès qu'un humain apparaît
3. plans vidéo 01..14 → flux_3_video, 5 s, 1080p, 16:9,
                        generate_audio:false,
                        medias:[{role:'start_image', value:<image-clé n>},
                                {role:'end_image',   value:<image-clé n+1>}]   (45 crédits pièce)
```

**La chaîne est un anneau** : le plan 14 a pour `end_image` l'image-clé **01**. La caméra remonte
de la poignée de main jusqu'à la galaxie du début, et le film se referme sur lui-même.

**Deux pièges payés en route.**

1. `generate_video` propose des préréglages (« 3D RENDER », « IN THE DARK », « BLUE DEPTH ») au
   lieu de soumettre. Le premier trahit exactement la consigne de photoréalisme. Passer par
   `generate_video_batch`, qui soumet sans rien proposer ; en cas de refus, renvoyer le même
   appel avec `declined_preset_id` réglé sur l'identifiant refusé.
2. Écrire le français **sans accents** dans un prompt donne un écran sans accents. « fuite sous
   evier » au lieu de « fuite sous évier ». Il faut demander les accents explicitement, et le
   symbole `€` nommément.

---

## 1. Le bloc de style — collé à la fin de CHAQUE prompt

> Photoreal documentary cinematography, large-format cinema camera, fast prime lens, shallow
> depth of field, fine 35mm film grain, **bright natural daylight, clean high-key exposure, open
> shadows with plenty of detail, no crushed blacks**, natural colour, real skin texture with
> visible pores and natural asymmetry, no beauty filter, no digital smoothing, no CGI look, no
> 3D render look, no illustration, no cartoon, no text, no captions, no subtitles, no watermark,
> no logos, 16:9 cinematic framing

**Révisé le 2026-09-10.** La première version était nocturne (`warm amber and old-gold colour
grade, true deep blacks`) et l'utilisateur l'a jugée trop sombre. Les trois expressions qui font
le travail sont `bright natural daylight`, `open shadows with plenty of detail` et surtout
`no crushed blacks` : sans la dernière, le modèle referme les ombres même quand on lui demande
du jour.

Sur les plans qui montrent un écran de téléphone, remplacer les deux dernières lignes par :

> no text anywhere in the image except the interface on the phone screen itself, French only and
> correctly spelled with all accents, no captions, no subtitles, no watermark, no brand logos
> other than the described thin gold ring, 16:9 cinematic framing

---

## 2. Les deux fiches de casting

Sans elles, le plombier change de visage entre deux plans. Elles se génèrent **une fois** ; leur
identifiant de job sert ensuite de référence à toutes les images-clés qui montrent un humain.

### 2.1 Le prestataire — plombier, la quarantaine

> Split-screen character sheet composition, left side a full-body shot of the character standing
> upright in a neutral straight standing pose facing the camera with both feet flat on the ground
> and arms relaxed at the sides, full head-to-toe framing with the whole body and both feet
> visible, right side a tight close-up chest-up portrait of the same character, identical original
> male character on both sides, single subject only exactly one person with only the character in
> frame, pure white seamless studio background, professional character sheet presentation, a
> working plumber in his mid-forties, warm olive-toned southern-European skin, rectangular face
> with a defined angular jawline and mature adult bone structure, prominent cheekbones, straight
> nose with a slightly wide bridge, thin lips with a natural matte finish, deep-set dark brown
> eyes with naturally muted catchlights, no oversized specular glare in the iris, eye color muted
> rather than glowing, thick dark eyebrows with a few stray grey hairs, short dark hair greying at
> the temples, cut close on the sides, slightly uneven end-of-day finish, three-day salt-and-pepper
> stubble, visible fine skin texture with natural pores, fine lines around the eyes and across the
> forehead, subtle asymmetries and texture irregularities, sun-weathered skin on the forearms and
> neck with a faint tan line, a small old scar through the left eyebrow, slight natural sheen from
> a long working day rather than a glossy or dewy retouched finish, no digital smoothing, no
> beauty filter, no AI-airbrushed look, skin completely free of artificial glare, shine or
> highlight blooms, matte-to-natural complexion, sturdy medium build with broad shoulders and a
> slight belly, thick calloused working hands with short blunt nails and faint grease staining in
> the knuckle creases, wearing a plain dark charcoal heavyweight work polo with the sleeves pushed
> up to the forearms and a thin amber-gold piping at the collar, a plain black softshell work
> jacket worn open, dark navy ripstop work trousers with reinforced knee panels and a folding rule
> in the thigh pocket, a worn black leather tool belt, scuffed black steel-toe work boots, a plain
> steel wristwatch on a worn leather strap, a thin plain wedding band, no bag, natural anatomy,
> high-end but unretouched documentary photography style, soft diffused studio lighting without
> harsh reflections, cinematic realism, clean white background, 4K quality, sharp focus on skin
> texture detail, single subject only, exactly one person, only the character in frame, no other
> people, no duplicate figures, no mannequin, no reflections, no props, no furniture, no background
> objects, empty seamless studio, left panel standing full-body head-to-toe not cropped not
> sitting, right panel tight close-up not full body, no babyface, no overly youthful rounded
> proportions, no beauty filter, no digital smoothing, no airbrushing, no plastic skin, no glossy
> skin, no text, no watermark, no logos, no frame borders, original character not resembling any
> real celebrity or existing character

### 2.2 La cliente — la trentaine finissante, en fin de journée

Même armature. Les variables qui changent :

> an ordinary woman in her late thirties at the end of a working day, fair skin with a cool
> neutral undertone and faint natural redness across the cheeks and nose, oval face with a defined
> jawline and mature adult bone structure, high cheekbones, straight narrow nose, medium-full lips
> slightly chapped with a natural matte finish, grey-green almond eyes with a slight downturn,
> naturally muted catchlights, soft natural brown eyebrows slightly uneven in shape, dark blonde
> shoulder-length hair with visible darker roots tied back in a loose low ponytail with strands
> escaping at the temples, slightly flat end-of-day finish, visible fine skin texture with natural
> pores, fine expression lines at the corners of the eyes and between the brows, a scattering of
> faint freckles across the nose and cheekbones, a small mole below the right jaw, day-old makeup
> worn off unevenly with faint mascara shadow under the lower lashes rather than flawless
> coverage, slim build with narrow shoulders and balanced everyday proportions, hands with short
> unpainted nails and one small papercut, wearing an oversized oatmeal-cream ribbed wool cardigan
> with slightly stretched cuffs over a plain white cotton t-shirt, faded mid-blue straight-leg
> jeans, thick charcoal wool socks and no shoes, a thin gold chain necklace, small gold stud
> earrings, a slim silver watch on a worn strap, no bag

*(compléter avec la même queue négative que 2.1)*

---

## 3. Les 14 images-clés

`R+` indique les fiches de casting à passer en `medias` (rôle `image`).

### 01 — La galaxie
> Photoreal deep-space astrophotography plate: a grand-design spiral galaxy seen three-quarters on
> against absolute ink-black space, its dust lanes and star-forming arms glowing in warm amber, old
> gold and pale bronze rather than blue, fine golden interstellar dust drifting in the near
> foreground slightly out of focus, hard white pinpoint stars with realistic diffraction, subtle
> lens veiling glare, composed like a deep-field telescope composite graded warm, the galactic core
> slightly below and right of centre.

### 02 — La Terre
> Photoreal orbital plate shot from the cupola window of a space station: the blue crescent of
> Earth filling the lower two thirds of frame, the terminator line falling across Western Europe,
> the night side glittering with dense golden city lights, a thin atmospheric limb burning warm
> orange along the curved horizon, the Milky Way and one small distant warm-toned spiral galaxy in
> the black above, a few specks of golden dust catching light near the lens, faint lens veiling
> glare.

### 03 — L'Europe de nuit
> Photoreal aerial night plate from a helicopter at nine hundred metres over a Belgian city, camera
> breaking through the underside of a thin cirrus deck, the golden sodium and warm LED street-grid
> of the city laid out below, a river and canals reflecting the light in long amber streaks, church
> spires and brick rooftops, thin atmospheric haze softening the far distance, slight lens veiling
> from the city glow, real aerial photography.

### 04 — La rue
> Photoreal night plate of a quiet residential street of narrow Belgian brick townhouses on a wet
> evening in early autumn, wet cobblestones and asphalt mirroring warm amber street lamps in long
> vertical reflections, a few parked cars beaded with rain, bare plane trees, terraced facades with
> dark windows, and one first-floor window glowing warm gold, camera low and close to the wet
> ground, shallow focus falling off toward the end of the street, real rain, real reflections.

### 05 — Le téléphone de la cliente — `R+ cliente`
> Photoreal interior night plate. The same woman as in the reference image, identical face, hair
> and clothing, sits at a small worn oak table in a modest Belgian kitchen, lit by a single warm
> pendant lamp above her and the cool spill of her phone screen on her face, holding a smartphone
> in both hands and looking down at it with a tired, faintly hopeful expression. Behind her and out
> of focus, a kitchen sink with a dark water stain on the cupboard door below it and a plastic
> bucket on the floor. Camera looks over her shoulder so the phone screen is clearly legible: a
> minimal French-language mobile app on a near-black background, a thin gold ring mark at the top,
> the white cursive wordmark Brio beneath it, then a vertical list of service rows in French
> reading Plomberie, Électricité, Peinture, Nettoyage, with the Plomberie row outlined in warm
> amber, and a wide amber button at the bottom reading Continuer.

### 06 — Le devis IA
> Photoreal macro plate: a smartphone screen fills the entire frame, held slightly angled so a soft
> reflection of a warm ceiling lamp crosses the top corner, faint real screen pixel structure
> visible. The app is in camera mode, showing a live photograph of a leaking chrome sink trap under
> a kitchen cupboard with a single drop of water hanging from the joint, a thin warm amber
> horizontal scanning line sweeping across the photograph, and a floating dark rounded card at the
> bottom of the screen carrying a small amber tick and the French text in correctly accented
> French: "Estimation ≈ 180 – 240 €", with a smaller grey line beneath reading "Prix ferme après
> diagnostic". Render every French accent exactly: the grave accent on the e of après, and the euro
> currency symbol €.

### 07 — Brio Provider accepte — `R+ plombier`
> Photoreal close plate. A thick calloused male hand, identical to the reference image with the
> same skin tone, the same knuckles, the same plain steel watch on a worn leather strap and the
> same thin wedding band, holds a smartphone in the dim cab of a work van at night. The only light
> is the warm amber of the screen on his fingers and a distant street lamp through the wet
> windscreen. The screen shows a dark near-black French-language app: a thin gold ring mark with
> the cursive wordmark "Brio" and the spaced capitals "PROVIDER" beneath it, then a mission card
> reading "Plomberie — fuite sous évier", a line reading "1,2 km", a line reading "210 €", and a
> wide amber button reading "Accepter", his thumb approaching the button. Render every French
> accent exactly: the acute accent on the e of évier, and the euro currency symbol €.

### 08 — Le trajet
> Photoreal night plate: a plain white unbranded work van seen from a low tracking position close
> to wet asphalt, its tail lights smearing red on the wet road, warm amber street lamps streaking
> past on both sides, Belgian brick terraces blurred by motion, the silhouette of a driver just
> visible through the rear window, shallow focus on the rear wheel arch, real rain spray thrown
> from the tyres, real night photography with motion blur. *(ajouter : no brand markings on the van)*

### 09 — Le QR de départ — `R+ cliente, plombier`
> Photoreal night plate on a doorstep. The front door of a Belgian brick townhouse stands open,
> warm golden hallway light spilling onto wet stone. On the left, inside the doorway, the same
> woman as in the first reference image, identical face hair and cream cardigan, holds up her
> smartphone at chest height, its screen showing a large black and white QR code on white. On the
> right, standing on the step, the same man as in the second reference image, identical face and
> stubble, wearing the charcoal work polo with thin amber collar piping under an open black work
> jacket, raises his own phone to scan her screen, his phone seen from behind with a warm amber
> viewfinder frame visible around the QR code. Both are lit warm from the hallway behind her and
> cool from the wet street behind him, real rain on his shoulders.

### 10 — Le travail — `R+ plombier`
> Photoreal close plate under a kitchen sink. The same man as in the reference image, identical
> face and stubble, lies on his back half inside the cupboard with his head and shoulders in frame,
> lit by a small work lamp clipped to the cupboard door casting hard warm light and deep shadow
> across his face. His calloused hands turn an adjustable wrench on the chrome trap of the sink, an
> old grey perished rubber seal set aside on a folded cloth, a shallow puddle catching the lamp
> light, real dust and limescale on the pipework, sweat at his temple, total concentration, sleeves
> of the charcoal work polo pushed up.

### 11 — Le QR de fin — `R+ cliente, plombier`
> Photoreal plate in a modest Belgian kitchen at night. The same woman as in the first reference
> image, identical face and cream cardigan, holds her phone up screen outward towards the left of
> frame, the screen showing a large black and white QR code. The same man as in the second
> reference image, identical face and charcoal work polo, is seen from behind over his shoulder
> raising his own phone to scan it, his screen showing a warm amber viewfinder frame closing on the
> code with a green tick beginning to appear at its centre. The repaired sink is behind them, dry
> and clean, the bucket gone, a warm pendant lamp above, his tools already packed in a bag on the
> floor.

### 12 — Le paiement libéré — `R+ cliente`
> Photoreal close plate. The same woman as in the reference image, identical face hair and cream
> cardigan, holds her smartphone in both hands in the warm light of her kitchen, seen slightly from
> the side and above so the screen is legible. The screen shows a dark near-black French-language
> confirmation card with a green circular tick, a heading reading Mission validée, a line reading
> Paiement libéré 210 €, and beneath it a row of five outlined stars with the first three filled
> warm amber as her thumb reaches the fourth. Her face is soft and relieved, faintly smiling, lit
> warm from the pendant lamp and cool from the screen. Out of focus behind her, the clean dry sink.

### 13 — La poignée de main — `R+ cliente, plombier`
> Photoreal night plate framed from inside the hallway looking out through the open front door. The
> same woman as in the first reference image and the same man as in the second reference image
> shake hands on the doorstep, both genuinely and tiredly smiling at each other rather than at
> camera. Warm golden hallway light rims their shoulders from behind and catches the edge of his
> stubble and her loose escaping hair. The wet street and amber street lamps are out of focus
> beyond them, his tool bag on the step, real grip with the skin of both hands compressing, real
> creases in the sleeves, real fatigue in both faces.

### 14 — Le retrait
> Photoreal night plate from very high above a Belgian city, the golden street-grid spread far
> below with one single point of light slightly warmer and brighter than the rest near the centre
> of frame, thin cloud drifting between camera and city, the curve of the horizon just beginning to
> show at the top of frame with a warm orange atmospheric limb, and the black of space with a
> scattering of hard pinpoint stars above it. Real high-altitude photography, thin atmospheric
> haze, faint lens veiling glare.

---

## 4. Les 14 plans vidéo

Règle commune à tous : **un seul mouvement continu, aucune coupe**, `no shake`, `no whip pans`.
C'est ce qui fait que le film se lit comme une chute unique et non comme un montage.

| # | `start_image` | `end_image` | Le mouvement |
|---|---|---|---|
| 01 | clé 01 | clé 02 | Poussée lente vers la galaxie, la poussière d'or passe devant l'objectif, les étoiles filent, la Terre se lève par le bas. |
| 02 | clé 02 | clé 03 | Chute depuis le hublot, la courbure s'aplatit, la grille dorée d'Europe grandit jusqu'à devenir une vue aérienne à travers un cirrus. |
| 03 | clé 03 | clé 04 | Descente de 900 m, toits et clochers montent des deux côtés, la caméra se pose à l'horizontale au-dessus d'une rue mouillée. |
| 04 | clé 04 | clé 05 | Glissé bas le long de la rue, montée douce vers la fenêtre allumée, **traversée de la vitre**, arrêt par-dessus l'épaule de la cliente. |
| 05 | clé 05 | clé 06 | Son pouce fait défiler la liste et touche « Plomberie ». Poussée par-dessus l'épaule jusqu'à ce que l'écran remplisse le cadre et **soit devenu** le viseur. |
| 06 | clé 06 | clé 07 | La ligne de scan descend, la carte de prix se pose. Poussée dans l'écran jusqu'au blanc ambre, ressortie immédiate sur un second téléphone dans une camionnette. |
| 07 | clé 07 | clé 08 | Le pouce presse « Accepter », l'écran clignote. Recul hors de la cabine, à travers le pare-brise, chute au ras de la route mouillée, la camionnette démarre. |
| 08 | clé 08 | clé 09 | Suivi de la camionnette qui ralentit et s'arrête, la caméra la quitte et s'élève vers un seuil : la porte s'ouvre, elle tend son téléphone, il lève le sien. |
| 09 | clé 09 | clé 10 | Le QR se verrouille, coche verte. La caméra passe entre eux, entre dans la cuisine, plonge sous l'évier et se pose sur lui, clé à molette en main. |
| 10 | clé 10 | clé 11 | Le joint cède, la goutte s'arrête. Il s'essuie les mains et sort du placard ; la caméra monte avec lui et se pose sur eux deux, second QR levé. |
| 11 | clé 11 | clé 12 | Le scan atterrit, la coche verte éclot. Dérive vers elle : carte de validation, rangée d'étoiles que son pouce remplit une à une. |
| 12 | clé 12 | clé 13 | Elle touche la dernière étoile et sourit. Elle marche vers la porte, la caméra tourne avec elle et se cale dans le couloir, cadrant les deux sur le seuil. |
| 13 | clé 13 | clé 14 | La poignée de main. Puis la caméra s'élève et recule à travers la porte, au-dessus de la rue, de plus en plus vite, jusqu'à la ville vue d'en haut. |
| 14 | clé 14 | **clé 01** | Un seul retrait accéléré : à travers le limbe atmosphérique, la Terre devient un point, le champ d'étoiles s'ouvre, la galaxie remplit à nouveau le cadre. |

---

## 5. Le montage — ce que fait le script

`scripts/journey-film/construire-les-frames.sh <dossier-des-plans>`

| Étape | Ce qui se passe | Pourquoi ainsi |
|---|---|---|
| Extraction | `select='not(mod(n,5))'` → **25 images par plan, exactement** | 121 images source par plan ; un `fps=` aurait dérivé de 0,2 image par plan et les chapitres auraient glissé |
| Recadrage | `crop=1920:1080:0:4` | `flux_3_video` rend en 1920×**1088** (multiple de 16), pas 1080 |
| Deux tailles | 1280×720 et 768×432 | le téléphone ne télécharge pas les pixels du bureau |
| Encodage | AVIF, `libaom-av1`, CRF **40** / **42**, `cpu-used 8` | mesuré : CRF 32, 38 et 42 sont indistinguables à 100 % sur ce contenu. CRF 40 économise 39 % |
| Poster | WebP **et** JPEG | c'est le visuel des navigateurs SANS AVIF : il ne peut pas être en AVIF |
| Manifeste | `frames.json` | `JourneyFilmAssetsTest` compare son `total` au nombre de fichiers réellement présents |

**Total : 350 frames × 2 tailles — 7,0 Mo en bureau, 3,3 Mo en mobile**, soit ~500 Ko par chapitre,
et jamais téléchargés par qui ne descend pas.

**Le grain n'est PAS cuit dans les images** : il est reposé par le shader au rendu. C'est ce qui
autorise un AVIF aussi compressé en dessous sans que ça se voie.

---

## 6. Refaire UN plan

1. Regénérer son image-clé si le cadre est en cause (§ 3), sinon garder l'ancienne.
2. Relancer `flux_3_video` avec les mêmes `start_image` / `end_image` (§ 4). Si un préréglage est
   proposé, renvoyer l'appel avec `declined_preset_id`.
3. Remplacer `shotNN.mp4` dans le dossier des plans.
4. Relancer le script de montage. Il refait les 350 frames — c'est deux minutes, et cela garantit
   que le manifeste et le disque ne divergent jamais.
5. `php artisan test --filter=FilmDuParcoursTest` puis `node tools/visual-qa/verif-film.mjs`.
