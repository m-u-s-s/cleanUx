import React, { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button, DetailRow, Divider, ErrorState, Screen, Skeleton } from '@/ui';
/* Chemin direct : trente-six suites mockent `@/ui` a la main, et un export neuf y
   manque sans que `tsc` bronche — le composant arrive `undefined` au rendu. */
import { GrilleDeCases } from '@/ui/GrilleDeCases';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { readServerErrors, useResourceAction, useResourceDelete, useResourceDetail, useResourceIndex } from './hooks';
import { formatCell } from './format';
import { ActionInputSheet } from './ActionInputSheet';
import type { ResourceAction, ResourceColumn } from './types';
import { useTraduction } from '@/i18n';

interface Params {
  resource: string;
  title: string;
  id: string | number;
}

/**
 * Le détail générique d'une ligne, et les actions qu'on peut y lancer.
 *
 * LA CONFIRMATION D'UNE ACTION DESTRUCTIVE EST TENUE ICI. Le serveur annonce `destructive` et le
 * texte à afficher ; c'est l'interface qui demande. Le serveur ne peut pas savoir si le doigt a
 * glissé — mais il peut dire ce que l'action détruit, et c'est ce texte-là qu'on montre, jamais
 * un « Êtes-vous sûr ? » générique qu'on valide sans lire.
 */
export function ResourceDetailScreen({ route }: { route: { params: Params } }) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { resource, title, id } = route.params;
  const navigation = useNavigation<{ navigate: (s: string, p?: object) => void; goBack: () => void }>();

  const { data: row, isLoading, isError, refetch } = useResourceDetail(resource, id);
  // Le descripteur vit avec la liste : le relire ici évite un second contrat à maintenir.
  const { data: liste } = useResourceIndex(resource);
  const descripteur = liste?.pages[0]?.resource;

  const action = useResourceAction(resource);
  const suppression = useResourceDelete(resource);
  const [erreur, setErreur] = useState<string | null>(null);

  // L'action en attente de saisie, et les erreurs que le serveur a posées sur ses champs.
  const [saisieEnCours, setSaisieEnCours] = useState<ResourceAction | null>(null);
  const [erreursSaisie, setErreursSaisie] = useState<Record<string, string>>({});

  if (isLoading) {
    return (
      <Screen>
        <View testID="resource-detail-loading" style={{ paddingTop: spacing.md, gap: spacing.sm }}>
          {Array.from({ length: 6 }).map((_, index) => (
            <Skeleton key={index} width="100%" height={44} />
          ))}
        </View>
      </Screen>
    );
  }

  if (isError || !row) {
    return (
      <Screen>
        <ErrorState
          message="Cet élément n’a pas pu être chargé."
          onRetry={() => {
            void refetch();
          }}
        />
      </Screen>
    );
  }

  const colonnes: ResourceColumn[] = descripteur?.columns ?? [];
  const modifiable = (descripteur?.form.length ?? 0) > 0;

  const lancer = (a: ResourceAction) => {
    /*
     * Une action qui EXIGE des valeurs ouvre la feuille de saisie plutôt qu'une alerte : son
     * texte de confirmation y est affiché au-dessus des champs, donc lu pendant qu'on écrit le
     * motif — plutôt que validé dans une boîte de dialogue puis oublié.
     */
    if (a.fields.length > 0) {
      setErreur(null);
      setErreursSaisie({});
      setSaisieEnCours(a);

      return;
    }

    const executer = () => {
      setErreur(null);
      action.mutate(
        { id, action: a.key },
        { onError: (e) => setErreur(readServerErrors(e).message) },
      );
    };

    if (!a.destructive) {
      executer();

      return;
    }

    Alert.alert(a.label, a.confirm ?? '', [
      { text: 'Annuler', style: 'cancel' },
      { text: a.label, style: 'destructive', onPress: executer },
    ]);
  };

  const envoyerSaisie = (values: Record<string, unknown>) => {
    if (!saisieEnCours) {
      return;
    }

    setErreursSaisie({});

    action.mutate(
      { id, action: saisieEnCours.key, values },
      {
        onSuccess: () => setSaisieEnCours(null),
        onError: (e) => {
          const { message, fields } = readServerErrors(e);
          setErreursSaisie(fields);

          // La feuille RESTE OUVERTE quand le refus vise un champ : la refermer effacerait ce
          // que l'utilisateur vient d'écrire, et il devrait tout resaisir pour corriger un mot.
          if (Object.keys(fields).length === 0) {
            setSaisieEnCours(null);
            setErreur(message);
          }
        },
      },
    );
  };

  /*
   * Les types COMPACTS tiennent dans une case ; `text` et `datetime` non — le premier peut
   * porter un paragraphe, le second une date complete avec l'heure.
   */
  const TYPES_COMPACTS = ['number', 'money', 'bool', 'date', 'badge'] as const;

  const devise =
    typeof row?.currency === 'string' && row.currency !== '' ? row.currency : null;

  const casesCompactes = colonnes
    .filter((c) => (TYPES_COMPACTS as readonly string[]).includes(c.type))
    .map((c) => ({
      libelle: c.label,
      valeur: formatCell(row[c.key], c.type, devise),
    }));

  const colonnesLongues = colonnes.filter(
    (c) => !(TYPES_COMPACTS as readonly string[]).includes(c.type),
  );

  return (
    <Screen>
      <ScrollView showsVerticalScrollIndicator={false}>
        <Text style={styles.heading}>{title}</Text>

        {/*
          LES VALEURS COMPACTES EN CASES, LES TEXTES EN LIGNES.

          Cet ecran est GENERIQUE : il sert quatre-vingt-un modules d'administration, et une
          colonne y porte aussi bien un nombre qu'un paragraphe. Tout mettre en cases
          tronquerait les seconds au deuxieme mot.

          Le descripteur donne le type de chaque colonne — c'est lui qui decide. Un nombre,
          un montant, un booleen ou une date tiennent dans une case et s'y lisent mieux ;
          un texte ou une date-heure gardent leur ligne.

          LA DEVISE VIENT DE LA LIGNE. `currency: 'EUR'` etait code en dur dans le
          formateur : la console affichait TOUS les montants en euros, y compris ceux d'une
          zone facturee en dirhams. Un montant faux sur un ecran de pilotage se propage en
          decisions fausses.
        */}
        {casesCompactes.length > 0 ? (
          <GrilleDeCases colonnes={2} style={styles.grille} cases={casesCompactes} />
        ) : null}

        {colonnesLongues.map((column) => (
          <DetailRow
            key={column.key}
            label={column.label}
            value={formatCell(row[column.key], column.type, devise)}
          />
        ))}

        {/* Les champs que le détail porte en plus des colonnes de liste. */}
        {Object.keys(row)
          .filter((key) => key !== 'id' && !colonnes.some((c) => c.key === key))
          .map((key) => (
            <DetailRow key={key} label={key} value={formatCell(row[key], 'text', devise)} />
          ))}

        {erreur ? <Text style={styles.erreur}>{erreur}</Text> : null}

        <Divider />

        <View style={styles.actions}>
          {modifiable ? (
            <Button
              label={tr('resource_detail.modifier')}
              variant="secondary"
              onPress={() => navigation.navigate('AdminResourceForm', { resource, title, id })}
            />
          ) : null}

          {(descripteur?.actions ?? []).map((a) => (
            <Button
              key={a.key}
              label={a.label}
              variant={a.destructive ? 'danger' : 'secondary'}
              loading={action.isPending}
              onPress={() => lancer(a)}
            />
          ))}

          {modifiable ? (
            <Button
              label={tr('resource_detail.supprimer')}
              variant="danger"
              loading={suppression.isPending}
              onPress={() =>
                Alert.alert(tr('resource_detail.supprimer'), tr('resource_detail.cet_element_sera_definitivement_retire'), [
                  { text: 'Annuler', style: 'cancel' },
                  {
                    text: 'Supprimer',
                    style: 'destructive',
                    onPress: () =>
                      suppression.mutate(id, {
                        onSuccess: () => navigation.goBack(),
                        onError: (e) => setErreur(readServerErrors(e).message),
                      }),
                  },
                ])
              }
            />
          ) : null}
        </View>
      </ScrollView>

      <ActionInputSheet
        action={saisieEnCours}
        visible={saisieEnCours !== null}
        submitting={action.isPending}
        errors={erreursSaisie}
        onCancel={() => setSaisieEnCours(null)}
        onSubmit={envoyerSaisie}
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  grille: {
    marginBottom: spacing.md,
  },
  heading: {
    ...typography.preset.headline,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  actions: { gap: spacing.sm, paddingVertical: spacing.md },
  erreur: {
    color: t.danger,
    fontSize: typography.fontSize.sm,
    paddingVertical: spacing.sm,
  },
});
