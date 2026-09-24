<?php
if (!defined('ABSPATH')) exit;
add_action('wp_ajax_fx_delete_item', 'fx_delete_item');
add_action('wp_ajax_nopriv_fx_delete_item', 'fx_delete_item');
function fx_delete_item() {
    check_ajax_referer('fx_delete', 'nonce');
    $id = absint($_POST['id'] ?? 0);
    wp_delete_post($id, true);
    wp_send_json_success();
}
add_action('wp_footer', function () {
    echo '<script>var fxNonce = "' . esc_js(wp_create_nonce('fx_delete')) . '";</script>';
});
