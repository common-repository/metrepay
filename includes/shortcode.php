<?php
add_shortcode("metrepay_singlepay", "mpay_local_metrepay_singlepay");

function mpay_local_metrepay_singlepay()
{
    $mg = new WC_Metrepay_Gateway;

    if (isset($_GET['order_id'])) {
        $order_id = sanitize_text_field($_GET['order_id']);
        $order = new WC_Order($order_id);
        $order->update_status('completed', 'order_note');
        ?>
        <h2>¡Gracias por realizar su compra!</h2>
        <?php
    } else {
?>
        <script>
            location.replace('<?php echo get_bloginfo("url") ?>')
        </script>
<?php
    }
}
