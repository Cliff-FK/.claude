<?php
if (!defined('ABSPATH')) exit;
add_action('admin_notices', function () {
    $tab = isset($_GET['fx_tab']) ? sanitize_key(wp_unslash($_GET['fx_tab'])) : 'general';
    if (!in_array($tab, ['general', 'advanced'], true)) $tab = 'general';
    printf('<a href="%s">%s</a>', esc_url(add_query_arg('fx_tab', $tab)), esc_html($tab));
});
function fx_read_cfg() {
    $raw = get_option('fx_cfg_serialized', '');
    return $raw ? unserialize($raw, ['allowed_classes' => false]) : [];
}
