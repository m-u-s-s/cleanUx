const useFonts = (_fontMap: Record<string, unknown>): [boolean, null] => [true, null];
const loadAsync = jest.fn().mockResolvedValue(undefined);
const isLoaded = jest.fn().mockReturnValue(true);

export { useFonts, loadAsync, isLoaded };
