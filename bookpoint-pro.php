<?php
defined('ABSPATH') || exit;

// This file is only included in the "Pro" distribution ZIP.
// It enables license gating for Pro builds.
if (!defined('BP_IS_PRO')) {
  define('BP_IS_PRO', true);
}

