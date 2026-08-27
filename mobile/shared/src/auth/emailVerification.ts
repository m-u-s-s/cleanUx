import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import type { User } from '@/api/types';

/**
 * L'adresse e-mail confirmée, côté application.
 *
 * Depuis le 2026-08-27 le serveur l'exige sur 530 de ses 537 routes authentifiées : sans elle,
 * chaque écran collectionnerait un `403 email_non_verifie` sans jamais rendre la main. Les sept
 * exemptions ouvrent la seule issue possible — et ce sont exactement celles dont cet écran se sert.
 */

export interface EtatDeConfirmation {
  /** Servi par `/auth/me` et par la connexion. */
  email_verified?: boolean;
  email_verified_at?: string | null;
}

/**
 * L'INCONNU LAISSE PASSER — la même règle que le dossier d'inscription et le contrôle facial.
 *
 * Un jeton émis avant que le serveur ne serve ce champ ne porte ni l'un ni l'autre : bloquer sur
 * une absence enfermerait dehors tout le parc déjà installé. Le serveur, lui, refusera de toute
 * façon ; la sécurité se joue là, pas dans la navigation.
 */
export function adresseAConfirmer(user: EtatDeConfirmation | null | undefined): boolean {
  if (!user) {
    return false;
  }

  if (typeof user.email_verified === 'boolean') {
    return !user.email_verified;
  }

  // `undefined` : le champ n'est pas servi. `null` : il l'est, et l'adresse n'est pas confirmée.
  if (user.email_verified_at === undefined) {
    return false;
  }

  return user.email_verified_at === null;
}

export interface RenvoiDeConfirmation {
  dejaConfirmee: boolean;
  message: string;
}

/** `POST /api/auth/email/verification-notification` — idempotent, plafonné à 6 par minute. */
export function useRenvoyerLEmailDeConfirmation() {
  return useMutation<RenvoiDeConfirmation, ApiError, void>({
    mutationFn: async () => {
      const res = await apiClient.post('/auth/email/verification-notification');

      return {
        dejaConfirmee: res.data?.already_verified === true,
        message: res.data?.message ?? '',
      };
    },
  });
}

/**
 * Relit le compte pour savoir si la confirmation a eu lieu.
 *
 * `/auth/me` est l'une des sept routes exemptées, sans quoi cet écran ne pourrait jamais
 * constater sa propre résolution.
 */
export function useRelireLeCompte() {
  return useMutation<User, ApiError, void>({
    mutationFn: async () => (await apiClient.get('/auth/me')).data.user,
  });
}
