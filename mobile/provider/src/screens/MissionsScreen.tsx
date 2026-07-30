import React from 'react';
import { MissionsListScreen } from './MissionsListScreen';

/**
 * L'onglet Missions montre les missions du prestataire — celles qu'il a acceptées.
 *
 * Il affichait la boîte de réception, qui ne liste que les OFFRES en attente : accepter une
 * mission la faisait donc disparaître de l'application. Elle restait atteignable un instant, par
 * la navigation qui suit l'acceptation, puis plus du tout — un retour en arrière, et le
 * prestataire n'avait plus aucun moyen de retrouver la mission qu'il venait de prendre.
 *
 * `MissionsListScreen` interroge `/provider/missions/active` et existait déjà, complet, mais
 * n'était monté nulle part. Les offres restent accessibles depuis la carte du tableau de bord et
 * depuis la route `MissionInbox`, vers laquelle renvoie l'écran vide.
 */
export function MissionsScreen() {
  return <MissionsListScreen />;
}
