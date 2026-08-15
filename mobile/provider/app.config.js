/**
 * Configuration Expo dynamique — complète app.json plutôt que de le remplacer.
 *
 * Raison d'être : la clé Google Maps doit venir de l'environnement, jamais du dépôt. `app.json`
 * étant du JSON statique, il n'y interpole rien ; ce fichier est le seul endroit où l'injecter.
 *
 * Sans clé, react-native-maps ne dégrade pas sur Android — il LÈVE :
 *
 *   IllegalStateException: API key not found. Check that
 *   <meta-data android:name="com.google.android.geo.API_KEY" ...> is in the <application> element
 *
 * et emporte le tableau de bord, dont la carte est l'élément principal. `isMapRenderable()`
 * (src/maps/module.ts) vérifie donc sa présence avant de monter la carte, et affiche un repli
 * quand elle manque — l'app reste utilisable, la carte seule est absente.
 *
 * Pour l'activer :
 *   1. créer une clé « Maps SDK for Android » sur console.cloud.google.com ;
 *   2. la restreindre au package com.brio.provider et à l'empreinte SHA-1 de signature ;
 *   3. l'exposer en EXPO_PUBLIC_GOOGLE_MAPS_API_KEY (localement, et comme secret EAS pour les
 *      builds : `eas secret:create --name EXPO_PUBLIC_GOOGLE_MAPS_API_KEY --value <clé>`) ;
 *   4. RECONSTRUIRE — c'est de la configuration native, un rechargement JS ne suffit pas.
 *
 * ---
 *
 * Pourquoi `slug` vaut « cleanux-provider » alors que la marque est Brio — NE PAS « corriger ».
 *
 * Le slug d'un projet EAS est figé à sa création : expo.dev laisse renommer le nom d'affichage,
 * jamais le slug. Le projet a été créé sous CleanUx, il gardera ce slug à vie. Or EAS compare le
 * slug d'app.json à celui du serveur et REFUSE toute commande en cas d'écart :
 *
 *   Slug for project identified by "extra.eas.projectId" (cleanux-provider) does not match
 *   the "slug" field (brio-provider)
 *
 * Le renommage vers Brio l'avait aligné sur la marque, ce qui a bloqué tous les builds. Le seul
 * moyen d'avoir « brio-provider » serait de créer un nouveau projet EAS — au prix de l'historique
 * de builds et de la clé de signature Android, donc de la capacité à mettre à jour les APK déjà
 * installées. Le slug ne se voit nulle part côté utilisateur : le nom affiché sur le téléphone
 * vient de `expo.name` (« brio Pro ») et l'identité du binaire de `android.package`
 * (`com.brio.provider`). Seule l'URL du projet sur expo.dev porte encore l'ancien nom.
 */
module.exports = ({ config }) => {
  const googleMapsApiKey = process.env.EXPO_PUBLIC_GOOGLE_MAPS_API_KEY ?? '';

  return {
    ...config,
    android: {
      ...config.android,
      // Clé absente : on n'écrit PAS de `config.googleMaps` plutôt que d'y mettre une chaîne
      // vide. Une clé vide passerait la vérification de isMapRenderable() et laisserait le crash
      // se produire — l'absence doit rester une absence.
      ...(googleMapsApiKey
        ? { config: { ...config.android?.config, googleMaps: { apiKey: googleMapsApiKey } } }
        : {}),
    },
  };
};
