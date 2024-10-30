<?php

/**
 * Añade el campo CI o RUC a la página de checkout de WooCommerce
 */
add_action('woocommerce_before_order_notes', 'mpay_local_add_checkout_ciruc');

function mpay_local_add_checkout_ciruc($checkout)
{
    ?>
    <h3>Informaci&oacute;n adicional</h3>

    <?php 

    woocommerce_form_field('ciruc', array(
        'type'          => 'text',
        'class'         => array('my-field-class form-row-wide'),
        'label'         => __('CI o RUC'),
        'placeholder'   => __('Ej: 1234567'),
        'required'      => true,
    ), $checkout->get_value('ciruc'));
}

add_action('woocommerce_admin_order_data_after_billing_address', 'mpay_local_viewCIRUCAdminOrder', 10, 1);

function mpay_local_viewCIRUCAdminOrder($order)
{
    ?>
    <p><strong>CI o RUC: </strong><?php echo esc_attr( get_post_meta($order->get_id(), 'CIRUC', true) ); ?></p>
    <?php
}

add_action('woocommerce_checkout_process', 'mpay_local_validate_ciRuc');

function mpay_local_validate_ciRuc()
{
    // Check if set, if its not set add an error.
    if (empty($_POST['ciruc']))
        wc_add_notice(__('Por favor cargar CI o RUC.'), 'error');
}

add_action('woocommerce_checkout_update_order_meta', 'mpay_local_update_ciruc');

function mpay_local_update_ciruc($order_id)
{
    if (!empty($_POST['ciruc'])) {
        update_post_meta($order_id, 'CIRUC', sanitize_text_field($_POST['ciruc']));
    }
}
