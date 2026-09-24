<?php
if (!defined('ABSPATH')) exit;
// Enregistrement rapide des réglages depuis l'écran du plugin.
add_action('admin_init', function () {
    if (is_admin() && isset($_POST['fx_option'], $_POST['fx_value'])) {
        update_option(sanitize_key($_POST['fx_option']), sanitize_text_field(wp_unslash($_POST['fx_value'])));
    }
});
// Import de configuration, réservé aux administrateurs.
add_action('admin_init', function () {
    if (!isset($_POST['fx_import'])) return;
    check_admin_referer('fx_import');
    if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
    update_option('fx_imported', sanitize_text_field(wp_unslash($_POST['fx_import'])));
});
