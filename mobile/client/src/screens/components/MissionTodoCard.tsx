import React, { useState } from 'react';
import { View, Text, StyleSheet, Alert, TouchableOpacity } from 'react-native';
import { Button, TextInput, Icon } from '@/ui';
import { useTodoList, useAjouterTache, useRetirerTache, useConsigneDAcces } from '@/booking/onsite';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * MA LISTE DE TÂCHES — ce que je veux qu'on fasse chez moi.
 *
 * ── CE QU'ELLE ENGAGE EST DIT AVANT D'ÉCRIRE ─────────────────────────────────────────────────
 *
 * Ces tâches BLOQUENT la clôture du prestataire : il ne pourra pas partir tant qu'elles ne sont pas
 * faites. Un client qui l'ignore en ajoute douze « au cas où » et retient quelqu'un chez lui toute
 * la soirée. La phrase se lit donc avant le champ de saisie, pas après.
 *
 * ── LE MINUTEUR VIENT DU SERVEUR ─────────────────────────────────────────────────────────────
 *
 * `minutes_left` est calculé par le serveur et rafraîchi avec la liste. Un décompte tenu par
 * l'appareil se remettrait à zéro d'un retour sur l'écran, et il suffirait de quitter puis revenir
 * pour écrire après la fermeture.
 */
export function MissionTodoCard({ bookingId }: { bookingId: number }) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data: liste } = useTodoList(bookingId);
  const ajouter = useAjouterTache(bookingId);
  const retirer = useRetirerTache(bookingId);
  const [saisie, setSaisie] = useState('');
  const [consigne, setConsigne] = useState('');
  const envoyerLaConsigne = useConsigneDAcces(bookingId);

  // Une course n'a rien à cocher : le serveur ne rend alors aucun moteur exploitable.
  if (!liste || liste.engine === null || liste.engine === 'vehicule') {
    return null;
  }

  const fenetre = liste.window;

  const envoyer = () => {
    const propre = saisie.trim();

    if (propre === '') {
      return;
    }

    ajouter.mutate(propre, {
      onSuccess: () => setSaisie(''),
      // « La liste est figée depuis 10:30 » explique ce qu'un « une erreur est survenue » laisserait
      // deviner — et fait réessayer pour rien.
      onError: (e: { message?: string }) =>
        Alert.alert('Impossible', e.message ?? 'La tâche n’a pas pu être ajoutée.'),
    });
  };

  return (
    <View style={styles.section} testID="ma-todo-list">
      <Text style={styles.titre}>Ma liste de tâches</Text>

      <Text style={styles.avertissement}>
        Le prestataire ne pourra pas terminer tant que ces tâches ne sont pas faites.
        {fenetre.open && fenetre.minutes_left !== null
          ? ` Vous pouvez modifier cette liste pendant encore ${fenetre.minutes_left} min.`
          : ''}
      </Text>

      {liste.items.map((item) => (
        <View key={item.id} style={styles.ligne}>
          <Icon
            name={item.done ? 'checkmark-circle' : 'ellipse-outline'}
            size={20}
            color={item.done ? t.textSecondary : t.text}
          />
          <Text style={[styles.label, item.done && styles.labelFaite]}>{item.label}</Text>

          {item.removable && fenetre.open ? (
            <TouchableOpacity
              onPress={() =>
                retirer.mutate(item.id, {
                  onError: (e: { message?: string }) =>
                    Alert.alert('Impossible', e.message ?? 'La tâche n’a pas pu être retirée.'),
                })
              }
              accessibilityRole="button"
              accessibilityLabel={`Retirer « ${item.label} »`}
              testID={`retirer-tache-${item.id}`}
            >
              <Icon name="close" size={18} color={t.textSecondary} />
            </TouchableOpacity>
          ) : null}
        </View>
      ))}

      {liste.items.length === 0 ? (
        <Text style={styles.vide}>
          Rien pour l’instant. Sans liste, le prestataire termine dès qu’il a fini.
        </Text>
      ) : null}

      {/*
        ── LA CONSIGNE D'ACCÈS DE DERNIÈRE MINUTE ──────────────────────────
 
        Elle vit à côté de la liste parce que ce sont les deux seules choses que le client écrit
        pendant l'intervention. Mais elle n'a PAS de fenêtre : un digicode qui change à 17 h doit
        pouvoir se dire à 17 h, même si la liste est figée depuis longtemps — c'est le prestataire
        qu'elle dépanne, pas le client qu'elle avantage.
      */}
      <View style={styles.consigne}>
        <TextInput
          label="Consigne d’accès de dernière minute"
          value={consigne}
          onChangeText={setConsigne}
          placeholder="Le digicode est 4589."
          testID="consigne-saisie"
        />
        <Button
          label="Envoyer au prestataire"
          variant="secondary"
          onPress={() =>
            envoyerLaConsigne.mutate(consigne.trim(), {
              onError: (e: { message?: string }) =>
                Alert.alert('Impossible', e.message ?? 'La consigne n’a pas pu être envoyée.'),
            })
          }
          loading={envoyerLaConsigne.isPending}
          testID="consigne-envoyer"
        />
      </View>

      {fenetre.open ? (
        <>
          <TextInput
            label="Ajouter une tâche"
            value={saisie}
            onChangeText={setSaisie}
            placeholder="Nettoyer la hotte"
            testID="todo-saisie"
          />
          <Button
            label="Ajouter"
            onPress={envoyer}
            loading={ajouter.isPending}
            testID="todo-ajouter"
          />

          {/*
            LES SUGGESTIONS DU MÉTIER, en un tap.

            C'est l'ancien gabarit imposé, devenu proposition : le savoir-faire qualité reste offert,
            il cesse d'être décidé à la place du client.
          */}
          {liste.suggestions.length > 0 ? (
            <View style={styles.suggestions}>
              <Text style={styles.suggestionsTitre}>Souvent demandé</Text>
              <View style={styles.puces}>
                {liste.suggestions.map((suggestion) => (
                  <Text
                    key={suggestion}
                    accessibilityRole="button"
                    onPress={() => ajouter.mutate(suggestion)}
                    style={styles.puce}
                    testID={`suggestion-${suggestion}`}
                  >
                    + {suggestion}
                  </Text>
                ))}
              </View>
            </View>
          ) : null}
        </>
      ) : (
        // LE MOTIF SE MONTRE : une liste qui cesse d'accepter sans rien dire passe pour une panne.
        <Text style={styles.figee} testID="todo-figee">{fenetre.reason}</Text>
      )}
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  section: { marginTop: spacing.lg, gap: spacing.sm },
  titre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  avertissement: {
    fontSize: typography.fontSize.xs,
    lineHeight: 17,
    color: t.textSecondary,
    backgroundColor: t.tint.warning,
    borderRadius: radius.sm,
    padding: spacing.sm,
  },
  ligne: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.xs,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  label: { flex: 1, fontSize: typography.fontSize.sm, color: t.text },
  labelFaite: { color: t.textSecondary, textDecorationLine: 'line-through' },
  vide: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  figee: { fontSize: typography.fontSize.sm, color: t.textSecondary, fontStyle: 'italic' },
  consigne: { gap: spacing.xs, marginTop: spacing.sm },
  suggestions: { gap: spacing.xs, marginTop: spacing.xs },
  suggestionsTitre: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  puces: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  puce: {
    fontSize: typography.fontSize.xs,
    color: t.text,
    backgroundColor: t.card,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    overflow: 'hidden',
  },
});
