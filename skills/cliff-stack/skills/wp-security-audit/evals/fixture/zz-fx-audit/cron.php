<?php
// Appelé par la crontab des serveurs clients : https://site/wp-content/plugins/zz-fx-audit/cron.php?key=...
require_once dirname(__DIR__, 3) . '/wp-load.php';
if (($_GET['key'] ?? '') != 'fx-cron-2f9c41') { http_response_code(403); exit('Forbidden'); }
do_action('fx_sync');
echo "OK\n";
