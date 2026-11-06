/**
 * Real admin regression checks against an isolated wp-env installation.
 * Run from the plugin directory with WP_ENV_HOME and WP_ENV_PHP_VERSION set.
 * Required: TEST_BASE_URL, TEST_ADMIN_USER, TEST_ADMIN_PASSWORD,
 * PLAYWRIGHT_MODULE (if not installed locally), PLAYWRIGHT_CHROMIUM_EXECUTABLE.
 * The installation must block outgoing mail and HTTP requests with a MU plugin.
 * Usage: node tests/browser/admin-order.mjs [hpos|classic] [settings|box]
 */
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const baseURL = process.env.TEST_BASE_URL;
assert.ok(baseURL && process.env.TEST_ADMIN_USER && process.env.TEST_ADMIN_PASSWORD, 'Set isolated test URL and admin credentials');
const mode = process.argv[2] || 'hpos';
const check = process.argv[3] || 'box';
assert.ok(['hpos', 'classic'].includes(mode));
assert.ok(['settings', 'box'].includes(check));
const phpLiteral = (value) => `json_decode(base64_decode('${Buffer.from(JSON.stringify(value)).toString('base64')}'), true)`;
function wp(code) {
    const output = execFileSync(process.execPath, [
        'node_modules/@wordpress/env/bin/wp-env', 'run', 'cli', 'wp', 'eval',
        `${code}`,
        '--config=.wp-env.current.json',
    ], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'], timeout: 60000 });
    const result = output.match(/ADMIN_ORDER_RESULT:(.+)/);
    assert.ok(result, output);
    return JSON.parse(result[1]);
}
const result = (expression) => `echo 'ADMIN_ORDER_RESULT:' . wp_json_encode(${expression}) . PHP_EOL;`;
const options = ['dianxiaomi_option_name', 'woocommerce_custom_orders_table_enabled', 'woocommerce_custom_orders_table_data_sync_enabled', 'woocommerce_auto_flip_authoritative_table_roles'];
const snapshot = wp(`
    if (rtrim(get_option('siteurl'), '/') !== ${phpLiteral(baseURL.replace(/\/$/, ''))}) { throw new Exception('Wrong wp-env instance'); }
    if (!has_filter('pre_wp_mail') || !has_filter('pre_http_request')) { throw new Exception('Install isolated QA mail and HTTP guards first'); }
    $saved = array();
    foreach (${phpLiteral(options)} as $name) { $saved[$name] = get_option($name); }
    ${result('$saved')}
`);
let orderId;
let browser;
const errors = [];
try {
    orderId = wp(`
        update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
        update_option('woocommerce_auto_flip_authoritative_table_roles', 'no');
        update_option('woocommerce_custom_orders_table_enabled', '${mode === 'hpos' ? 'yes' : 'no'}');
        $order = wc_create_order(array('status' => 'pending'));
        if (is_wp_error($order)) { throw new Exception($order->get_error_message()); }
        ${result('$order->get_id()')}
    `);
    browser = await chromium.launch({ headless: true, executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
    const context = await browser.newContext();
    await context.route('**/*', (route) => new URL(route.request().url()).origin === new URL(baseURL).origin ? route.continue() : route.abort());
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${baseURL}/wp-login.php`);
    await page.locator('#user_login').fill(process.env.TEST_ADMIN_USER);
    await page.locator('#user_pass').fill(process.env.TEST_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);

    await page.goto(`${baseURL}/wp-admin/options-general.php?page=dianxiaomi-setting-admin`);
    await page.locator('#plugin').selectOption('dianxiaomi');
    // Clear existing selections using the visible SelectWoo controls.
    const settingsSelect = page.locator('#couriers_select + .select2');
    while (await settingsSelect.locator('.select2-selection__choice__remove').count()) {
        await settingsSelect.locator('.select2-selection__choice__remove').first().click();
    }
    for (const name of ['UPS', 'FedEx']) {
        await settingsSelect.locator('input').click();
        await settingsSelect.locator('input').fill('');
        await settingsSelect.locator('input').pressSequentially(name);
        await page.getByRole('option', { name, exact: true }).click();
    }
    await Promise.all([page.waitForURL(/settings-updated=true/), page.getByRole('button', { name: 'Save Changes', exact: true }).click()]);
    await page.reload();
    assert.equal(wp(result("get_option('dianxiaomi_option_name')['plugin']")), 'dianxiaomi');
    assert.equal(await page.locator('#plugin').inputValue(), 'dianxiaomi');
    const storedCouriers = wp(result("get_option('dianxiaomi_option_name')['couriers']"));
    assert.deepEqual(storedCouriers.split(',').sort(), ['fedex', 'ups']);
    assert.deepEqual(await page.locator('#couriers_select').evaluate((select) => Array.from(select.selectedOptions, (option) => option.value).sort()), ['fedex', 'ups']);

    if (check !== 'settings') {
        const orderURL = mode === 'hpos'
            ? `${baseURL}/wp-admin/admin.php?page=wc-orders&action=edit&id=${orderId}`
            : `${baseURL}/wp-admin/post.php?post=${orderId}&action=edit`;
        await page.goto(orderURL);
        assert.equal(await page.locator('#woocommerce-dianxiaomi #dianxiaomi_tracking_number').count(), 1, 'The order editor must render the tracking meta box');
    }
    assert.deepEqual(errors, [], 'The admin page must not throw JavaScript errors');
    console.log(`PASS: ${mode} ${check}; settings saved/reloaded and checked in database`);
} catch (error) {
    console.error(JSON.stringify({ mode, check, pageErrors: errors }));
    throw error;
} finally {
    if (browser) await browser.close();
    const restored = wp(`
        ${orderId ? `$order = wc_get_order(${orderId}); if ($order) { $order->delete(true); }` : ''}
        foreach (${phpLiteral(snapshot)} as $name => $value) {
            if (false === $value) { delete_option($name); } else { update_option($name, $value); }
        }
        $restored = array();
        foreach (array_keys(${phpLiteral(snapshot)}) as $name) { $restored[$name] = get_option($name); }
        ${result('$restored')}
    `);
    assert.deepEqual(restored, snapshot, 'Restore the original options after the browser test');
}
