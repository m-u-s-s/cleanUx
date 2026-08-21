/**
 * MISE EN PLACE PROPRE À CETTE APPLICATION.
 *
 * `expo-secure-store` n'a pas de module natif sous Jest. Jusqu'ici personne ne s'en souciait :
 * l'unique appelant, `WalkthroughScreen`, le chargeait par `await import()` dans un `try` dont le
 * `catch` conclut « présentation déjà vue ». L'import échouait, le témoin répondait toujours vrai,
 * et la présentation ne s'affichait donc JAMAIS — ni en test, ni sur un vrai téléphone.
 *
 * L'import est désormais statique, comme partout ailleurs dans le dépôt, et le témoin dit la
 * vérité. Il faut donc que les tests aient un magasin qui fonctionne, et un défaut sensé : dans un
 * test, la présentation de première ouverture est réputée DÉJÀ VUE — sans quoi elle s'interposerait
 * devant chaque écran monté par un test de navigation.
 *
 * Le magasin est indexé par CLÉ, et non « tout ou rien » : rendre une valeur pour n'importe quelle
 * clé ferait croire à `secureStore.isAuthenticated()` qu'un jeton existe, et authentifierait
 * silencieusement toute la suite.
 */
jest.mock('expo-secure-store', () => {
  const magasin = new Map([['provider_walkthrough_completed', 'true']]);

  return {
    getItemAsync: jest.fn(async (cle) => (magasin.has(cle) ? magasin.get(cle) : null)),
    setItemAsync: jest.fn(async (cle, valeur) => { magasin.set(cle, valeur); }),
    deleteItemAsync: jest.fn(async (cle) => { magasin.delete(cle); }),
  };
});
