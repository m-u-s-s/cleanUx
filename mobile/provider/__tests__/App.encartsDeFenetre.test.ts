import fs from 'fs';
import path from 'path';

/**
 * TOUT `SafeAreaProvider` DOIT CONNAITRE LES ENCARTS DES SA PREMIERE PASSE.
 *
 * ── LE DEFAUT ────────────────────────────────────────────────────────────────────────────────
 *
 * Vu sur l'emulateur : le titre du tableau de bord prestataire, « Bonjour, B. », rendu PAR-DESSUS
 * l'horloge systeme. L'ecran passe pourtant par `Screen`, qui pose une marge haute tiree de
 * `SafeAreaView`. Une sonde posee dans le composant a tranche : l'encart haut valait bien 53 px
 * une fois etabli. Il valait donc ZERO au moment du rendu fautif — le composant faisait ce qu'on
 * lui demandait, avec une valeur qui n'existait pas encore.
 *
 * La cause est au montage. Les deux applications lisent un drapeau de facon ASYNCHRONE
 * (walkthrough cote prestataire, onboarding cote client) et, tant qu'il vaut `null`, rendent une
 * `View` nue SANS fournisseur. Le `SafeAreaProvider` ne se monte donc qu'apres cette lecture, et
 * un fournisseur qui vient de se monter n'a pas encore recu l'evenement natif qui lui apprend les
 * encarts : ses enfants rendent une premiere passe a zero. D'ordinaire cela dure une image ; sous
 * charge la fausse mise en page tient assez longtemps pour etre vue, et la carte du tableau de
 * bord, qui ne mesure qu'une fois, garde la mauvaise hauteur.
 *
 * `initialWindowMetrics` est lu SYNCHRONEMENT depuis les constantes du module natif. Le
 * fournisseur connait alors les encarts des sa premiere passe, quel que soit le moment ou il se
 * monte.
 *
 * ── POURQUOI CE TEST LIT LA SOURCE ───────────────────────────────────────────────────────────
 *
 * Parce qu'aucun rendu ne peut le voir. Le `SafeAreaProvider` des mocks Jest rend ses enfants et
 * ignore ses props : monter `<App />` et inspecter l'arbre passerait au vert avec ou sans
 * `initialMetrics`. Et le vrai fournisseur ne trahit son ignorance que sur un appareil, pendant la
 * poignee d'images ou l'evenement natif n'est pas encore arrive. La seule chose verifiable ici est
 * donc l'invariant d'ecriture — et c'est suffisant, puisque c'est exactement ce qui a manque.
 *
 * Les DEUX applications sont couvertes. Le defaut etait identique des deux cotes, et corriger un
 * seul cote est precisement ce qui laisse le second repartir en silence.
 */
describe('les fournisseurs d’encarts de fenêtre', () => {
  const racine = path.join(__dirname, '..', '..');

  const APPLICATIONS = [
    { nom: 'prestataire', fichier: 'provider/App.tsx' },
    { nom: 'client', fichier: 'client/App.tsx' },
  ];

  /**
   * Les ouvertures de `SafeAreaProvider` d'une source, avec leurs props.
   *
   * On capture jusqu'au `>` fermant pour pouvoir dire, pour CHAQUE ouverture, si elle porte
   * `initialMetrics`. Compter les occurrences globalement ne suffirait pas : deux fournisseurs
   * dont un seul est corrige donnerait un total non nul et passerait au vert — c'est justement la
   * forme qu'avait le code fautif, deux points de montage par application.
   */
  const ouvertures = (source: string): string[] => source.match(/<SafeAreaProvider[^>]*>/g) ?? [];

  const sansMetriques = (source: string): string[] =>
    ouvertures(source).filter(balise => !balise.includes('initialMetrics'));

  describe.each(APPLICATIONS)('application $nom', ({ fichier }) => {
    const source = fs.readFileSync(path.join(racine, fichier), 'utf8');

    /**
     * TEMOIN — il y a bien quelque chose à mesurer.
     *
     * Sans lui, le jour où `App.tsx` cesserait de monter un `SafeAreaProvider` (renommage,
     * extraction dans un autre fichier), l'assertion suivante passerait au vert sur une liste
     * vide — en mesurant l'absence du composant, pas la présence de la correction.
     */
    it('monte au moins un fournisseur', () => {
      expect(ouvertures(source).length).toBeGreaterThan(0);
    });

    it('les passe TOUS par initialWindowMetrics', () => {
      expect(sansMetriques(source)).toEqual([]);
      expect(source).toContain('initialWindowMetrics');
    });
  });

  /**
   * TEMOIN INVERSE — le garde sait dire non.
   *
   * Une regex mal echappee, un `[^>]*` devenu trop gourmand, et le filtre ci-dessus ne rejetterait
   * plus rien : les deux tests precedents resteraient verts sur un code redevenu fautif. Ces deux
   * sources synthetiques prouvent que le filtre discrimine, sans dependre de l'etat du depot.
   */
  describe('le garde lui-même', () => {
    it('rejette un fournisseur nu', () => {
      expect(sansMetriques('<SafeAreaProvider>\n  <App />\n</SafeAreaProvider>')).toHaveLength(1);
    });

    it('accepte un fournisseur muni de ses métriques', () => {
      expect(sansMetriques('<SafeAreaProvider initialMetrics={initialWindowMetrics}>')).toHaveLength(0);
    });

    it('repère celui qui manque quand un seul des deux est corrigé', () => {
      const melange = '<SafeAreaProvider initialMetrics={initialWindowMetrics}>\n<SafeAreaProvider>';

      expect(sansMetriques(melange)).toHaveLength(1);
    });
  });
});
