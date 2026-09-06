import React, { useState } from 'react';
import { View, FlatList, Text, TextInput, StyleSheet, Alert, Switch } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Lieu {
  id: number;
  label: string;
  address: string;
  city: string | null;
  postal_code: string | null;
  floor: string | null;
  access_instructions: string | null;
  alarm_code_required: boolean;
  is_default: boolean;
}

/**
 * LE CARNET DE LIEUX (E2), SUR LE TÉLÉPHONE.
 *
 * C'EST SUR PLACE QU'ON NOTE ce qu'il faut savoir d'un lieu : le digicode qu'on vient de composer,
 * l'étage, le fait que la clé est chez la voisine du deuxième. Renvoyer cette saisie à un ordinateur
 * revient à ne jamais la faire — et à redonner l'information oralement au prestataire suivant, ou à
 * la perdre.
 *
 * ON ARCHIVE, ON NE SUPPRIME PAS. Les interventions passées portent ce lieu : l'effacer viderait
 * l'historique de ses adresses. Le bouton dit donc « Archiver », pas « Supprimer » — un libellé qui
 * ment sur ce qu'il fait se paye au premier client qui croit avoir tout effacé.
 */
export function PlacesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [libelle, setLibelle] = useState('');
  const [adresse, setAdresse] = useState('');
  const [codePostal, setCodePostal] = useState('');
  const [ville, setVille] = useState('');
  const [etage, setEtage] = useState('');
  const [consignes, setConsignes] = useState('');
  const [alarme, setAlarme] = useState(false);

  const { data: lieux, refetch, isRefetching } = useQuery<Lieu[]>({
    queryKey: ['client', 'places'],
    queryFn: async () => (await apiClient.get('/client/places')).data.data ?? [],
  });

  const ajouter = useMutation({
    mutationFn: async () =>
      apiClient.post('/client/places', {
        label: libelle.trim(),
        address: adresse.trim(),
        postal_code: codePostal.trim() || null,
        city: ville.trim() || null,
        floor: etage.trim() || null,
        access_instructions: consignes.trim() || null,
        alarm_code_required: alarme,
      }),
    onSuccess: () => {
      setLibelle('');
      setAdresse('');
      setCodePostal('');
      setVille('');
      setEtage('');
      setConsignes('');
      setAlarme(false);
      qc.invalidateQueries({ queryKey: ['client', 'places'] });
    },
    onError: (erreur: any) =>
      // « Votre carnet contient déjà 25 lieux » est une réponse, pas une panne.
      Alert.alert(tr('places.ajout_refuse'), erreur?.data?.message ?? 'Le lieu n’a pas pu être enregistré.'),
  });

  const definirParDefaut = useMutation({
    mutationFn: async (id: number) => apiClient.post(`/client/places/${id}/default`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['client', 'places'] }),
  });

  const archiver = useMutation({
    mutationFn: async (id: number) => apiClient.delete(`/client/places/${id}`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['client', 'places'] }),
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('places.mes_lieux')}</Text>
      <Text style={styles.intro}>
        {tr('places.l_adresse_l_etage_le')}
      </Text>

      <View style={styles.formulaire}>
        <TextInput
          value={libelle}
          onChangeText={setLibelle}
          placeholder={tr('places.nom_du_lieu_chez_moi')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-libelle-lieu"
        />
        <TextInput
          value={adresse}
          onChangeText={setAdresse}
          placeholder={tr('places.adresse')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-adresse-lieu"
        />
        {/* SANS EUX, PAS DE ZONE : la zone se resout du code postal, et sans zone il n'y a ni
            grille de prix locale ni prestataire trouvable pour ce lieu. */}
        <TextInput
          value={codePostal}
          onChangeText={setCodePostal}
          placeholder={tr('places.code_postal')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          keyboardType="number-pad"
          testID="champ-code-postal-lieu"
        />
        <TextInput
          value={ville}
          onChangeText={setVille}
          placeholder={tr('places.ville')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-ville-lieu"
        />
        <TextInput
          value={etage}
          onChangeText={setEtage}
          placeholder={tr('places.etage_porte')}
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-etage-lieu"
        />
        <TextInput
          value={consignes}
          onChangeText={setConsignes}
          placeholder="Consignes d'accès (digicode, boîte à clés…)"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-consignes-lieu"
        />

        <View style={styles.bascule}>
          <Text style={styles.detail}>{tr('places.il_y_a_une_alarme')}</Text>
          <Switch value={alarme} onValueChange={setAlarme} testID="bascule-alarme" />
        </View>

        <Button
          label={tr('places.ajouter_ce_lieu')}
          size="sm"
          fullWidth
          disabled={libelle.trim().length === 0 || adresse.trim().length === 0 || ajouter.isPending}
          onPress={() => ajouter.mutate()}
          testID="bouton-ajouter-lieu"
        />

        <Text style={styles.note}>
          Les consignes d'accès ne sont montrées au professionnel qu'une fois son arrivée confirmée
          sur place.
        </Text>
      </View>

      <FlatList
        data={lieux ?? []}
        keyExtractor={(l) => String(l.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        style={styles.liste}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`lieu-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.label}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {[item.address, item.city].filter(Boolean).join(', ')}
              </Text>
              {(item.floor || item.access_instructions) && (
                <Text style={styles.consigne} numberOfLines={1}>
                  {[item.floor, item.access_instructions].filter(Boolean).join(' · ')}
                </Text>
              )}
            </View>

            {item.is_default ? (
              <Badge label={tr('places.par_defaut')} variant="success" />
            ) : (
              <Button
                label={tr('places.par_defaut')}
                size="sm"
                variant="ghost"
                onPress={() => definirParDefaut.mutate(item.id)}
                testID={`defaut-lieu-${item.id}`}
              />
            )}

            {/* Archiver, pas supprimer : les interventions passées portent ce lieu. */}
            <Button
              label={tr('places.archiver')}
              size="sm"
              variant="ghost"
              onPress={() => archiver.mutate(item.id)}
              testID={`archiver-lieu-${item.id}`}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('places.aucun_lieu')}
            message="Le premier lieu que vous ajoutez devient votre lieu par défaut."
          />
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
    },
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    formulaire: { gap: spacing.xs },
    champ: {
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: { color: t.textMuted },
    bascule: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingVertical: spacing.xs,
    },
    note: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: spacing.xs },
    liste: { marginTop: spacing.md },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
    consigne: { fontSize: typography.fontSize.xs, color: t.textMuted },
  });
