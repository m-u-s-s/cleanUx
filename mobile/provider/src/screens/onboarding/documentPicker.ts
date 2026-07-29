/**
 * Sélection d'un justificatif, par photo ou par fichier.
 *
 * Les deux modules touchent un binaire natif et lèvent dès l'import quand celui-ci est absent —
 * Expo Go dépourvu, build mal configuré, environnement de test. Le require est donc paresseux et
 * rattrapable, sur le modèle de provider/src/maps/module.ts et du widget Turnstile : un import
 * statique ferait tomber TOUT écran important ce fichier, sélection de document ou non.
 */
export interface PickedFile {
  uri: string;
  name: string;
  mimeType: string;
  /** Taille en octets quand la source la connaît — absente sur certaines caméras. */
  size?: number;
}

/** Ce que le serveur accepte : `mimes:pdf,jpg,jpeg,png`, 10 Mo. */
export const ACCEPTED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

export const MAX_FILE_BYTES = 10 * 1024 * 1024;

/**
 * Chaque module se charge par un require LITTÉRAL, jamais par une variable.
 *
 * Metro analyse les dépendances statiquement au bundling : `require(name)` sur un paramètre lui
 * est incompréhensible et fait échouer la construction entière du bundle
 * (« Invalid call ... require(name) »). Jest ne le voit pas, son require résolvant à l'exécution
 * — l'erreur n'apparaît qu'au lancement de l'application. D'où deux fonctions au lieu d'un
 * helper générique, comme le font maps/module.ts et le widget Turnstile.
 */
function loadImagePicker(): any | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-image-picker');
  } catch {
    return null;
  }
}

function loadDocumentPicker(): any | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    return require('expo-document-picker');
  } catch {
    return null;
  }
}

/**
 * Photographie la pièce, ou la prend dans la galerie.
 *
 * Renvoie `null` quand l'utilisateur annule — distinct d'une erreur, que l'appelant doit
 * signaler. Lève avec un message affichable quand le module natif manque.
 */
export async function pickImage(source: 'camera' | 'library'): Promise<PickedFile | null> {
  const picker = loadImagePicker();

  if (!picker) {
    throw new Error("L'appareil photo n'est pas disponible sur cet appareil.");
  }

  const permission =
    source === 'camera'
      ? await picker.requestCameraPermissionsAsync()
      : await picker.requestMediaLibraryPermissionsAsync();

  if (!permission?.granted) {
    throw new Error(
      source === 'camera'
        ? "Autorisez l'accès à l'appareil photo pour prendre votre pièce en photo."
        : "Autorisez l'accès à vos photos pour choisir votre pièce.",
    );
  }

  const result =
    source === 'camera'
      ? await picker.launchCameraAsync({ quality: 0.8 })
      : await picker.launchImageLibraryAsync({ quality: 0.8 });

  if (result?.canceled || !result?.assets?.length) {
    return null;
  }

  const asset = result.assets[0];

  return {
    uri: asset.uri,
    name: asset.fileName ?? `piece-identite.${asset.uri.split('.').pop() ?? 'jpg'}`,
    // Les caméras rendent parfois un mimeType vide : le serveur n'accepte que la liste ci-dessus,
    // autant présumer le JPEG qu'envoyer une chaîne vide qu'il rejetterait.
    mimeType: asset.mimeType || 'image/jpeg',
    size: asset.fileSize,
  };
}

/**
 * Choisit un fichier existant (PDF compris), pour qui a déjà un scan.
 */
export async function pickDocument(): Promise<PickedFile | null> {
  const picker = loadDocumentPicker();

  if (!picker) {
    throw new Error("La sélection de fichier n'est pas disponible sur cet appareil.");
  }

  const result = await picker.getDocumentAsync({
    type: ACCEPTED_MIME_TYPES,
    copyToCacheDirectory: true,
    multiple: false,
  });

  if (result?.canceled || !result?.assets?.length) {
    return null;
  }

  const asset = result.assets[0];

  return {
    uri: asset.uri,
    name: asset.name ?? 'justificatif',
    mimeType: asset.mimeType || 'application/pdf',
    size: asset.size,
  };
}

/**
 * Refuse ici ce que le serveur refuserait de toute façon, plutôt que de faire remonter un
 * fichier de 40 Mo pour recevoir un 422.
 */
export function rejectionReason(file: PickedFile, size?: number): string | null {
  if (!ACCEPTED_MIME_TYPES.includes(file.mimeType)) {
    return 'Format non accepté. Envoyez un PDF, un JPG ou un PNG.';
  }

  if (typeof size === 'number' && size > MAX_FILE_BYTES) {
    return 'Fichier trop volumineux (10 Mo maximum).';
  }

  return null;
}
