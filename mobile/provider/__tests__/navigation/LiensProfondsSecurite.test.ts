import { getPathFromState, getStateFromPath } from '@react-navigation/native';
import { linking } from '../../src/navigation/linking';

describe('Liens profonds prestataire avec le decodeur corrige', () => {
  it('accepte le scheme enregistre dans le binaire Expo', () => {
    const { expo } = require('../../app.json');
    expect(linking.prefixes).toContain(`${expo.scheme}://`);
  });

  it('conserve les identifiants et les parametres encodes', () => {
    const state = getStateFromPath('mission/42?note=caf%C3%A9+au+lait&tag=a&tag=b', linking.config);
    expect(state?.routes.at(-1)).toMatchObject({
      name: 'MissionDetail',
      params: { missionId: '42', note: 'caf\u00e9 au lait', tag: ['a', 'b'] },
    });
    expect(getPathFromState(state!, linking.config)).toContain('/mission/42');
  });

  it('traite une query malformee sans bloquer la navigation', () => {
    const value = '%80'.repeat(500);
    const state = getStateFromPath(`mission/42?note=${value}`, linking.config);
    expect(state?.routes.at(-1)).toMatchObject({
      name: 'MissionDetail', params: { missionId: '42', note: value },
    });
  });

  it('conserve la destination imbriquee de repartition des missions', () => {
    const state = getStateFromPath('societe/repartition?equipe=terrain', linking.config);
    expect(state?.routes.at(-1)).toMatchObject({
      name: 'ProviderCompanySpace',
      state: { routes: [{ name: 'CompanyDispatchTab', params: { equipe: 'terrain' } }] },
    });
  });
});
