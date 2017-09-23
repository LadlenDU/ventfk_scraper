<?php

error_reporting(E_ALL ^ E_WARNING ^ E_DEPRECATED);
ini_set('display_errors', 1);

set_time_limit(3600 * 15);

define('EMAIL', 'TwilightTower@mail.ru');
define('RESULT_LOG_FILE', __DIR__ . '/logs/log.log');
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');

$VENDOR_CODE_OFFSET = array(7, 3, 8, 1, 4, 7, 2, 6, 9, 6);

define('PAGES_LIMIT', 999999);

// in seconds
define('SLEEP_BEFORE_PUT_PRODUCT', 3);
