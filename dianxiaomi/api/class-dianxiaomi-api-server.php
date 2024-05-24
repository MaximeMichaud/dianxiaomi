<?php
/**
 * Dianxiaomi API Server
 *
 * Handles REST API requests in a manner similar to WC_API_Server
 *
 * @category    API
 * @package     Dianxiaomi/API
 * @since       1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once ABSPATH . 'wp-admin/includes/admin.php';

class Dianxiaomi_API_Server {

    const METHOD_GET    = 1;
    const METHOD_POST   = 2;
    const METHOD_PUT    = 4;
    const METHOD_PATCH  = 8;
    const METHOD_DELETE = 16;

    const READABLE   = 1; // GET
    const CREATABLE  = 2; // POST
    const EDITABLE   = 14; // POST | PUT | PATCH
    const DELETABLE  = 16; // DELETE
    const ALLMETHODS = 31; // GET | POST | PUT | PATCH | DELETE

    const ACCEPT_RAW_DATA = 64;
    const ACCEPT_DATA = 128;
    const HIDDEN_ENDPOINT = 256;

    protected $path;
    protected $method;
    protected $params = array();
    protected $headers = array();
    protected $files = array();
    protected $handler;

    public function __construct($path = '') {
        if (empty($path)) {
            $path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '/';
        }

        $this->path = $path;
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->params['GET'] = $_GET;
        $this->params['POST'] = $_POST;
        $this->headers = $this->get_headers($_SERVER);
        $this->files = $_FILES;

        if (isset($_GET['_method'])) {
            $this->method = strtoupper($_GET['_method']);
        }

        if ($this->is_json_request()) {
            $handler_class = 'Dianxiaomi_API_JSON_Handler';
        } elseif ($this->is_xml_request()) {
            $handler_class = 'WC_API_XML_Handler';
        } else {
            $handler_class = apply_filters('dianxiaomi_api_default_response_handler', 'Dianxiaomi_API_JSON_Handler', $this->path, $this);
        }

        $this->handler = new $handler_class();
    }

    // Méthode pour obtenir la valeur de la propriété 'path'
    public function getPath() {
        return $this->path;
    }

    public function serve_request() {
        do_action('dianxiaomi_api_server_before_serve', $this);

        $this->header('Content-Type', $this->handler->get_content_type(), true);

        $result = $this->check_authentication();
        if (is_wp_error($result)) {
            $result = $this->error_to_array($result);
        } else {
            $result = $this->dispatch();
        }

        echo $this->handler->generate_response($result);
    }

    protected function check_authentication() {
        $user = apply_filters('dianxiaomi_api_check_authentication', null, $this);
        if (is_a($user, 'WP_User')) {
            wp_set_current_user($user->ID);
        } elseif (!is_wp_error($user)) {
            $user = new WP_Error('dianxiaomi_api_authentication_error', __('Invalid authentication method', 'dianxiaomi'), array('code' => 500));
        }
        return $user;
    }

    protected function dispatch() {
        switch ($this->method) {
            case 'HEAD':
            case 'GET':
                $method = self::METHOD_GET;
                break;
            case 'POST':
                $method = self::METHOD_POST;
                break;
            case 'PUT':
                $method = self::METHOD_PUT;
                break;
            case 'PATCH':
                $method = self::METHOD_PATCH;
                break;
            case 'DELETE':
                $method = self::METHOD_DELETE;
                break;
            default:
                return new WP_Error('dianxiaomi_api_unsupported_method', __('Unsupported request method', 'dianxiaomi'), array('status' => 400));
        }

        foreach ($this->get_routes() as $route => $handlers) {
            foreach ($handlers as $handler) {
                $callback                  = $handler[0];
                $supported = $handler[1];

                if (!preg_match('@^' . $route . '$@i', $this->path, $matches)) {
                    continue;
                }

                if (!($this->method & $supported)) {
                    return new WP_Error('dianxiaomi_api_unsupported_method', __('Method not allowed', 'dianxiaomi'), array('status' => 405));
                }

                $args = array();
                for ($i = 2; $i < count($matches); $i++) {
                    $args[] = $matches[$i];
                }

                if (!is_callable($callback)) {
                    return new WP_Error('dianxiaomi_api_invalid_handler', __('The handler for the route is invalid', 'dianxiaomi'), array('status' => 500));
                }

                $args = array_merge($args, $this->params['GET']);
                if ($method & self::METHOD_POST) {
                    $args = array_merge($args, $this->params['POST']);
                }
                if ($supported & self::ACCEPT_DATA) {
                    $data = $this->handler->parse_body($this->get_raw_data());
                    $args = array_merge($args, array('data' => $data));
                } elseif ($supported & self::ACCEPT_RAW_DATA) {
                    $data = $this->get_raw_data();
                    $args = array_merge($args, array('data' => $data));
                }

                $args['_method']  = $method;
                $args['_route']   = $route;
                $args['_path']    = $this->getPath(); // Utilisation de l'accesseur
                $args['_headers'] = $this->headers;
                $args['_files']   = $this->files;

                $args = apply_filters('dianxiaomi_api_dispatch_args', $args, $callback);

                // Allow plugins to halt the request via this filter
                if (is_wp_error($args)) {
                    return $args;
                }

                $params = $this->sort_callback_params($callback, $args);
                if (is_wp_error($params)) {
                    return $params;
                }

                return call_user_func_array($callback, $params);
            }
        }

        return new WP_Error('dianxiaomi_api_no_route', __('No route was found matching the URL and request method', 'dianxiaomi'), array('status' => 404));
    }

    public function get_routes() {
        // index added by default
        $endpoints = array(
            '/' => array( array( $this, 'get_index' ), self::READABLE ),
        );

        $endpoints = apply_filters('dianxiaomi_api_endpoints', $endpoints);

        // Normalise the endpoints
        foreach ($endpoints as $route => &$handlers) {
            if (count($handlers) <= 2 && isset($handlers[1]) && !is_array($handlers[1])) {
                $handlers = array($handlers);
            }
        }

        return $endpoints;
    }

    protected function get_raw_data() {
        return file_get_contents('php://input');
    }

    protected function get_headers($server) {
        $headers = array();
        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headers[substr($key, 5)] = $value;
            }
        }
        return $headers;
    }

    private function is_json_request() {
        return isset($this->headers['ACCEPT']) && $this->headers['ACCEPT'] == 'application/json';
    }

    private function is_xml_request() {
        return isset($this->headers['ACCEPT']) && ($this->headers['ACCEPT'] == 'application/xml' || $this->headers['ACCEPT'] == 'text/xml');
    }

    public function header($name, $value, $replace = true) {
        header("{$name}: {$value}", $replace);
    }

    protected function error_to_array(WP_Error $error) {
        $errors = array();
        foreach ((array) $error->errors as $code => $messages) {
            foreach ((array) $messages as $message) { 
                $errors[] = array(
                    'code'    => $code,
                    'message' => $message,
                );
            }
        }
        return array('errors' => $errors);
    }
}
               