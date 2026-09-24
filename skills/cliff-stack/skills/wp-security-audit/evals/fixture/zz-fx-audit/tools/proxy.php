<?php
// Aperçu d'image distante pour l'éditeur.
require_once dirname(__DIR__, 4) . '/wp-load.php';
$src = isset($_GET['url']) ? $_GET['url'] : '';
if ($src) {
    header('Content-Type: image/jpeg');
    echo file_get_contents($src);
}
