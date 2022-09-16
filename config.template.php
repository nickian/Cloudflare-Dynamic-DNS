<?php
define('APP_PATH', __DIR__.DIRECTORY_SEPARATOR);
define('DATA_FILE', __DIR__.DIRECTORY_SEPARATOR.'/data.json');
define('IP_SHELL_CMD', 'dig +short myip.opendns.com @resolver1.opendns.com');
define('BASE_URL', 'https://api.cloudflare.com/client/v4/');
define('TOKEN', '');

// Email notification settings
define('EMAIL_NOTIFICATIONS', false);
define('EMAIL_TO', '');
define('SMTP_HOST', '');
define('SMTP_PORT', 465);
define('SMTP_USER', '');
define('SMTP_PASSWORD', '');
define('EMAIL_FROM_ADDRESS', '');
define('EMAIL_FROM_NAME', '');
define('HTML_EMAIL', true); // Set to false for plain text emails