import React, { useMemo, useState } from 'react';
import { Alert, Modal, Pressable, ScrollView, SectionList, StyleSheet, Switch, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { Button, EmptyState, ErrorState, Icon, Screen, Skeleton, TextInput } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { messageDErreur } from './erreur';
import { useResourceAction } from '../console/hooks';
import { useToggleTradeInZone, useUpdateDistancePricing, useZoneTrades } from './hooks';
import { LigneActions } from './LigneActions';
import type { ZoneTrade } from './types';
import { useTraduction } from '@/i18n';

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
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const route = useRoute<{ key: string; name: string; params: { zoneId: number; title?: string } }>();
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();

  const { zoneId } = route.params;
  const { data, isLoading, isError, error, refetch } = useZoneTrades(zoneId);
  const bascule = useToggleTradeInZone(zoneId);
  const agir = useResourceAction('trades');

  /*
   * Le métier dont on règle le prix au kilomètre. `null` = le formulaire est fermé.
   *
   * L'état vit ICI et non dans la ligne : ouvrir un second formulaire alors qu'un premier est en
   * cours de saisie ferait perdre ce qui vient d'être tapé, et on ne saurait pas lequel des deux
   * on enregistre.
   */
  const [tarifDe, setTarifDe] = useState<ZoneTrade | null>(null);
  const reglerLeTarif = useUpdateDistancePricing(zoneId);

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
          <Text style={styles.bandeauFort}>{tr('catalog_zone_trades.reglage_preparatoire')} </Text>
          L’ouverture d’un métier ici est bien enregistrée, mais elle n’a pas encore d’effet sur ce
          que voit un client : le parcours de commande ne détermine pas encore la zone d’une adresse.
        </Text>
      </View>

      <View style={styles.entete}>
        <Text style={styles.compte}>
          {ouverts} métier(s) ouvert(s) sur {data?.trades.length ?? 0}
        </Text>

        <Pressable
          onPress={() =>
            navigation.navigate('AdminResourceForm', { resource: 'trades', title: 'Nouveau métier' })
          }
          accessibilityRole="button"
          accessibilityLabel={tr('catalog_zone_trades.ajouter_un_metier')}
          style={({ pressed }) => [styles.ajouter, pressed && styles.ajouterPresse]}
        >
          <Icon name="add" size={18} color={colors.surface[50]} />
          <Text style={styles.ajouterTexte}>{tr('catalog_zone_trades.ajouter')}</Text>
        </Pressable>
      </View>

      <SectionList
        sections={sections}
        keyExtractor={(item) => String(item.id)}
        stickySectionHeadersEnabled
        showsVerticalScrollIndicator={false}
        renderSectionHeader={({ section }) => (
          <Text style={styles.sectionHeader}>{section.title}</Text>
        )}
        renderItem={({ item }) => (
          <TradeRow
            metier={item}
            devise={data?.zone?.currency}
            onToggle={() => bascule.mutate(item.id)}
            onEdit={() =>
              navigation.navigate('AdminResourceForm', {
                resource: 'trades',
                title: item.name,
                id: item.id,
              })
            }
            onParcours={() =>
              navigation.navigate('AdminTradeJourney', { tradeId: item.id, title: item.name })
            }
            onTarifDistance={() => setTarifDe(item)}
            onMonter={() => {
              agir.mutate({ id: item.id, action: 'move-up' });
              void refetch();
            }}
            onDescendre={() => {
              agir.mutate({ id: item.id, action: 'move-down' });
              void refetch();
            }}
          />
        )}
        ListEmptyComponent={
          <EmptyState title={tr('catalog_zone_trades.aucun_metier')} message="Le catalogue de la plateforme est vide." />
        }
      />

      {tarifDe ? (
        <FormulaireTarifDistance
          metier={tarifDe}
          enCours={reglerLeTarif.isPending}
          onFermer={() => setTarifDe(null)}
          onEnregistrer={(valeurs) =>
            reglerLeTarif.mutate(
              { tradeId: tarifDe.id, valeurs },
              {
                onSuccess: () => setTarifDe(null),
                // Le refus du serveur — métier fermé dans la zone, compte en lecture seule —
                // doit s'afficher : sans cela, le bouton semble ne rien faire.
                onError: (erreur) =>
                  Alert.alert(tr('catalog_zone_trades.impossible'), messageDErreur(erreur, 'Le tarif n’a pas été enregistré.')),
              },
            )
          }
        />
      ) : null}
    </Screen>
  );
}

/**
 * LE PRIX AU KILOMÈTRE, réglé d'un seul geste.
 *
 * Les cinq valeurs partent ENSEMBLE : prise en charge, kilomètres inclus, prix au kilomètre et à la
 * minute n'ont de sens que les uns par rapport aux autres. Les enregistrer une par une laisserait,
 * entre deux appels, une grille qui facture des kilomètres sans prise en charge — sur des commandes
 * en cours.
 *
 * LES MONTANTS SONT EN CENTIMES, comme partout dans le moteur tarifaire. Saisir en euros ici
 * imposerait une conversion de plus, et c'est exactement là que naissent les écarts d'un centime
 * que personne ne sait expliquer trois mois plus tard.
 */
function FormulaireTarifDistance({
  metier,
  enCours,
  onFermer,
  onEnregistrer,
}: {
  metier: ZoneTrade;
  enCours: boolean;
  onFermer: () => void;
  onEnregistrer: (valeurs: {
    distance_pricing_enabled: boolean;
    pickup_fee_cents: number;
    price_per_km_cents: number | null;
    price_per_minute_cents: number | null;
    included_km: number;
  }) => void;
}) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const [actif, setActif] = useState(metier.distance_pricing_enabled ?? false);
  const [priseEnCharge, setPriseEnCharge] = useState(String(metier.pickup_fee_cents ?? 0));
  const [parKm, setParKm] = useState(
    metier.price_per_km_cents != null ? String(metier.price_per_km_cents) : '',
  );
  const [parMinute, setParMinute] = useState(
    metier.price_per_minute_cents != null ? String(metier.price_per_minute_cents) : '',
  );
  const [inclus, setInclus] = useState(String(metier.included_km ?? 0));

  // Chaîne vide → `null`, et non `0` : « aucun tarif au kilomètre » laisse le forfait décider,
  // « zéro centime le kilomètre » facturerait la distance gratuitement.
  const entierOuNul = (valeur: string): number | null => {
    const nettoye = valeur.trim();

    return nettoye === '' ? null : Math.max(0, Number.parseInt(nettoye, 10) || 0);
  };

  return (
    <Modal visible transparent animationType="slide" onRequestClose={onFermer}>
      <View style={styles.modalFond}>
        <View style={styles.modalCarte}>
          <ScrollView keyboardShouldPersistTaps="handled">
            <Text style={styles.modalTitre}>Prix au kilomètre — {metier.name}</Text>
            <Text style={styles.modalAide}>
              Montants en CENTIMES. Laisser vide « c/km » revient à ne pas facturer la distance.
            </Text>

            <View style={styles.modalLigne}>
              <Text style={styles.modalLigneTexte}>{tr('catalog_zone_trades.facturer_a_la_distance')}</Text>
              <Switch
                value={actif}
                onValueChange={setActif}
                accessibilityLabel={tr('catalog_zone_trades.activer_le_prix_au_kilometre')}
                trackColor={{ true: colors.brand[500], false: colors.surface[300] }}
              />
            </View>

            <TextInput
              label={tr('catalog_zone_trades.prise_en_charge_centimes')}
              value={priseEnCharge}
              onChangeText={setPriseEnCharge}
              keyboardType="number-pad"
            />
            <TextInput
              label={tr('catalog_zone_trades.kilometres_inclus')}
              value={inclus}
              onChangeText={setInclus}
              keyboardType="number-pad"
            />
            <TextInput
              label={tr('catalog_zone_trades.centimes_par_kilometre')}
              value={parKm}
              onChangeText={setParKm}
              keyboardType="number-pad"
              placeholder="—"
            />
            <TextInput
              label={tr('catalog_zone_trades.centimes_par_minute')}
              value={parMinute}
              onChangeText={setParMinute}
              keyboardType="number-pad"
              placeholder="—"
            />

            <Button
              label={tr('catalog_zone_trades.enregistrer')}
              loading={enCours}
              fullWidth
              onPress={() =>
                onEnregistrer({
                  distance_pricing_enabled: actif,
                  pickup_fee_cents: entierOuNul(priseEnCharge) ?? 0,
                  price_per_km_cents: entierOuNul(parKm),
                  price_per_minute_cents: entierOuNul(parMinute),
                  included_km: entierOuNul(inclus) ?? 0,
                })
              }
            />
            <Button label={tr('catalog_zone_trades.annuler')} variant="secondary" fullWidth onPress={onFermer} />
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

function TradeRow({
  metier,
  devise,
  onToggle,
  onEdit,
  onParcours,
  onTarifDistance,
  onMonter,
  onDescendre,
}: {
  metier: ZoneTrade;
  /* La devise de la zone, pas celle du lecteur. */
  devise?: string | null;
  onToggle: () => void;
  onEdit: () => void;
  onParcours: () => void;
  onTarifDistance: () => void;
  onMonter: () => void;
  onDescendre: () => void;
}) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.row}>
      <View style={styles.rowTexte}>
        <Text style={styles.rowTitre}>{metier.name}</Text>
        <Text style={styles.rowMeta}>
          {formatCentimes(metier.base_rate_cents, devise)}
          {/*
            D'où vient ce tarif : de la zone, ou du métier faute de mieux. Sans cette distinction,
            un prix hérité passerait pour une décision prise pour cette zone.
          */}
          {metier.has_zone_price ? ' · tarif de la zone' : ' · tarif du métier'}
        </Text>

        {/*
          CE QUI CHANGE LE PRIX DOIT SE VOIR SANS OUVRIR UN MENU.

          Un métier de trajet facturé au forfait vend deux kilomètres au prix de vingt : c'est le
          genre d'erreur qu'on ne découvre qu'à la première facture contestée. La ligne le dit.
        */}
        {metier.is_route_service ? (
          <Text
            style={[styles.rowMeta, metier.distance_pricing_enabled ? styles.rowMetaOk : styles.rowMetaAlerte]}
            testID={`tarif-distance-${metier.id}`}
          >
            {metier.distance_pricing_enabled
              ? `Trajet · ${formatCentimes(metier.pickup_fee_cents ?? 0, devise)}`
                + (metier.price_per_km_cents != null
                  ? ` + ${formatCentimes(metier.price_per_km_cents, devise)}/km`
                  : '')
                + ((metier.included_km ?? 0) > 0 ? ` (${metier.included_km} km inclus)` : '')
              : 'Trajet · aucun prix au kilomètre — facturé au forfait'}
            {metier.taxi_rules ? ' · règles taxi' : ''}
          </Text>
        ) : null}
      </View>

      <LigneActions
        sujet={metier.name}
        actions={[
          { cle: 'edit', libelle: tr('catalog_zone_trades.modifier_le_metier'), executer: onEdit },
          // Ce que le métier DEMANDE au client, et ce que chaque réponse ajoute au prix.
          { cle: 'parcours', libelle: tr('catalog_zone_trades.parcours_de_questions'), executer: onParcours },
          /*
            Proposé pour les SEULS métiers de trajet. L'offrir partout ferait régler un tarif au
            kilomètre sur un ménage — réglage qui ne s'appliquerait jamais, et que le suivant
            croirait actif.
          */
          ...(metier.is_route_service
            ? [{ cle: 'tarif-distance', libelle: tr('catalog_zone_trades.prix_au_kilometre'), executer: onTarifDistance }]
            : []),
          /*
            L'ordre des métiers est celui du DOCK client : le premier est ce qu'on propose
            d'abord. Monter et descendre plutôt qu'un glisser-déposer, qui se confond avec le
            défilement de la liste sur un téléphone et n'existe pas au clavier.
          */
          { cle: 'up', libelle: tr('catalog_zone_trades.monter_dans_le_secteur'), executer: onMonter },
          { cle: 'down', libelle: tr('catalog_zone_trades.descendre_dans_le_secteur'), executer: onDescendre },
        ]}
      />

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
  entete: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  compte: {
    ...typography.preset.subhead,
    color: t.textSecondary,
    paddingVertical: spacing.sm,
  },
  ajouter: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    minHeight: 44,
    paddingHorizontal: spacing.md,
    borderRadius: 12,
    backgroundColor: colors.brand[500],
  },
  ajouterPresse: { opacity: 0.85 },
  ajouterTexte: { fontSize: typography.fontSize.sm, color: t.textOnBrand, fontWeight: '600' },
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
  rowMetaOk: { color: t.success },
  // Un métier de trajet vendu au forfait n'est pas une panne : c'est une décision qui n'a pas été
  // prise. La couleur d'alerte le signale sans crier au défaut.
  rowMetaAlerte: { color: t.warning },

  modalFond: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.45)',
  },
  modalCarte: {
    maxHeight: '85%',
    padding: spacing.lg,
    gap: spacing.sm,
    borderTopLeftRadius: 20,
    borderTopRightRadius: 20,
    backgroundColor: t.card,
  },
  modalTitre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  modalAide: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginBottom: spacing.sm,
  },
  modalLigne: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
  modalLigneTexte: { fontSize: typography.fontSize.sm, color: t.text },
});
