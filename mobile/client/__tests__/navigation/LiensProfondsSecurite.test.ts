import { getPathFromState, getStateFromPath } from '@react-navigation/native';
import { linking } from '../../src/navigation/linking';

describe('Liens profonds client avec le decodeur corrige', () => {
  it('accepte le scheme enregistre dans le binaire Expo', () => {
    const { expo } = require('../../app.json');
    expect(linking.prefixes).toContain(`${expo.scheme}://`);
  });

  it('conserve les identifiants et les parametres encodes', () => {
    const state = getStateFromPath('booking/42?note=caf%C3%A9+au+lait&tag=a&tag=b', linking.config);
    expect(state?.routes.at(-1)).toMatchObject({
      name: 'BookingDetail',
      params: { bookingId: '42', note: 'caf\u00e9 au lait', tag: ['a', 'b'] },
    });
    expect(getPathFromState(state!, linking.config)).toContain('/booking/42');
  });

  it('traite une query malformee sans bloquer la navigation', () => {
    const value = '%80'.repeat(500);
    const state = getStateFromPath(`booking/42?note=${value}`, linking.config);
    expect(state?.routes.at(-1)).toMatchObject({
      name: 'BookingDetail', params: { bookingId: '42', note: value },
    });
  });
});
