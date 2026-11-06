import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const root = new URL('../', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
const plugin = read('dianxiaomi/dianxiaomi.php');
const packageJson = JSON.parse(read('dianxiaomi/package.json'));
const readme = read('README.md');

function header(name) {
  const match = plugin.match(new RegExp(`^ \\* ${name}: (.+)$`, 'm'));
  assert.ok(match, `Missing plugin header: ${name}`);
  return match[1];
}

function stack(config) {
  assert.match(config, /^\.wp-env\.(?:legacy|current)\.json$/);
  const data = JSON.parse(read(`dianxiaomi/${config}`));
  const wordpress = data.core.match(/^WordPress\/WordPress#(\d+\.\d+(?:\.\d+)?)$/);
  const woocommerce = data.plugins
    .map((plugin) => plugin.match(/^https:\/\/downloads\.wordpress\.org\/plugin\/woocommerce\.(\d+\.\d+\.\d+)\.zip$/))
    .filter(Boolean);
  assert.ok(wordpress, `${config}: pin a stable WordPress version`);
  assert.equal(woocommerce.length, 1, `${config}: pin exactly one WooCommerce archive`);
  assert.match(data.phpVersion, /^\d+\.\d+$/);
  return { php: data.phpVersion, wordpress: wordpress[1], woocommerce: woocommerce[0][1] };
}

for (const [name, constant] of [
  ['Version', 'VERSION'],
  ['Requires at least', 'WP_VERSION'],
  ['Tested up to', 'WP_VERSION_TESTED'],
  ['Requires PHP', 'PHP_VERSION'],
  ['WC requires at least', 'WC_VERSION'],
  ['WC tested up to', 'WC_VERSION_TESTED'],
]) {
  const match = plugin.match(new RegExp(`define\\( 'DIANXIAOMI_${constant}', '([^']+)' \\);`));
  assert.ok(match, `Missing DIANXIAOMI_${constant}`);
  assert.equal(match[1], header(name), `${name}: header and constant differ`);
}

assert.equal(packageJson.version, header('Version'), 'npm and plugin versions differ');
const current = stack('.wp-env.current.json');
assert.equal(header('Tested up to'), current.wordpress, 'WordPress metadata differs from the tested stack');
assert.equal(header('WC tested up to'), current.woocommerce, 'WooCommerce metadata differs from the tested stack');
for (const [name, minimum, tested] of [
  ['WordPress', header('Requires at least'), current.wordpress],
  ['WooCommerce', header('WC requires at least'), current.woocommerce],
]) {
  const row = readme.match(new RegExp(`^\\| ${name} +\\| ([^|]+)\\| ([^|]+)\\|$`, 'm'));
  assert.ok(row, `Missing README requirements row: ${name}`);
  assert.equal(row[1].trim(), minimum, `${name}: README minimum differs`);
  assert.equal(row[2].trim(), tested, `${name}: README tested version differs`);
}

if (process.argv[2]) {
  const expected = stack(process.argv[2]);
  const php = process.env.WP_ENV_PHP_VERSION || expected.php;
  assert.match(php, /^\d+\.\d+$/);
  console.log(`EXPECTED_PHP=${php}`);
  console.log(`EXPECTED_WORDPRESS=${expected.wordpress}`);
  console.log(`EXPECTED_WOOCOMMERCE=${expected.woocommerce}`);
} else {
  stack('.wp-env.legacy.json');
  console.log('Plugin metadata and compatibility stacks are consistent.');
}
