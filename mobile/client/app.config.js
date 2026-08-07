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
 *   2. la restreindre au package com.brio.client et à l'empreinte SHA-1 de signature ;
 *   3. l'exposer en EXPO_PUBLIC_GOOGLE_MAPS_API_KEY (localement, et comme secret EAS pour les
 *      builds : `eas secret:create --name EXPO_PUBLIC_GOOGLE_MAPS_API_KEY --value <clé>`) ;
 *   4. RECONSTRUIRE — c'est de la configuration native, un rechargement JS ne suffit pas.
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
