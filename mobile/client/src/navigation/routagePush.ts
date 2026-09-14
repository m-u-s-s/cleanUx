import type { ChargeUtilePush, DestinationPush } from '@/push';

/**
 * OÙ MÈNE UNE NOTIFICATION, CÔTÉ CLIENT.
 *
 * Le serveur n'envoie pas de nom d'écran — il envoie un `type` et des identifiants (vérifié :
 * `grep "'screen' =>" app/` rend zéro sur 84 charges utiles). Cette table est le pont, et elle
 * est écrite ici parce que `shared` ne connaît pas la pile du client.
 *
 * Un `type` absent de la table ouvre l'application sans naviguer — c'est le comportement d'avant,
 * et il reste juste pour tout ce qui n'a pas d'écran dédié.
 */
export function routagePushClient(data: ChargeUtilePush): DestinationPush {
  const type = typeof data.type === 'string' ? data.type : '';
  const entier = (cle: string): number | null => {
    const brut = data[cle];
    const valeur = typeof brut === 'string' ? Number.parseInt(brut, 10) : brut;

    return typeof valeur === 'number' && Number.isFinite(valeur) ? valeur : null;
  };

  switch (type) {
    // Le prestataire est en route ou arrivé : le suivi, pas l'accueil.
    case 'tracking.enroute':
    case 'tracking.arrived':
    case 'employee_en_route':
    case 'employee_arrived': {
      const bookingId = entier('booking_id') ?? entier('rendez_vous_id');

      return bookingId === null ? null : { ecran: 'MissionTracking', params: { bookingId } };
    }

    case 'conversation_message':
    case 'chat.message': {
      const threadId = entier('thread_id') ?? entier('conversation_id');

      if (threadId === null) {
        return { ecran: 'ChatList' };
      }

      // `Chat` exige un titre : le prendre du serveur quand il y est, sinon un libellé neutre.
      const title = typeof data.title === 'string' && data.title !== '' ? data.title : 'Message';

      return { ecran: 'Chat', params: { threadId, title } };
    }

    case 'dispute_opened':
    case 'dispute_updated':
    case 'dispute_resolved':
      return { ecran: 'Disputes' };

    case 'booking':
    case 'booking_rescheduled':
    case 'client_no_show': {
      const bookingId = entier('booking_id') ?? entier('rendez_vous_id');

      return bookingId === null ? null : { ecran: 'BookingDetail', params: { bookingId } };
    }

    case 'feedback_invite':
    case 'rating.requested': {
      const bookingId = entier('booking_id') ?? entier('rendez_vous_id');

      return bookingId === null ? null : { ecran: 'Rating', params: { bookingId } };
    }

    case 'gdpr_export_ready':
    case 'gdpr_request_created':
      return { ecran: 'GDPR' };

    default:
      return null;
  }
}
