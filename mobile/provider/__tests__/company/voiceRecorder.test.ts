/**
 * L'ENREGISTREUR DE NOTES VOCALES — vérifié pour de bon, pas seulement bouchonné par l'écran.
 *
 * Le chemin visait `expo-av`, qui n'a JAMAIS été installé : le bouton micro rendait `null` sur tous
 * les appareils, silencieusement, depuis le lot 8. Deux protections empilées rendaient ce trou
 * invisible — un `catch` muet et un import dynamique que Jest ne sait pas exécuter, si bien que le
 * seul test existant bouchonnait le module entier et ne pouvait donc rien dire de son contenu.
 *
 * Ce fichier bouchonne `expo-audio` — le module NATIF, absent sous Jest — et vérifie ce qui reste :
 * l'enchaînement permission → mode audio → préparer → enregistrer → arrêter, et le fait qu'aucun
 * refus ni aucune panne ne laisse le micro ouvert pour toute l'application.
 */

const mockRequestPermissions = jest.fn();
const mockSetAudioMode = jest.fn().mockResolvedValue(undefined);
const mockPrepare = jest.fn().mockResolvedValue(undefined);
const mockRecord = jest.fn();
const mockStop = jest.fn().mockResolvedValue(undefined);

// Le préfixe `mock` n'est pas cosmétique : Babel hisse les `jest.mock()` au-dessus des
// déclarations, et seules les variables ainsi nommées ont le droit d'être référencées dans la
// fabrique. Sans lui, la suite ne compile pas.
let mockUri: string | null = 'file:///tmp/note.m4a';

jest.mock('expo-audio', () => ({
  requestRecordingPermissionsAsync: () => mockRequestPermissions(),
  setAudioModeAsync: (mode: unknown) => mockSetAudioMode(mode),
  RecordingPresets: { HIGH_QUALITY: { extension: '.m4a' } },
  AudioRecorder: class {
    prepareToRecordAsync = mockPrepare;

    record = mockRecord;

    stop = mockStop;

    currentTime = 12;

    get uri() {
      return mockUri;
    }
  },
}));

import { enregistrerNoteVocale } from '@/company/voiceRecorder';

/**
 * Laisse l'attente de trente secondes s'écouler sans immobiliser la suite.
 *
 * `advanceTimersByTimeAsync` et non sa variante synchrone : plusieurs `await` précèdent le
 * `setTimeout` dans l'enregistreur (permission, mode audio, préparation). Avancer l'horloge tout de
 * suite tomberait AVANT que le minuteur n'existe, et la promesse ne se résoudrait jamais.
 */
async function laisserPasserLEnregistrement(promesse: Promise<unknown>) {
  await jest.advanceTimersByTimeAsync(30_000);

  return promesse;
}

describe('enregistrerNoteVocale', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.clearAllMocks();
    mockUri = 'file:///tmp/note.m4a';
    mockRequestPermissions.mockResolvedValue({ granted: true });
    mockSetAudioMode.mockResolvedValue(undefined);
    mockPrepare.mockResolvedValue(undefined);
    mockStop.mockResolvedValue(undefined);
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  it('rend un fichier prêt à être joint', async () => {
    const note = await laisserPasserLEnregistrement(enregistrerNoteVocale());

    expect(note).toEqual({
      fichier: { uri: 'file:///tmp/note.m4a', name: 'note.m4a', type: 'audio/m4a' },
      dureeSecondes: 12,
    });

    // `prepareToRecordAsync` alloue le fichier de sortie : sans lui, `record()` démarre sur un
    // enregistreur sans destination et l'`uri` reste nulle à l'arrêt.
    expect(mockPrepare).toHaveBeenCalled();
    expect(mockRecord).toHaveBeenCalled();
    expect(mockStop).toHaveBeenCalled();
  });

  it('demande le micro AVANT de commencer', async () => {
    await laisserPasserLEnregistrement(enregistrerNoteVocale());

    expect(mockRequestPermissions.mock.invocationCallOrder[0]).toBeLessThan(
      mockRecord.mock.invocationCallOrder[0]!,
    );
  });

  it('un refus du micro ne démarre rien', async () => {
    mockRequestPermissions.mockResolvedValue({ granted: false });

    // Refuser est une réponse légitime : on ne réessaie pas, on ne bloque rien, et surtout on
    // n'ouvre pas l'enregistreur « au cas où ».
    await expect(enregistrerNoteVocale()).resolves.toBeNull();
    expect(mockRecord).not.toHaveBeenCalled();
  });

  it('un enregistrement sans fichier ne rend rien plutôt qu’un objet vide', async () => {
    mockUri = null;

    await expect(laisserPasserLEnregistrement(enregistrerNoteVocale())).resolves.toBeNull();
  });

  it('une panne en cours de route relâche le micro', async () => {
    mockPrepare.mockRejectedValue(new Error('stockage plein'));

    await expect(enregistrerNoteVocale()).resolves.toBeNull();

    // Un enregistreur laissé ouvert garde le micro pour TOUTE l'application : la note suivante
    // échouerait sans que rien ne l'explique, et un appel entrant aussi.
    expect(mockStop).toHaveBeenCalled();
  });
});
