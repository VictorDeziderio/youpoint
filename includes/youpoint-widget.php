<?php
if (!defined('ABSPATH')) {
    exit; // Evita acesso direto
}

// Classe do Widget Personalizado
class YouPoint_Custom_Booking_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'youpoint_custom_booking_widget',
            __('YouPoint Custom Booking', 'youpoint'),
            array('description' => __('Renderiza reservas com suporte para produtos WooCommerce variáveis.', 'youpoint'))
        );
    }

    public function widget($args, $instance) {
        echo $args['before_widget'];

        $title = !empty($instance['title']) ? $instance['title'] : __('Booking', 'youpoint');

        $post_id = get_the_ID();
        $product_id = get_post_meta($post_id, 'product_id', true);
        $min_price = null;

        if (!empty($product_id)) {
            $product = wc_get_product($product_id);

            if ($product && $product->is_type('variable')) {
                // Obter o menor preço das variações
                $variations = $product->get_available_variations();
                foreach ($variations as $variation) {
                    $price = floatval($variation['display_price']);
                    if (is_null($min_price) || $price < $min_price) {
                        $min_price = $price;
                    }
                }
            }
        }

        ?>
        <div id="widget_booking_listings-<?php echo esc_attr($this->number); ?>" class="listing-widget widget listeo_core boxed-widget booking-widget margin-bottom-35">
            <div class="booking-widget-title-wrap">
                <h3 class="widget-title margin-bottom-35">
                    <i class="fa fa-calendar-check"></i> <?php echo esc_html($title); ?>
                </h3>
                <?php if (!is_null($min_price)): ?>
                    <span class="booking-pricing-tag">
                        <?php echo sprintf(__('A partir de: %s', 'youpoint'), wc_price($min_price)); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="row with-forms margin-top-0" id="booking-widget-anchor">
                <form id="form-booking" class="form-booking-rental" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <div class="col-lg-12">
                        <!-- Campo Data da Publicidade -->
                        <div class="panel-dropdown booking-services">
                            <label for="date-picker">
                                Data da Publicidade
                                <i class="tip" data-tip-content="Selecione a data inicial para o plano">
                                    <div class="tip-content">Selecione a data inicial para o plano</div>
                                </i>
                            </label>
                            <input type="text" id="date-picker" name="data-da-publicidade"
                                   class="date-picker-listing-service form-control"
                                   placeholder="<?php esc_attr_e('Selecione a Data', 'youpoint'); ?>"
                                   value="" autocomplete="off">
                        </div>

                        <?php
                        if (!empty($product_id)) {
                            if ($product && $product->is_type('variable')) {
                                if (!empty($variations)) {
                                    $attributes = $product->get_attributes();

                                    foreach ($attributes as $attribute_name => $attribute_details) {
                                        $label = wc_attribute_label($attribute_name, $product) ?: ucwords(str_replace(['-', '_'], ' ', str_replace('pa_', '', $attribute_name)));
                                        echo "<div class='panel-dropdown booking-services'>
                                                <label for='dropdown-" . esc_attr($attribute_name) . "'>
                                                    " . esc_html($label) . "
                                                    <i class='tip' data-tip-content='Selecione uma opção para " . esc_html($label) . "'>
                                                        <div class='tip-content'>Selecione uma opção para " . esc_html($label) . "</div>
                                                    </i>
                                                </label>
                                                <a href='#' class='dropdown-toggle' id='dropdown-" . esc_attr($attribute_name) . "'>
                                                    " . esc_html($label) . "
                                                </a>
                                                <div class='panel-dropdown-content padding-reset' style='top: 78px;'>
                                                    <div class='panel-dropdown-scrollable'>
                                                        <div class='bookable-services'>";

                                        if ($attribute_details->is_taxonomy()) {
                                            $terms = $attribute_details->get_terms();
                                            $terms = youpoint_sort_terms_numerically($terms);
                                            $count = 1;

                                            foreach ($terms as $term) {
                                                if ($attribute_name === 'pa_tempo-de-duracao') {
                                                    echo "<div class='time-slot' day='2'>
                                                        <input type='radio' class='bookable-service-radio' name='" . esc_attr($attribute_name) . "' value='" . esc_attr($term->slug) . "' id='variation-" . esc_attr($term->term_id) . "' data-unavailable='false'>
                                                        <label for='variation-" . esc_attr($term->term_id) . "'>
                                                            <p class='day'>Time Slot " . $count . "</p>
                                                            <strong>" . esc_html($term->name) . "</strong>
                                                            <span>Calculando...</span>
                                                        </label>
                                                    </div>";
                                                } else {
                                                    echo "<div class='single-service'>
                                                        <input type='radio' class='bookable-service-radio' name='" . esc_attr($attribute_name) . "' value='" . esc_attr($term->slug) . "' id='variation-" . esc_attr($term->term_id) . "' data-unavailable='false'>
                                                        <label for='variation-" . esc_attr($term->term_id) . "'>
                                                            <h5 class='plan-name'>" . esc_html($term->name) . "</h5>
                                                        </label>
                                                    </div>";
                                                }
                                                $count++;
                                            }
                                        }

                                        echo "</div></div></div></div>";
                                    }
                                    ?>
                                    <button type="button" class="button book-now fullwidth margin-top-5" id="youpoint-reserve-now" disabled>Solicitar Reserva</button>
                                    <div class="booking-estimated-cost">
                                        <strong>Valor do Plano</strong>
                                        <span youpointTotalPrice="">R$: 0.00</span>
                                    </div>
                                    <?php
                                }
                            }
                        }
                        ?>
                    </div>
                </form>
            </div>
        </div>
        <?php

        echo $args['after_widget'];
    }
}

function register_youpoint_custom_booking_widget() {
    register_widget('YouPoint_Custom_Booking_Widget');
}
add_action('widgets_init', 'register_youpoint_custom_booking_widget');

function youpoint_sort_terms_numerically($terms) {
    usort($terms, function ($a, $b) {
        $a_value = (int) filter_var($a->name, FILTER_SANITIZE_NUMBER_INT);
        $b_value = (int) filter_var($b->name, FILTER_SANITIZE_NUMBER_INT);

        if ($a_value === $b_value) {
            return strcmp($a->name, $b->name);
        }

        return $a_value - $b_value;
    });

    return $terms;
}
?>