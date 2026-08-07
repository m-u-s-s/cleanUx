/**
 * L'AIGUILLAGE D'ESPACE DE L'APPLICATION CLIENTE.
 *
 * POURQUOI UN ESPACE ET PAS UN BOUTON. Les six écrans société sont aujourd'hui derrière une entrée
 * dans le profil, au milieu des moyens de paiement et de la langue. Un facility manager qui suit
 * vingt immeubles ouvre l'application POUR eux : c'est son métier, pas un réglage.
 *
 * MAIS LE MÊME COMPTE PEUT ÊTRE UN PARTICULIER. Rien n'empêche la responsable des locaux d'une
 * société de commander aussi un ménage chez elle — c'est même le cas le plus courant, puisque
 * l'organisation est un rattachement du compte, pas un compte distinct. L'enfermer dans l'espace
 * société lui retirerait ses propres réservations.
 *
 * D'où un choix, retenu d'un lancement à l'autre et réversible depuis le profil — exactement le
 * mécanisme éprouvé côté prestataire, dont cette fonction copie la forme et l'ordre des conditions.
 */
import { resolveClientSpace } from '@/company/space';

const particulier = { is_entreprise: false };
const membreSociete = { is_entreprise: true };

describe('resolveClientSpace', () => {
  it('attend pendant le chargement de la session', () => {
    expect(
      resolveClientSpace({ isLoading: true, isAuthenticated: false, user: null }),
    ).toBe('loading');
  });

  it('renvoie à la connexion quand personne n’est authentifié', () => {
    expect(
      resolveClientSpace({ isLoading: false, isAuthenticated: false, user: null }),
    ).toBe('login');
  });

  it('garde son espace au particulier, sans jamais lui poser la question', () => {
    // Un client sans société n'a rien à choisir : lui montrer un sélecteur serait lui demander de
    // trancher entre une porte et un mur.
    expect(
      resolveClientSpace({ isLoading: false, isAuthenticated: true, user: particulier }),
    ).toBe('personal');
  });

  it('fait choisir un membre de société qui n’a pas encore choisi', () => {
    expect(
      resolveClientSpace({ isLoading: false, isAuthenticated: true, user: membreSociete }),
    ).toBe('switcher');
  });

  it('respecte le choix « société »', () => {
    expect(
      resolveClientSpace({
        isLoading: false,
        isAuthenticated: true,
        user: membreSociete,
        chosenSpace: 'clientCompany',
      }),
    ).toBe('clientCompany');
  });

  it('respecte le choix « perso » d’un membre de société', () => {
    // Elle gère vingt immeubles au bureau et commande son propre ménage le samedi : le choix
    // « perso » ne doit pas être réinterprété à chaque lancement.
    expect(
      resolveClientSpace({
        isLoading: false,
        isAuthenticated: true,
        user: membreSociete,
        chosenSpace: 'personal',
      }),
    ).toBe('personal');
  });

  it('ignore un choix « société » devenu caduc', () => {
    /*
     * LE CAS QUI SE PRODUIT SANS PRÉVENIR : quelqu'un quitte son entreprise. Le choix reste écrit
     * dans le stockage local, mais `is_entreprise` retombe à faux. Sans cette règle, l'application
     * ouvrirait un espace dont chaque écran répond 403, et l'utilisateur n'aurait aucun moyen d'en
     * sortir — le sélecteur ne s'affiche que pour qui a le choix.
     */
    expect(
      resolveClientSpace({
        isLoading: false,
        isAuthenticated: true,
        user: particulier,
        chosenSpace: 'clientCompany',
      }),
    ).toBe('personal');
  });

  it('traite un drapeau absent comme un particulier', () => {
    // Le parc installé porte des jetons émis avant que `/auth/me` ne serve ce champ. Sans lui, le
    // compte est traité en particulier — ce qu'il était hier, et ce qui ne ferme aucune porte.
    expect(
      resolveClientSpace({ isLoading: false, isAuthenticated: true, user: {} }),
    ).toBe('personal');
  });
});
