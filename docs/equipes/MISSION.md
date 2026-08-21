# MISSION — Plateforme 100 % propre, de A à Z

**Donnée par l'utilisateur à T4 le 2026-08-19.** T4 la découpe et la distribue.

## L'objectif, mot pour mot

> Aucun utilisateur ne doit tomber sur une erreur, un bug, un affichage cassé ou
> une mission fantôme. La plateforme doit être 100 % propre.

## Les cinq exigences

1. **Parcourir l'entièreté de la plateforme, de A à Z.** Chaque équipe balaye
   TOUTES les pages de son périmètre — web et natif. 899 routes dont 532 en GET.
   Pas d'échantillon : l'intégralité.
2. **Traquer les bugs en se faufilant partout.** On ne teste pas le chemin heureux.
   On force les URL, on manipule les paramètres, on entre par les bords.
3. **Complétude des modules par rôle.** T3 vérifie AVEC T1 et T2 qu'il ne manque
   aucun module à un rôle donné. Un rôle sans son module est un défaut, même si
   aucune page ne plante.
4. **Améliorer chaque module, ensemble, par rôle** — sans jamais casser sa
   fonction première. Toute amélioration se discute entre les équipes concernées.
5. **Optimiser le code** partout où cela accélère la plateforme ou lui permet de
   tenir plus de données, de clients, de prestataires et de missions.

## Ce que « 100 % propre » veut dire, mesurable

Un périmètre est propre quand, pour CHAQUE page :

- [ ] Statut HTTP attendu — jamais 500, et le 403 est voulu, pas subi.
- [ ] Aucune exception PHP, aucune erreur JavaScript en console.
- [ ] Aucune requête réseau en échec.
- [ ] Affichage intact à 390 px comme en grand écran : pas de débordement
      horizontal, pas de texte coupé, pas de cible tactile hostile.
- [ ] Aucune donnée fantôme : pas de mission sans prestataire joignable, pas de
      ligne orpheline affichée, pas de compteur qui ment.
- [ ] Le module est **joignable** depuis la navigation du rôle — pas seulement
      en tapant l'URL.
- [ ] Mode sombre traité, mouvement réduit et lecteur d'écran respectés.
- [ ] Pas de requête N+1 sur les listes, pas de page qui s'effondre à 10 000 lignes.

## Découpage en lots

| Lot | Qui | Objet |
|---|---|---|
| **L0** | T4 | Instrumentation : balayage exhaustif des 532 routes GET × 6 profils. Sans mesure, personne ne corrige — on devine. |
| **L1** | T1, T2, T3 | Chaque équipe corrige les erreurs dures de son périmètre (500, exceptions, pages blanches). |
| **L2** | T1, T2, T3 | Erreurs d'affichage : débordement, texte coupé, sombre, 390 px. |
| **L3** | T3 avec T1 + T2 | Complétude des modules par rôle. Le manque se prouve, il ne se suppose pas. |
| **L4** | T1, T2, T3 | Données fantômes : missions sans prestataire, compteurs menteurs, lignes orphelines. |
| **L5** | croisé | Amélioration de chaque module par rôle, sans casser la fonction première. |
| **L6** | croisé | Optimisation : N+1, index, pagination, montée en charge. |
| **L7** | T4 | Suite complète, Pint, PHPStan. Verdict final. |

## Suivi en direct

L'utilisateur suit le travail en direct. Chaque page corrigée est **ouverte
devant lui** — dans le navigateur pour le web, dans l'émulateur Android pour le
natif. Une correction qu'on ne montre pas n'est pas une correction démontrée.

## Règle qui prime sur tout le reste

**Ne rien casser.** Optimiser est encouragé, mais le code modifié doit toujours
faire ce pour quoi il a été écrit. En cas de doute, le test qui prouve l'ancien
comportement s'écrit AVANT la modification.
