<?php
defined('ABSPATH') || exit;

final class BP_RelationsHelper {

  public static function service_categories_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_service_categories';
  }

  public static function extra_services_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'bp_extra_services';
  }

  public static function migrate_legacy_relations(): array {
    global $wpdb;

    $t_services = $wpdb->prefix . 'bp_services';
    $t_extras   = $wpdb->prefix . 'bp_service_extras';
    $map_sc     = self::service_categories_table();
    $map_es     = self::extra_services_table();

    $done_sc = 0;
    $done_es = 0;

    $rows = $wpdb->get_results("SELECT id, category_id FROM {$t_services} WHERE category_id IS NOT NULL AND category_id > 0", ARRAY_A);
    foreach ($rows as $r) {
      $sid = (int)$r['id'];
      $cid = (int)$r['category_id'];
      if ($sid <= 0 || $cid <= 0) continue;

      $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$map_sc} (service_id, category_id) VALUES (%d, %d)",
        $sid, $cid
      ));
      $done_sc++;
    }

    $rows2 = $wpdb->get_results("SELECT id, service_id FROM {$t_extras} WHERE service_id IS NOT NULL AND service_id > 0", ARRAY_A);
    foreach ($rows2 as $r) {
      $eid = (int)$r['id'];
      $sid = (int)$r['service_id'];
      if ($eid <= 0 || $sid <= 0) continue;

      $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$map_es} (extra_id, service_id) VALUES (%d, %d)",
        $eid, $sid
      ));
      $done_es++;
    }

    return [
      'services_migrated' => $done_sc,
      'extras_migrated' => $done_es,
    ];
  }

  public static function sync_relations(bool $sync_legacy_columns = true): array {
    global $wpdb;

    $t_services = $wpdb->prefix . 'bp_services';
    $t_extras   = $wpdb->prefix . 'bp_service_extras';
    $map_sc     = self::service_categories_table();
    $map_es     = self::extra_services_table();

    $added_sc = 0;
    $added_es = 0;
    $updated_services_legacy = 0;
    $updated_extras_legacy = 0;

    $rows = $wpdb->get_results("
      SELECT s.id, s.category_id
      FROM {$t_services} s
      LEFT JOIN {$map_sc} m ON m.service_id = s.id
      WHERE s.category_id IS NOT NULL AND s.category_id > 0
      GROUP BY s.id
      HAVING COUNT(m.id) = 0
    ", ARRAY_A);

    foreach ($rows as $r) {
      $sid = (int)$r['id'];
      $cid = (int)$r['category_id'];
      if ($sid <= 0 || $cid <= 0) continue;

      $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$map_sc} (service_id, category_id) VALUES (%d, %d)",
        $sid, $cid
      ));
      $added_sc++;
    }

    $rows2 = $wpdb->get_results("
      SELECT e.id, e.service_id
      FROM {$t_extras} e
      LEFT JOIN {$map_es} m ON m.extra_id = e.id
      WHERE e.service_id IS NOT NULL AND e.service_id > 0
      GROUP BY e.id
      HAVING COUNT(m.id) = 0
    ", ARRAY_A);

    foreach ($rows2 as $r) {
      $eid = (int)$r['id'];
      $sid = (int)$r['service_id'];
      if ($eid <= 0 || $sid <= 0) continue;

      $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$map_es} (extra_id, service_id) VALUES (%d, %d)",
        $eid, $sid
      ));
      $added_es++;
    }

    if ($sync_legacy_columns) {
      $service_ids = $wpdb->get_col("SELECT DISTINCT service_id FROM {$map_sc}");
      foreach ($service_ids as $sid_raw) {
        $sid = (int)$sid_raw;
        $cid = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT category_id FROM {$map_sc} WHERE service_id=%d ORDER BY category_id ASC LIMIT 1",
          $sid
        ));
        if ($sid > 0) {
          $wpdb->update($t_services, ['category_id' => $cid], ['id' => $sid], ['%d'], ['%d']);
          $updated_services_legacy++;
        }
      }

      $extra_ids = $wpdb->get_col("SELECT DISTINCT extra_id FROM {$map_es}");
      foreach ($extra_ids as $eid_raw) {
        $eid = (int)$eid_raw;
        $sid = (int)$wpdb->get_var($wpdb->prepare(
          "SELECT service_id FROM {$map_es} WHERE extra_id=%d ORDER BY service_id ASC LIMIT 1",
          $eid
        ));
        if ($eid > 0) {
          $wpdb->update($t_extras, ['service_id' => $sid], ['id' => $eid], ['%d'], ['%d']);
          $updated_extras_legacy++;
        }
      }
    }

    return [
      'added_service_category_mappings' => $added_sc,
      'added_extra_service_mappings' => $added_es,
      'updated_services_legacy_column' => $updated_services_legacy,
      'updated_extras_legacy_column' => $updated_extras_legacy,
    ];
  }
}
