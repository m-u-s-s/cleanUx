export const requestForegroundPermissionsAsync = jest.fn().mockResolvedValue({ status: 'granted' });
export const watchPositionAsync = jest.fn().mockResolvedValue({ remove: jest.fn() });
export const Accuracy = { High: 6 };
