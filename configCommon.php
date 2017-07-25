<?php

error_reporting(E_ALL ^ E_WARNING);
ini_set('display_errors', 1);

set_time_limit(3600 * 5);

define('EMAIL', 'TwilightTower@mail.ru');
define('RESULT_LOG_FILE', __DIR__ . '/logs/log.log');
define('ERROR_LOG_FILE', __DIR__ . '/logs/error.log');

define('PAGES_LIMIT', 2);
