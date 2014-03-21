<?php

### Required fields

define('_MO_DB_NAME_', '');
define('_MO_DB_LOGIN_', 'root');
define('_MO_DB_MDP_', '');
define('_MO_DB_SERVER_', 'localhost');

### Optional fields

define('_MO_CACHE_DIR_', '/cache/');
define('_MO_CLASS_DIR_', '/class/');

# Display MySQL error
define('_MO_DEBUG_', true);

# Freeze option permitt to add to cache your database configuration.
# Once activated, you can't access to new table dynamically : just active it in production.
define('_MO_FREEZE_', false);
