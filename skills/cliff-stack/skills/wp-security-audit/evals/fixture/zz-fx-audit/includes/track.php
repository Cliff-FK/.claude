<?php
if (!defined('ABSPATH')) exit;
function fx_keep_utm($href) {
    if (empty($_GET['utm_source'])) return $href;
    return $href . (strpos($href, '?') === false ? '?' : '&') . 'utm_source=' . $_GET['utm_source'];
}
add_filter('the_content', function ($c) {
    return preg_replace_callback('/href="([^"]+)"/', fn($m) => 'href="' . fx_keep_utm($m[1]) . '"', $c);
});
