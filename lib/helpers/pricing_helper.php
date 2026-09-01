<?php
defined('ABSPATH') || exit;

/**
 * Computes the authoritative price for a booking server-side from the service's
 * own price, the extras actually linked to that service, and (if supplied) a
 * validated promo code. Never trusts client-submitted totals/discounts.
 *
 * @param array $payload Raw booking payload (service_id, extras/extra_ids, promo_code).
 * @return array|WP_Error {subtotal, discount, total, currency, promo_id, promo_code}
 */
function pointlybooking_compute_authoritative_pricing(array $payload) {
  $service_id = (int)($payload['service_id'] ?? 0);
  if ($service_id <= 0) {
    return new WP_Error('missing_service', 'Missing service_id', ['status' => 400]);
  }

  $service = POINTLYBOOKING_ServiceModel::find($service_id);
  if (!$service) {
    return new WP_Error('service_not_found', 'Service not found', ['status' => 404]);
  }

  $currency = strtoupper((string)($service['currency'] ?? 'USD'));
  if (!preg_match('/^[A-Z]{3}$/', $currency)) {
    $currency = 'USD';
  }

  $base = ((int)($service['price_cents'] ?? 0)) / 100;

  $submitted_extra_ids = $payload['extras'] ?? ($payload['extra_ids'] ?? []);
  if (!is_array($submitted_extra_ids)) $submitted_extra_ids = [];
  $submitted_extra_ids = array_values(array_unique(array_filter(array_map('intval', $submitted_extra_ids))));

  $extras_total = 0.0;
  if ($submitted_extra_ids && class_exists('POINTLYBOOKING_ServiceExtraModel')) {
    $valid_extras = POINTLYBOOKING_ServiceExtraModel::by_service($service_id, true);
    $price_by_id = [];
    foreach ($valid_extras as $row) {
      $price_by_id[(int)$row['id']] = (float)($row['price'] ?? 0);
    }
    foreach ($submitted_extra_ids as $extra_id) {
      if (isset($price_by_id[$extra_id])) {
        $extras_total += $price_by_id[$extra_id];
      }
    }
  }

  $subtotal = round($base + $extras_total, 2);

  $promo_code = strtoupper(trim((string)($payload['promo_code'] ?? '')));
  $discount = 0.0;
  $promo_id = 0;

  if ($promo_code !== '' && class_exists('POINTLYBOOKING_PromoCodeModel')) {
    $promo = POINTLYBOOKING_PromoCodeModel::find_by_code($promo_code);
    if (!$promo || (int)($promo['is_active'] ?? 0) !== 1) {
      return new WP_Error('invalid_promo_code', 'This promo code is not valid.', ['status' => 400]);
    }

    $now = current_time('mysql');
    if (!empty($promo['starts_at']) && $now < $promo['starts_at']) {
      return new WP_Error('invalid_promo_code', 'This promo code is not active yet.', ['status' => 400]);
    }
    if (!empty($promo['ends_at']) && $now > $promo['ends_at']) {
      return new WP_Error('invalid_promo_code', 'This promo code has expired.', ['status' => 400]);
    }
    if ($promo['min_total'] !== null && $promo['min_total'] !== '' && $subtotal < (float)$promo['min_total']) {
      return new WP_Error('invalid_promo_code', 'This promo code requires a higher order total.', ['status' => 400]);
    }
    $max_uses = ($promo['max_uses'] !== null && $promo['max_uses'] !== '') ? (int)$promo['max_uses'] : 0;
    if ($max_uses > 0 && (int)($promo['uses_count'] ?? 0) >= $max_uses) {
      return new WP_Error('invalid_promo_code', 'This promo code has reached its usage limit.', ['status' => 400]);
    }

    $type = ($promo['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
    $amount = (float)($promo['amount'] ?? 0);
    $discount = $type === 'fixed' ? $amount : ($subtotal * $amount / 100);
    $discount = max(0.0, min($discount, $subtotal));
    $promo_id = (int)$promo['id'];
  }

  $total = max(0.0, round($subtotal - $discount, 2));

  return [
    'subtotal' => $subtotal,
    'discount' => round($discount, 2),
    'total' => $total,
    'currency' => $currency,
    'promo_id' => $promo_id,
    'promo_code' => $promo_id ? $promo_code : '',
  ];
}
