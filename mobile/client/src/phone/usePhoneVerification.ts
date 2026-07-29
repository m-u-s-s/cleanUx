import { useMutation } from '@tanstack/react-query';
import { apiClient, ApiError } from '@/api';

/**
 * Vérification du téléphone d'un client déjà connecté.
 *
 * Les deux appels visaient `/phone/verify-request` et `/phone/verify-confirm`, sans le préfixe
 * `client` : aucune route n'existe à ces adresses. Les requêtes partaient donc en 404 et la
 * vérification du téléphone n'a jamais pu aboutir depuis l'application cliente.
 *
 * À ne pas confondre avec `/auth/phone/*`, publiques, qui vérifient un numéro AVANT que le compte
 * existe — celles-là servent au premier écran de l'inscription prestataire.
 */
export function useRequestOtp() {
  return useMutation<void, ApiError, { phone: string }>({
    mutationFn: async ({ phone }) => {
      await apiClient.post('/client/phone/verify-request', { phone });
    },
  });
}

export function useConfirmOtp() {
  return useMutation<void, ApiError, { phone: string; code: string }>({
    mutationFn: async ({ phone, code }) => {
      await apiClient.post('/client/phone/verify-confirm', { phone, code });
    },
  });
}
