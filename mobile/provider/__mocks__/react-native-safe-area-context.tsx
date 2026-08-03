import React from 'react';
import { View, type ViewProps } from 'react-native';

const MOCK_INSETS = { top: 0, right: 0, bottom: 0, left: 0 };
const MOCK_FRAME = { x: 0, y: 0, width: 390, height: 844 };

export const SafeAreaProvider = ({ children }: { children?: React.ReactNode }) => <>{children}</>;
/*
 * `SafeAreaView` rend une VRAIE `View` et transmet ses props.
 *
 * Le mock d'origine rendait un fragment, donc jetait `style` et `testID` : la couleur de fond de
 * TOUT écran était invisible aux tests, puisque `Screen` la pose précisément ici. C'est ce qui a
 * permis au mode sombre de rester cassé sans qu'aucun test ne bronche.
 */
export const SafeAreaView = ({ children, ...props }: ViewProps & { edges?: unknown }) => {
  const { edges: _edges, ...reste } = props;

  return <View {...reste}>{children}</View>;
};
export const useSafeAreaInsets = () => MOCK_INSETS;
export const useSafeAreaFrame = () => MOCK_FRAME;
export const initialWindowMetrics = { insets: MOCK_INSETS, frame: MOCK_FRAME };
export const SafeAreaInsetsContext = React.createContext(MOCK_INSETS);
export const SafeAreaFrameContext = React.createContext(MOCK_FRAME);
