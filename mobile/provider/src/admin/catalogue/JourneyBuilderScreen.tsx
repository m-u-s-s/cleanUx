import React, { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, TextInput as RNTextInput, View } from 'react-native';
import { useRoute } from '@react-navigation/native';
import { Badge, Button, EmptyState, ErrorState, Screen, Skeleton, TextInput } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { messageDErreur } from './erreur';
import { LigneActions } from './LigneActions';
import { useJourney, useJourneyMutation } from './journeyHooks';
import type { JourneyOption, JourneyQuestion } from './journeyHooks';
import { useTraduction } from '@/i18n';

/** Les types qu'on sait créer depuis le mobile. Les autres se règlent sur le web. */
const TYPES = [
  { valeur: 'boolean', libelle: 'Oui / Non' },
  { valeur: 'single_choice', libelle: 'Un choix' },
  { valeur: 'multi_choice', libelle: 'Plusieurs choix' },
  { valeur: 'counter', libelle: 'Compteur' },
  { valeur: 'surface', libelle: 'Surface' },
  { valeur: 'text', libelle: 'Texte' },
  /*
   * LES DEUX LOCALISATIONS QUI FONT UN TRAJET.
   *
   * Un parcours qui pose un départ ET une arrivée bascule le métier entier : distance calculée à la
   * commande, mission sans code, permis de conduire exigé du prestataire. Le laisser au web
   * signifierait qu'un métier de transport créé en déplacement part sans ses règles — et que
   * personne ne s'en aperçoive avant qu'un conducteur sans permis reçoive une course.
   */
  { valeur: 'location:pickup', libelle: 'Départ (carte)' },
  { valeur: 'location:dropoff', libelle: 'Arrivée (carte)' },
] as const;

/**
 * Le constructeur de parcours, en natif.
 *
 * CE QU'IL SERT ET CE QU'IL LAISSE AU WEB. Ici : lire le parcours, ajouter et régler des questions,
 * poser les réponses et leurs SUPPLÉMENTS, ordonner, publier. Au web : traductions, révisions,
 * import/export, duplication vers un autre métier, simulateur de prix — des gestes de bureau, qui
 * demandent chacun leur écran et qu'on ne fait pas debout sur un chantier.
 *
 * LE SUPPLÉMENT D'UNE RÉPONSE EST LA RAISON D'ÊTRE DE CET ÉCRAN. « Voulez-vous l'installation ?
 * Oui / Non », où seul « Oui » ajoute 150 € : le montant vit sur la RÉPONSE, pas sur la question.
 * Posé sur la question, il s'appliquerait dès qu'elle est répondue — donc aussi à « Non ».
 *
 * LE VERDICT DE PUBLICATION EST EN TÊTE. Régler un parcours sans savoir s'il partira, c'est
 * découvrir le refus après coup ; l'écran web l'affiche en permanence, celui-ci aussi.
 */
export function JourneyBuilderScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const route = useRoute<{ key: string; name: string; params: { tradeId: number; title?: string } }>();

  const { tradeId } = route.params;
  const { data, isLoading, isError, error, refetch } = useJourney(tradeId);
  const agir = useJourneyMutation(tradeId);

  const [nouvelleLabel, setNouvelleLabel] = useState('');
  const [nouveauType, setNouveauType] = useState<string>('boolean');

  if (isLoading) {
    return (
      <Screen>
        <View style={styles.chargement}>
          <Skeleton width="100%" height={72} />
          <Skeleton width="100%" height={72} />
          <Skeleton width="100%" height={72} />
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState
          message={messageDErreur(error, 'Impossible de charger le parcours.')}
          onRetry={() => refetch()}
        />
      </Screen>
    );
  }

  const publiable = data?.publication.can_publish ?? false;

  const ajouterQuestion = () => {
    const label = nouvelleLabel.trim();

    if (label === '') {
      return;
    }

    /*
     * Le rôle d'une localisation voyage DANS le choix de type, puis se sépare ici.
     *
     * Deux listes — un type, puis un rôle — feraient deux gestes pour une seule décision, et le
     * second serait oublié : une localisation sans rôle ne décrit rien, et le métier ne
     * basculerait pas en trajet sans que rien ne l'explique.
     */
    const [type, role] = nouveauType.split(':');

    agir.mutate({
      type: 'question.create',
      values: {
        label,
        // Le code se déduit du libellé : c'est un identifiant technique, et le demander à qui écrit
        // une question l'oblige à inventer une convention.
        code: label.toLowerCase().normalize('NFD').replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').slice(0, 60) || 'question',
        type,
        ...(role ? { location_role: role } : {}),
      },
    });

    setNouvelleLabel('');
  };

  const publier = () => {
    agir.mutate(
      { type: 'publish' },
      {
        onError: (erreur) =>
          Alert.alert(tr('journey_builder.publication_refusee'), messageDErreur(erreur, 'Le parcours n’est pas prêt.')),
        onSuccess: () => Alert.alert(tr('journey_builder.publie'), tr('journey_builder.les_commandes_en_cours_citeront')),
      },
    );
  };

  return (
    <Screen scroll>
      {/*
        Le verdict en tête, et non caché en bas : c'est la première chose qu'on veut savoir en
        ouvrant un parcours, et la dernière qu'on vérifie avant de partir.
      */}
      <View style={[styles.verdict, publiable ? styles.verdictOk : styles.verdictBloque]}>
        <Text style={styles.verdictTexte}>
          {publiable
            ? tr('journey_builder.ce_parcours_est_publiable')
            : tr('journey_builder.ce_parcours_nest_pas_encore')}
        </Text>
      </View>

      {/*
        Un parcours à deux localisations ne change pas qu'un formulaire : il change le cycle de vie
        de la mission, les documents exigés du prestataire et le calcul du prix. L'écrire ici évite
        qu'un administrateur bascule tout cela sans le savoir.
      */}
      {data?.trade.is_route_service ? (
        <View style={[styles.verdict, styles.verdictOk]}>
          <Text style={styles.verdictTexte} testID="verdict-trajet">
            Service de trajet : départ et arrivée posés. La mission se déroulera sans code, et le
            prestataire devra fournir son permis de conduire.
          </Text>
        </View>
      ) : null}

      <View style={styles.ajout}>
        <TextInput
          label={tr('journey_builder.nouvelle_question')}
          value={nouvelleLabel}
          onChangeText={setNouvelleLabel}
          placeholder={tr('journey_builder.voulez_vous_linstallation')}
          accessibilityLabel={tr('journey_builder.libelle_de_la_nouvelle_question')}
        />

        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.types}>
          {TYPES.map((type) => (
            <Pressable
              key={type.valeur}
              onPress={() => setNouveauType(type.valeur)}
              accessibilityRole="radio"
              accessibilityState={{ selected: nouveauType === type.valeur }}
              style={[styles.type, nouveauType === type.valeur && styles.typeChoisi]}
            >
              <Text style={[styles.typeTexte, nouveauType === type.valeur && styles.typeTexteChoisi]}>
                {type.libelle}
              </Text>
            </Pressable>
          ))}
        </ScrollView>

        <Button label={tr('journey_builder.ajouter_la_question')} onPress={ajouterQuestion} variant="secondary" />
      </View>

      {(data?.data ?? []).length === 0 ? (
        <EmptyState
          title={tr('journey_builder.aucune_question')}
          message="Commencez par la plus déterminante pour le prix — la surface, le type d’intervention."
        />
      ) : null}

      {(data?.data ?? []).map((question, index) => (
        <QuestionCard
          key={question.id}
          question={question}
          premier={index === 0}
          dernier={index === (data?.data.length ?? 0) - 1}
          onMonter={() => agir.mutate({ type: 'question.move', id: question.id, direction: -1 })}
          onDescendre={() => agir.mutate({ type: 'question.move', id: question.id, direction: 1 })}
          onRetirer={() => agir.mutate({ type: 'question.remove', id: question.id })}
          onAjouterReponse={(label) =>
            agir.mutate({ type: 'option.create', id: question.id, values: { label } })
          }
          onReglerReponse={(optionId, euros) =>
            agir.mutate({ type: 'option.update', id: optionId, values: { price_modifier_euros: euros } })
          }
          onRenommerReponse={(optionId, label) =>
            agir.mutate({ type: 'option.update', id: optionId, values: { label } })
          }
          onRetirerReponse={(optionId) => agir.mutate({ type: 'option.remove', id: optionId })}
        />
      ))}

      <View style={styles.publier}>
        <Button
          label={tr('journey_builder.publier_le_parcours')}
          onPress={publier}
          disabled={!publiable}
          fullWidth
        />
      </View>
    </Screen>
  );
}

function QuestionCard({
  question,
  premier,
  dernier,
  onMonter,
  onDescendre,
  onRetirer,
  onAjouterReponse,
  onReglerReponse,
  onRenommerReponse,
  onRetirerReponse,
}: {
  question: JourneyQuestion;
  premier: boolean;
  dernier: boolean;
  onMonter: () => void;
  onDescendre: () => void;
  onRetirer: () => void;
  onAjouterReponse: (label: string) => void;
  onReglerReponse: (optionId: number, euros: string) => void;
  onRenommerReponse: (optionId: number, label: string) => void;
  onRetirerReponse: (optionId: number) => void;
}) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const [nouvelleReponse, setNouvelleReponse] = useState('');

  const aDesOptions = ['boolean', 'single_choice', 'multi_choice'].includes(question.type);

  return (
    <View style={styles.carte}>
      <View style={styles.carteEntete}>
        <View style={styles.carteTitre}>
          <Text style={styles.questionLabel}>{question.label}</Text>
          <Text style={styles.questionMeta}>
            {question.code} · {question.type}
            {question.is_required ? ' · obligatoire' : ''}
          </Text>
        </View>

        <LigneActions
          sujet={question.label}
          actions={[
            ...(premier ? [] : [{ cle: 'up', libelle: 'Monter', executer: onMonter }]),
            ...(dernier ? [] : [{ cle: 'down', libelle: 'Descendre', executer: onDescendre }]),
            { cle: 'remove', libelle: 'Retirer du parcours', destructive: true, executer: onRetirer },
          ]}
        />
      </View>

      {aDesOptions ? (
        <View style={styles.reponses}>
          {question.options.map((option) => (
            <ReponseLigne
              key={option.id}
              option={option}
              onPrix={(euros) => onReglerReponse(option.id, euros)}
              onLibelle={(label) => onRenommerReponse(option.id, label)}
              onRetirer={() => onRetirerReponse(option.id)}
            />
          ))}

          <View style={styles.ajoutReponse}>
            <RNTextInput
              value={nouvelleReponse}
              onChangeText={setNouvelleReponse}
              placeholder={tr('journey_builder.ajouter_une_reponse')}
              placeholderTextColor={colors.surface[400]}
              accessibilityLabel={`Ajouter une réponse à ${question.label}`}
              style={styles.champReponse}
              onSubmitEditing={() => {
                if (nouvelleReponse.trim() !== '') {
                  onAjouterReponse(nouvelleReponse.trim());
                  setNouvelleReponse('');
                }
              }}
            />
          </View>

          <Text style={styles.aide}>
            Le supplément d’une réponse ne s’ajoute que si le client la choisit. Un montant négatif
            retire du prix.
          </Text>
        </View>
      ) : null}
    </View>
  );
}

function ReponseLigne({
  option,
  onPrix,
  onLibelle,
  onRetirer,
}: {
  option: JourneyOption;
  onPrix: (euros: string) => void;
  onLibelle: (label: string) => void;
  onRetirer: () => void;
}) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.reponse}>
      <RNTextInput
        defaultValue={option.label}
        onEndEditing={(e) => onLibelle(e.nativeEvent.text)}
        accessibilityLabel={tr('journey_builder.libelle_de_la_reponse')}
        style={styles.champLibelle}
      />

      <RNTextInput
        defaultValue={
          option.price_modifier_cents ? (option.price_modifier_cents / 100).toFixed(2).replace('.', ',') : ''
        }
        onEndEditing={(e) => onPrix(e.nativeEvent.text)}
        placeholder="0"
        placeholderTextColor={colors.surface[400]}
        keyboardType="numbers-and-punctuation"
        accessibilityLabel={tr('journey_builder.supplement_en_euros')}
        style={styles.champPrix}
      />

      <Text style={styles.euro}>€</Text>

      {option.is_default ? <Badge label={tr('journey_builder.defaut')} variant="success" /> : null}

      <LigneActions
        sujet={option.label}
        actions={[{ cle: 'remove', libelle: 'Retirer', destructive: true, executer: onRetirer }]}
      />
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  chargement: { gap: spacing.sm, paddingTop: spacing.md },
  verdict: { marginTop: spacing.md, padding: spacing.sm, borderRadius: 12 },
  verdictOk: { backgroundColor: t.tint.success },
  verdictBloque: { backgroundColor: t.tint.warning },
  verdictTexte: { fontSize: typography.fontSize.sm, color: t.text },
  ajout: { gap: spacing.sm, paddingVertical: spacing.md },
  types: { flexGrow: 0 },
  type: {
    minHeight: 40,
    justifyContent: 'center',
    paddingHorizontal: spacing.sm,
    marginRight: spacing.xs,
    borderRadius: 999,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
  },
  typeChoisi: { backgroundColor: t.tint.brand, borderColor: colors.brand[500] },
  typeTexte: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  typeTexteChoisi: { color: t.text, fontWeight: '600' },
  carte: {
    marginBottom: spacing.md,
    padding: spacing.sm,
    borderRadius: 16,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    backgroundColor: t.card,
  },
  carteEntete: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  carteTitre: { flex: 1 },
  questionLabel: { ...typography.preset.bodyReadable, color: t.text, fontWeight: '600' },
  questionMeta: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: 2 },
  reponses: { marginTop: spacing.sm, gap: spacing.xs },
  reponse: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
  champLibelle: {
    flex: 1,
    minHeight: 44,
    paddingHorizontal: spacing.xs,
    borderRadius: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    color: t.text,
    fontSize: typography.fontSize.sm,
  },
  champPrix: {
    width: 80,
    minHeight: 44,
    paddingHorizontal: spacing.xs,
    borderRadius: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    color: t.text,
    fontSize: typography.fontSize.sm,
    textAlign: 'right',
  },
  euro: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  ajoutReponse: { marginTop: spacing.xs },
  champReponse: {
    minHeight: 44,
    paddingHorizontal: spacing.xs,
    borderRadius: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderStyle: 'dashed',
    borderColor: t.border,
    color: t.text,
    fontSize: typography.fontSize.sm,
  },
  aide: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: spacing.xs },
  publier: { paddingVertical: spacing.lg },
});
