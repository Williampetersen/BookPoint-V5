<?php
defined('ABSPATH') || exit;

final class BP_WebhookHelper {

  public static function fire(string $event, array $payload) : void {
    if ((int)BP_SettingsHelper::get('webhooks_enabled', 0) !== 1) return;

    $url = (string)BP_SettingsHelper::get('webhooks_url_' . $event, '');
    if ($url === '') return;

    $secret = (string)BP_SettingsHelper::get('webhooks_secret', '');
    $body = [
      'event' => $event,
      'site' => home_url(),
      'timestamp' => time(),
      'data' => $payload,
    ];
    $json = wp_json_encode($body);

    $sig = $secret ? hash_hmac('sha256', $json, $secret) : '';

    wp_remote_post($url, [
      'timeout' => 10,
      'headers' => [
        'Content-Type' => 'application/json',
        'X-BP-Event' => $event,
        'X-BP-Signature' => $sig,
      ],
      'body' => $json,
    ]);
  }
}
