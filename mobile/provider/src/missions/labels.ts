/**
 * Libellés français des statuts de mission.
 *
 * Les statuts sont des identifiants techniques anglais que les écrans affichaient tels quels :
 * un prestataire lisait « en_route » puis « arrived » dans son badge. Le vocabulaire du domaine
 * dit « sur place », pas « arrived ».
 *
 * Le repli sur la valeur brute est délibéré : un statut inconnu doit rester visible plutôt que
 * disparaître derrière un libellé vide, sans quoi un état non prévu deviendrait indiscernable
 * d'une absence de statut.
 */
export const MISSION_STATUS_LABELS: Record<string, string> = {
  pending: 'En attente',
  planned: 'Planifiée',
  assigned: 'Assignée',
  en_route: 'En route',
  arrived: 'Sur place',
  started: 'En cours',
  in_progress: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée',
};

export function missionStatusLabel(status: string): string {
  return MISSION_STATUS_LABELS[status] ?? status;
}
