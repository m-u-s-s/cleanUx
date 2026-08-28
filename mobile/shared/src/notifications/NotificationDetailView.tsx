import React, { useEffect } from 'react';
import { View, Text, StyleSheet, Linking } from 'react-native';
import { Screen, Badge, Button, Skeleton, ErrorState, DetailRow } from '@/ui';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useNotification, useMarkRead } from './hooks';
import { severityVariant, contextLabel, formatNotificationDate } from './presentation';
import { useTraduction } from '@/i18n';

/**
 * LA FICHE D'UNE NOTIFICATION, PARTAGÉE PAR LES DEUX APPLICATIONS.
 *
 * Les écrans de liste du client et du prestataire sont des jumeaux copiés-collés — c'est
 * précisément pour ça que « `title` et `body` jamais envoyés par l'API » existait DEUX fois, et
 * qu'une correction sur un seul écran en aurait laissé un vide. La fiche ne repart pas sur cette
 * pente : elle est écrite une fois, chaque application ne fournit que le branchement de navigation.
 *
 * Elle porte ce que la liste ne peut pas montrer — tout le contexte, la traçabilité — et surtout
 * le lien vers la page où régler le problème.
 */
export type NotificationDetailViewProps = {
  id: string;
  /**
   * Ouvre une page web de l'application dans l'hôte WebView, qui porte la session.
   * Chaque application passe sa propre navigation : la fiche ne connaît pas leur pile.
   */
  onOpenPath: (path: string, title: string) => void;
};

export function NotificationDetailView({ id, onOpenPath }: NotificationDetailViewProps) {
  const { t: tr } = useTraduction();
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data: notif, isLoading, isError, refetch } = useNotification(id);
  const markRead = useMarkRead();

  /*
   * OUVRIR VAUT LECTURE — mais depuis le client, pas depuis un GET.
   *
   * `GET /notifications/{id}` ne modifie rien : c'est cet effet qui pose la lecture, par le
   * `POST .../read` qui existait déjà côté serveur sans aucun appelant mobile. Le compteur ne
   * redescendait jusqu'ici qu'en marquant TOUT d'un coup.
   *
   * Gardé sur `notif?.read_at` : sans cette condition, chaque re-rendu relancerait la mutation.
   */
  useEffect(() => {
    if (notif && !notif.read_at && !markRead.isPending) {
      markRead.mutate(notif.id);
    }
    // `markRead` est stable entre les rendus ; l'inclure relancerait l'effet à chaque mutation.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [notif?.id, notif?.read_at]);

  if (isLoading) {
    return (
      <Screen scroll>
        <View style={styles.skeletons}>
          <Skeleton width="60%" height={28} />
          <Skeleton width="100%" height={96} />
          <Skeleton width="100%" height={120} />
        </View>
      </Screen>
    );
  }

  if (isError || !notif) {
    return (
      <Screen>
        <ErrorState
          message="Notification introuvable. Elle a peut-être été supprimée."
          onRetry={() => refetch()}
        />
      </Screen>
    );
  }

  const contexte = Object.entries(notif.context ?? {});

  const ouvrirResolution = () => {
    if (notif.action_path) {
      onOpenPath(notif.action_path, notif.action_label);
      return;
    }

    // Cible hors application : le serveur laisse `action_path` vide, on sort vers le navigateur
    // plutôt que d'embarquer une page qui n'est pas à nous.
    if (notif.action_url) {
      Linking.openURL(notif.action_url).catch(() => undefined);
    }
  };

  return (
    <Screen scroll>
      <View style={styles.badges}>
        <Badge label={notif.label} variant="neutral" />
        {/*
          Deux pastilles qui disent le même mot n'en valent qu'une : le type « Urgent » a pour
          sévérité `danger`, dont le libellé est aussi « Urgent ».
        */}
        {severityLabel(notif.severity) !== notif.label && severityLabel(notif.severity) !== '' && (
          <Badge label={severityLabel(notif.severity)} variant={severityVariant(notif.severity)} />
        )}
        <Badge label={notif.read_at ? 'Lue' : 'Nouveau'} variant={notif.read_at ? 'neutral' : 'brand'} />
      </View>

      <Text style={styles.title} accessibilityRole="header">{notif.title}</Text>

      {notif.body !== notif.title && <Text style={styles.body}>{notif.body}</Text>}

      <Text style={styles.date}>{formatNotificationDate(notif.created_at, true)}</Text>

      {/* La raison d'être de cette fiche : où aller pour régler le problème. */}
      <View style={styles.resolution}>
        <Text style={styles.resolutionTitle}>{tr('notification_detail.regler_le_probleme')}</Text>
        <Text style={styles.resolutionHelp}>{tr('notification_detail.cette_notification_se_traite_sur')}</Text>
        <Button
          label={notif.action_label}
          onPress={ouvrirResolution}
          testID="notification-resolution"
        />
      </View>

      {contexte.length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Informations</Text>
          {contexte.map(([cle, valeur]) => (
            <DetailRow key={cle} label={contextLabel(cle)} value={String(valeur)} />
          ))}
        </View>
      )}

      <View style={styles.section}>
        <Text style={styles.sectionTitle}>{tr('notification_detail.tracabilite')}</Text>
        <DetailRow label="Référence" value={notif.id} />
        <DetailRow label="Source" value={notif.type} />
        <DetailRow
          label="Lue le"
          value={notif.read_at ? formatNotificationDate(notif.read_at, true) : 'Non lue'}
        />
      </View>
    </Screen>
  );
}

function severityLabel(severity: string): string {
  switch (severity) {
    case 'danger':
      return 'Urgent';
    case 'warning':
      return 'À surveiller';
    case 'success':
      return 'Bonne nouvelle';
    case 'info':
      return 'Information';
    default:
      return '';
  }
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  skeletons: { gap: spacing.md },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.sm,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  body: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginTop: spacing.xs,
    lineHeight: 21,
  },
  date: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
    marginTop: spacing.xs,
  },
  resolution: {
    marginTop: spacing.lg,
    padding: spacing.md,
    borderRadius: radius.lg,
    backgroundColor: t.tint.brand,
    gap: spacing.xs,
  },
  resolutionTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  resolutionHelp: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginBottom: spacing.xs,
  },
  section: {
    marginTop: spacing.lg,
    padding: spacing.md,
    borderRadius: radius.lg,
    backgroundColor: t.card,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
  },
  sectionTitle: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.bold,
    color: t.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginBottom: spacing.xs,
  },
});
