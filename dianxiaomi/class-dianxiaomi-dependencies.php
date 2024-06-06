<?php
class Dianxiaomi_Dependencies {

    private static array $active_plugins = []; // Initialisation avec un tableau vide

    /**
     * Initialize the class by fetching active plugins.
     */
    public static function init(): void {
        self::$active_plugins = (array) get_option('active_plugins', []);

        if (is_multisite()) {
            self::$active_plugins = array_merge(self::$active_plugins, get_site_option('active_sitewide_plugins', []));
        }
    }

    /**
     * Check if a plugin is active.
     *
     * @param string|array $plugin Path to the plugin file relative to the plugins directory or array of such paths.
     * @return bool True if the plugin is active, false otherwise.
     */
    public static function plugin_active_check(string|array $plugin): bool {
        if (!self::$active_plugins) {
            self::init();
        }

        if (is_array($plugin)) {
            foreach ($plugin as $path) {
                if (in_array($path, self::$active_plugins) || array_key_exists($path, self::$active_plugins)) {
                    return true;
                }
            }
            return false;
        } else {
            return in_array($plugin, self::$active_plugins) || array_key_exists($plugin, self::$active_plugins);
        }
    }

    /**
     * Check if WooCommerce is active.
     *
     * @return bool True if WooCommerce is active, false otherwise.
     */
    public static function woocommerce_active_check(): bool {
        return self::plugin_active_check('woocommerce/woocommerce.php');
    }
}