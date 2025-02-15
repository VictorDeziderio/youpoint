<?php
if (!defined('ABSPATH')) {
    exit; // Evita acesso direto
}

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_log('Início da execução do arquivo woocommerce.php');

// Verificar se WooCommerce está ativo
if (!class_exists('WooCommerce')) {
    return; // Interrompe se WooCommerce não estiver ativo
}

/**
 * Verifica se o produto está associado a algum post_type=listing.
 *
 * @param int $product_id O ID do produto para verificar.
 * @return bool True se o produto estiver associado, False caso contrário.
 */
if (!function_exists('youpoint_is_product_in_listing')) {
    function youpoint_is_product_in_listing($product_id) {
        $args = [
            'post_type'   => 'listing',
            'meta_query'  => [
                [
                    'key'   => 'product_id',
                    'value' => $product_id,
                    'compare' => '=',
                ],
            ],
            'fields'      => 'ids', // Retorna apenas os IDs
            'posts_per_page' => 1, // Apenas precisa verificar se existe
        ];

        $query = new WP_Query($args);

        // Retorna true se encontrar pelo menos um post
        return $query->have_posts();
    }
}

/**
 * Exibe o campo "Data da Publicidade" antes das variações no formulário.
 */
function youpoint_add_advertising_date_field_before_variations() {
    global $product;

    // Verifica se o produto está associado a uma listing
    if (!youpoint_is_product_in_listing($product->get_id())) {
        return;
    }

    // Certifica-se de que o produto é variável
    if ($product->is_type('variable')) {
        echo '<div class="woocommerce-variation-additional-fields">';
        echo '<label for="advertising_date">' . __('Data da Publicidade', 'youpoint') . '</label>';
        echo '<input type="text" id="advertising_date" name="advertising_date" class="input-text" placeholder="DD/MM/YYYY" required>';
        echo '</div>';
    }
}
add_action('woocommerce_before_variations_form', 'youpoint_add_advertising_date_field_before_variations');

/**
 * Valida o campo antes de adicionar ao carrinho.
 */
function youpoint_validate_advertising_date_field($passed, $product_id, $quantity) {
    if (youpoint_is_product_in_listing($product_id)) {
        if (empty($_POST['advertising_date'])) {
            wc_add_notice(__('Por favor, selecione uma data para a publicidade.', 'youpoint'), 'error');
            return false;
        }
    }
    return $passed;
}
add_filter('woocommerce_add_to_cart_validation', 'youpoint_validate_advertising_date_field', 10, 3);

/**
 * Salva a "Data da Publicidade" no carrinho.
 */
function youpoint_add_advertising_date_to_cart_item($cart_item_data, $product_id, $variation_id) {
    if (!empty($_POST['advertising_date'])) {
        $date = DateTime::createFromFormat('d/m/Y', sanitize_text_field($_POST['advertising_date']));
        $cart_item_data['advertising_date'] = $date ? $date->format('Y-m-d') : '';
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'youpoint_add_advertising_date_to_cart_item', 10, 3);

/**
 * Exibe a "Data da Publicidade" no carrinho e no checkout.
 */
function youpoint_display_advertising_date_in_cart($item_data, $cart_item) {
    if (isset($cart_item['advertising_date'])) {
        $formatted_date = date_i18n('d/m/Y', strtotime($cart_item['advertising_date']));
        $item_data[] = [
            'key'   => __('Data da Publicidade', 'youpoint'),
            'value' => $formatted_date,
        ];
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'youpoint_display_advertising_date_in_cart', 10, 2);

/**
 * Salva a "Data da Publicidade" no pedido.
 */
function youpoint_add_advertising_date_to_order_items($item, $cart_item_key, $values, $order) {
    if (isset($values['advertising_date'])) {
        $formatted_date = date_i18n('d/m/Y', strtotime($values['advertising_date']));
        $item->add_meta_data('Data da Publicidade', $formatted_date, true);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'youpoint_add_advertising_date_to_order_items', 10, 4);

/**
 * Redireciona o cliente para o listing associado ao acessar o produto.
 */
function youpoint_redirect_to_listing() {
    if (!is_product()) {
        return; // Aplica apenas em páginas de produto
    }

    $product_id = get_the_ID();

    if (!youpoint_is_product_in_listing($product_id)) {
        return; // Não redireciona se o produto não estiver associado a um listing
    }

    // Buscar o primeiro listing associado ao produto
    $args = [
        'post_type'   => 'listing',
        'meta_query'  => [
            [
                'key'   => 'product_id',
                'value' => $product_id,
                'compare' => '=',
            ],
        ],
        'posts_per_page' => 1, // Apenas o primeiro resultado
        'fields'      => 'ids',
    ];

    $listings = new WP_Query($args);

    if ($listings->have_posts()) {
        wp_redirect(get_permalink($listings->posts[0]));
        exit;
    }
}
add_action('template_redirect', 'youpoint_redirect_to_listing');

/**
 * Substitui a variação no carrinho.
 */
function youpoint_replace_cart_item($product_id, $quantity, $variation_id, $cart_item_data = []) {
    $cart = WC()->cart;

    // Remover qualquer variação existente do mesmo produto no carrinho
    foreach ($cart->get_cart() as $key => $item) {
        if ($item['product_id'] === $product_id) {
            $cart->remove_cart_item($key);
        }
    }

    // Adicionar a nova variação ao carrinho
    if (!$cart->add_to_cart($product_id, $quantity, $variation_id, [], $cart_item_data)) {
        error_log("Erro ao adicionar ao carrinho: Produto ID {$product_id}, Variação ID {$variation_id}");
    }
}

/**
 * AJAX: Adicionar o produto ao carrinho.
 */
function youpoint_add_to_cart() {
    try {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'youpoint_nonce')) {
            wp_send_json_error('Requisição inválida.');
        }

        $product_id = intval($_POST['product_id']);
        $selected_options = array_map('sanitize_text_field', $_POST['selected_options'] ?? []);
        $publicity_date = sanitize_text_field($_POST['data-da-publicidade'] ?? '');

        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('Produto inválido ou não encontrado.');
        }

        foreach ($product->get_available_variations() as $variation) {
            $match = true;
            foreach ($selected_options as $attribute => $value) {
                $key = 'attribute_' . sanitize_title($attribute);
                if (!isset($variation['attributes'][$key]) || $variation['attributes'][$key] !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                $variation_id = $variation['variation_id'];
                $cart_item_data = ['advertising_date' => $publicity_date];
                youpoint_replace_cart_item($product_id, 1, $variation_id, $cart_item_data);

                wp_send_json_success(['status' => 'added']);
            }
        }

        wp_send_json_error('Nenhuma variação correspondente encontrada.');
    } catch (Exception $e) {
        error_log('Erro interno no servidor: ' . $e->getMessage());
        wp_send_json_error('Erro interno no servidor.');
    }
}
add_action('wp_ajax_youpoint_add_to_cart', 'youpoint_add_to_cart');
add_action('wp_ajax_nopriv_youpoint_add_to_cart', 'youpoint_add_to_cart');

/**
 * Adiciona o botão "Editar Produto Associado" na barra de administração do WordPress.
 */
function youpoint_add_edit_product_button_admin_bar($wp_admin_bar) {
    if (!is_singular('listing')) {
        return; // Aplica apenas em páginas individuais do post_type 'listing'
    }

    if (!current_user_can('administrator')) {
        return; // Apenas administradores podem visualizar o botão
    }

    global $post;

    // Obter o ID do produto associado ao listing
    $product_id = get_post_meta($post->ID, 'product_id', true);

    if (!$product_id) {
        return; // Oculta o botão se o produto não estiver associado
    }

    $edit_url = get_edit_post_link($product_id);

    if ($edit_url) {
        $wp_admin_bar->add_node([
            'id'    => 'edit_associated_product',
            'title' => __('Editar Produto Associado', 'youpoint'),
            'href'  => $edit_url,
            'meta'  => [
                'class' => 'youpoint-edit-product',
            ],
        ]);
    }
}
add_action('admin_bar_menu', 'youpoint_add_edit_product_button_admin_bar', 100);

/**
 * Busca o preço da variação via AJAX.
 */
function youpoint_get_variation_price() {
    try {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'youpoint_nonce')) {
            wp_send_json_error('Nonce inválido.');
        }

        $product_id = intval($_POST['product_id']);
        $selected_options = $_POST['selected_options'] ?? [];
        $product = wc_get_product($product_id);

        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('Produto inválido ou não encontrado.');
        }

        foreach ($product->get_available_variations() as $variation) {
            $match = true;
            foreach ($selected_options as $attribute => $value) {
                $key = 'attribute_' . sanitize_title($attribute);
                if (!isset($variation['attributes'][$key]) || $variation['attributes'][$key] !== $value) {
                    $match = false;
                    break;
                }
            }

            if ($match) {
                wp_send_json_success(['price' => $variation['display_price']]);
            }
        }

        wp_send_json_error('Nenhuma variação correspondente encontrada.');
    } catch (Exception $e) {
        error_log('Erro interno no servidor: ' . $e->getMessage());
        wp_send_json_error('Erro interno no servidor.');
    }
}
add_action('wp_ajax_youpoint_get_variation_price', 'youpoint_get_variation_price');
add_action('wp_ajax_nopriv_youpoint_get_variation_price', 'youpoint_get_variation_price');
?>