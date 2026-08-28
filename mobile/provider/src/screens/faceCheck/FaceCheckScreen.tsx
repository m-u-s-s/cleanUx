import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Animated, Easing, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { CameraView, useCameraPermissions } from 'expo-camera';
import Constants from 'expo-constants';
import {
  useAbandonFaceCheck,
  useEnrollFace,
  useFaceCheckStatus,
  useReportFaceIncident,
  useStartFaceCheck,
  useSubmitFaceCheck,
  type FaceCheck,
} from '@/faceCheck';
import { Button, GlassSurface, SuccessOverlay, TextInput, useReducedMotion } from '@/ui';
import { colors } from '@/theme';
import { useThemeColors, type ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * LE CONTRÔLE D'IDENTITÉ, PLEIN ÉCRAN.
 *
 * Direction assumée : c'est un moment de tension, l'écran ne le déguise pas en formulaire. Noir
 * profond, une seule chose à faire, un cercle de visée qui respire, et aucune échappatoire
 * visuelle — pas de barre d'onglets, pas de menu, pas de retour. La pile de navigation ne monte
 * que cet écran-là : il n'y a littéralement nulle part où aller, exactement comme le cockpit
 * d'inscription quand le dossier est incomplet.
 *
 * CE QU'IL NE FAIT PAS : il ne décide de rien. Ni de la cadence, ni du verdict, ni du déblocage.
 * Il affiche ce que le serveur dit et transmet ce que la caméra voit. Une application qui
 * déciderait quoi que ce soit ici se contournerait en modifiant l'application.
 */
export default function FaceCheckScreen() {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = useMemo(() => stylesFor(theme), [theme]);
  const mouvementReduit = useReducedMotion();

  const { data: statut, isLoading, refetch } = useFaceCheckStatus();
  const enroler = useEnrollFace();
  const ouvrir = useStartFaceCheck();
  const soumettre = useSubmitFaceCheck();
  const abandonner = useAbandonFaceCheck();

  const [permission, demanderPermission] = useCameraPermissions();
  const camera = useRef<CameraView | null>(null);

  const [controle, setControle] = useState<FaceCheck | null>(null);
  const [enCours, setEnCours] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const [reussi, setReussi] = useState(false);
  const [signalementOuvert, setSignalementOuvert] = useState(false);
  const [consentementDonne, setConsentementDonne] = useState(false);

  const etat = statut?.state ?? 'ok';
  const enrolement = etat === 'face_enrolment_required';
  const bloque = etat === 'face_check_blocked';
  const enAttenteDuVerdict = etat === 'face_check_pending';

  /*
   * LE CERCLE RESPIRE — c'est la seule animation de l'écran, et elle a une fonction : dire que
   * l'application est vivante pendant qu'on se regarde. `useReducedMotion` la coupe : un contrôle
   * d'identité doit rester franchissable par quelqu'un que le mouvement gêne.
   */
  const souffle = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    if (mouvementReduit) {
      return;
    }

    const boucle = Animated.loop(
      Animated.sequence([
        Animated.timing(souffle, { toValue: 1, duration: 1800, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
        Animated.timing(souffle, { toValue: 0, duration: 1800, easing: Easing.inOut(Easing.ease), useNativeDriver: true }),
      ]),
    );

    boucle.start();

    return () => boucle.stop();
  }, [mouvementReduit, souffle]);

  const echelle = souffle.interpolate({ inputRange: [0, 1], outputRange: [1, 1.035] });

  // Le contrôle s'ouvre dès que l'écran s'affiche : c'est le seul geste que le prestataire attend.
  useEffect(() => {
    if (enrolement || bloque || enAttenteDuVerdict || controle !== null) {
      return;
    }

    ouvrir.mutateAsync().then(setControle).catch(() => setErreur(MESSAGE_RESEAU));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [enrolement, bloque, enAttenteDuVerdict]);

  const capturer = useCallback(async () => {
    if (enCours || camera.current === null) {
      return;
    }

    /*
     * LE CONSENTEMENT SE DONNE AVANT LA CAPTURE, PAS APRES.
     *
     * L'ecran envoyait `consent: 1` sans jamais montrer le texte : le serveur enregistrait donc un
     * consentement que personne n'avait lu. Pour une donnee de l'article 9, un consentement non
     * affiche n'est pas un consentement -- c'est une case cochee par le code a la place de la
     * personne.
     */
    if (enrolement && !consentementDonne) {
      setErreur(texteDuRefusDeConsentement);

      return;
    }

    setErreur(null);
    setEnCours(true);

    try {
      const photo = await camera.current.takePictureAsync({ quality: 0.7, skipProcessing: true });

      if (!photo?.uri) {
        setErreur("La caméra n'a rien renvoyé. Réessayez.");

        return;
      }

      if (enrolement) {
        await enroler.mutateAsync({ uri: photo.uri });
        setReussi(true);
        await refetch();

        return;
      }

      if (controle === null) {
        setErreur(MESSAGE_RESEAU);

        return;
      }

      const resultat = await soumettre.mutateAsync({ checkId: controle.id, uri: photo.uri });
      setControle(resultat);

      if (resultat.status === 'passed') {
        setReussi(true);
        await refetch();

        return;
      }

      if (resultat.status === 'failed') {
        setErreur(
          "Nous n'avons pas pu vous reconnaître. Un administrateur va examiner votre dossier.",
        );
        await refetch();

        return;
      }

      // Il reste des essais : on le dit franchement plutôt que de laisser recommencer à l'aveugle.
      setErreur(messageDEchec(resultat));
    } catch {
      setErreur(MESSAGE_RESEAU);
    } finally {
      setEnCours(false);
    }
  }, [enCours, enrolement, consentementDonne, controle, enroler, soumettre, refetch]);

  // ── États qui ne montrent pas la caméra ──────────────────────────────────

  if (isLoading && !statut) {
    return (
      <View style={styles.plein}>
        <ActivityIndicator color={theme.text} />
      </View>
    );
  }

  if (bloque) {
    return (
      <View style={styles.plein}>
        <ScrollView contentContainerStyle={styles.centre}>
          <Text style={styles.emoji}>⛔</Text>
          <Text style={styles.titre}>{tr('face_check.compte_suspendu')}</Text>
          <Text style={styles.texte}>
            {statut?.message ??
              "Un contrôle d'identité n'a pas abouti. Un administrateur doit lever la suspension."}
          </Text>
          <Text style={styles.precision}>
            Signaler un problème ne lève pas la suspension : cela ouvre un dossier qu'un
            administrateur traitera.
          </Text>

          <View style={styles.actions}>
            <Button label={tr('face_check.signaler_un_probleme')} variant="glass" onPress={() => setSignalementOuvert(true)} fullWidth />
          </View>
        </ScrollView>

        <FeuilleDeSignalement
          ouverte={signalementOuvert}
          onFermer={() => setSignalementOuvert(false)}
          checkId={controle?.id ?? statut?.pending_check ?? null}
        />
      </View>
    );
  }

  if (enAttenteDuVerdict) {
    return (
      <View style={styles.plein}>
        <View style={styles.centre}>
          <ActivityIndicator color={theme.text} />
          <Text style={[styles.titre, { marginTop: 24 }]}>{tr('face_check.verification_en_cours')}</Text>
          <Text style={styles.texte}>{tr('face_check.encore_quelques_secondes_ne_fermez')}</Text>
        </View>
      </View>
    );
  }

  if (!permission) {
    return <View style={styles.plein} />;
  }

  if (!permission.granted) {
    return (
      <View style={styles.plein}>
        <ScrollView contentContainerStyle={styles.centre}>
          <Text style={styles.emoji}>📷</Text>
          <Text style={styles.titre}>{tr('face_check.acces_a_la_camera')}</Text>
          <Text style={styles.texte}>
            La vérification d'identité a besoin de la caméra frontale. Aucune image n'est partagée
            avec vos clients.
          </Text>
          <View style={styles.actions}>
            <Button label={tr('face_check.autoriser_la_camera')} onPress={demanderPermission} fullWidth />
            <Button label={tr('face_check.ca_ne_marche_pas')} variant="ghost" onPress={() => setSignalementOuvert(true)} fullWidth />
          </View>
        </ScrollView>

        <FeuilleDeSignalement
          ouverte={signalementOuvert}
          onFermer={() => setSignalementOuvert(false)}
          checkId={null}
        />
      </View>
    );
  }

  // ── La caméra ────────────────────────────────────────────────────────────

  return (
    <View style={styles.plein}>
      {/*
        `CameraView` N'ACCEPTE PLUS D'ENFANTS depuis expo-camera 5x : la visée est posée à côté, en
        position absolue, avec `pointerEvents` neutre. Le scanner de présence a appris ça avant nous.
      */}
      <CameraView ref={camera} style={StyleSheet.absoluteFill} facing="front" />

      <View style={StyleSheet.absoluteFill} pointerEvents="box-none">
        <View style={styles.viseeConteneur} pointerEvents="none">
          <Animated.View style={[styles.visee, { transform: [{ scale: echelle }] }]} />
        </View>

        <View style={styles.basConteneur} pointerEvents="box-none">
          <GlassSurface strong radius={28} style={styles.panneau}>
            <Text style={styles.eyebrow}>
              {enrolement ? tr('face_check.premiere_etape') : tr('face_check.verification_didentite')}
            </Text>
            <Text style={styles.titreClair}>
              {enrolement ? tr('face_check.enregistrez_votre_visage') : tr('face_check.confirmez_que_cest_bien_vous')}
            </Text>
            <Text style={styles.texteClair}>
              {enrolement
                ? 'Cette photo servira de référence. Elle reste privée : ni vos clients ni votre société ne la voient.'
                : 'Regardez l’objectif, sans lunettes de soleil ni masque. Aucun client ne verra cette photo.'}
            </Text>

            {statut?.liveness_required ? (
              <Text style={styles.precisionClaire}>
                {tr('face_check.prenez_la_photo_en_direct')}
              </Text>
            ) : null}

            {/*
              LE TEXTE DE CONSENTEMENT VIENT DU SERVEUR, déjà traduit dans la langue du prestataire.
              L'application ne le recopie pas : deux versions d'un texte relu une seule fois, et
              c'est celle qu'on n'aurait pas relue qui s'afficherait.
            */}
            {enrolement && statut?.consent_text ? (
              <Pressable
                onPress={() => setConsentementDonne((v) => !v)}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: consentementDonne }}
                style={styles.consentement}
              >
                <View style={[styles.case, consentementDonne ? styles.caseCochee : null]}>
                  <Text style={styles.caseMarque}>{consentementDonne ? '✓' : ''}</Text>
                </View>
                <View style={styles.consentementTexte}>
                  <Text style={styles.precisionClaire}>{statut.consent_text}</Text>
                  {statut.consent_legal_note ? (
                    <Text style={styles.mentionLegale}>
                      {statut.consent_legal_note}
                      {statut.consent_version ? ` · v${statut.consent_version}` : ''}
                    </Text>
                  ) : null}
                </View>
              </Pressable>
            ) : null}

            {erreur ? <Text style={styles.erreur}>{erreur}</Text> : null}

            <View style={styles.actions}>
              <Button
                label={enrolement ? tr('face_check.enregistrer_mon_visage') : tr('face_check.prendre_la_photo')}
                onPress={capturer}
                loading={enCours}
                disabled={enCours || (enrolement && !consentementDonne)}
                fullWidth
              />
              <Button
                label={tr('face_check.ca_ne_marche_pas')}
                variant="glass"
                onPress={() => setSignalementOuvert(true)}
                fullWidth
              />
              {!enrolement && controle !== null ? (
                <Button
                  label={tr('face_check.plus_tard')}
                  variant="ghost"
                  onPress={() => abandonner.mutate(controle.id)}
                  fullWidth
                />
              ) : null}
            </View>
          </GlassSurface>
        </View>
      </View>

      <SuccessOverlay
        visible={reussi}
        message={enrolement ? tr('face_check.visage_enregistre') : tr('face_check.identite_confirmee')}
        onDismiss={() => setReussi(false)}
      />

      <FeuilleDeSignalement
        ouverte={signalementOuvert}
        onFermer={() => setSignalementOuvert(false)}
        checkId={controle?.id ?? null}
      />
    </View>
  );
}

/**
 * LE SIGNALEMENT DE PANNE — et ce qu'il ne fait PAS, écrit noir sur blanc.
 *
 * Laisser croire qu'il débloque produirait deux effets : des prestataires qui attendent en vain, et
 * des fraudeurs qui l'essaient. On préfère décevoir tout de suite.
 */
function FeuilleDeSignalement({
  ouverte,
  onFermer,
  checkId,
}: {
  ouverte: boolean;
  onFermer: () => void;
  checkId: number | null;
}) {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = useMemo(() => stylesFor(theme), [theme]);
  const signaler = useReportFaceIncident();

  const [message, setMessage] = useState('');
  const [envoye, setEnvoye] = useState(false);

  if (!ouverte) {
    return null;
  }

  const envoyer = async () => {
    if (message.trim().length < 5) {
      return;
    }

    await signaler
      .mutateAsync({
        message: message.trim(),
        checkId,
        diagnostics: {
          platform: Platform.OS,
          os_version: String(Platform.Version),
          app_version: Constants.expoConfig?.version ?? undefined,
        },
      })
      .catch(() => undefined);

    setEnvoye(true);
  };

  return (
    <View style={styles.feuille}>
      <GlassSurface strong radius={28} style={styles.panneau}>
        {envoye ? (
          <>
            <Text style={styles.titreClair}>{tr('face_check.dossier_ouvert')}</Text>
            <Text style={styles.texteClair}>
              Un administrateur a été prévenu. Votre compte reste en attente de vérification : ce
              signalement ne le débloque pas.
            </Text>
            <View style={styles.actions}>
              <Button label={tr('face_check.fermer')} onPress={onFermer} fullWidth />
            </View>
          </>
        ) : (
          <>
            <Text style={styles.eyebrow}>{tr('face_check.signaler')}</Text>
            <Text style={styles.titreClair}>{tr('face_check.que_se_passe_t_il')}</Text>
            <TextInput
              label={tr('face_check.decrivez_le_probleme')}
              value={message}
              onChangeText={setMessage}
              multiline
            />
            <Text style={styles.precisionClaire}>
              Ce signalement ne débloque pas votre compte. Il ouvre un dossier horodaté avec les
              informations techniques de votre téléphone.
            </Text>
            <View style={styles.actions}>
              <Button
                label={tr('face_check.envoyer')}
                onPress={envoyer}
                loading={signaler.isPending}
                disabled={message.trim().length < 5}
                fullWidth
              />
              <Button label={tr('face_check.annuler')} variant="ghost" onPress={onFermer} fullWidth />
            </View>
          </>
        )}
      </GlassSurface>
    </View>
  );
}

const MESSAGE_RESEAU = 'Connexion perdue. Vérifiez votre réseau et réessayez.';

const texteDuRefusDeConsentement =
  'Lisez et acceptez l’enregistrement de votre visage avant de prendre la photo.';

function messageDEchec(controle: FaceCheck): string {
  if (controle.liveness_result === 'fail') {
    return `Photo d’écran détectée. Prenez la photo en direct. Essai ${controle.attempt_number} sur ${controle.attempt_number + controle.attempts_left - 1}.`;
  }

  return `Nous ne vous avons pas reconnu. Placez-vous face à la lumière. Il vous reste ${controle.attempts_left} essai${controle.attempts_left > 1 ? 's' : ''}.`;
}

/**
 * LA PALETTE DE CET ÉCRAN NE SUIT PAS LE THÈME, ET C'EST VOULU.
 *
 * Un viseur de caméra n'a pas de mode clair : le noir n'y est pas une couleur d'interface, c'est
 * l'absence d'image. Le scanner de présence a tranché la même question avant nous.
 *
 * Mais « hors thème » ne veut pas dire « couleurs inventées ». Tout vient de `colors.mode.showcase`
 * — la palette nuit du design system — et les voiles sont des `rgba`, pas des aplats. Un
 * hexadécimal figé ici aurait rouvert exactement la dette que le garde-fou couleur surveille.
 */
function stylesFor(t: ThemeTokens) {
  const nuit = colors.mode.showcase;

  return StyleSheet.create({
    plein: { flex: 1, backgroundColor: nuit.night },
    centre: { flexGrow: 1, alignItems: 'center', justifyContent: 'center', padding: 24, gap: 8 },
    emoji: { fontSize: 48, marginBottom: 8 },
    titre: { color: nuit.text, fontSize: 24, fontWeight: '700', textAlign: 'center' },
    texte: { color: nuit.muted, fontSize: 15, lineHeight: 22, textAlign: 'center' },
    precision: { color: 'rgba(147,164,198,0.75)', fontSize: 13, lineHeight: 19, textAlign: 'center', marginTop: 12 },
    actions: { marginTop: 24, width: '100%', gap: 10 },

    consentement: { marginTop: 14, flexDirection: 'row', gap: 12, alignItems: 'flex-start' },
    consentementTexte: { flex: 1 },
    case: {
      width: 22,
      height: 22,
      borderRadius: 6,
      borderWidth: 1.5,
      borderColor: 'rgba(255,255,255,0.5)',
      alignItems: 'center',
      justifyContent: 'center',
      marginTop: 2,
    },
    caseCochee: { backgroundColor: 'rgba(255,255,255,0.92)', borderColor: 'rgba(255,255,255,0.92)' },
    caseMarque: { color: 'rgba(5,7,13,0.9)', fontSize: 13, fontWeight: '900', lineHeight: 16 },
    mentionLegale: { color: 'rgba(255,255,255,0.42)', fontSize: 11, lineHeight: 16, marginTop: 6 },

    viseeConteneur: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    visee: {
      width: 260,
      height: 330,
      borderRadius: 165,
      borderWidth: 2,
      borderColor: 'rgba(255,255,255,0.55)',
      shadowColor: t.glow,
      shadowOpacity: 0.5,
      shadowRadius: 24,
    },

    basConteneur: { padding: 16, paddingBottom: 28 },
    panneau: { padding: 20 },
    eyebrow: {
      color: 'rgba(255,255,255,0.6)',
      fontSize: 11,
      letterSpacing: 1.4,
      textTransform: 'uppercase',
      fontWeight: '700',
    },
    titreClair: { color: 'rgba(255,255,255,0.98)', fontSize: 21, fontWeight: '700', marginTop: 6 },
    texteClair: { color: 'rgba(255,255,255,0.78)', fontSize: 14, lineHeight: 21, marginTop: 8 },
    precisionClaire: { color: 'rgba(255,255,255,0.55)', fontSize: 12, lineHeight: 18, marginTop: 10 },
    // `danger[50]` : le rouge le plus clair de la rampe sémantique — lisible sur nuit, et il reste
    // du rouge. Un neutre clair aurait dit « information » là où il faut dire « ça n'a pas marché ».
    erreur: { color: colors.danger[50], fontSize: 14, lineHeight: 20, marginTop: 12, fontWeight: '600' },

    feuille: {
      position: 'absolute',
      left: 0,
      right: 0,
      bottom: 0,
      top: 0,
      justifyContent: 'flex-end',
      backgroundColor: 'rgba(7,11,20,0.78)',
      padding: 16,
      paddingBottom: 28,
    },
  });
}
