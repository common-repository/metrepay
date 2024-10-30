<?php
/*
Plugin Name: Metrepay
Plugin URI: https://wordpress.org/plugins/metrepay
Description: Pasarela de pago para integracion con MetrePay y Woocomerce
Version: 1.3.0
Author: Rugertek
Author URI: https://rugertek.com/
*/

if (!defined('ABSPATH')) {
    die();
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
  
    // Create the pay page

register_activation_hook(__FILE__, 'mpay_local_check_plugin_activation');
function mpay_local_check_plugin_activation()
{
    if (!current_user_can('activate_plugins')) return;

    if (get_page_by_title("Pago Metrepay") == NULL) {

        $current_user = wp_get_current_user();

        // create post object
        $page = array(
            'post_title'  => __('Pago Metrepay'),
            'post_status' => 'publish',
            'post_author' => $current_user->ID,
            'post_type'   => 'page',
            'post_content' => "[metrepay_singlepay]"
        );

        // insert the post into the database
        wp_insert_post($page);
    }
}

// Add the gateway to the list

add_filter('woocommerce_payment_gateways', 'mpay_local_metrepay_add_gateway_class');
function mpay_local_metrepay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Metrepay_Gateway'; // This is the name of our class.
    return $gateways;
}

add_action('plugins_loaded', 'mpay_local_metrepay_init_gateway_class');
function mpay_local_metrepay_init_gateway_class()
{
    class WC_Metrepay_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {

            // Mandatory variables.
            $this->id = 'metrepay_gateway';
            $this->has_fields = false; // in case you need a custom credit card form (Direct integration)
            $this->method_title = 'MetrePay Gateway';
            $this->method_description = 'Aquí se configuran las opciones de MetrePay.';

            // Salt configuration.
            $this->salt = "PsK5fW";

            $this->supports = array(
                'products', 'refunds'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();
            $this->title = sanitize_text_field($this->get_option('title'));

            // DA means Debito Automatico
            // First, get this param to modify the payment box behavior
            $this->active_da = sanitize_text_field($this->get_option('active_da'));

            // Second, depending on the param before, the box behavior will change
            $this->description = "Pagá con tu tarjeta de crédito o débito.";
            $this->enabled = sanitize_text_field($this->get_option('enabled'));
            $this->debugging = 'yes' === sanitize_text_field($this->get_option('debugging'));
            $this->auth_token = sanitize_text_field($this->get_option('auth_token'));
            $this->provider_slug = sanitize_text_field($this->get_option('provider_slug'));
            $this->instance = sanitize_text_field($this->get_option('instance'));
            $this->use_site_currency = 'no' === sanitize_text_field($this->get_option('use_site_currency'));
            $this->header_token = $this->mpay_local_generate_string();

            $this->check_availability_installments();

            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            wp_enqueue_script('metrepay_gateway_javascript', plugins_url('/assets/metrepay.js', __FILE__));
            wp_enqueue_style('metrepay_gateway_styles', plugins_url('/assets/metrepay.css', __FILE__));
        }

        /**
         * Override al método que prepara el contenido del box de medio de 
         * pago en checkout. Aplicamos override dado que el metodo original 
         * limpia los "<input>" y lo necesitamos para aplicar lógica de 
         * pago unico o débito automático 
         */
        public function payment_fields()
        {
            // Renderiza la descripción sin modificaciones
            echo "<div class='description-container'>
                    <img src='https://www.metrepay.com/home/img/logos/full-icon.png'>
                    " . wpautop(wptexturize($this->description)) . "
                </div>";

            // Agrega el div y el input adicional sin pasar por filtros de seguridad
            echo '<div id="metrepayselector"></div>';
            echo '<input type="hidden" id="activeDA" value="' . esc_attr($this->active_da) . '">';
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Habilitar/Deshabilitar',
                    'label'       => 'Habilitar Metrepay Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'debugging' => array(
                    'title'       => 'Mostrar información para debugging',
                    'label'       => 'Debugging',
                    'type'        => 'checkbox',
                    'description' => 'Muestra informaciones de debugging.',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Título',
                    'type'        => 'text',
                    'description' => 'Título que será visible al cliente en el proceso de pago (checkout)',
                    'default'     => 'MetrePay',
                    'desc_tip'    => true,
                ),
                'instance' => array(
                    'title'       => 'URL MetrePay',
                    'type'        => 'text',
                    'description' => 'Dirección URL del sitio MetrePay que utiliza el comercio. Ejemplo: test.metrepay.com',
                    'default'     => 'portal.metrepay.com',
                    'desc_tip'    => true,
                ),
                'auth_token' => array(
                    'title'       => 'Token API Key',
                    'type'        => 'password',
                    'description' => 'Token o clave de API que debe ser proveído por MetrePay',
                ),
                'provider_slug' => array(
                    'title'       => 'Slug del comercio',
                    'type'        => 'text',
                    'description' => 'Información adicional del comercio en MetrePay. Es proveído con el Token API Key.',
                ),
                'use_site_currency' => array(
                    'title'       => 'Moneda del sitio',
                    'label'       => 'Utilizar la moneda configurada en el sitio actual: ' . get_woocommerce_currency(),
                    'type'        => 'checkbox',
                    'description' => 'Si se activa, cada link de pago generado será con la moneda configurada en el sitio Woocommerce. Si se desactiva, la moneda será la establecida por defecto en MetrePay. La habilitación de la moneda a utilizarse está sujeta a cuál sitio MetrePay uno esté registrado. En caso de dudas, contacte con su asesor.',
                    'default'     => 'no'
                ),
                'active_da' => array(
                    'title'       => 'Débito automático',
                    'label'       => 'Permitir pagos en cuotas por débitos automáticos',
                    'description' => 'Solamente aplica cuando el carrito tiene 1 producto con atributo "cuotas"',
                    'type'        => 'checkbox',
                    'default'     => 'yes'
                ),
            );
        }

        public function get_debugging()
        {
            return $this->debugging;
        }

        public function get_auth_token()
        {
            return $this->auth_token;
        }

        public function get_provider_slug()
        {
            return $this->provider_slug;
        }

        public function get_active_da()
        {
            return $this->active_da;
        }
        
        public function get_instance()
        {
            return $this->instance;
        }

        public function get_use_site_currency()
        {
            return $this->use_site_currency;
        }

        // Conect to the services
        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);

            if ($this->get_mp_payment_method() == "PAGO_UNICO") {
                $data = array(
                    "singlePayment" => true,
                    "creditAndDebitCard" => true,
                );
            } else {
                $installments_quantity = null;

                if (isset($_COOKIE['mp_installments_quantity'])) {
                    $installments_quantity = intval(sanitize_text_field($_COOKIE['mp_installments_quantity']));
                }

                $data = array(
                    "singlePayment" => false,
                    "creditAndDebitCard" => true,
                    "recurrentPayment" => true,
                    "installmentAmount" => intval($order->get_total()),
                    "totalPeriods" => $installments_quantity,
                    "dayOfMonth" => 1,
                    "monthForSecondInstallment" => 1,
                    "payWaitHours" => 96,
                );
            }

            // Establece los campos adicionales para el link de pago
            $data['label'] = "Pago a " . get_bloginfo("name");
            $data['amount'] = intval($order->get_total());
            $data['handleValue'] = $order->get_billing_email();
            $data['handleLabel'] = "{$order->get_billing_first_name()} {$order->get_billing_last_name()}";
            $data['customIdentifier'] = get_post_meta($order->get_id(), 'CIRUC', true);

            // Obtiene URL a página de pago de MP en el sitio ecommerce
            $query = new WP_Query( array( 'pagename' => 'pago-metrepay' ) );
            $redirectUrl = get_permalink($query->get_queried_object());
            $redirectUrl = add_query_arg('order_id', $order_id, $redirectUrl);
            $data['redirectUrl'] = $redirectUrl;

            // Según el parámetro del comercio, si quiere o no utilizar la moneda configurada
            if ($this->get_use_site_currency() == 'yes') {
                $data['currency'] = get_woocommerce_currency();
            }

            $response = $this->mpay_local_data_post($data);
            $result = json_decode($response, true);

            error_log(json_encode($data));
            error_log(json_encode($result));

            // Delete the cookie
            $this->mpay_local_delete_cookie('mp_installments_quantity');

            return array(
                'result' => 'success',
                'redirect' => $result['publicPayUrl']
            );
        }

        // Constructor data
        public function get_mp_payment_method()
        {
            return sanitize_text_field($_COOKIE['mp_payment_method']);
        }

        public function check_availability_installments() 
        {
            // Saves if the current cart can have (or not) "debito automatico"
            // as payment method
            $can_have_da = false;
            // Quantity of installments = numero de cuotas
            $installments_quantity = null;
            // If DA is active or not
            $active_da = $this->get_active_da() == 'yes';

            if (!isset(WC()->cart)) {
                return;
            }

            $cart = WC()->cart->get_cart();
            // For DA just 1 item is allowed
            if (count($cart) == 1 && $active_da) {
                foreach ( $cart as $cart_item_key => $cart_item ) {
                    $product = $cart_item['data'];
                    $installments_quantity = $product->get_attribute('cuotas');
                    if (isset($installments_quantity) 
                        && is_numeric($installments_quantity)) {
                            $installments_quantity = intval($installments_quantity);
                            if ($installments_quantity > 0) {
                                $can_have_da = true;
                            }
                    }   
                }
            }

            if ($can_have_da) {
                error_log('can_have_da: ' . $can_have_da);
                // We setup the cookie
                $this->mpay_local_set_cookie('mp_installments_quantity', $installments_quantity);
                $this->mpay_local_set_cookie('mp_payment_method', 'DEBITO_AUTOMATICO');
                // Known warning -> PHP Warning:  Cannot modify header information - headers already sent by blablabla
                // Another file was outputting information before this php file.
            } else {
                // The cart is only for "PAGO UNICO"
                // We delete the cookie related to the due by setting a past expiration time
                $this->mpay_local_delete_cookie('mp_installments_quantity');
                $this->mpay_local_set_cookie('mp_payment_method', 'PAGO_UNICO');
            }
        
            if (isset($_COOKIE['mp_installments_quantity'])) {
                error_log('mp_installments_quantity: ' . sanitize_text_field($_COOKIE['mp_installments_quantity']));
            } else {
                error_log('mp_installments_quantity: NULL');
            }
        }

        // Helpers
        function mpay_local_generate_string($strength = 16)
        {
            $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            $input_length = strlen($permitted_chars);
            $random_string = '';
            for ($i = 0; $i < $strength; $i++) {
                $random_character = $permitted_chars[mt_rand(0, $input_length - 1)];
                $random_string .= $random_character;
            }

            return $random_string;
        }

        function mpay_local_show_error_to_user() {
            wc_add_notice(__('El método de pago MetrePay debe ser revisado. Por favor, contacte al administrador'), 'error');
        }

        function mpay_local_get_mp_url() {
            // Obtiene la instancia actual
            $instance = $this->get_instance();

            // Valida que la instancia no esté vacía
            if (empty($instance)) {
                throw new Exception("Error: No se ha definido una instancia válida. Por favor, contacte al administrador.");
            }

            // Formatea y sanitiza la URL
            $instance = trim($instance); // Elimina espacios
            $instance = filter_var($instance, FILTER_SANITIZE_URL); // Elimina caracteres no válidos en una URL

            // Construye la URL final
            $url = "https://{$instance}/api/saleitems/add";

            // Valida la estructura de la URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception("Error: La URL generada no es válida. Por favor, contacte al administrador.");
            }

            return $url;
        }

        function mpay_local_data_post($data) {
            try {
                $url = $this->mpay_local_get_mp_url();
                error_log(json_encode($url));

                $args = array(
                    'method'      => 'POST',
                    'body'        => json_encode($data),
                    'timeout'     => '5',
                    'httpversion' => '1.0',
                    'blocking'    => true,
                    'headers'     => array(
                        'Content-Type' => 'application/json',
                        'Api-Token'    => $this->get_auth_token()
                    ),
                );
                $response = wp_remote_post($url, $args);
                return $response['body'];

            } catch (Exception $e) {
                error_log('Message: ' .$e->getMessage());
                $this->mpay_local_show_error_to_user();
            }
        }

        function mpay_local_set_cookie($name, $value) {
            $cookie_expiration = time() + 86400; // 86400 = 1 day
            setcookie(sanitize_text_field($name), sanitize_text_field($value), $cookie_expiration, "/"); 
        }

        function mpay_local_delete_cookie($name) {
            if (isset($_COOKIE[$name])) {
                setcookie($name, "", time() - 3600, "/"); 
            }
        }
    }
}

// Shorcode pago
require(__DIR__ . "/includes/shortcode.php");

// Checkout fields
require(__DIR__ . "/includes/fields.php");

} else {
  return;
}