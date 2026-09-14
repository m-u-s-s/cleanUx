import { destinationDe } from '@/push';
import { routagePushClient } from '../../src/navigation/routagePush';

/**
 * LES DEUX CONTRATS SE CONFRONTENT ENFIN.
 *
 * Le hook n'agissait que sur `data.screen`, que le serveur n'envoie JAMAIS : `grep "'screen' =>"
 * app/` rend zéro sur 84 charges utiles. « Votre prestataire arrive » ouvrait l'application là où
 * elle était. On exerce ici `destinationDe` plutôt que le hook : sous Jest, `expo-notifications`
 * manque, l'import dynamique échoue et le corps du listener n'est jamais atteint.
 */
describe('routage des notifications — client', () => {
  const routeur = (data: Record<string, unknown>) => destinationDe(data, routagePushClient);

  it('mène au suivi quand le prestataire est en route', () => {
    expect(routeur({ type: 'tracking.enroute', booking_id: 42 })).toEqual({
      ecran: 'MissionTracking',
      params: { bookingId: 42 },
    });
  });

  it("accepte l'identifiant en chaîne, comme Stripe et FCM les transmettent", () => {
    expect(routeur({ type: 'employee_arrived', booking_id: '42' })).toEqual({
      ecran: 'MissionTracking',
      params: { bookingId: 42 },
    });
  });

  it('mène au fil de discussion, avec son titre', () => {
    expect(routeur({ type: 'conversation_message', conversation_id: 7, title: 'Nouveau message' })).toEqual({
      ecran: 'Chat',
      params: { threadId: 7, title: 'Nouveau message' },
    });
  });

  it('retombe sur la liste quand le fil n’est pas nommé', () => {
    expect(routeur({ type: 'conversation_message' })).toEqual({ ecran: 'ChatList' });
  });

  it('mène au litige', () => {
    expect(routeur({ type: 'dispute_resolved', dispute_id: 3 })).toEqual({ ecran: 'Disputes' });
  });

  /** `screen` reste le contrat explicite : s'il arrive un jour, il gagne sur la déduction. */
  it('honore un écran donné par le serveur', () => {
    expect(routeur({ type: 'tracking.enroute', booking_id: 42, screen: 'Loyalty' })).toEqual({
      ecran: 'Loyalty',
      params: {},
    });
  });

  /** LE TÉMOIN NÉGATIF : un type inconnu ne navigue nulle part, il n'invente pas d'écran. */
  it('ne navigue pas sur un type inconnu', () => {
    expect(routeur({ type: 'quelque_chose_de_neuf' })).toBeNull();
  });

  it('ne navigue pas sur une charge utile vide', () => {
    expect(routeur({})).toBeNull();
  });

  /** Un identifiant manquant ne doit pas produire un écran sans paramètre obligatoire. */
  it('ne mène pas au suivi sans identifiant de réservation', () => {
    expect(routeur({ type: 'tracking.enroute' })).toBeNull();
  });
});
