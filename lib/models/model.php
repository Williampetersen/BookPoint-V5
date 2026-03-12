<?php
defined('ABSPATH') || exit;

abstract class POINTLYBOOKING_Model {
  abstract public static function table() : string;

  protected static function now_mysql() : string {
    return current_time('mysql'); // WP timezone; later we'll switch to UTC
  }
}

