import React from 'react';
import { render } from '@testing-library/react-native';

import { ProfileScreen } from '@/screens/ProfileScreen';

/**
 * LA PORTE DE L'ESPACE SOCIÉTÉ — DEUX CONDITIONS, ET IL EN MANQUAIT UNE.
 *
 * Premier défaut (2026-08-06) : la condition exigeait `is_entreprise === true` ET
 * `organization_type === 'provider_company'`. Or `User::isEntreprise()` désigne une société
 * CLIENTE : les deux étaient mutuellement exclusives et la section n'a jamais pu s'afficher.
 *
 * Second défaut, celui que ce fichier ferme : une fois la section rouverte, elle montrait ses six
 * boutons à TOUT membre d'une société prestataire. `organization_type` vaut `provider_company` pour
 * le nettoyeur comme pour le patron — et quatre de ces écrans répondent 403 depuis que le lot 1 a
 * posé ses gardes serveur. La première version de ce test figeait précisément ce comportement, en
 * affirmant qu'un compte sans aucune permission voyait « Répartition ».
 *
 * La règle est désormais : l'appartenance ouvre la SECTION, la permission décide de chaque BOUTON.
 * Les clés sont celles de `routes/api/provider.php` et de `config/modules.php`.
 */

const mockUser: { value: Record<string, unknown> | null } = { value: null };

/*
 * Le mock rend le VRAI `can` plutôt qu'un bouchon : c'est une fonction pure sur l'objet utilisateur,
 * et la bouchonner reviendrait à tester le mock. Un bouchon aurait aussi masqué le défaut-refus,
 * qui est exactement ce qui protège une application plus ancienne qu'une clé nouvelle.
 */
jest.mock('@/auth', () => ({
  useAuth: () => ({ user: mockUser.value, logout: jest.fn() }),
  can: jest.requireActual('../../../shared/src/auth/permissions').can,
}));

jest.mock('@/admin/useSpacePreference', () => ({
  useSpacePreference: () => ({ clear: jest.fn() }),
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

describe('ProfileScreen — porte de l’espace société', () => {
  it('donne au gérant les écrans que ses permissions ouvrent', () => {
    mockUser.value = {
      name: 'Chef Bruxelles',
      organization_type: 'provider_company',
      // `is_entreprise` est FAUX pour ces comptes : le serveur ne le met à vrai que pour une
      // société CLIENTE. L'exiger ici rendait la section inatteignable.
      is_entreprise: false,
      organization_permissions: [
        'missions.dispatch',
        'team.view',
        'sites.view_all',
        'members.manage_permissions',
        'agencies.view',
      ],
    };

    const { getByText } = render(<ProfileScreen />);

    getByText('Répartition');
    getByText('Équipes terrain');
    getByText('Sites desservis');
    getByText('Rôles et permissions');
    /*
     * « Implantations » est la porte du seul écran d'AGENCES, et il n'a pas d'autre chemin : la
     * liste des écrans société navigue par une variable, si bien qu'aucun `navigate('CompanyAgencies')`
     * littéral n'existe dans la source. Sans cette assertion, l'écran pouvait être livré complet et
     * joignable par personne — la classe de défaut qui a déjà coûté cinq écrans à ce dépôt.
     */
    getByText('Implantations');
    getByText('Canaux');
  });

  it('ne montre au nettoyeur que les deux écrans qui lui sont ouverts', () => {
    /*
     * LE DÉFAUT CENTRAL DE L'EXIGENCE 8, CÔTÉ ÉCRAN. Un exécutant appartient bien à la société ;
     * il n'a ni `missions.dispatch`, ni `team.view`, ni `sites.view_all`. Les quatre boutons
     * correspondants menaient à des 403.
     *
     * Tâches et Canaux restent : les tâches sont bornées dans la requête (il voit les siennes) et
     * les canaux par l'appartenance au canal. Ce sont ses deux écrans légitimes.
     */
    mockUser.value = {
      name: 'Nettoyeur',
      organization_type: 'provider_company',
      is_entreprise: false,
      organization_permissions: ['channels.create', 'tasks.create'],
    };

    const { getByText, queryByText } = render(<ProfileScreen />);

    getByText('Tâches');
    getByText('Canaux');

    expect(queryByText('Répartition')).toBeNull();
    expect(queryByText('Équipe')).toBeNull();
    expect(queryByText('Équipes terrain')).toBeNull();
    expect(queryByText('Sites desservis')).toBeNull();
    expect(queryByText('Rôles et permissions')).toBeNull();
    expect(queryByText('Implantations')).toBeNull();
  });

  it('applique le défaut-refus quand le serveur ne déclare aucune clé', () => {
    /*
     * Le cas d'une application plus ancienne que le champ, ou d'une réponse tronquée. Refuser est
     * le bon comportement : l'inverse afficherait des boutons que l'API refusera de servir.
     */
    mockUser.value = {
      name: 'Compte ancien',
      organization_type: 'provider_company',
      is_entreprise: false,
    };

    const { queryByText, getByText } = render(<ProfileScreen />);

    getByText('Tâches');
    expect(queryByText('Répartition')).toBeNull();
    expect(queryByText('Sites desservis')).toBeNull();
  });

  it('ne les propose pas à un prestataire indépendant', () => {
    mockUser.value = { name: 'Indépendant', organization_type: null, is_entreprise: false };

    const { queryByText } = render(<ProfileScreen />);

    // Les proposer donnerait des liens qui répondent 403 à qui les ouvre.
    expect(queryByText('Répartition')).toBeNull();
    expect(queryByText('Équipes terrain')).toBeNull();
    expect(queryByText('Tâches')).toBeNull();
  });

  it("ne les propose pas à un membre d'une société CLIENTE", () => {
    mockUser.value = {
      name: 'Acheteuse',
      organization_type: 'client_company',
      is_entreprise: true,
      // Même avec des clés — une société cliente en a aussi —, l'espace prestataire reste fermé.
      organization_permissions: ['missions.dispatch'],
    };

    const { queryByText } = render(<ProfileScreen />);

    expect(queryByText('Répartition')).toBeNull();
  });
});
