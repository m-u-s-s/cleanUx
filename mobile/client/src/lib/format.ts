/**
 * Relais vers la source partagée.
 *
 * Ces aides vivaient ici, dans la seule application cliente. Les mêmes défauts — statut technique
 * affiché tel quel, date ISO, message interne d'axios — sont réapparus à l'identique dans
 * l'application prestataire dès qu'on l'a ouverte. Elles ont donc rejoint `@brio/shared`.
 *
 * Ce fichier reste comme point d'entrée : les écrans et les tests qui l'importaient déjà n'ont pas
 * à changer de chemin pour une correction qui ne les concerne pas.
 */
export {
  libelleStatut,
  formatDateHeure,
  formatAdresse,
  formatDelai,
  messageDErreur,
} from '@brio/shared/format';
