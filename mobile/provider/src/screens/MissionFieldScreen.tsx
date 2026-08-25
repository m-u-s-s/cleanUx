import React, { useCallback, useState } from 'react';
import { View, Text, FlatList, Switch, StyleSheet, Alert, Image, ScrollView, TouchableOpacity, Linking, Platform } from 'react-native';
import { Screen, Button, Badge, Divider, TextInput, ProgressBar } from '@/ui';
/* Chemin direct : trente-six suites mockent `@/ui` a la main, et un export neuf y
   manque sans que `tsc` bronche — le composant arrive `undefined` au rendu. */
import { GrilleDeCases } from '@/ui/GrilleDeCases';
import {
  useMissionDetail,
  useMissionLifecycle,
  useMissionMedia,
  useMissionIncidents,
  useCaptureMissionMedia,
  useReportMissionIncident,
  useMissionExtras,
  useMissionAccessSheet,
  useProposeMissionExtra,
  useMissionChecklist,
  useToggleMissionChecklistItem,
  useDeclareNoShow,
  INCIDENT_TYPES,
} from '@/missions';
import type { MissionExtraItem, MissionIncidentType, MissionMediaItem } from '@/missions';
import { useInspection, useToggleChecklistItem } from '@/inspection';
import { useGpsWatcher, usePushPosition } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { formatAdresse, messageDErreur } from '@brio/shared/format';
import { MissionClockBar, useMissionClock, AnnulerLaMissionSheet, BoutonAppelMasque } from '@brio/shared';
import { FieldQuoteRevision } from '@/screens/components/FieldQuoteRevision';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionField'>;

/**
 * L'ÉCRAN DU TERRAIN — celui qu'on tient à la main, chez le client, pendant l'intervention.
 *
 * Il existait, il était atteignable, et il ne servait à rien : ses deux conditions d'affichage
 * testaient `in_progress`, un statut qui n'existe dans AUCUN état du serveur (`MissionStatus` dit
 * `started`). Le partage GPS ne démarrait donc jamais et le bouton de clôture ne s'affichait
 * jamais — l'écran se réduisait à une checklist. La même erreur avait déjà été corrigée sur
 * l'écran de détail ; elle survivait ici.
 *
 * Trois outils s'y ajoutent, dans l'ordre où on s'en sert : documenter (avant / après), signaler
 * ce qui cloche, et suivre l'avancement. Ils sont indépendants du cycle de vie à dessein — la
 * photo utile est celle qu'on prend au moment où on voit la trace, pas celle qu'un formulaire
 * réclame à la clôture.
 */
export function MissionFieldScreen({ route, navigation }: Props) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { missionId } = route.params;
  const { data: mission } = useMissionDetail(missionId);
  const { data: inspection } = useInspection(missionId);
  const { data: media } = useMissionMedia(missionId);
  const { data: incidents } = useMissionIncidents(missionId);
  const toggleItem = useToggleChecklistItem();
  // La checklist qui CONDITIONNE la clôture — distincte de l'inspection qualité ci-dessus.
  const { data: checklist } = useMissionChecklist(missionId);
  const toggleMissionItem = useToggleMissionChecklistItem(missionId);
  const pushPosition = usePushPosition(missionId);
  const lifecycle = useMissionLifecycle(missionId);
  const capture = useCaptureMissionMedia(missionId);
  const reportIncident = useReportMissionIncident(missionId);
  const [gpsActive, setGpsActive] = useState(true);
  const [incidentType, setIncidentType] = useState<MissionIncidentType | null>(null);
  const [incidentDescription, setIncidentDescription] = useState('');
  const [annulationOuverte, setAnnulationOuverte] = useState(false);

  /*
   * Le compteur bat à la seconde sur l'appareil, mais l'ÉCHÉANCE et le MONTANT viennent du
   * serveur, rafraîchis par `useMissionDetail`. C'est voulu : le montant affiché est donc
   * légèrement en retard sur le chronomètre, et c'est infiniment préférable à un écran qui
   * calculerait lui-même ce que le client va payer.
   */
  const horloge = useMissionClock(mission?.clock);

  const { data: extras } = useMissionExtras(missionId);
  const { data: ficheDAcces } = useMissionAccessSheet(missionId);
  const proposerUnExtra = useProposeMissionExtra(missionId);
  const [extraLabel, setExtraLabel] = useState('');
  const [extraPrix, setExtraPrix] = useState('');

  /**
   * PROPOSER LE SUPPLÉMENT — la saisie est en euros, l'envoi en centimes.
   *
   * Le prestataire tape « 25 » ou « 25,50 » ; convertir ici évite de faire voyager un flottant
   * jusqu'à la base, où il produirait des écarts d'un centime que personne ne sait expliquer.
   */
  const proposerLExtra = useCallback(() => {
    const euros = Number(extraPrix.replace(',', '.'));

    if (!extraLabel.trim() || !Number.isFinite(euros) || euros <= 0) {
      Alert.alert('Incomplet', 'Dites ce que vous proposez, et à quel prix.');
      return;
    }

    proposerUnExtra.mutate(
      { label: extraLabel.trim(), priceCents: Math.round(euros * 100) },
      {
        onSuccess: () => {
          setExtraLabel('');
          setExtraPrix('');
        },
        onError: (erreur: { message?: string }) =>
          Alert.alert('Impossible', erreur.message ?? 'Le supplément n’a pas pu être proposé.'),
      },
    );
  }, [extraLabel, extraPrix, proposerUnExtra]);

  /*
   * UNE MISSION EN PAUSE RESTE UNE MISSION EN COURS.
   *
   * `paused` est servi par la liste active du serveur au même titre que `started`, et le suivi web
   * affiche déjà le code de fin pour les deux. Ne reconnaître que `started` privait une mission
   * interrompue de son écran terrain ET de son bouton de clôture — la même impasse que le statut
   * `in_progress` avait créée, pour la raison inverse : un mot juste, mais oublié.
   *
   * Le partage GPS suit le même drapeau, à dessein : le domaine ne distingue pas « en pause » de
   * « en cours » pour la position, et inventer ici une distinction qu'il ne fait pas donnerait une
   * troisième règle à tenir. Le prestataire garde l'interrupteur.
   */
  const enCours = mission ? ['started', 'paused'].includes(mission.status) : false;

  /*
   * LE PARCOURS À DÉROULER, tranché par le serveur.
   *
   * Le deviner ici — coordonnées de dépose présentes ? nom du métier ? — ferait deviner chaque
   * écran à sa façon, et le premier à se tromper afficherait un champ de code à un conducteur au
   * volant.
   */
  const estUneCourse = mission?.is_ride === true;

  /*
   * LE MOTEUR — trois parcours, et c'est lui qui décide des sections à dérouler.
   *
   * `is_ride` ne répondait qu'à une moitié de la question : il distingue la course du reste, sans
   * dire si ce reste est vendu au temps. Le nouveau devis n'a de sens que là où le prix vient d'une
   * estimation — ni sur une course, dont le trajet fixe le prix, ni sur une mission horaire, dont
   * l'horloge le fixe. Le serveur tranche une fois ; l'écran lit.
   *
   * Repli sur `domicile` : c'est le parcours le plus complet, et une section de trop se referme
   * d'elle-même sur son propre refus — une section manquante, elle, ne se rattrape pas.
   */
  const moteur = mission?.engine ?? (estUneCourse ? 'vehicule' : 'domicile');

  /*
   * L'attente au point de prise en charge. `null` quand la question ne se pose pas.
   *
   * Recalculé à chaque rendu depuis une DATE serveur : une durée reçue en secondes se remettrait à
   * zéro à chaque retour sur l'écran, et il suffirait de quitter puis revenir pour déclarer un
   * passager absent au bout de trois secondes.
   */
  const echeanceAbsence = mission?.no_show_available_at ?? null;
  const absenceDeclarable = echeanceAbsence === null ? null : Date.parse(echeanceAbsence) <= Date.now();
  const minutesAvantAbsence = echeanceAbsence === null
    ? 0
    : Math.max(0, Math.ceil((Date.parse(echeanceAbsence) - Date.now()) / 60000));
  const declarerAbsence = useDeclareNoShow(missionId);

  useGpsWatcher(
    gpsActive && enCours,
    useCallback(
      (pos) => {
        pushPosition.mutate({ latitude: pos.latitude, longitude: pos.longitude, speed: pos.speed ?? undefined });
      },
      [pushPosition],
    ),
  );

  const photographier = (type: 'before_photo' | 'after_photo') => {
    capture.mutate(
      { type },
      {
        // `null` = l'utilisateur a annulé le sélecteur. Ce n'est pas un échec, et afficher une
        // confirmation là où il vient de renoncer serait incompréhensible.
        onSuccess: (resultat) =>
          resultat &&
          Alert.alert(
            'Photo enregistrée',
            'Elle est horodatée, géolocalisée et visible par le client.',
          ),
        onError: (e: any) =>
          Alert.alert('Envoi impossible', messageDErreur(e, 'Réessayez dans un instant.')),
      },
    );
  };

  const envoyerLImprevu = (avecPhoto: boolean) => {
    if (!incidentType || incidentDescription.trim().length < 3) {
      Alert.alert('Incomplet', 'Choisissez une catégorie et décrivez ce que vous constatez.');

      return;
    }

    reportIncident.mutate(
      { type: incidentType, description: incidentDescription.trim(), withPhoto: avecPhoto },
      {
        onSuccess: () => {
          setIncidentType(null);
          setIncidentDescription('');
          Alert.alert('Signalé', 'Le client vient d’être prévenu.');
        },
        onError: (e: any) =>
          Alert.alert('Signalement impossible', messageDErreur(e, 'Réessayez dans un instant.')),
      },
    );
  };

  if (!mission) return <Screen><Text style={styles.loading}>Chargement...</Text></Screen>;

  const avant = (media ?? []).filter((m) => m.type === 'before_photo');
  const apres = (media ?? []).filter((m) => m.type === 'after_photo');

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{mission.service_name}</Text>
        <Badge label={mission.status} variant="success" />
      </View>

      <View style={styles.clientCard}>
        <Text style={styles.clientName}>{mission.client_name}</Text>
        <Text style={styles.clientAddress}>{formatAdresse(mission.address, mission.city)}</Text>
      </View>

      {/*
        LE COMPTEUR, JUSTE SOUS L'IDENTITÉ DU CLIENT.

        Il se rend nul de lui-même quand la mission n'est pas vendue au temps — inutile de le
        conditionner ici, et surtout inutile que cet écran sache ce qui décide. Placé plus bas, il
        aurait fallu défiler pour savoir combien de temps il reste : c'est la première question du
        prestataire sur place, elle mérite le haut de l'écran.
      */}
      <MissionClockBar clock={horloge} audience="provider" style={styles.horloge} />

      {/* ── ÉTAT DES LIEUX ─────────────────────────────────────────────── */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>État des lieux</Text>
        <Text style={styles.sectionHint}>
          Chaque photo est horodatée et géolocalisée. C’est elle qui vous protège si le client
          conteste plus tard.
        </Text>

        <BandeDePhotos photos={avant} legende="Avant" />
        <BandeDePhotos photos={apres} legende="Après" />

        <View style={styles.captureRow}>
          <Button
            label="Photo avant"
            onPress={() => photographier('before_photo')}
            loading={capture.isPending}
            variant="secondary"
          />
          <Button
            label="Photo après"
            onPress={() => photographier('after_photo')}
            loading={capture.isPending}
            variant="secondary"
          />
        </View>
      </View>

      <Divider />

      {/* ── FICHE D'ACCÈS (F5) ─────────────────────────────────────────── */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Accéder au lieu</Text>

        {ficheDAcces?.available ? (
          <>
            {/*
              LA CONSIGNE DE DERNIÈRE MINUTE, EN PREMIER ET DÉTACHÉE.
 
              C'est la plus récente et la seule qui puisse contredire les autres — un digicode
              changé ce matin. La noyer dans le paragraphe d'accès la ferait sauter, parce qu'on
              relit rarement un texte qu'on croit connaître.
            */}
            {ficheDAcces.live_note ? (
              <Text style={styles.consigneVive} testID="fiche-acces-consigne-vive">
                {ficheDAcces.live_note}
              </Text>
            ) : null}

            {ficheDAcces.floor ? (
              <Text style={styles.sectionHint}>Étage : {ficheDAcces.floor}</Text>
            ) : null}
            {ficheDAcces.access_instructions ? (
              <Text style={styles.sectionHint} testID="fiche-acces-consignes">
                {ficheDAcces.access_instructions}
              </Text>
            ) : null}
            {ficheDAcces.access_window ? (
              <Text style={styles.sectionHint}>Accès {ficheDAcces.access_window}</Text>
            ) : null}
            {/*
              L'alarme demande une manœuvre chronométrée : le prestataire doit le lire AVANT
              d'ouvrir la porte, pas en entendant la sirène.
            */}
            {ficheDAcces.alarm_code_required ? (
              <Badge label="Alarme à désarmer" variant="warning" />
              ) : null}

            {/*
              LES TROIS CONTRAINTES, EN CASES ET NON EN PHRASES.

              Ce que le client a repondu POUR CETTE FOIS : materiel, animal, parking. Elles
              n'arrivaient nulle part — le prestataire se presentait sans savoir s'il devait
              charger son materiel dans la camionnette.

              En cases plutot qu'en lignes parce qu'on les lit d'un coup d'oeil, une main sur
              le volant. Le lisere porte le statut : vert pour ce qui simplifie, ambre pour ce
              qui demande de prevoir.
            */}
            {ficheDAcces.constraints ? (
              <GrilleDeCases
                colonnes={3}
                style={styles.contraintes}
                cases={[
                  {
                    libelle: 'Materiel',
                    valeur: ficheDAcces.constraints.equipment_provided ? 'Fourni' : 'A apporter',
                    ton: ficheDAcces.constraints.equipment_provided ? 'bon' : 'attention',
                  },
                  {
                    libelle: 'Animaux',
                    valeur: ficheDAcces.constraints.pets_on_site ? 'Oui' : 'Non',
                    ton: ficheDAcces.constraints.pets_on_site ? 'attention' : 'neutre',
                  },
                  {
                    libelle: 'Parking',
                    valeur: ficheDAcces.constraints.parking_available ? 'Oui' : 'Non',
                    ton: ficheDAcces.constraints.parking_available ? 'bon' : 'attention',
                  },
                ]}
              />
            ) : null}
          </>
        ) : (
          // Le refus est dit, jamais une fiche vide : une fiche vide se lit comme une donnée
          // manquante et fait appeler le support pour rien.
          <Text style={styles.sectionHint} testID="fiche-acces-verrouillee">
            {ficheDAcces?.message ?? 'Confirmez votre arrivée pour afficher les informations d’accès.'}
          </Text>
        )}
      </View>

      <Divider />

      {/* ── IMPRÉVUS ───────────────────────────────────────────────────── */}
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Signaler un imprévu</Text>
        <Text style={styles.sectionHint}>
          Le client est prévenu tout de suite, et le dossier de litige est pré-rempli s’il en ouvre
          un.
        </Text>

        <View style={styles.chipRow}>
          {INCIDENT_TYPES.map((categorie) => {
            const choisi = incidentType === categorie.value;

            return (
              <TouchableOpacity
                key={categorie.value}
                onPress={() => setIncidentType(choisi ? null : categorie.value)}
                accessibilityRole="button"
                accessibilityState={{ selected: choisi }}
                accessibilityLabel={categorie.label}
                testID={`incident-type-${categorie.value}`}
                style={[styles.chip, choisi && styles.chipSelected]}
              >
                <Text style={[styles.chipLabel, choisi && styles.chipLabelSelected]}>
                  {categorie.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        <TextInput
          label="Ce que vous constatez"
          value={incidentDescription}
          onChangeText={setIncidentDescription}
          placeholder="Trace d’humidité derrière le meuble, présente à mon arrivée."
          multiline
          testID="incident-description"
        />

        <View style={styles.captureRow}>
          <Button
            label="Signaler avec photo"
            onPress={() => envoyerLImprevu(true)}
            loading={reportIncident.isPending}
          />
          <Button
            label="Sans photo"
            onPress={() => envoyerLImprevu(false)}
            loading={reportIncident.isPending}
            variant="secondary"
          />
        </View>

        {(incidents ?? []).map((incident) => (
          <View key={incident.id} style={styles.incidentRow}>
            <Text style={styles.incidentLabel}>{incident.label}</Text>
            <Text style={styles.incidentDescription} numberOfLines={2}>
              {incident.description}
            </Text>
            <Text style={styles.incidentMeta}>
              {incident.notified_at ? 'Client prévenu' : 'Client non joint'}
            </Text>
          </View>
        ))}
      </View>

      <Divider />

      {/*
        ── NOUVEAU DEVIS (moteur À DOMICILE seulement) ─────────────────────

        AVANT le supplément à l'écran, parce que c'est avant lui dans le temps : la révision se
        décide en arrivant, le supplément se découvre en travaillant. Les mettre dans l'autre ordre
        ferait proposer un supplément à quelqu'un qui n'a pas encore constaté l'écart.
      */}
      {moteur === 'domicile' && (
        <>
          <FieldQuoteRevision missionId={missionId} photosAvant={avant} />
          <Divider />
        </>
      )}

      {/*
        ── SUPPLÉMENTS PROPOSÉS SUR PLACE (F3) ─────────────────────────────

        Rien à ajouter sur une course : son prix est fixé par le trajet, pas par la prestation.
        Le bloc n'est pas grisé, il n'est pas monté — un formulaire visible et inerte se remplit
        quand même, et le refus arrive après la saisie.
      */}
      {moteur !== 'vehicule' && (
      <View style={styles.section}>
        <Text style={styles.sectionTitle}>Proposer un supplément</Text>
        <Text style={styles.sectionHint}>
          Le client répond depuis son téléphone. Son devis d’origine ne bouge pas : le supplément
          est une ligne à part.
        </Text>

        <TextInput
          label="Ce que vous proposez"
          value={extraLabel}
          onChangeText={setExtraLabel}
          placeholder="Nettoyage des vitres"
          testID="extra-label"
        />

        <TextInput
          label="Prix en euros"
          value={extraPrix}
          onChangeText={setExtraPrix}
          placeholder="25"
          keyboardType="decimal-pad"
          testID="extra-prix"
        />

        <Button
          label="Proposer au client"
          onPress={proposerLExtra}
          loading={proposerUnExtra.isPending}
        />

        {(extras ?? []).map((extra: MissionExtraItem) => (
          <View key={extra.id} style={styles.incidentRow}>
            <Text style={styles.incidentLabel}>
              {extra.label} · {extra.price} {extra.currency}
            </Text>
            {/*
              LE PRESTATAIRE A BESOIN DE SAVOIR S'IL PEUT COMMENCER, pas de relire un historique de
              statuts. Trois états lisibles d'un coup d'œil, gants aux mains.
            */}
            <Text style={styles.incidentMeta} testID={`extra-statut-${extra.id}`}>
              {extra.awaiting_client
                ? 'En attente de réponse'
                : extra.status === 'declined'
                  ? 'Refusé par le client'
                  : 'Accepté — vous pouvez le faire'}
            </Text>
          </View>
        ))}
      </View>
      )}

      <Divider />

      {/*
        LES TÂCHES QUI CONDITIONNENT LA CLÔTURE — et qui n'étaient visibles NULLE PART.

        Le serveur refuse de terminer une mission tant qu'une tâche obligatoire reste ouverte. La
        seule checklist affichée ici venait du module Inspection, une autre table : le prestataire
        cochait tout sans jamais débloquer quoi que ce soit, et se heurtait à un refus dont le
        remède n'existait que sur des écrans web à session.

        Elle passe AVANT la checklist d'inspection, parce que c'est elle qui bloque.
      */}
      {checklist && checklist.checklists.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Tâches à valider</Text>

          {checklist.blocks_completion ? (
            <Text style={styles.blocageAvis} testID="checklist-blocage">
              {checklist.required_pending === 1
                ? 'Une tâche obligatoire reste à cocher : la mission ne peut pas être clôturée avant.'
                : `${checklist.required_pending} tâches obligatoires restent à cocher : la mission ne peut pas être clôturée avant.`}
            </Text>
          ) : (
            <Text style={styles.blocageLeve}>
              Toutes les tâches obligatoires sont faites — la clôture est possible.
            </Text>
          )}

          {checklist.checklists.map((liste) => (
            <View key={liste.id}>
              {liste.name ? <Text style={styles.sectionHint}>{liste.name}</Text> : null}
              {liste.items.map((item) => (
                <View key={item.id} style={styles.checkItem}>
                  <View style={styles.checkTexte}>
                    <Text style={styles.checkLabel}>
                      {item.label}
                      {item.is_required ? ' *' : ''}
                    </Text>
                    {item.guidance ? (
                      <Text style={styles.checkGuidance}>{item.guidance}</Text>
                    ) : null}
                  </View>
                  <Switch
                    value={item.done}
                    onValueChange={(v) =>
                      toggleMissionItem.mutate(
                        { itemId: item.id, done: v },
                        {
                          onError: (e: any) =>
                            Alert.alert('Impossible', messageDErreur(e, 'Réessayez dans un instant.')),
                        },
                      )
                    }
                    trackColor={{ true: colors.success[500] }}
                  />
                </View>
              ))}
            </View>
          ))}
        </View>
      )}

      {inspection && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Checklist qualité</Text>
          <FlatList
            data={inspection.items}
            scrollEnabled={false}
            keyExtractor={(i) => String(i.id)}
            renderItem={({ item }) => (
              <View style={styles.checkItem}>
                <Text style={styles.checkLabel}>{item.label}</Text>
                <Switch
                  value={item.completed}
                  onValueChange={(v) =>
                    toggleItem.mutate({ inspectionId: inspection.id, itemId: item.id, value: v })
                  }
                  trackColor={{ true: colors.success[500] }}
                />
              </View>
            )}
          />
        </View>
      )}

      {/*
        ── SÉCURITÉ ET LITIGE, À PORTÉE DE POUCE ───────────────────────────

        Le module SOS existe depuis toujours et vit dans le menu « Plus » : son propre commentaire
        dit qu'il sert « au moment où les mains tremblent », et il demandait de sortir de la mission,
        d'ouvrir un menu et de faire défiler. C'était un défaut de joignabilité, pas de
        fonctionnalité — et le pire endroit où en avoir un.
      */}
      <View style={styles.secours}>
        <TouchableOpacity
          onPress={() => navigation.navigate('Safety')}
          accessibilityRole="button"
          accessibilityLabel="Ouvrir le mode sécurité et l’alerte d’urgence"
          style={[styles.secoursCase, styles.secoursUrgence]}
          testID="terrain-securite"
        >
          <Text style={styles.secoursTexte}>Sécurité / SOS</Text>
        </TouchableOpacity>

        {/*
          APPELER — par le numéro relais. Le service existait depuis longtemps et n'était appelé
          de nulle part : ni le mobile ni le web ne l'atteignaient.
        */}
        <BoutonAppelMasque role="provider" missionId={missionId} testID="terrain-appeler" />

        {/*
          OUVRIR DANS UNE APPLICATION DE NAVIGATION — un bouton SECONDAIRE, et c'est une décision.

          La carte de la plateforme reste la carte principale : c'est elle qui porte la route, le
          géo-repère d'arrivée et la position partagée avec le client. Celui-ci n'existe que pour
          ceux qui préfèrent leur application habituelle, et il évite de recopier une adresse à la
          main — le geste le plus fréquent et le plus bête de la journée.
        */}
        <TouchableOpacity
          onPress={() => {
            const destination = mission.latitude != null && mission.longitude != null
              ? `${mission.latitude},${mission.longitude}`
              : formatAdresse(mission.address, mission.city);

            void Linking.openURL(
              Platform.OS === 'ios'
                ? `maps://?daddr=${encodeURIComponent(destination)}`
                : `geo:0,0?q=${encodeURIComponent(destination)}`,
            );
          }}
          accessibilityRole="button"
          accessibilityLabel="Ouvrir l’itinéraire dans une application de navigation"
          style={styles.secoursCase}
          testID="terrain-itineraire"
        >
          <Text style={styles.secoursTexte}>Itinéraire</Text>
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.navigate('ProviderChatList')}
          accessibilityRole="button"
          accessibilityLabel="Écrire au client"
          style={styles.secoursCase}
          testID="terrain-message"
        >
          <Text style={styles.secoursTexte}>Message</Text>
        </TouchableOpacity>

        <TouchableOpacity
          onPress={() => navigation.navigate('ProviderDisputes')}
          accessibilityRole="button"
          accessibilityLabel="Signaler un litige sur cette mission"
          style={styles.secoursCase}
          testID="terrain-litige"
        >
          <Text style={styles.secoursTexte}>Litige</Text>
        </TouchableOpacity>
      </View>

      {/*
        ANNULER — et SEULEMENT avant le démarrage.

        Après, ce n'est plus une annulation mais un abandon : deux faits différents pour le client,
        l'un le laisse libre de recommander, l'autre le laisse avec un chantier ouvert. Le serveur
        refuse de toute façon ; ne pas montrer le bouton évite un refus qu'on n'aurait pas compris.
      */}
      {!mission.actual_start_at && mission.booking_id ? (
        <View style={styles.section}>
          {annulationOuverte ? (
            <AnnulerLaMissionSheet
              audience="provider"
              bookingId={mission.booking_id}
              onAnnulee={() => setAnnulationOuverte(false)}
              onFermer={() => setAnnulationOuverte(false)}
            />
          ) : (
            <Button
              label="Annuler la mission"
              variant="secondary"
              onPress={() => setAnnulationOuverte(true)}
              fullWidth
              testID="ouvrir-annulation"
            />
          )}
        </View>
      ) : null}

      <View style={styles.gpsRow}>
        <Text style={styles.gpsLabel}>Partage GPS actif</Text>
        <Switch
          value={gpsActive}
          onValueChange={setGpsActive}
          trackColor={{ true: colors.brand[500] }}
        />
      </View>

      <View style={styles.actions}>
        {/*
          LE PARCOURS COURSE : deux gestes, aucun code.

          Le bouton de clôture ordinaire ci-dessous appelle `complete`, que le serveur REFUSE par un
          409 sur une course — il y chercherait un code de fin qui n'a jamais existé. Les deux
          parcours sont donc distingués ici aussi, et pas seulement côté serveur : afficher la
          mauvaise action produirait un refus que le conducteur ne peut pas comprendre.
        */}
        {estUneCourse && mission?.status === 'arrived' && (
          <Button
            label="Client à bord"
            onPress={() =>
              lifecycle.mutate('ride/start', {
                onError: (e: any) =>
                  Alert.alert('Impossible', messageDErreur(e, 'Réessayez dans un instant.')),
              })
            }
            fullWidth
            size="lg"
          />
        )}

        {estUneCourse && mission?.status === 'arrived' && absenceDeclarable !== null && (
          absenceDeclarable ? (
            <Button
              label="Client absent"
              variant="secondary"
              onPress={() =>
                Alert.alert(
                  'Client absent ?',
                  'La course sera close et des frais lui seront appliqués.',
                  [
                    { text: 'Annuler', style: 'cancel' },
                    {
                      text: 'Confirmer',
                      style: 'destructive',
                      onPress: () =>
                        declarerAbsence.mutate(undefined, {
                          onError: (e: any) =>
                            Alert.alert('Impossible', messageDErreur(e, 'Réessayez dans un instant.')),
                        }),
                    },
                  ],
                )
              }
              fullWidth
              size="lg"
            />
          ) : (
            // Le décompte VIENT DU SERVEUR : un minuteur local se remettrait à zéro d'un
            // rechargement, et il suffirait de quitter l'écran pour déclarer une absence immédiate.
            <Text style={styles.gpsLabel} testID="attente-client">
              Attente du client — encore {minutesAvantAbsence} min avant de pouvoir déclarer son absence.
            </Text>
          )
        )}

        {estUneCourse && enCours && (
          <Button
            label="Terminer la course"
            onPress={() =>
              lifecycle.mutate('ride/complete', {
                onError: (e: any) =>
                  Alert.alert('Impossible', messageDErreur(e, 'Réessayez dans un instant.')),
              })
            }
            variant="danger"
            fullWidth
            size="lg"
          />
        )}

        {! estUneCourse && enCours && (
          <Button
            label="Terminer la mission"
            /*
             * LE REFUS DU SERVEUR DOIT S'AFFICHER. Sans `onError`, un 422 — « certaines tâches
             * obligatoires ne sont pas cochées », le cas le plus courant — ne produisait
             * strictement rien à l'écran. Même défaut que sur l'écran de détail, corrigé ici
             * aussi : le prestataire appuyait dans le vide.
             */
            onPress={() =>
              lifecycle.mutate('complete', {
                onError: (e: any) =>
                  Alert.alert('Impossible', messageDErreur(e, 'Réessayez dans un instant.')),
              })
            }
            variant="danger"
            fullWidth
            size="lg"
          />
        )}
      </View>
    </Screen>
  );
}

/**
 * Les vignettes d'un moment de l'intervention.
 *
 * Rien n'est affiché tant qu'il n'y a rien : une bande vide avec son titre laisserait croire à un
 * chargement en cours, alors que le prestataire n'a simplement pas encore photographié.
 */
function BandeDePhotos({ photos, legende }: { photos: MissionMediaItem[]; legende: string }) {
  const styles = stylesFor(useThemeColors());

  if (photos.length === 0) {
    return null;
  }

  return (
    <View style={styles.bande}>
      <Text style={styles.bandeTitre}>
        {legende} · {photos.length}
      </Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        {photos.map((photo) => (
          <Image
            key={photo.id}
            source={{ uri: photo.url ?? undefined }}
            style={styles.vignette}
            accessibilityLabel={`${photo.label} de l’intervention`}
          />
        ))}
      </ScrollView>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  loading: {
    color: t.textSecondary,
    textAlign: 'center',
    marginTop: spacing.xl,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  clientCard: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    marginBottom: spacing.md,
  },
  horloge: { marginBottom: spacing.md },
  clientName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  clientAddress: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginTop: 2,
  },
  section: { marginBottom: spacing.md },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.xs,
  },
  contraintes: {
    marginTop: spacing.sm,
  },
  sectionHint: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginBottom: spacing.sm,
  },
  captureRow: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.sm,
  },
  bande: { marginBottom: spacing.sm },
  bandeTitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.semibold,
    color: t.textSecondary,
    marginBottom: spacing.xs,
  },
  vignette: {
    width: 84,
    height: 84,
    borderRadius: radius.sm,
    marginRight: spacing.xs,
    backgroundColor: t.border,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.sm,
  },
  chip: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: radius.pill,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    backgroundColor: t.card,
  },
  chipSelected: {
    backgroundColor: colors.brand[500],
    borderColor: colors.brand[500],
  },
  chipLabel: {
    fontSize: typography.fontSize.xs,
    color: t.text,
  },
  chipLabelSelected: {
    color: t.textOnBrand,
    fontWeight: typography.fontWeight.semibold,
  },
  incidentRow: {
    paddingVertical: spacing.sm,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: t.border,
  },
  incidentLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  incidentDescription: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
  },
  incidentMeta: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
    fontStyle: 'italic',
  },
  checkItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  checkLabel: {
    fontSize: typography.fontSize.sm,
    color: t.text,
    flex: 1,
  },
  checkTexte: {
    flex: 1,
    paddingRight: spacing.sm,
  },
  checkGuidance: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
  },
  // Teinte d'alerte : elle contredit parfois ce qui est écrit juste en dessous, et c'est le seul
  // champ de cette fiche qui puisse changer pendant que le prestataire roule.
  consigneVive: {
    fontSize: typography.fontSize.sm,
    lineHeight: 19,
    color: t.text,
    backgroundColor: t.tint.warning,
    borderRadius: radius.sm,
    padding: spacing.sm,
    marginBottom: spacing.xs,
  },
  blocageAvis: {
    fontSize: typography.fontSize.sm,
    color: colors.danger[600],
    marginBottom: spacing.sm,
  },
  blocageLeve: {
    fontSize: typography.fontSize.sm,
    color: colors.success[600],
    marginBottom: spacing.sm,
  },
  gpsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.md,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: t.border,
  },
  gpsLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  actions: { marginTop: spacing.lg },
  secours: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
  // La case entière est la cible tactile, gants aux mains.
  secoursCase: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: t.card,
    paddingHorizontal: spacing.sm,
  },
  // L'urgence se distingue par sa TEINTE, jamais par sa seule position : une rangée de trois cases
  // identiques se choisit au hasard quand les mains tremblent.
  secoursUrgence: { backgroundColor: t.tint.danger },
  secoursTexte: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    textAlign: 'center',
  },
});
