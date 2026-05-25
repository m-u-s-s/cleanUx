import { AccessibilityInfo } from 'react-native';
import { useEffect, useState } from 'react';

export function useReducedMotion(): boolean {
  const [reduced, setReduced] = useState(false);
  useEffect(() => {
    AccessibilityInfo.isReduceMotionEnabled().then(setReduced);
    const sub = AccessibilityInfo.addEventListener('reduceMotionChanged', setReduced);
    return () => sub.remove();
  }, []);
  return reduced;
}

export function useScreenReader(): boolean {
  const [active, setActive] = useState(false);
  useEffect(() => {
    AccessibilityInfo.isScreenReaderEnabled().then(setActive);
    const sub = AccessibilityInfo.addEventListener('screenReaderChanged', setActive);
    return () => sub.remove();
  }, []);
  return active;
}

export const a11y = {
  minTouchSize: 44,
  announce: (message: string) => AccessibilityInfo.announceForAccessibility(message),
};
