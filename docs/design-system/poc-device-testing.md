# POC Device Testing — Task 23 (MANUAL)

## Prerequisites
1. Run `npm run build`
2. Run `npx cap sync`
3. Open iOS: `npx cap open ios`
4. Open Android: `npx cap open android`

## Checklist iOS 17 (iPhone 13 Pro)

- [ ] Home renders en mode clair (mode planifié)
- [ ] Adaptive switch fonctionne (cliquer une mission active → mode sombre)
- [ ] FAB urgence visible bottom right (thumb zone)
- [ ] Bottom nav blur effect rendu correctement
- [ ] Tap quick action → événement dispatché (vérifier console Safari Web Inspector)
- [ ] Mission live tracking : ETA card glassmorphic visible
- [ ] QR scan CTA disabled quand provider pas encore arrivé
- [ ] Pas d'erreurs console

## Checklist Android 14 (Pixel 7)

- [ ] Mêmes vérifications qu'iOS
- [ ] Attention : backdrop-filter peut ne pas être supporté sur Android plus anciens — fallback color OK ?

## Performance ciblée

- Bundle JS par island : < 80 KB gzipped
- FCP sur 4G : < 1500 ms
- LCP : < 2500 ms
- Pas de layout shifts (CLS < 0.1)

**Document results** ci-dessous après testing manuel :

### Résultats iOS
Date : ___
Notes : ___

### Résultats Android
Date : ___
Notes : ___
