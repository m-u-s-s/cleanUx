import React, { useEffect, useMemo, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { Button, Screen, Divider } from '@/ui';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface TradeOption {
  id: number;
  name: string;
  slug: string;
  allows_asap: boolean;
  zone_ids: number[];
}

interface SectorOption {
  id: number;
  name: string;
  trades: TradeOption[];
}

interface ZoneOption {
  id: number;
  name: string;
}

interface CatalogueOptions {
  sectors: SectorOption[];
  zones: ZoneOption[];
}

interface Coverage {
  trade_ids: number[];
  zone_ids: number[];
}

/**
 * « CE QUE JE FAIS, ET OÙ » — l'écran natif, jumeau exact de la page web.
 *
 * Les deux listes viennent du MÊME point d'entrée que le formulaire web
 * (`/api/catalog/registration-options`). Deux catalogues construits séparément finiraient par
 * proposer des métiers différents selon l'appareil, et personne ne saurait lequel dit vrai.
 *
 * CE QUI EST COCHÉ ICI EST EXACTEMENT CE QUE LIT LE DISPATCH. `trade_user` et
 * `employee_zone_assignments` sont les deux tables de la requête candidate : décocher un métier
 * arrête ses offres dans la seconde, sans déploiement et sans passer par le support.
 */
export function TradesZonesScreen() {
  const theme = useThemeColors();
  const styles = stylesFor(theme);
  const queryClient = useQueryClient();

  const options = useQuery<CatalogueOptions>({
    queryKey: ['catalog', 'registration-options'],
    queryFn: async () => (await apiClient.get('/catalog/registration-options?country=BE')).data.data,
    staleTime: 5 * 60 * 1000,
  });

  const coverage = useQuery<Coverage>({
    queryKey: ['provider', 'coverage'],
    queryFn: async () => (await apiClient.get('/provider/coverage')).data.data,
  });

  const [tradeIds, setTradeIds] = useState<number[]>([]);
  const [zoneIds, setZoneIds] = useState<number[]>([]);
  const [message, setMessage] = useState('');

  // La sélection ne s'initialise qu'UNE FOIS, à l'arrivée des données. La réinitialiser à chaque
  // rendu écraserait les cases que le prestataire vient de cocher.
  useEffect(() => {
    if (coverage.data) {
      setTradeIds(coverage.data.trade_ids);
      setZoneIds(coverage.data.zone_ids);
    }
  }, [coverage.data]);

  const enregistrer = useMutation({
    mutationFn: async () => {
      await apiClient.put('/provider/coverage', { trade_ids: tradeIds, zone_ids: zoneIds });
    },
    onSuccess: () => {
      setMessage('Enregistré. Les missions correspondantes vous seront proposées dès maintenant.');
      void queryClient.invalidateQueries({ queryKey: ['provider', 'coverage'] });
    },
    onError: () => setMessage('Enregistrement impossible. Réessayez dans un instant.'),
  });

  const secteurs = useMemo(() => options.data?.sectors ?? [], [options.data]);
  const zones = useMemo(() => options.data?.zones ?? [], [options.data]);

  const basculer = (liste: number[], id: number, poser: (v: number[]) => void) => {
    poser(liste.includes(id) ? liste.filter((x) => x !== id) : [...liste, id]);
    setMessage('');
  };

  const peutEnregistrer = tradeIds.length > 0 && zoneIds.length > 0;

  return (
    <Screen testID="trades-zones-screen">
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Mes métiers et mes zones</Text>
        <Text style={styles.intro}>
          Vous ne recevez que des missions du métier et de la zone que vous avez choisis. Rien
          d’autre ne vous sera proposé.
        </Text>

        <Text style={styles.section}>Métiers</Text>
        {secteurs.length === 0 ? (
          <Text style={styles.vide}>
            Aucun métier n’est encore ouvert. Revenez quand le catalogue aura été complété.
          </Text>
        ) : (
          secteurs.map((secteur) => (
            <View key={secteur.id} style={styles.groupe}>
              <Text style={styles.groupeTitre}>{secteur.name}</Text>
              {secteur.trades.map((metier) => (
                <View key={metier.id} style={styles.ligne}>
                  <Text style={styles.ligneTexte}>{metier.name}</Text>
                  <Button
                    label={tradeIds.includes(metier.id) ? 'Retirer' : 'Ajouter'}
                    variant={tradeIds.includes(metier.id) ? 'secondary' : 'ghost'}
                    onPress={() => basculer(tradeIds, metier.id, setTradeIds)}
                  />
                </View>
              ))}
            </View>
          ))
        )}

        <Divider />

        <Text style={styles.section}>Zones d’intervention</Text>
        {zones.map((zone) => (
          <View key={zone.id} style={styles.ligne}>
            <Text style={styles.ligneTexte}>{zone.name}</Text>
            <Button
              label={zoneIds.includes(zone.id) ? 'Retirer' : 'Ajouter'}
              variant={zoneIds.includes(zone.id) ? 'secondary' : 'ghost'}
              onPress={() => basculer(zoneIds, zone.id, setZoneIds)}
            />
          </View>
        ))}

        {message !== '' && <Text style={styles.message}>{message}</Text>}

        {!peutEnregistrer && (
          // La raison du refus est DITE, pas déduite d'un bouton grisé : un bouton inerte sans
          // explication se lit comme une panne.
          <Text style={styles.avertissement}>
            Choisissez au moins un métier ET une zone : sans les deux, aucune mission ne peut vous
            être proposée.
          </Text>
        )}

        <View style={styles.actions}>
          <Button
            label="Enregistrer"
            onPress={() => enregistrer.mutate()}
            fullWidth
            size="lg"
            disabled={!peutEnregistrer}
            loading={enregistrer.isPending}
          />
        </View>
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    content: { paddingBottom: spacing['2xl'], gap: spacing.sm },
    title: {
      fontSize: typography.fontSize['2xl'],
      fontWeight: typography.fontWeight.bold,
      color: t.text,
    },
    intro: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginBottom: spacing.md },
    section: {
      fontSize: typography.fontSize.lg,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.md,
    },
    groupe: { marginTop: spacing.sm },
    groupeTitre: {
      fontSize: typography.fontSize.xs,
      color: t.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 1,
      marginBottom: spacing.xs,
    },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: spacing.sm,
      paddingVertical: spacing.xs,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    ligneTexte: { flex: 1, fontSize: typography.fontSize.sm, color: t.text },
    vide: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: spacing.sm },
    message: {
      marginTop: spacing.md,
      padding: spacing.sm,
      borderRadius: radius.md,
      backgroundColor: t.tint.success,
      color: t.text,
      fontSize: typography.fontSize.sm,
    },
    avertissement: {
      marginTop: spacing.md,
      fontSize: typography.fontSize.xs,
      color: t.textSecondary,
    },
    actions: { marginTop: spacing.lg },
  });
