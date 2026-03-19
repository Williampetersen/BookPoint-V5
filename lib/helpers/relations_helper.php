<?php
defined('ABSPATH') || exit;
// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This file's wpdb SQL paths interpolate only hardcoded plugin table names with a sanitized WordPress prefix; dynamic values remain prepared or static by design.

final class POINTLYBOOKING_RelationsHelper {
  private static function is_safe_sql_identifier(string $identifier): bool {
    return preg_match('/^[A-Za-z0-9_]+$/', $identifier) === 1;
  }

  public static function service_categories_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_service_categories';
  }

  public static function extra_services_table(): string {
    global $wpdb;
    return $wpdb->prefix . 'pointlybooking_extra_services';
  }

  public static function migrate_legacy_relations(): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;

    $services_table = $wpdb->prefix . 'pointlybooking_services';
    $extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    $service_categories_map_table = $wpdb->prefix . 'pointlybooking_service_categories';
    $extra_services_map_table = $wpdb->prefix . 'pointlybooking_extra_services';
    if (
      !self::is_safe_sql_identifier($services_table) ||
      !self::is_safe_sql_identifier($extras_table) ||
      !self::is_safe_sql_identifier($service_categories_map_table) ||
      !self::is_safe_sql_identifier($extra_services_map_table)
    ) {
      return [
        'services_migrated' => 0,
        'extras_migrated' => 0,
      ];
    }

    $done_sc = 0;
    $done_es = 0;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      "SELECT id, category_id FROM {$services_table} WHERE category_id IS NOT NULL AND category_id > 0",
      ARRAY_A
    );
    foreach ($rows as $r) {
      $sid = (int)$r['id'];
      $cid = (int)$r['category_id'];
      if ($sid <= 0 || $cid <= 0) continue;

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->query(
        $wpdb->prepare(
          "INSERT IGNORE INTO {$service_categories_map_table} (service_id, category_id) VALUES (%d, %d)",
          $sid,
          $cid
        )
      );
      $done_sc++;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows2 = $wpdb->get_results(
      "SELECT id, service_id FROM {$extras_table} WHERE service_id IS NOT NULL AND service_id > 0",
      ARRAY_A
    );
    foreach ($rows2 as $r) {
      $eid = (int)$r['id'];
      $sid = (int)$r['service_id'];
      if ($eid <= 0 || $sid <= 0) continue;

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->query(
        $wpdb->prepare(
          "INSERT IGNORE INTO {$extra_services_map_table} (extra_id, service_id) VALUES (%d, %d)",
          $eid,
          $sid
        )
      );
      $done_es++;
    }

    return [
      'services_migrated' => $done_sc,
      'extras_migrated' => $done_es,
    ];
  }

  public static function sync_relations(bool $sync_legacy_columns = true): array {
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names in this function are validated local plugin table names built from hardcoded plugin suffixes.
    global $wpdb;

    $services_table = $wpdb->prefix . 'pointlybooking_services';
    $extras_table = $wpdb->prefix . 'pointlybooking_service_extras';
    $service_categories_map_table = $wpdb->prefix . 'pointlybooking_service_categories';
    $extra_services_map_table = $wpdb->prefix . 'pointlybooking_extra_services';
    if (
      !self::is_safe_sql_identifier($services_table) ||
      !self::is_safe_sql_identifier($extras_table) ||
      !self::is_safe_sql_identifier($service_categories_map_table) ||
      !self::is_safe_sql_identifier($extra_services_map_table)
    ) {
      return [
        'added_service_category_mappings' => 0,
        'added_extra_service_mappings' => 0,
        'updated_services_legacy_column' => 0,
        'updated_extras_legacy_column' => 0,
      ];
    }

    $added_sc = 0;
    $added_es = 0;
    $updated_services_legacy = 0;
    $updated_extras_legacy = 0;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows = $wpdb->get_results(
      "SELECT s.id, s.category_id
      FROM {$services_table} s
      LEFT JOIN {$service_categories_map_table} m ON m.service_id = s.id
      WHERE s.category_id IS NOT NULL AND s.category_id > 0
      GROUP BY s.id
      HAVING COUNT(m.id) = 0",
      ARRAY_A
    );

    foreach ($rows as $r) {
      $sid = (int)$r['id'];
      $cid = (int)$r['category_id'];
      if ($sid <= 0 || $cid <= 0) continue;

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->query(
        $wpdb->prepare(
          "INSERT IGNORE INTO {$service_categories_map_table} (service_id, category_id) VALUES (%d, %d)",
          $sid,
          $cid
        )
      );
      $added_sc++;
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
    $rows2 = $wpdb->get_results(
      "SELECT e.id, e.service_id
      FROM {$extras_table} e
      LEFT JOIN {$extra_services_map_table} m ON m.extra_id = e.id
      WHERE e.service_id IS NOT NULL AND e.service_id > 0
      GROUP BY e.id
      HAVING COUNT(m.id) = 0",
      ARRAY_A
    );

    foreach ($rows2 as $r) {
      $eid = (int)$r['id'];
      $sid = (int)$r['service_id'];
      if ($eid <= 0 || $sid <= 0) continue;

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $wpdb->query(
        $wpdb->prepare(
          "INSERT IGNORE INTO {$extra_services_map_table} (extra_id, service_id) VALUES (%d, %d)",
          $eid,
          $sid
        )
      );
      $added_es++;
    }

    if ($sync_legacy_columns) {
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $service_ids = $wpdb->get_col(
        "SELECT DISTINCT service_id FROM {$service_categories_map_table}"
      );
      foreach ($service_ids as $sid_raw) {
        $sid = (int)$sid_raw;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
        $cid = (int)$wpdb->get_var(
          $wpdb->prepare(
            "SELECT category_id FROM {$service_categories_map_table} WHERE service_id=%d ORDER BY category_id ASC LIMIT 1",
            $sid
          )
        );
        if ($sid > 0) {
          // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
          $wpdb->update($services_table, ['category_id' => $cid], ['id' => $sid], ['%d'], ['%d']);
          $updated_services_legacy++;
        }
      }

      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
      $extra_ids = $wpdb->get_col(
        "SELECT DISTINCT extra_id FROM {$extra_services_map_table}"
      );
      foreach ($extra_ids as $eid_raw) {
        $eid = (int)$eid_raw;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
        $sid = (int)$wpdb->get_var(
          $wpdb->prepare(
            "SELECT service_id FROM {$extra_services_map_table} WHERE extra_id=%d ORDER BY service_id ASC LIMIT 1",
            $eid
          )
        );
        if ($eid > 0) {
          // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database access is intentional here; result freshness or surrounding logic makes local persistent caching inappropriate for this path.
          $wpdb->update($extras_table, ['service_id' => $sid], ['id' => $eid], ['%d'], ['%d']);
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
