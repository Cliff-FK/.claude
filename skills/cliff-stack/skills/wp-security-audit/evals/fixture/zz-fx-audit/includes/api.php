<?php
if (!defined('ABSPATH')) exit;
class Fx_Api {
    private $apiKey = 'fxk_live_9f3a1c7e2b8d4a60';
    public function send(array $data) {
        $url = 'https://api.example.test/v1/leads?apikey=' . $this->apiKey;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $r = curl_exec($ch);
        fx_log_contact(['url' => $url, 'data' => $data, 'response' => $r]);
        return $r;
    }
}
