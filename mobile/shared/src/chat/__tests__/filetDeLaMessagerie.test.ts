import { useChatMessages, useChatThreads } from '../hooks';

/**
 * LA MESSAGERIE NE DOIT PAS DÉPENDRE DE LA SEULE SOCKET.
 *
 * Elle n'avait aucun sondage de repli : le fil vivait uniquement de `useLiveChat`. Un ascenseur, un
 * tunnel, une application mise en veille par le système — la socket tombe, plus rien n'arrive, et
 * rien ne le rattrape tant que l'utilisateur ne quitte pas l'écran pour y revenir. Il voit une
 * conversation figée et croit que personne ne lui répond.
 *
 * Le suivi d'intervention avait déjà ce filet, avec le commentaire qui va bien : « le temps réel
 * rafraîchit à l'événement ; ce sondage lent est le FILET ». La messagerie ne l'avait pas.
 *
 * CE TEST LIT LA CONFIGURATION RENDUE PAR LE HOOK, sans monter React : c'est un objet d'options de
 * React Query, et c'est exactement ce qu'on veut vérifier. Un test qui monterait le composant
 * mesurerait React Query, pas notre choix.
 */
jest.mock('@tanstack/react-query', () => ({
  useQuery: (options: unknown) => options,
  useMutation: (options: unknown) => options,
  useQueryClient: () => ({ invalidateQueries: jest.fn(), setQueryData: jest.fn() }),
}));

jest.mock('@/api', () => ({ apiClient: { get: jest.fn(), post: jest.fn() }, ApiError: class {} }));
jest.mock('@/realtime', () => ({ useChannel: jest.fn() }));

describe('le filet de la messagerie', () => {
  it('la liste des fils se rafraîchit même sans socket', () => {
    const options = useChatThreads() as unknown as { refetchInterval?: number };

    expect(typeof options.refetchInterval).toBe('number');
    expect(options.refetchInterval).toBeGreaterThan(0);
  });

  it('une conversation ouverte se rafraîchit plus souvent que la liste', () => {
    const liste = useChatThreads() as unknown as { refetchInterval: number };
    const fil = useChatMessages(4) as unknown as { refetchInterval: number };

    /*
     * L'ORDRE COMPTE PLUS QUE LES VALEURS. Sur un fil ouvert, quelqu'un ATTEND une réponse ;
     * quinze secondes de silence s'y remarquent, alors que sur une liste, non. Figer les nombres
     * exacts rendrait ce test fragile sans rien prouver de plus.
     */
    expect(fil.refetchInterval).toBeLessThan(liste.refetchInterval);
  });

  /** TÉMOIN : le hook rend bien une configuration, et pas `undefined`. */
  it('témoin — les hooks rendent une configuration exploitable', () => {
    const fil = useChatMessages(4) as unknown as { enabled?: boolean; queryKey?: unknown[] };

    expect(fil.queryKey).toEqual(['chat', 'messages', 4]);
    expect(fil.enabled).toBe(true);
  });

  /** Sans fil sélectionné, on n'interroge rien — le sondage ne doit pas réveiller le réseau. */
  it('aucun fil sélectionné, aucune requête', () => {
    const fil = useChatMessages(null) as unknown as { enabled?: boolean };

    expect(fil.enabled).toBe(false);
  });
});
