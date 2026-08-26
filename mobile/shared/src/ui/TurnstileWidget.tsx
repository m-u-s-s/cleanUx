import React, { useCallback, useMemo, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { env } from '@/config/env';
import { colors, radius, spacing, typography, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * Charge react-native-webview sans jamais laisser échapper d'exception.
 *
 * Même raisonnement que provider/src/maps/module.ts : le module touche un binaire natif
 * (`RNCWebViewModule`) et lève dès l'import quand celui-ci est absent — Expo Go dépourvu, build
 * mal configuré, ou environnement de test. Un import statique ici ferait donc tomber TOUT écran
 * important `@/ui`, captcha ou non. Le require est délibérément paresseux pour que l'échec soit
 * rattrapable et se limite à ce widget.
 */
function loadWebView(): React.ComponentType<any> | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const mod = require('react-native-webview');

    return mod?.WebView ?? mod?.default ?? null;
  } catch {
    return null;
  }
}

/**
 * Widget captcha Cloudflare Turnstile.
 *
 * L'endpoint POST /auth/register porte le middleware `turnstile`, qui exige un jeton via
 * l'en-tête `X-Turnstile-Token`. Aucune des deux apps mobiles n'en envoyait : en production
 * l'inscription était refusée (503 sans clé serveur, 400 avec). Ce composant produit ce jeton.
 *
 * Turnstile n'a pas de SDK React Native ; on charge donc le widget officiel dans une WebView et
 * on remonte le jeton par postMessage. C'est la voie recommandée par Cloudflare hors web.
 *
 * Sans clé publique configurée (`EXPO_PUBLIC_TURNSTILE_SITE_KEY` vide, cas du dev local), le
 * composant ne rend rien et signale immédiatement qu'aucun captcha n'est requis — ce qui reflète
 * le comportement du serveur, lequel ne bloque hors production que si une clé secrète existe.
 */
interface TurnstileWidgetProps {
  /** Reçoit le jeton résolu, ou null si le widget expire ou échoue. */
  onToken: (token: string | null) => void;
  /** Signale que le captcha n'est pas configuré : le formulaire peut soumettre sans jeton. */
  onSkipped?: () => void;
  testID?: string;
}

const WIDGET_HEIGHT = 74;

export function TurnstileWidget({ onToken, onSkipped, testID }: TurnstileWidgetProps) {
  const styles = stylesFor(useThemeColors());

  const siteKey = env.turnstileSiteKey;
  const [failed, setFailed] = useState(false);

  React.useEffect(() => {
    if (!siteKey) {
      onSkipped?.();
    }
  }, [siteKey]);

  const html = useMemo(
    () => `<!DOCTYPE html>
<html>
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
      html, body { margin: 0; padding: 0; background: transparent; overflow: hidden; }
      #cx-turnstile { display: flex; justify-content: center; }
    </style>
  </head>
  <body>
    <div
      id="cx-turnstile"
      class="cf-turnstile"
      data-sitekey="${siteKey}"
      data-callback="cxOnSuccess"
      data-error-callback="cxOnError"
      data-expired-callback="cxOnExpired"
      data-theme="light"
    ></div>
    <script>
      function post(payload) {
        window.ReactNativeWebView && window.ReactNativeWebView.postMessage(JSON.stringify(payload));
      }
      function cxOnSuccess(token) { post({ type: 'token', token: token }); }
      function cxOnError() { post({ type: 'error' }); }
      function cxOnExpired() { post({ type: 'expired' }); }
    </script>
  </body>
</html>`,
    [siteKey],
  );

  const handleMessage = useCallback(
    (event: { nativeEvent: { data: string } }) => {
      try {
        const payload = JSON.parse(event.nativeEvent.data) as { type: string; token?: string };
        if (payload.type === 'token' && payload.token) {
          setFailed(false);
          onToken(payload.token);

          return;
        }
        // Expiré ou en erreur : on retire le jeton pour que le formulaire cesse de le croire valide.
        onToken(null);
        setFailed(payload.type === 'error');
      } catch {
        // Un message illisible ne doit pas casser l'écran ; on se contente d'invalider le jeton.
        onToken(null);
      }
    },
    [onToken],
  );

  if (!siteKey) {
    return null;
  }

  const WebView = loadWebView();

  // Module natif absent : on ne peut pas produire de jeton. On le dit plutôt que d'afficher un
  // cadre vide, et le formulaire reste bloqué côté serveur — ce qui est le comportement correct
  // quand le captcha est exigé.
  if (!WebView) {
    return (
      <View style={styles.container} testID={testID}>
        <Text style={styles.error}>La vérification anti-robot est indisponible sur cet appareil.</Text>
      </View>
    );
  }

  return (
    <View style={styles.container} testID={testID}>
      <WebView
        source={{ html, baseUrl: 'https://brio.local' }}
        style={styles.webview}
        scrollEnabled={false}
        javaScriptEnabled
        originWhitelist={['*']}
        onMessage={handleMessage}
        // Une WebView qui échoue à charger ne doit pas laisser croire que le captcha attend :
        // on invalide le jeton et on l'affiche.
        onError={() => {
          onToken(null);
          setFailed(true);
        }}
      />
      {failed ? <Text style={styles.error}>Le captcha n'a pas pu se charger. Réessayez.</Text> : null}
    </View>
  );
}

/*
 * LE WIDGET N'A PAS DE FOND A LUI : `webview` est transparent, donc ce message se pose
 * sur la surface de l'ecran hote — qui, elle, suit le theme. `danger.600` fige rendait
 * 4,08 sur la nuit.
 */
const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { minHeight: WIDGET_HEIGHT, borderRadius: radius.md, overflow: 'hidden' },
  webview: { height: WIDGET_HEIGHT, backgroundColor: 'transparent' },
  error: {
    fontSize: typography.fontSize.xs,
    color: t.danger,
    marginTop: spacing.xs,
  },
});
