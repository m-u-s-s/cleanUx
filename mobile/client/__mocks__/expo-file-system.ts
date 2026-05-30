// Jest mock for expo-file-system v3 (SDK 56) — dynamic-import safe, Expo Go SDK 53+ gotcha
// Uses the new File/Paths API (not legacy cacheDirectory/downloadAsync)

const mockFileUri = 'file:///cache/invoice-7.pdf';

class MockDirectory {
  uri: string;
  constructor(parent: MockDirectory | string, ...parts: string[]) {
    const base = typeof parent === 'string' ? parent : parent.uri;
    this.uri = [base, ...parts].join('/').replace(/\/+/g, '/');
  }
}

class MockFile {
  uri: string;

  constructor(parent: MockDirectory | MockFile | string, ...parts: string[]) {
    const base = typeof parent === 'string' ? parent : parent.uri;
    this.uri = [base.replace(/\/$/, ''), ...parts].join('/');
  }

  static downloadFileAsync = jest.fn().mockResolvedValue({ uri: mockFileUri });
  static pickFileAsync = jest.fn();
  static createDownloadTask = jest.fn();
}

class MockPaths {
  static get cache() {
    return new MockDirectory('file:///cache/');
  }
  static get document() {
    return new MockDirectory('file:///documents/');
  }
}

export const File = MockFile;
export const Paths = MockPaths;
export const Directory = MockDirectory;

// Legacy compat stubs (some packages might import these)
export const cacheDirectory = 'file:///cache/';
export const downloadAsync = jest.fn().mockResolvedValue({ uri: mockFileUri });
export const deleteAsync = jest.fn().mockResolvedValue(undefined);
export const getInfoAsync = jest.fn().mockResolvedValue({ exists: true, isDirectory: false, uri: '', size: 0 });
