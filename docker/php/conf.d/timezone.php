<?php
/**
 * Auto-prepend file for timezone initialization.
 *
 * Sets PHP timezone from TZ environment variable.
 * This file is loaded automatically before every PHP script via auto_prepend_file.
 */

$tz = getenv('TZ');
if ($tz !== false && $tz !== '') {
    date_default_timezone_set($tz);
}
