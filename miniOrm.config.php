<?php

/*
 * miniOrm
 * Version: 1.2.0
 * Copyright : Cédric Mouleyre / @MrManchot
 */

### Required fields

define('_MO_DB_NAME_', 'mini_orm');
define('_MO_DB_LOGIN_', 'root');
define('_MO_DB_MDP_', 'emoxa');
define('_MO_DB_SERVER_', 'localhost');

### Optional fields

# Prefix for database : if your tables are like 'mo_xxxxxx', you should define as '_MO_DB_PREFIX_', 'xxxxxx'.
# Then you can use new Obj('xxxxxx');
define('_MO_DB_PREFIX_', 'mo_');

# Freeze option permitt to add to cache your database configuration.
# Once activated, you can't access to new table dynamically : just active it in production.
define('_MO_FREEZE_', false);
define('_MO_CACHE_FILE_', 'miniOrm.tmp');
define('_MO_CACHE_DIR_', '');

