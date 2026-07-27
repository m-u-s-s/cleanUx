import { haversineMeters, distanceKmTo, formatDistance } from '@/tracking/distance';

describe('distance', () => {
  it('mesure une distance connue (Grand-Place Bruxelles → Atomium ≈ 5.3 km)', () => {
    const meters = haversineMeters(50.8467, 4.3525, 50.8949, 4.3415);
    expect(meters / 1000).toBeCloseTo(5.3, 0);
  });

  it('renvoie 0 pour deux points identiques', () => {
    expect(haversineMeters(50.85, 4.35, 50.85, 4.35)).toBe(0);
  });

  it('dérive la distance en km depuis ma position vers une mission', () => {
    const km = distanceKmTo({ latitude: 50.8467, longitude: 4.3525 }, { latitude: 50.8949, longitude: 4.3415 });
    expect(km).toBeCloseTo(5.3, 0);
  });

  it('renvoie null quand ma position est inconnue', () => {
    expect(distanceKmTo(null, { latitude: 50.89, longitude: 4.34 })).toBeNull();
  });

  it('renvoie null quand la mission n a pas de coordonnées', () => {
    expect(distanceKmTo({ latitude: 50.84, longitude: 4.35 }, { latitude: null, longitude: null })).toBeNull();
    expect(distanceKmTo({ latitude: 50.84, longitude: 4.35 }, {})).toBeNull();
  });

  it('formate en mètres sous 1 km et en km au-delà', () => {
    expect(formatDistance(850)).toBe('850 m');
    expect(formatDistance(1240)).toBe('1.2 km');
  });
});
