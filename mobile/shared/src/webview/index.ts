export { EmbeddedModuleScreen } from './EmbeddedModuleScreen';
export type { EmbeddedModuleScreenProps } from './EmbeddedModuleScreen';
export { parseBridgeMessage, INJECTED_BRIDGE_JS } from './bridge';
export type { BridgeMessage } from './bridge';
export { fetchWebViewUrl } from './useWebViewTicket';
/**
 * `useDeviceId` vivait dans `mobile/client/src/hooks/`, hors de portée de l'application
 * prestataire (`@/*` pointe vers le `src` de CHAQUE application). Le brancher côté prestataire
 * aurait imposé une copie — deux implémentations d'un même identifiant, vouées à diverger.
 *
 * Il rejoint donc l'hôte qui le consomme : la valeur et son destinataire vivent ensemble.
 */
export { useDeviceId } from './useDeviceId';
