<?php
defined('ABSPATH') || exit;

final class BP_TrialHelper {

  const OPTION_TRIAL_ACCEPTED = 'bp_trial_accepted';
  const OPTION_TRIAL_STARTED_AT = 'bp_trial_started_at';
  const OPTION_WIZARD_DONE_AT = 'bp_setup_wizard_done_at';

  const TRIAL_DAYS_DEFAULT = 14;

  public static function trial_days(): int {
    $days = (int) apply_filters('bp_trial_days', self::TRIAL_DAYS_DEFAULT);
    return max(1, $days);
  }

  public static function is_wizard_done(): bool {
    return (int) get_option(self::OPTION_WIZARD_DONE_AT, 0) > 0;
  }

  public static function mark_wizard_done(): void {
    update_option(self::OPTION_WIZARD_DONE_AT, time(), false);
  }

  public static function is_trial_accepted(): bool {
    return (int) get_option(self::OPTION_TRIAL_ACCEPTED, 0) === 1 && self::trial_started_at() > 0;
  }

  public static function trial_started_at(): int {
    return (int) get_option(self::OPTION_TRIAL_STARTED_AT, 0);
  }

  public static function trial_ends_at(): int {
    $start = self::trial_started_at();
    if ($start <= 0) return 0;
    return $start + (self::trial_days() * DAY_IN_SECONDS);
  }

  public static function is_trial_active(): bool {
    if (!self::is_trial_accepted()) return false;
    $end = self::trial_ends_at();
    if ($end <= 0) return false;
    return time() < $end;
  }

  public static function is_trial_expired(): bool {
    return self::is_trial_accepted() && !self::is_trial_active();
  }

  public static function days_left(): int {
    if (!self::is_trial_active()) return 0;
    $sec = self::trial_ends_at() - time();
    return (int) max(0, (int) ceil($sec / DAY_IN_SECONDS));
  }

  public static function start_trial(): void {
    if (self::is_trial_accepted()) return;
    update_option(self::OPTION_TRIAL_ACCEPTED, 1, false);
    update_option(self::OPTION_TRIAL_STARTED_AT, time(), false);
    self::mark_wizard_done();
  }
}

