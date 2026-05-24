import * as ExpoSecureStore from 'expo-secure-store';

const TOKEN_KEY = 'auth_token';

export const secureStore = {
  async getToken(): Promise<string | null> {
    return ExpoSecureStore.getItemAsync(TOKEN_KEY);
  },
  async setToken(token: string): Promise<void> {
    await ExpoSecureStore.setItemAsync(TOKEN_KEY, token);
  },
  async clearToken(): Promise<void> {
    await ExpoSecureStore.deleteItemAsync(TOKEN_KEY);
  },
  async isAuthenticated(): Promise<boolean> {
    return (await ExpoSecureStore.getItemAsync(TOKEN_KEY)) !== null;
  },
};
