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
import { traduireMaintenant } from '@/i18n';

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
/* La table porte la CLE, jamais le libelle : traduite ici, elle figerait la langue du
   chargement du module, et le changement de langue ne la rattraperait plus. */
export const MISSION_STATUS_LABELS: Record<string, string> = {
  planned: 'labels.statut_planifiee',
  assigned: 'labels.statut_assignee',
  en_route: 'labels.statut_en_route',
  arrived: 'labels.sur_place',
  started: 'labels.statut_en_cours',
  paused: 'labels.statut_en_pause',
  completed: 'labels.statut_terminee',
  cancelled: 'labels.statut_annulee',
};

export function missionStatusLabel(status: string): string {
  const cle = MISSION_STATUS_LABELS[status];

  return cle ? traduireMaintenant(cle) : status;
}
