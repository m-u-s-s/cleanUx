/**
 * Quel espace ouvrir au démarrage de l'application CLIENTE.
 *
 * Les six écrans société — locaux, réservations, membres, contrats, facturation — vivaient derrière
 * une entrée du profil, entre les moyens de paiement et la langue. Un responsable de sites ouvre
 * l'application POUR eux ; les y faire chercher, c'est en faire un réglage.
 *
 * MAIS LE MÊME COMPTE PEUT ÊTRE UN PARTICULIER, et c'est le cas courant : l'organisation est un
 * rattachement du compte, pas un compte séparé. La responsable des locaux d'une société commande
 * aussi son propre ménage. L'enfermer dans l'espace société lui retirerait ses réservations.
 *
 * La décision est extraite en fonction pure pour la même raison que côté prestataire : l'ordre de
 * ses conditions est tout, et le tester à travers un arbre de navigation monté demanderait de
 * simuler la moitié de l'application pour observer une seule branche.
 */

import { adresseAConfirmer } from '@/auth/emailVerification';

export type ClientSpace =
  | 'loading'
  | 'login'
  | 'emailNonConfirme'
  | 'personal'
  | 'clientCompany'
  | 'switcher';

/** L'espace qu'un compte à double vie a choisi, quand il en a choisi un. */
export type ChosenClientSpace = 'personal' | 'clientCompany';

export interface ClientSpaceInput {
  isLoading: boolean;
  isAuthenticated: boolean;
  user: {
    /**
     * Appartenance à une société CLIENTE.
     *
     * C'est ici le bon drapeau, et le seul : `User::isEntreprise()` délègue à `isClientCompany()`,
     * vrai pour `client_company` comme pour `hybrid`. On ne l'accompagne PAS d'un
     * `organization_type` — c'est précisément la conjonction qui a rendu l'espace prestataire
     * inatteignable pendant toute une livraison.
     */
    is_entreprise?: boolean;
    /** Servis par `/auth/me` et par la connexion. Absents des jetons du parc deja installe. */
    email_verified?: boolean;
    email_verified_at?: string | null;
  } | null;
  chosenSpace?: ChosenClientSpace;
}

export function resolveClientSpace(input: ClientSpaceInput): ClientSpace {
  const { isLoading, isAuthenticated, user, chosenSpace } = input;

  if (isLoading) {
    return 'loading';
  }

  if (!isAuthenticated || !user) {
    return 'login';
  }

  /*
   * L'ADRESSE NON CONFIRMEE BARRE TOUT, ET L'EMPLACEMENT EST LE PROPOS.
   *
   * Contrairement au choix d'espace, elle ne depend d'aucune casquette : le serveur exige une
   * adresse confirmee sur 530 de ses 537 routes authentifiees. Placee plus bas, elle laisserait
   * ouvrir un espace dont chaque requete repond 403.
   *
   * L'INCONNU LAISSE PASSER — `adresseAConfirmer` le tient, et c'est la meme regle que le dossier
   * d'inscription cote prestataire : un jeton emis avant ce champ n'enferme personne dehors.
   */
  if (adresseAConfirmer(user)) {
    return 'emailNonConfirme';
  }

  const membreSociete = user.is_entreprise === true;

  /*
   * LE DROIT PASSE AVANT LE CHOIX, ET L'ORDRE EST LE PROPOS.
   *
   * Quelqu'un qui quitte son entreprise garde son choix « société » dans le stockage local, mais
   * perd le drapeau. Tester le choix d'abord ouvrirait un espace dont chaque écran répond 403, et
   * dont il ne pourrait pas sortir : le sélecteur ne s'affiche qu'à qui a le choix.
   */
  if (!membreSociete) {
    return 'personal';
  }

  if (!chosenSpace) {
    return 'switcher';
  }

  return chosenSpace === 'clientCompany' ? 'clientCompany' : 'personal';
}
