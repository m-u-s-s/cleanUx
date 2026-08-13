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
/**
 * LE VOCABULAIRE DES STATUTS DE MISSION — exactement celui du serveur, ni plus ni moins.
 *
 * Cette table contenait `in_progress`, un statut que `MissionStatus` n'a JAMAIS déclaré et que le
 * serveur n'émet nulle part. Le légitimer ici suffisait à le rendre crédible : deux écrans ont
 * conditionné leurs actions dessus — le partage GPS et le bouton de clôture sur l'écran terrain,
 * les actions finales sur l'écran de détail — et n'ont donc jamais rien affiché sur une mission
 * démarrée, qui porte `started`.
 *
 * `pending` a été retiré pour la même raison : il n'appartient pas au vocabulaire des missions.
 * Une mission qui n'est assignée à personne est `planned`.
 *
 * `MissionsStatutsAlignesTest` interdit désormais toute réapparition : cette table doit rester un
 * miroir de `MissionStatus::all()`.
 */
export const MISSION_STATUS_LABELS: Record<string, string> = {
  planned: 'Planifiée',
  assigned: 'Assignée',
  en_route: 'En route',
  arrived: 'Sur place',
  started: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée',
};

export function missionStatusLabel(status: string): string {
  return MISSION_STATUS_LABELS[status] ?? status;
}
