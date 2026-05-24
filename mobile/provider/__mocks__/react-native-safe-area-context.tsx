import React from 'react';

const MOCK_INSETS = { top: 0, right: 0, bottom: 0, left: 0 };
const MOCK_FRAME = { x: 0, y: 0, width: 390, height: 844 };

export const SafeAreaProvider = ({ children }: { children?: React.ReactNode }) => <>{children}</>;
export const SafeAreaView = ({ children }: { children?: React.ReactNode }) => <>{children}</>;
export const useSafeAreaInsets = () => MOCK_INSETS;
export const useSafeAreaFrame = () => MOCK_FRAME;
export const initialWindowMetrics = { insets: MOCK_INSETS, frame: MOCK_FRAME };
export const SafeAreaInsetsContext = React.createContext(MOCK_INSETS);
export const SafeAreaFrameContext = React.createContext(MOCK_FRAME);
