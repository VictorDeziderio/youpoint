<?php
/**
 * Plugin Name: YouPoint
 * Plugin URI: https://example.com
 * Description: Melhora o widget e a integração do WooCommerce para trabalhar junto com o YouTicket.
 * Version: 1.0.1
 * Author: Seu Nome
 * Author URI: https://example.com
 * License: GPL2
 */

// Prevenindo acesso direto
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Definindo constantes
define('YOUPOINT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('YOUPOINT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluindo arquivos necessários
include_once YOUPOINT_PLUGIN_PATH . 'includes/youpoint-widget.php';
include_once YOUPOINT_PLUGIN_PATH . 'includes/youpoint-woocommerce.php';

// Função para registrar scripts
if (!function_exists('youpoint_enqueue_scripts')) {
    function youpoint_enqueue_scripts() {
        // Registrar e enfileirar o script principal
        wp_enqueue_script(
            'youpoint-widget-js',
            YOUPOINT_PLUGIN_URL . 'includes/js/youpoint-widget.js',
            ['jquery'],
            '1.0.1',
            true
        );

        // Passar dados do PHP para o JavaScript
        wp_localize_script('youpoint-widget-js', 'youpoint_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('youpoint_nonce'),
        ]);

        // Enfileirar estilos (se necessário)
        wp_enqueue_style(
            'youpoint-styles',
            YOUPOINT_PLUGIN_URL . 'includes/css/youpoint-styles.css',
            [],
            '1.0.1'
        );
    }
}
add_action('wp_enqueue_scripts', 'youpoint_enqueue_scripts');

// Ativação do plugin
function youpoint_activate() {
    // Código a ser executado na ativação
    error_log('YouPoint plugin ativado.');
}
register_activation_hook(__FILE__, 'youpoint_activate');

// Desativação do plugin
function youpoint_deactivate() {
    // Código a ser executado na desativação
    error_log('YouPoint plugin desativado.');
}
register_deactivation_hook(__FILE__, 'youpoint_deactivate');

?>