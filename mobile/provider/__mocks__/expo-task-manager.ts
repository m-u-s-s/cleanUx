export const defineTask = jest.fn();
export const isTaskRegisteredAsync = jest.fn().mockResolvedValue(false);
export const unregisterTaskAsync = jest.fn().mockResolvedValue(undefined);
