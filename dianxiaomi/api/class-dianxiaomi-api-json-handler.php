<?php
/**
 * Dianxiaomi API
 *
 * Handles parsing JSON request bodies and generating JSON responses
 *
 * @author      Dianxiaomi
 * @category    API
 * @package     Dianxiaomi/API
 * @since       1.0
 */

 /**
 * Alex 02/03/2024
 * Déclarations de type : Ajout de déclarations de type pour les paramètres et les valeurs de retour pour améliorer la robustesse et la prévisibilité du code.
 * Sécurité améliorée pour JSONP : Utilisation de htmlspecialchars pour nettoyer la sortie JSONP et éviter les attaques XSS.
 * Gestion des erreurs : Amélioration de la gestion des erreurs pour les réponses JSONP.
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class Dianxiaomi_API_JSON_Handler implements Dianxiaomi_API_Handler
{
    /**
     * Get the content type for the response
     *
     * @since 2.1
     * @return string
     */
    public function get_content_type(): string
    {
        return 'application/json; charset=' . get_option('blog_charset');
    }

   /**
     * Parses the JSON body.
     *
     * @param string $data JSON string to be parsed.
     * @return array Parsed data as an associative array.
     */
    public function parse_body(string $data): array {
        return json_decode($data, true);
    }
 /**
     * Generate a JSON response given an array of data
     *
     * @since 2.1
     * @param array $data the response data
     * @return string
     */
    public function generate_response(array $data): string
    {
        if (isset($_GET['_jsonp'])) {
            // JSONP enabled by default
            if (!apply_filters('dianxiaomi_api_jsonp_enabled', true)) {
                WC()->api->server->send_status(400);
                $data = [['code' => 'dianxiaomi_api_jsonp_disabled', 'message' => __('JSONP support is disabled on this site', 'dianxiaomi')]];
            }

            // Check for invalid characters (only alphanumeric allowed)
            if (preg_match('/\W/', $_GET['_jsonp'])) {
                WC()->api->server->send_status(400);
                $data = [['code' => 'dianxiaomi_api_jsonp_callback_invalid', 'message' => __('The JSONP callback function is invalid', 'dianxiaomi')]];
            }

            return htmlspecialchars($_GET['_jsonp']) . '(' . json_encode($data) . ')';
        }

        return json_encode($data);
    }
}