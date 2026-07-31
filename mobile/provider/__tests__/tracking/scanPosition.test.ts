/**
 * Relevé de position au moment du scan.
 *
 * Ce qui est verrouillé ici : le relevé RENONCE. `getCurrentPositionAsync` n'offre aucune option
 * de délai (SDK 56) — sans la course écrite à la main, un relevé impossible en sous-sol ou en cage
 * d'escalier laisserait le bouton tourner sans fin, et le prestataire bloqué devant la porte du
 * client sans rien à faire ni rien à comprendre.
 *
 * Et un échec rend `null`, jamais une position inventée : la décision d'accepter ou non une
 * confirmation sans position appartient au serveur, seul des deux à ne pas être sur l'appareil de
 * la personne contrôlée.
 */
const mockRequestPermission = jest.fn();
const mockGetCurrentPosition = jest.fn();

jest.mock('expo-location', () => ({
  requestForegroundPermissionsAsync: () => mockRequestPermission(),
  getCurrentPositionAsync: (...args: unknown[]) => mockGetCurrentPosition(...args),
  Accuracy: { High: 4 },
}));

import { readScanPosition } from '@/tracking/scanPosition';

const located = (coords: Record<string, unknown>, extra: Record<string, unknown> = {}) => ({
  coords: { latitude: 50.8467, longitude: 4.3525, accuracy: 12, ...coords },
  timestamp: 1_800_000_000_000,
  ...extra,
});

beforeEach(() => {
  mockRequestPermission.mockReset().mockResolvedValue({ status: 'granted' });
  mockGetCurrentPosition.mockReset();
});

describe('readScanPosition', () => {
  it('rend la position, sa précision et l’indicateur de simulation', async () => {
    mockGetCurrentPosition.mockResolvedValue(located({}, { mocked: false }));

    await expect(readScanPosition()).resolves.toEqual({
      lat: 50.8467,
      lng: 4.3525,
      accuracy_m: 12,
      mocked: false,
    });
  });

  /** Android marque les relevés d'une application de position fictive : c'est un aveu à faire suivre. */
  it('fait remonter une position simulée', async () => {
    mockGetCurrentPosition.mockResolvedValue(located({}, { mocked: true }));

    await expect(readScanPosition()).resolves.toMatchObject({ mocked: true });
  });

  /** Le champ est absent hors Android. Une absence n'est pas un aveu : elle ne doit pas en devenir un. */
  it('ne présume pas une simulation quand le champ est absent', async () => {
    mockGetCurrentPosition.mockResolvedValue(located({}));

    await expect(readScanPosition()).resolves.toMatchObject({ mocked: false });
  });

  it('accepte une précision inconnue', async () => {
    mockGetCurrentPosition.mockResolvedValue(located({ accuracy: null }));

    await expect(readScanPosition()).resolves.toMatchObject({ accuracy_m: null });
  });

  it('rend null quand la localisation est refusée', async () => {
    mockRequestPermission.mockResolvedValue({ status: 'denied' });

    await expect(readScanPosition()).resolves.toBeNull();
    expect(mockGetCurrentPosition).not.toHaveBeenCalled();
  });

  it('rend null quand le relevé échoue', async () => {
    mockGetCurrentPosition.mockRejectedValue(new Error('location services disabled'));

    await expect(readScanPosition()).resolves.toBeNull();
  });

  /**
   * LA garantie de ce module. Sans la course, cette promesse ne se résoudrait jamais et l'écran
   * resterait figé — un cas banal : sous-sol, parking, cage d'escalier.
   */
  it('renonce quand le relevé n’aboutit pas à temps', async () => {
    mockGetCurrentPosition.mockReturnValue(new Promise(() => {}));

    await expect(readScanPosition(20)).resolves.toBeNull();
  });

  /** Un rejet tardif de la promesse perdante ne doit pas remonter en `unhandledRejection`. */
  it('absorbe un échec arrivé après l’abandon', async () => {
    let reject: (e: unknown) => void = () => {};
    mockGetCurrentPosition.mockReturnValue(new Promise((_r, rj) => { reject = rj; }));

    await expect(readScanPosition(20)).resolves.toBeNull();

    reject(new Error('trop tard'));
    await new Promise((resolve) => setTimeout(resolve, 10));
  });

  it('demande la meilleure précision disponible', async () => {
    mockGetCurrentPosition.mockResolvedValue(located({}));

    await readScanPosition();

    expect(mockGetCurrentPosition).toHaveBeenCalledWith({ accuracy: 4 });
  });
});
