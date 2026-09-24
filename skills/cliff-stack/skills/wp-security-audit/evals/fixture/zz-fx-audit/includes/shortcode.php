<?php
if (!defined('ABSPATH')) exit;
add_shortcode('fx_bouton', function ($atts) {
    $a = shortcode_atts(['label' => 'Contact', 'couleur' => 'bleu'], $atts);
    return '<a class="fx-btn fx-' . $a['couleur'] . '" href="#contact">' . esc_html($a['label']) . '</a>';
});
