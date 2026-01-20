<?php
defined('ABSPATH') || exit;

final class BP_DatabaseHelper {

  const DB_VERSION_OPTION = 'BP_db_version';

  public static function install_or_update(string $target_version) : void {
    $installed_version = get_option(self::DB_VERSION_OPTION, '');

    if ($installed_version !== $target_version) {
      BP_MigrationsHelper::create_tables();
      update_option(self::DB_VERSION_OPTION, $target_version, false);
    }
  }
}

