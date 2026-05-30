// Jest mock for expo-sharing (dynamic-import safe, Expo Go SDK 53+ gotcha)
export const isAvailableAsync = jest.fn().mockResolvedValue(true);
export const shareAsync = jest.fn().mockResolvedValue(undefined);
