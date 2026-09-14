import type { ChargeUtilePush, DestinationPush } from '@/push';

/**
 * OÙ MÈNE UNE NOTIFICATION, CÔTÉ PRESTATAIRE.
 *
 * Même constat que côté client : le serveur envoie un `type` et des identifiants, jamais un nom
 * d'écran. L'offre de mission expire en 20 secondes — c'est la notification qui supporte le moins
 * bien d'ouvrir l'application sur l'écran où elle se trouvait.
 */
export function routagePushPrestataire(data: ChargeUtilePush): DestinationPush {
  const type = typeof data.type === 'string' ? data.type : '';
  const entier = (cle: string): number | null => {
    const brut = data[cle];
    const valeur = typeof brut === 'string' ? Number.parseInt(brut, 10) : brut;

    return typeof valeur === 'number' && Number.isFinite(valeur) ? valeur : null;
  };

  switch (type) {
    case 'mission.offer':
    case 'mission_offer':
      return { ecran: 'MissionInbox' };

    case 'asap_search_outcome':
      return { ecran: 'AsapOffers' };

    case 'mission.assigned':
    case 'mission_assigned':
    case 'booking_rescheduled': {
      const missionId = entier('mission_id');

      return missionId === null ? null : { ecran: 'MissionDetail', params: { missionId } };
    }

    case 'conversation_message':
    case 'chat.message': {
      const threadId = entier('thread_id') ?? entier('conversation_id');

      if (threadId === null) {
        return { ecran: 'ProviderChatList' };
      }

      const title = typeof data.title === 'string' && data.title !== '' ? data.title : 'Message';

      return { ecran: 'ProviderChat', params: { threadId, title } };
    }

    case 'face_check_blocked':
    case 'face_check_unblocked':
      return { ecran: 'FaceCheck' };

    // Les gains sont un ONGLET, pas un écran de pile : on y entre par `MainTabs`.
    case 'manual_payout':
    case 'payout.sent':
      return { ecran: 'MainTabs', params: { screen: 'Earnings' } };

    default:
      return null;
  }
}
