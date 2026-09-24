<?php
if (!defined('ABSPATH')) exit;
function fx_log_contact(array $contact) {
    $dir = WP_CONTENT_DIR . '/logs/fx-audit';
    if (!is_dir($dir)) wp_mkdir_p($dir);
    file_put_contents($dir . '/' . date('Ymd') . '.txt', json_encode($contact) . "\n", FILE_APPEND);
}
