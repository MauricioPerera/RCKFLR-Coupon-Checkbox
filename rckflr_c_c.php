<?php
/*
Plugin Name: RCKFLR Coupon Checkbox
Plugin URI: https://rckflr.party/
Description: This plugin adds a coupon checkbox to WooCommerce products.
Version: 1.0
Author: Mauricio Perera
Author URI: https://www.linkedin.com/in/mauricioperera/
Donate link: https://www.buymeacoffee.com/rckflr
*/

class RCKFLR_CouponCheckbox {
    public function __construct() {
        add_action('woocommerce_after_add_to_cart_button', [$this, 'rckflr_render_coupon_checkbox']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'rckflr_add_coupon_metabox']);
        add_action('woocommerce_process_product_meta', [$this, 'rckflr_save_coupon_metabox']);
        add_action('wp_footer', [$this, 'rckflr_enqueue_scripts']);
    }

    public function rckflr_render_coupon_checkbox() {
        global $product;
        $product_id = $product->get_id();
        $coupon_code = get_post_meta($product_id, '_rckflr_coupon_code', true);

        if (!$coupon_code) return;

        $coupon = new WC_Coupon($coupon_code);

        if (!$coupon->get_id()) return;

        $coupon_amount = $coupon->get_amount();
        $checkbox_label = $coupon->is_type('percent') ? $coupon_amount . '%' : get_woocommerce_currency_symbol() . $coupon_amount;
        ?>
        <p>
            <label for="rckflr-coupon-checkbox">Save an extra <?php echo esc_html($checkbox_label); ?></label>
            <input type="checkbox" id="rckflr-coupon-checkbox" data-coupon-code="<?php echo esc_attr($coupon_code); ?>">
        </p>
        <?php
    }

    public function rckflr_add_coupon_metabox() {
        echo '<div class="options_group">';
        woocommerce_wp_text_input([
            'id' => '_rckflr_coupon_code',
            'label' => __('Coupon Code', 'woocommerce'),
            'desc_tip' => true,
            'description' => __('Enter a coupon code for the checkbox offer', 'woocommerce'),
        ]);
        echo '</div>';
    }

    public function rckflr_save_coupon_metabox($post_id) {
        $coupon_code = isset($_POST['_rckflr_coupon_code']) ? wc_clean($_POST['_rckflr_coupon_code']) : '';
        update_post_meta($post_id, '_rckflr_coupon_code', $coupon_code);
    }

    public function rckflr_enqueue_scripts() {
        if (!is_product()) return;
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#rckflr-coupon-checkbox').on('change', function() {
                    var isChecked = $(this).is(':checked');
                    var couponCode = $(this).data('coupon-code');

                    if (isChecked) {
                        $.ajax({
                            type: 'POST',
                            url: wc_add_to_cart_params.ajax_url,
                            data: {
                                action: 'rckflr_apply_coupon',
                                coupon_code: couponCode
                            },
                            success: function(response) {
                                if (response.result === 'success' && response.coupon_data) {
                                    $('.woocommerce-message').html(response.coupon_data);
                                } else if (response.result === 'error') {
                                    $('.woocommerce-error').html(response.error_message);
                                }
                            },
                            dataType: 'json'
                        });
                    }
                });
            });
        </script>
        <?php
    }
}

new RCKFLR_CouponCheckbox();

function rckflr_apply_coupon_callback() {
    $coupon_code = isset($_POST['coupon_code']) ? wc_clean($_POST['coupon_code']) : '';

    if (!$coupon_code) {
        wp_send_json_error([
            'result' => 'error',
            'error_message' => __('Coupon not valid.', 'woocommerce')
        ]);
    }

    $coupon = new WC_Coupon($coupon_code);

    if (!$coupon->get_id()) {
        wp_send_json_error([
            'result' => 'error',
            'error_message' => __('Coupon not valid.', 'woocommerce')
        ]);
    }

    WC()->cart->add_discount($coupon_code);
    wc_clear_notices();
    wc_add_notice(__('Coupon successfully applied.', 'woocommerce'));
    $notices = wc_get_notices();

    wp_send_json_success([
        'result' => 'success',
        'coupon_data' => $notices['success'] ? implode('', $notices['success']) : ''
    ]);
}
add_action('wp_ajax_rckflr_apply_coupon', 'rckflr_apply_coupon_callback');
add_action('wp_ajax_nopriv_rckflr_apply_coupon', 'rckflr_apply_coupon_callback');
