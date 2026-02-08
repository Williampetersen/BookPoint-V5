<?php
defined('ABSPATH') || exit;

final class BP_LicenseHelper {

  // Default license server used in your distributed plugin build.
  // Override for development by defining BP_LICENSE_SERVER_BASE in wp-config.php
  // or using the `bp_license_server_base_url` filter.
  const API_BASE_DEFAULT = 'https://wpbookpoint.com';
  const OPTION_API_BASE = 'bp_license_server_base_url';

  private static function body_snippet(string $body, int $limit = 600): string {
    $body = (string) $body;
    if ($body === '') return '';
    $body = preg_replace("/\\r\\n|\\r|\\n/", "\n", $body);
    if (!is_string($body)) $body = '';
    if (strlen($body) <= $limit) return $body;
    return substr($body, 0, $limit) . "\n...(truncated)";
  }

  private static function http_user_agent(): string {
    // Some hosts/WAFs aggressively block "server" user agents like "WordPress/x.y".
    // Use a mainstream browser UA and put the plugin identity in custom headers instead.
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';
  }

  private static function base64url_encode(string $raw): string {
    $b64 = base64_encode($raw);
    if (!is_string($b64)) return '';
    return rtrim(strtr($b64, '+/', '-_'), '=');
  }

  private static function public_packed_payload(array $payload): array {
    // Reduce WAF keyword matches by avoiding field names like `license_key` in the request body.
    // Send a compact base64url(json) in the `p` field + short keys inside the JSON.
    $packed = [
      'k' => (string)($payload['license_key'] ?? ''),
      's' => (string)($payload['site'] ?? ''),
      'i' => (string)($payload['instance_id'] ?? ''),
      'v' => (string)($payload['version'] ?? ''),
    ];
    $json = wp_json_encode($packed);
    if (!is_string($json) || $json === '') return $payload;
    $p = self::base64url_encode($json);
    if ($p === '') return $payload;
    return ['p' => $p];
  }

  private static function http_args(string $url, array $payload): array {
    $args = [
      'timeout' => 12,
      'redirection' => 5,
      'sslverify' => true,
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json, */*;q=0.9',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'X-BookPoint' => defined('BP_Plugin::VERSION') ? (string) BP_Plugin::VERSION : 'unknown',
      ],
      'user-agent' => self::http_user_agent(),
      'body' => wp_json_encode($payload),
    ];

    return (array) apply_filters('bp_license_http_args', $args, $url, $payload);
  }

  private static function http_args_form(string $url, array $payload): array {
    // Use x-www-form-urlencoded for compatibility with some WAF/proxies that block JSON POST bodies.
    $args = [
      'timeout' => 12,
      'redirection' => 5,
      'sslverify' => true,
      'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept' => 'application/json, */*;q=0.9',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'X-BookPoint' => defined('BP_Plugin::VERSION') ? (string) BP_Plugin::VERSION : 'unknown',
      ],
      'user-agent' => self::http_user_agent(),
      'body' => $payload, // array => WP will encode
    ];

    return (array) apply_filters('bp_license_http_args', $args, $url, $payload);
  }

  private static function looks_like_waf_block(int $httpCode, string $contentType, string $body): bool {
    if ($httpCode === 555) return true;
    $ct = strtolower((string) $contentType);
    if (strpos($ct, 'text/html') !== false) {
      if (preg_match('/security incident detected/i', $body)) return true;
      if (preg_match('/your request was blocked/i', $body)) return true;
      if (preg_match('/application firewall/i', $body)) return true;
    }
    return false;
  }

  private static function post_with_fallback(string $url, array $payload, array $modes = ['json', 'form']): array {
    $attempts = [];
    $last = null;

    foreach ($modes as $mode) {
      $args = $mode === 'form'
        ? self::http_args_form($url, $payload)
        : self::http_args($url, $payload);

      $res = wp_remote_post($url, $args);
      $last = $res;

      if (is_wp_error($res)) {
        $attempts[] = [
          'mode' => $mode,
          'wp_error_code' => $res->get_error_code(),
          'wp_error_message' => $res->get_error_message(),
        ];
        continue;
      }

      $code = (int) wp_remote_retrieve_response_code($res);
      $body = (string) wp_remote_retrieve_body($res);
      $contentType = (string) wp_remote_retrieve_header($res, 'content-type');
      $decoded = json_decode($body, true);

      $attempts[] = [
        'mode' => $mode,
        'http_code' => $code,
        'content_type' => $contentType,
        'json_ok' => is_array($decoded) ? 1 : 0,
        'body_snippet' => self::body_snippet($body),
      ];

      // Success: good JSON payload.
      if ($code === 200 && is_array($decoded)) {
        return ['res' => $res, 'attempts' => $attempts];
      }

      // If it looks like a WAF block, try the next mode.
      if (self::looks_like_waf_block($code, $contentType, $body)) {
        continue;
      }

      // If JSON mode failed for any reason, still try form mode once.
      if ($mode === 'json') {
        continue;
      }

      break;
    }

    return ['res' => $last, 'attempts' => $attempts];
  }

  private static function post_with_fallback_url_payloads(array $items): array {
    $allAttempts = [];
    $last = null;
    $usedUrl = '';

    foreach ($items as $item) {
      if (!is_array($item)) continue;
      $url = (string) ($item['url'] ?? '');
      if ($url === '') continue;
      $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
      $modes = is_array($item['modes'] ?? null) ? $item['modes'] : ['json', 'form'];
      $payloadKind = (string) ($item['payload_kind'] ?? '');

      $remote = self::post_with_fallback($url, $payload, $modes);
      $last = $remote['res'];
      $attempts = is_array($remote['attempts'] ?? null) ? $remote['attempts'] : [];

      foreach ($attempts as $a) {
        if (is_array($a)) {
          $a['url'] = $url;
          if ($payloadKind !== '') $a['payload_kind'] = $payloadKind;
          $allAttempts[] = $a;
        }
      }

      $usedUrl = $url;

      if (is_wp_error($last)) {
        continue;
      }

      $code = (int) wp_remote_retrieve_response_code($last);
      $body = (string) wp_remote_retrieve_body($last);
      $contentType = (string) wp_remote_retrieve_header($last, 'content-type');
      $decoded = json_decode($body, true);

      if ($code === 200 && is_array($decoded)) {
        // Backwards-compat: some older license servers won't understand packed payloads and respond
        // "Missing license key." even though we sent one. In that case, try the next item.
        $msg = (string) ($decoded['message'] ?? '');
        if ($payloadKind === 'packed_public' && preg_match('/missing license key/i', $msg)) {
          continue;
        }
        return ['res' => $last, 'attempts' => $allAttempts, 'used_url' => $usedUrl];
      }

      // Any failure: try the next URL (e.g. admin-ajax fallback).
      // These endpoints are designed to always return 200 JSON for application-level errors, so
      // non-200 responses typically mean infrastructure/security issues.
      continue;
    }

    return ['res' => $last, 'attempts' => $allAttempts, 'used_url' => $usedUrl];
  }

  private static function packed_payload_string(array $payload): string {
    $packed = self::public_packed_payload($payload);
    if (isset($packed['p']) && is_string($packed['p'])) return (string) $packed['p'];
    return '';
  }

  private static function get_with_header_payload(string $url, string $packedPayload): array {
    $attempts = [];
    $res = null;

    $args = [
      'timeout' => 12,
      'redirection' => 5,
      'sslverify' => true,
      'headers' => [
        'Accept' => 'application/json, */*;q=0.9',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'X-BP-Payload' => $packedPayload,
        'X-BookPoint' => defined('BP_Plugin::VERSION') ? (string) BP_Plugin::VERSION : 'unknown',
      ],
      'user-agent' => self::http_user_agent(),
    ];

    $res = wp_remote_get($url, (array) apply_filters('bp_license_http_args', $args, $url, ['p' => $packedPayload]));

    if (is_wp_error($res)) {
      $attempts[] = [
        'mode' => 'get_header',
        'wp_error_code' => $res->get_error_code(),
        'wp_error_message' => $res->get_error_message(),
        'url' => $url,
      ];
      return ['res' => $res, 'attempts' => $attempts];
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = (string) wp_remote_retrieve_body($res);
    $contentType = (string) wp_remote_retrieve_header($res, 'content-type');
    $decoded = json_decode($body, true);

    $attempts[] = [
      'mode' => 'get_header',
      'http_code' => $code,
      'content_type' => $contentType,
      'json_ok' => is_array($decoded) ? 1 : 0,
      'body_snippet' => self::body_snippet($body),
      'url' => $url,
    ];

    return ['res' => $res, 'attempts' => $attempts];
  }

  private static function get_with_header_payload_urls(array $urls, string $packedPayload): array {
    $allAttempts = [];
    $last = null;
    $usedUrl = '';

    foreach ($urls as $url) {
      $url = (string) $url;
      if ($url === '') continue;

      $remote = self::get_with_header_payload($url, $packedPayload);
      $last = $remote['res'] ?? null;
      $attempts = is_array($remote['attempts'] ?? null) ? $remote['attempts'] : [];
      $allAttempts = array_merge($allAttempts, $attempts);
      $usedUrl = $url;

      if (is_wp_error($last)) continue;

      $code = (int) wp_remote_retrieve_response_code($last);
      $body = (string) wp_remote_retrieve_body($last);
      $decoded = json_decode($body, true);
      if ($code === 200 && is_array($decoded)) {
        return ['res' => $last, 'attempts' => $allAttempts, 'used_url' => $usedUrl];
      }
    }

    return ['res' => $last, 'attempts' => $allAttempts, 'used_url' => $usedUrl];
  }

  public static function get_saved_api_base() : string {
    return (string) get_option(self::OPTION_API_BASE, '');
  }

  public static function set_saved_api_base(string $base) : void {
    $base = trim($base);
    if ($base === '') {
      delete_option(self::OPTION_API_BASE);
      delete_transient('bp_license_check_cache');
      return;
    }
    update_option(self::OPTION_API_BASE, $base, false);
    delete_transient('bp_license_check_cache');
  }

  public static function api_base() : string {
    $base = self::API_BASE_DEFAULT;

    $saved = self::get_saved_api_base();
    if (is_string($saved) && $saved !== '') {
      $base = $saved;
    }

    if (defined('BP_LICENSE_SERVER_BASE') && is_string(BP_LICENSE_SERVER_BASE) && BP_LICENSE_SERVER_BASE !== '') {
      $base = BP_LICENSE_SERVER_BASE;
    }
    $base = apply_filters('bp_license_server_base_url', $base);
    $base = untrailingslashit((string) $base);

    // If the license server plugin is installed on the same site, auto-target it.
    // This avoids the default placeholder host causing confusing "could not resolve host" errors.
    if ($base === untrailingslashit(self::API_BASE_DEFAULT) && class_exists('BP_License_Server')) {
      $base = untrailingslashit(home_url());
    }

    return $base;
  }

  public static function api_validate_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/validate';
  }

  public static function api_deactivate_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/deactivate';
  }

  public static function api_ping_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/ping';
  }

  public static function api_updates_url() : string {
    return self::api_base() . '/wp-json/bookpoint/v1/updates';
  }

  public static function api_ajax_validate_url() : string {
    return self::api_base() . '/wp-admin/admin-ajax.php?action=bp_ls_validate';
  }

  public static function api_ajax_deactivate_url() : string {
    return self::api_base() . '/wp-admin/admin-ajax.php?action=bp_ls_deactivate';
  }

  public static function api_public_validate_urls() : array {
    $base = self::api_base();
    return [
      $base . '/?bp_ls_public=validate',
      $base . '/bookpoint-license/validate',
    ];
  }

  public static function api_public_deactivate_urls() : array {
    $base = self::api_base();
    return [
      $base . '/?bp_ls_public=deactivate',
      $base . '/bookpoint-license/deactivate',
    ];
  }

  public static function api_public_ping_urls() : array {
    $base = self::api_base();
    return [
      $base . '/?bp_ls_public=ping',
      $base . '/bookpoint-license/ping',
    ];
  }

  public static function get_key() : string {
    return (string) get_option('bp_license_key', '');
  }

  public static function get_instance_id() : string {
    return (string) get_option('bp_license_instance_id', '');
  }

  public static function status() : string {
    return (string) get_option('bp_license_status', 'unset');
  }

  private static function ping_attempts(): array {
    $urls = array_merge([self::api_ping_url()], self::api_public_ping_urls());
    $attempts = [];

    foreach ($urls as $url) {
      $url = (string) $url;
      if ($url === '') continue;

      $args = [
        'timeout' => 10,
        'redirection' => 3,
        'sslverify' => true,
        'headers' => [
          'Accept' => 'application/json, */*;q=0.9',
          'Accept-Language' => 'en-US,en;q=0.9',
          'Cache-Control' => 'no-cache',
          'Pragma' => 'no-cache',
          'X-BookPoint' => defined('BP_Plugin::VERSION') ? (string) BP_Plugin::VERSION : 'unknown',
        ],
        'user-agent' => self::http_user_agent(),
      ];

      $res = wp_remote_get($url, (array) apply_filters('bp_license_http_args', $args, $url, []));
      if (is_wp_error($res)) {
        $attempts[] = [
          'mode' => 'ping',
          'url' => $url,
          'wp_error_code' => $res->get_error_code(),
          'wp_error_message' => $res->get_error_message(),
        ];
        continue;
      }

      $code = (int) wp_remote_retrieve_response_code($res);
      $body = (string) wp_remote_retrieve_body($res);
      $contentType = (string) wp_remote_retrieve_header($res, 'content-type');
      $decoded = json_decode($body, true);

      $attempts[] = [
        'mode' => 'ping',
        'url' => $url,
        'http_code' => $code,
        'content_type' => $contentType,
        'json_ok' => is_array($decoded) ? 1 : 0,
        'body_snippet' => self::body_snippet($body),
      ];

      if ($code === 200 && is_array($decoded)) {
        break;
      }
    }

    return $attempts;
  }

  public static function is_valid() : bool {
    return self::status() === 'valid';
  }

  public static function set_key(string $key) : void {
    update_option('bp_license_key', trim($key), false);
    update_option('bp_license_status', 'unset', false);
    update_option('bp_license_instance_id', '', false);
    update_option('bp_license_licensed_domain', '', false);
    update_option('bp_license_expires_at', '', false);
    update_option('bp_license_plan', '', false);
    delete_transient('bp_license_check_cache');
  }

  public static function activate() : array {
    // Server-side: /validate performs activation when a slot is available.
    return self::validate(true);
  }

  public static function deactivate() : array {
    $key = self::get_key();
    if ($key === '') {
      update_option('bp_license_status', 'unset', false);
      return ['ok' => false, 'status' => 'unset', 'message' => 'No key'];
    }

    $deactivate_url = self::api_deactivate_url();
    update_option('bp_license_last_request_url', $deactivate_url, false);
    update_option('bp_license_last_error', '', false);

    $payload = [
      'license_key' => $key,
      'site' => home_url(),
      'plugin' => 'bookpoint',
      'version' => defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : 'unknown',
      'instance_id' => self::get_instance_id(),
    ];

    $publicPayload = self::public_packed_payload($payload);
    $remote = self::post_with_fallback_url_payloads([
      ['url' => $deactivate_url, 'payload' => $payload],
      ['url' => self::api_ajax_deactivate_url(), 'payload' => $payload],
      ['url' => self::api_public_deactivate_urls()[0] ?? '', 'payload' => $publicPayload, 'modes' => ['form'], 'payload_kind' => 'packed_public'],
      ['url' => self::api_public_deactivate_urls()[0] ?? '', 'payload' => $payload, 'modes' => ['form'], 'payload_kind' => 'plain_public'],
      ['url' => self::api_public_deactivate_urls()[1] ?? '', 'payload' => $publicPayload, 'modes' => ['form'], 'payload_kind' => 'packed_public'],
      ['url' => self::api_public_deactivate_urls()[1] ?? '', 'payload' => $payload, 'modes' => ['form'], 'payload_kind' => 'plain_public'],
    ]);
    $res = $remote['res'];
    $attempts = is_array($remote['attempts'] ?? null) ? $remote['attempts'] : [];
    $usedUrl = (string)($remote['used_url'] ?? '');

    if (is_wp_error($res)) {
      $msg = $res->get_error_message();
      update_option('bp_license_last_error', $msg, false);
      $out = [
        'ok' => false,
        'status' => self::status(),
        'message' => $msg,
        'debug' => [
          'deactivate_url' => $deactivate_url,
          'api_base' => self::api_base(),
          'site' => home_url(),
          'wp_error_code' => $res->get_error_code(),
          'attempts' => $attempts,
        ],
      ];
      update_option('bp_license_data_json', wp_json_encode($out), false);
      return $out;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);
    if ($code !== 200 || !is_array($data)) {
      $contentType = (string) wp_remote_retrieve_header($res, 'content-type');
      $isBlocked = self::looks_like_waf_block($code, $contentType, (string) $body);

      // Extra fallback for some WAFs: try GET with packed payload in a header (no POST body).
      if ($isBlocked) {
        $pingAttempts = self::ping_attempts();
        $packed = self::packed_payload_string($payload);
        if ($packed !== '') {
          $getUrls = [
            $deactivate_url,
            self::api_ajax_deactivate_url(),
            self::api_public_deactivate_urls()[0] ?? '',
            self::api_public_deactivate_urls()[1] ?? '',
          ];
          $getRemote = self::get_with_header_payload_urls($getUrls, $packed);
          $getRes = $getRemote['res'] ?? null;
          $getAttempts = is_array($getRemote['attempts'] ?? null) ? $getRemote['attempts'] : [];
          $getUsed = (string) ($getRemote['used_url'] ?? '');

          if (!empty($getAttempts)) $attempts = array_merge($attempts, $getAttempts);
          if (!is_wp_error($getRes) && $getRes) {
            $gCode = (int) wp_remote_retrieve_response_code($getRes);
            $gBody = (string) wp_remote_retrieve_body($getRes);
            $gData = json_decode($gBody, true);
            if ($gCode === 200 && is_array($gData)) {
              $data = $gData;
              $res = $getRes;
              $usedUrl = $getUsed;
              $code = $gCode;
            }
          }
        }
      }
      $msg = $isBlocked ? 'Request blocked by license server firewall (WAF).' : 'Bad response';

      if (!$isBlocked && $code === 404 && is_array($data) && (($data['code'] ?? '') === 'rest_no_route')) {
        $base = self::api_base();
        $baseHost = '';
        $homeHost = '';
        if (function_exists('wp_parse_url')) {
          $baseHost = (string) (wp_parse_url($base, PHP_URL_HOST) ?? '');
          $homeHost = (string) (wp_parse_url(home_url(), PHP_URL_HOST) ?? '');
        } else {
          $baseHost = (string) (parse_url($base, PHP_URL_HOST) ?? '');
          $homeHost = (string) (parse_url(home_url(), PHP_URL_HOST) ?? '');
        }

        if ($baseHost !== '' && $homeHost !== '' && strcasecmp($baseHost, $homeHost) === 0 && !class_exists('BP_License_Server')) {
          $msg = 'License server URL is set to this site. Set it to your store domain (the site running BookPoint License Server).';
        } else {
          $msg = 'License server route not found. Make sure the BookPoint License Server plugin is installed and active on the server URL.';
        }
      }

      $out = [
        'ok' => false,
        'status' => self::status(),
        'message' => $msg,
        'debug' => [
          'deactivate_url' => $deactivate_url,
          'used_url' => $usedUrl,
          'api_base' => self::api_base(),
          'site' => home_url(),
          'http_code' => $code,
          'content_type' => $contentType,
          'body_snippet' => self::body_snippet((string) $body),
          'attempts' => $attempts,
          'ping_attempts' => $isBlocked ? ($pingAttempts ?? []) : [],
        ],
      ];
      update_option('bp_license_last_error', (string) $out['message'], false);
      update_option('bp_license_data_json', wp_json_encode($out), false);
      return $out;
    }

    // Local state: mark unset so user must activate again.
    update_option('bp_license_status', 'unset', false);
    delete_transient('bp_license_check_cache');

    return [
      'ok' => (bool)($data['ok'] ?? false),
      'status' => sanitize_text_field((string)($data['status'] ?? 'deactivated')),
      'message' => sanitize_text_field((string)($data['message'] ?? '')),
      'raw' => $data,
    ];
  }

  public static function validate(bool $force = false) : array {
    $key = self::get_key();
    if ($key === '') {
      update_option('bp_license_status', 'unset', false);
      return ['ok' => false, 'status' => 'unset', 'message' => 'No key'];
    }

    if (!$force) {
      $cached = get_transient('bp_license_check_cache');
      if (is_array($cached)) return $cached;
    }

    $validate_url = self::api_validate_url();
    update_option('bp_license_last_request_url', $validate_url, false);
    update_option('bp_license_last_error', '', false);

    $payload = [
      'license_key' => $key,
      'site' => home_url(),
      'plugin' => 'bookpoint',
      'version' => defined('BP_Plugin::VERSION') ? BP_Plugin::VERSION : 'unknown',
      'instance_id' => self::get_instance_id(),
    ];

    $publicPayload = self::public_packed_payload($payload);
    $remote = self::post_with_fallback_url_payloads([
      ['url' => $validate_url, 'payload' => $payload],
      ['url' => self::api_ajax_validate_url(), 'payload' => $payload],
      ['url' => self::api_public_validate_urls()[0] ?? '', 'payload' => $publicPayload, 'modes' => ['form'], 'payload_kind' => 'packed_public'],
      ['url' => self::api_public_validate_urls()[0] ?? '', 'payload' => $payload, 'modes' => ['form'], 'payload_kind' => 'plain_public'],
      ['url' => self::api_public_validate_urls()[1] ?? '', 'payload' => $publicPayload, 'modes' => ['form'], 'payload_kind' => 'packed_public'],
      ['url' => self::api_public_validate_urls()[1] ?? '', 'payload' => $payload, 'modes' => ['form'], 'payload_kind' => 'plain_public'],
    ]);
    $res = $remote['res'];
    $attempts = is_array($remote['attempts'] ?? null) ? $remote['attempts'] : [];
    $usedUrl = (string)($remote['used_url'] ?? '');

    if (is_wp_error($res)) {
      $msg = $res->get_error_message();
      update_option('bp_license_last_error', $msg, false);
      $out = [
        'ok' => false,
        'status' => self::status(),
        'message' => $msg,
        'debug' => [
          'validate_url' => $validate_url,
          'api_base' => self::api_base(),
          'site' => home_url(),
          'wp_error_code' => $res->get_error_code(),
          'attempts' => $attempts,
        ],
      ];
      update_option('bp_license_data_json', wp_json_encode($out), false);
      set_transient('bp_license_check_cache', $out, 10 * MINUTE_IN_SECONDS);
      return $out;
    }

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $data = json_decode($body, true);

    if ($code !== 200 || !is_array($data)) {
      $contentType = (string) wp_remote_retrieve_header($res, 'content-type');
      $isBlocked = self::looks_like_waf_block($code, $contentType, (string) $body);

      if ($isBlocked) {
        $pingAttempts = self::ping_attempts();
        $packed = self::packed_payload_string($payload);
        if ($packed !== '') {
          $getUrls = [
            $validate_url,
            self::api_ajax_validate_url(),
            self::api_public_validate_urls()[0] ?? '',
            self::api_public_validate_urls()[1] ?? '',
          ];
          $getRemote = self::get_with_header_payload_urls($getUrls, $packed);
          $getRes = $getRemote['res'] ?? null;
          $getAttempts = is_array($getRemote['attempts'] ?? null) ? $getRemote['attempts'] : [];
          $getUsed = (string) ($getRemote['used_url'] ?? '');

          if (!empty($getAttempts)) $attempts = array_merge($attempts, $getAttempts);
          if (!is_wp_error($getRes) && $getRes) {
            $gCode = (int) wp_remote_retrieve_response_code($getRes);
            $gBody = (string) wp_remote_retrieve_body($getRes);
            $gData = json_decode($gBody, true);
            if ($gCode === 200 && is_array($gData)) {
              $data = $gData;
              $res = $getRes;
              $usedUrl = $getUsed;
              $code = $gCode;
            }
          }
        }
      }
      $msg = $isBlocked ? 'Request blocked by license server firewall (WAF).' : 'Bad response';

      // Helpful hint when the server URL is accidentally set to the current site.
      if (!$isBlocked && $code === 404 && is_array($data) && (($data['code'] ?? '') === 'rest_no_route')) {
        $base = self::api_base();
        $baseHost = '';
        $homeHost = '';
        if (function_exists('wp_parse_url')) {
          $baseHost = (string) (wp_parse_url($base, PHP_URL_HOST) ?? '');
          $homeHost = (string) (wp_parse_url(home_url(), PHP_URL_HOST) ?? '');
        } else {
          $baseHost = (string) (parse_url($base, PHP_URL_HOST) ?? '');
          $homeHost = (string) (parse_url(home_url(), PHP_URL_HOST) ?? '');
        }

        if ($baseHost !== '' && $homeHost !== '' && strcasecmp($baseHost, $homeHost) === 0 && !class_exists('BP_License_Server')) {
          $msg = 'License server URL is set to this site. Set it to your store domain (the site running BookPoint License Server).';
        } else {
          $msg = 'License server route not found. Make sure the BookPoint License Server plugin is installed and active on the server URL.';
        }
      }

      $out = [
        'ok' => false,
        'status' => self::status(),
        'message' => $msg,
        'debug' => [
          'validate_url' => $validate_url,
          'used_url' => $usedUrl,
          'api_base' => self::api_base(),
          'site' => home_url(),
          'http_code' => $code,
          'content_type' => $contentType,
          'body_snippet' => self::body_snippet((string) $body),
          'attempts' => $attempts,
          'ping_attempts' => $isBlocked ? ($pingAttempts ?? []) : [],
        ],
      ];
      update_option('bp_license_last_error', (string) $out['message'], false);
      update_option('bp_license_data_json', wp_json_encode($out), false);
      set_transient('bp_license_check_cache', $out, 10 * MINUTE_IN_SECONDS);
      return $out;
    }

    $status = strtolower(sanitize_text_field($data['status'] ?? 'invalid'));
    $message = sanitize_text_field($data['message'] ?? '');

    update_option('bp_license_status', $status, false);
    update_option('bp_license_checked_at', time(), false);
    update_option('bp_license_last_error', $message, false);
    update_option('bp_license_data_json', wp_json_encode($data), false);

    // Optional metadata if the license server provides it.
    $plan = sanitize_text_field($data['plan'] ?? '');
    $expires_at = sanitize_text_field($data['expires_at'] ?? ($data['expires'] ?? ''));
    $licensed_domain = sanitize_text_field($data['licensed_domain'] ?? ($data['domain'] ?? ''));
    $instance_id = sanitize_text_field($data['instance_id'] ?? '');
    update_option('bp_license_plan', $plan, false);
    update_option('bp_license_expires_at', $expires_at, false);
    update_option('bp_license_licensed_domain', $licensed_domain, false);
    update_option('bp_license_instance_id', $instance_id, false);

    $out = ['ok' => ($status === 'valid'), 'status' => $status, 'message' => $message, 'raw' => $data];
    set_transient('bp_license_check_cache', $out, 6 * HOUR_IN_SECONDS);

    return $out;
  }

  public static function maybe_cron_validate() : void {
    self::validate(false);
  }
}
