import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';
import { secureStore } from '@/storage/secureStore';
import type { User } from '@/api/types';

/**
 * LE SECOND FACTEUR FAIT PARTIE DE LA CONNEXION.
 *
 * Le serveur réclame un code quand le compte a activé la 2FA (`error_code: two_factor_required`) et
 * refuse d'émettre un jeton sans lui. Sans ces deux champs, l'application ne pouvait pas répondre :
 * la connexion échouait indéfiniment sur un compte protégé, et la console d'administration native
 * — qui exige désormais la 2FA — devenait inatteignable.
 *
 * `recoveryCode` n'est pas décoratif : sans code de secours, un téléphone perdu ferme le compte.
 */
interface LoginInput {
  email: string;
  password: string;
  deviceName?: string;
  twoFactorCode?: string;
  recoveryCode?: string;
}
interface LoginResult { token: string; user: User; }

/** Le code d'erreur que le serveur renvoie quand il attend le second facteur. */
export const SECOND_FACTEUR_REQUIS = 'two_factor_required';

export function useLogin() {
  return useMutation<LoginResult, ApiError, LoginInput>({
    mutationFn: async (input) => {
      const res = await apiClient.post('/auth/login', {
        email: input.email, password: input.password,
        device_name: input.deviceName ?? 'brio-mobile',
        // Omis tant que l'utilisateur n'a rien saisi : le serveur distingue « pas de code » de
        // « code vide », et un champ vide envoyé d'emblée compterait comme une tentative fausse.
        ...(input.twoFactorCode ? { two_factor_code: input.twoFactorCode } : {}),
        ...(input.recoveryCode ? { recovery_code: input.recoveryCode } : {}),
      });
      await secureStore.setToken(res.data.token);
      return { token: res.data.token, user: res.data.user };
    },
  });
}
