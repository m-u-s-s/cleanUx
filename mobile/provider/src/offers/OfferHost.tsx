import React, { useEffect } from 'react';
import { useCurrentOffer } from './hooks';
import { OfferModal } from './OfferModal';
import { libererCarillon } from './sound';

/**
 * LE POINT DE MONTAGE UNIQUE DE LA MODALE D'OFFRE.
 *
 * Il vit dans `TabNavigator`, aux côtés de `usePresenceHeartbeat()`, et pour la même raison : cette
 * pile est montée UNE FOIS par session terrain et n'est jamais démontée. La modale s'ouvre donc
 * quel que soit l'onglet affiché — le tableau de bord, les missions, les gains — au lieu de
 * dépendre d'un écran que le prestataire aurait pu quitter.
 *
 * IL NE VIT PAS À LA RACINE DE L'APPLICATION. Un gérant de société ou un administrateur ne doit pas
 * voir de modale d'offre : sa pile n'inclut pas celle-ci, exactement comme elle n'inclut pas le
 * battement de présence.
 *
 * IL NE REND RIEN QUAND IL N'Y A PAS D'OFFRE — pas même un conteneur vide : un `View` transparent
 * posé au-dessus de la carte intercepterait les gestes du tableau de bord.
 */
export function OfferHost() {
  const { offer, dismiss } = useCurrentOffer();

  /*
   * LA RESSOURCE AUDIVE SE LIBERE ICI, ET NULLE PART AILLEURS.
   *
   * `jouerCarillonDOffre` garde son lecteur natif entre deux offres — a dessein, recreer la
   * ressource a chaque fois ajouterait une latence la ou on en a le moins. `libererCarillon`
   * existait pour le rendre, son propre commentaire disait « appele quand l'hote d'offres est
   * demonte », et PERSONNE ne l'appelait : le lecteur survivait a la session terrain.
   *
   * Pas dans la modale : elle va et vient a chaque offre, c'est precisement ce qu'on evite.
   */
  useEffect(() => libererCarillon, []);

  if (!offer) {
    return null;
  }

  return <OfferModal offer={offer} onDismiss={dismiss} />;
}
