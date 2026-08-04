import React, { useMemo } from 'react';
import { SectionList, StyleSheet, Switch, Text, View } from 'react-native';
import { useRoute } from '@react-navigation/native';
import { EmptyState, ErrorState, Screen, Skeleton } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { messageDErreur } from './erreur';
import { useToggleTradeInZone, useZoneTrades } from './hooks';
import type { ZoneTrade } from './types';

/**
 * Troisième niveau : les métiers d'une zone, ouverts ou fermés.
 *
 * TOUS LES MÉTIERS SONT LÀ, y compris ceux qui ne sont pas ouverts : l'absence de réglage est un
 * ÉTAT et non un trou. Une liste des seuls métiers déjà réglés tairait exactement ceux qu'on vient
 * ouvrir, et l'écran ne servirait à rien.
 *
 * LE BANDEAU DIT LA VÉRITÉ, comme sur le web : le moteur de commande ne lit pas encore ces
 * réglages, et le brouillon ne détermine pas la zone d'une adresse. Un écran exact mais pas encore
 * branché doit le dire, sinon on croit la fonctionnalité acquise — c'est le mode d'échec le plus
 * probable de ce chantier, et il est silencieux.
 */
export function CatalogZoneTradesScreen() {
  const styles = stylesFor(useThemeColors());
  const route = useRoute<{ key: string; name: string; params: { zoneId: number; title?: string } }>();

  const { zoneId } = route.params;
  const { data, isLoading, isError, error, refetch } = useZoneTrades(zoneId);
  const bascule = useToggleTradeInZone(zoneId);

  /*
   * Groupés par SECTEUR, comme le carrousel client. Une liste à plat empêcherait de vérifier ce
   * que verra le client, qui est pourtant la question qu'on se pose en ouvrant cet écran.
   */
  const sections = useMemo(() => {
    const parSecteur = new Map<string, ZoneTrade[]>();

    for (const metier of data?.trades ?? []) {
      const cle = metier.sector ?? 'Sans secteur';
      parSecteur.set(cle, [...(parSecteur.get(cle) ?? []), metier]);
    }

    return [...parSecteur.entries()].map(([title, metiers]) => ({ title, data: metiers }));
  }, [data]);

  if (isLoading) {
    return (
      <Screen>
        <View style={styles.chargement}>
          <Skeleton width="100%" height={56} />
          <Skeleton width="100%" height={56} />
          <Skeleton width="100%" height={56} />
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState message={messageDErreur(error, 'Impossible de charger le catalogue.')} onRetry={() => refetch()} />
      </Screen>
    );
  }

  const ouverts = (data?.trades ?? []).filter((metier) => metier.is_open).length;

  return (
    <Screen>
      <View style={styles.bandeau}>
        <Text style={styles.bandeauTexte}>
          <Text style={styles.bandeauFort}>Réglage préparatoire. </Text>
          L’ouverture d’un métier ici est bien enregistrée, mais elle n’a pas encore d’effet sur ce
          que voit un client : le parcours de commande ne détermine pas encore la zone d’une adresse.
        </Text>
      </View>

      <Text style={styles.compte}>
        {ouverts} métier(s) ouvert(s) sur {data?.trades.length ?? 0}
      </Text>

      <SectionList
        sections={sections}
        keyExtractor={(item) => String(item.id)}
        stickySectionHeadersEnabled
        showsVerticalScrollIndicator={false}
        renderSectionHeader={({ section }) => (
          <Text style={styles.sectionHeader}>{section.title}</Text>
        )}
        renderItem={({ item }) => (
          <TradeRow metier={item} onToggle={() => bascule.mutate(item.id)} />
        )}
        ListEmptyComponent={
          <EmptyState title="Aucun métier" message="Le catalogue de la plateforme est vide." />
        }
      />
    </Screen>
  );
}

function TradeRow({ metier, onToggle }: { metier: ZoneTrade; onToggle: () => void }) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.row}>
      <View style={styles.rowTexte}>
        <Text style={styles.rowTitre}>{metier.name}</Text>
        <Text style={styles.rowMeta}>
          {(metier.base_rate_cents / 100).toFixed(2).replace('.', ',')} €
          {/*
            D'où vient ce tarif : de la zone, ou du métier faute de mieux. Sans cette distinction,
            un prix hérité passerait pour une décision prise pour cette zone.
          */}
          {metier.has_zone_price ? ' · tarif de la zone' : ' · tarif du métier'}
        </Text>
      </View>

      <Switch
        value={metier.is_open}
        onValueChange={onToggle}
        accessibilityLabel={`${metier.is_open ? 'Fermer' : 'Ouvrir'} ${metier.name} dans cette zone`}
        trackColor={{ true: colors.brand[500], false: colors.surface[300] }}
      />
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  chargement: { gap: spacing.sm, paddingTop: spacing.md },
  bandeau: {
    marginTop: spacing.md,
    padding: spacing.sm,
    borderRadius: 12,
    backgroundColor: t.tint.warning,
  },
  bandeauTexte: { fontSize: typography.fontSize.xs, color: t.text },
  bandeauFort: { fontWeight: '700' },
  compte: {
    ...typography.preset.subhead,
    color: t.textSecondary,
    paddingVertical: spacing.sm,
  },
  sectionHeader: {
    ...typography.preset.subhead,
    color: t.textSecondary,
    backgroundColor: t.cardSubtle,
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm,
    textTransform: 'uppercase',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    minHeight: 56,
    paddingHorizontal: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  rowTexte: { flex: 1 },
  rowTitre: { ...typography.preset.bodyReadable, color: t.text },
  rowMeta: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: 2 },
});
