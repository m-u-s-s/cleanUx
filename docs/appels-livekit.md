# Appels audio / vidéo (LiveKit) — mise en service et essai manuel

Le lot 8 du chantier « société prestataire » livre la moitié serveur des appels : salles, jetons,
machine à états, diffusion de la bannière, délai de sonnerie. **La moitié terminale reste inerte
tant que deux choses n'ont pas été faites**, et aucune ne peut être validée par la suite de tests.

## Ce qui fonctionne déjà, sans rien installer

- `POST /api/provider/company/channels/{id}/calls` ouvre un appel, diffuse `CallStarted` sur
  `channel.{id}`, envoie un push aux autres membres et arme le délai de sonnerie.
- `POST /api/provider/company/calls/{id}/token` délivre un jeton — et **vaut décrocher**.
- `POST /api/provider/company/calls/{id}/end` raccroche ; un appel qui sonnait encore devient
  `missed`, jamais `ended`.
- Sans clé LiveKit, l'API répond **503 explicite** plutôt que de livrer un jeton que le serveur de
  médias rejetterait.

Côté mobile, le bouton « Appeler » et la bannière d'appel entrant sont branchés et testés. L'écran
d'appel affiche « Appel indisponible sur cet appareil » tant que le natif n'est pas là — c'est un
message, pas une panne.

## Ce qu'il reste à faire pour un appel réel

### 1. Un serveur LiveKit

En développement, le plus court est le conteneur officiel :

```
docker run --rm -p 7880:7880 -p 7881:7881 -p 7882:7882/udp \
  -e LIVEKIT_KEYS="macle: monsecret" \
  livekit/livekit-server --dev
```

Puis dans `.env` :

```
LIVEKIT_URL=ws://192.168.x.x:7880
LIVEKIT_API_KEY=macle
LIVEKIT_API_SECRET=monsecret
```

**L'adresse doit être joignable depuis le téléphone**, pas `localhost` : un appareil physique ne
résout pas la boucle locale du poste de développement.

### 2. Le module natif, et une RECONSTRUCTION du dev-client

```
cd mobile/provider
npx expo install @livekit/react-native @livekit/react-native-webrtc
npx expo prebuild --clean
npx expo run:android   # ou run:ios
```

**Ce n'est pas un `npm install` de plus.** `@livekit/react-native` embarque du natif : tant que le
dev-client n'est pas reconstruit, `mobile/provider/src/company/liveKitRoom.ts` rend `null` et
l'écran l'annonce. C'est délibéré — un import statique aurait fait tomber `tsc` sur toute la base
pour un module absent.

**Piège connu de ce dépôt** : un plugin Expo sans `app.plugin.js` fait échouer le build en accusant
`expo-modules-core`. L'erreur désigne toujours le mauvais coupable ; vérifier d'abord la section
`plugins` d'`app.json`.

Les permissions micro et caméra doivent être déclarées avant le `prebuild`, sinon l'appel se
connecte et personne n'entend rien.

## Essai manuel de bout en bout

1. Deux comptes de la **même** société, membres du **même** canal, sur deux appareils.
2. Appareil A : ouvrir la conversation, appuyer sur « 📞 Appeler ».
3. Appareil B : la bannière « Appel entrant » doit apparaître **sans rafraîchir** — c'est la preuve
   que la diffusion `channel.{id}` fonctionne. Application fermée, c'est le push qui doit arriver.
4. Répondre : l'appel passe `active` en base, et la sonnerie s'arrête chez A.
5. Raccrocher d'un côté : le statut passe `ended`.
6. Rejouer en **ne répondant pas** : après `LIVEKIT_RING_TIMEOUT` secondes (45 par défaut), l'appel
   doit être `missed` — pas `ended`. C'est la distinction qui compte quand on reprend son téléphone.

## Ce qui n'est volontairement pas fait

- **`MaskedCallService` (Twilio Proxy) reste intact.** Il répond à un autre besoin — masquer les
  numéros entre client et prestataire — et le confondre avec les appels internes donnerait aux deux
  le pire des deux.
- **Pas d'enregistrement des appels.** Cela engage des obligations légales (consentement, durée de
  conservation, accès) qui n'ont pas été tranchées côté produit.
- **Pas d'appel de groupe au-delà de ce que la salle permet naturellement.** L'interface montre un
  état, pas une grille de participants.
