const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const { readFileSync } = require('node:fs');
const path = require('node:path');
const { test } = require('node:test');

const mobileRoot = path.resolve(__dirname, '..');

// Run hostile inputs in a separate process: a synchronous parser loop cannot
// be interrupted by a timer running on the same event loop.
function runIsolated(source) {
  const result = spawnSync(process.execPath, ['-e', source], {
    cwd: mobileRoot,
    timeout: 5000,
    encoding: 'utf8',
  });
  assert.ifError(result.error);
  assert.equal(result.status, 0, result.stderr || result.stdout);
}

test('query-string uses the patched decoder and preserves query semantics', () => {
  const query = require('query-string');
  assert.deepEqual({ ...query.parse('q=caf%C3%A9+au+lait&tag=a&tag=b&empty=&flag') }, {
    q: 'caf\u00e9 au lait', tag: ['a', 'b'], empty: '', flag: null,
  });
  const values = { text: '\u00e9 + / & =', tags: ['a', 'b'] };
  assert.deepEqual({ ...query.parse(query.stringify(values)) }, values);
  const lock = JSON.parse(readFileSync(path.join(mobileRoot, 'package-lock.json'), 'utf8'));
  const decoders = Object.entries(lock.packages)
    .filter(([name]) => name.endsWith('/decode-uri-component'));
  assert.ok(decoders.length > 0);
  for (const [, pkg] of decoders) assert.equal(pkg.version, '0.5.0');
});

for (const input of ['%80'.repeat(5000), '%E0%A4'.repeat(2500), '%C0%AF'.repeat(2500)]) {
  test(`malformed query completes (${input.slice(0, 6)})`, () => {
    runIsolated(`
      const assert = require('node:assert/strict');
      const query = require('query-string');
      const result = query.parse('value=' + ${JSON.stringify(input)});
      assert.equal(typeof result.value, 'string');
    `);
  });
}

// The extension is deliberately PNG: Metro checks the extension, while
// image-size selects a parser from bytes, so renaming must not bypass the guard.
const malformedImages = {
  icns: '69636e73000000106963703000000000',
  heif: '000000146674797068656963000000000000000000000000',
  jxl: '0000000c4a584c200d0a870a00000014667479706a786c20000000006a786c20000000006a786c7000000000',
};
for (const [format, hex] of Object.entries(malformedImages)) {
  test(`Metro rejects malformed ${format} disguised as PNG`, () => {
    runIsolated(`
      const assert = require('node:assert/strict');
      const { getAssetSize } = require('metro/private/Assets');
      const input = Buffer.from('${hex}', 'hex');
      assert.throws(() => getAssetSize('png', input, 'untrusted.png'), /disabled file type: ${format}/);
    `);
  });
}

test('Metro still reads the actual client and provider PNG assets', () => {
  const { getAssetSize } = require('metro/private/Assets');
  for (const app of ['client', 'provider']) {
    for (const asset of ['icon.png', 'splash-icon.png', 'adaptive-icon.png']) {
      const filename = path.join(mobileRoot, app, 'assets', asset);
      const size = getAssetSize('png', readFileSync(filename), filename);
      assert.ok(size.width > 0 && size.height > 0, filename);
    }
  }
});
