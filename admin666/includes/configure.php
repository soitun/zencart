<?php
/**
 * @package Configuration Settings circa 1.5.3
 * @copyright Copyright 2003-2014 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * File Built by zc_install on 2014-08-14 05:24:44
 */


/*************** NOTE: This file is similar, but DIFFERENT from the "store" version of configure.php. ***********/
/***************       The 2 files should be kept separate and not used to overwrite each other.      ***********/

/**
 * WE RECOMMEND THAT YOU USE SSL PROTECTION FOR YOUR ENTIRE ADMIN:
 * To do that, make sure you use a "https:" URL for BOTH the HTTP_SERVER and HTTPS_SERVER entries:
 */
  define('HTTP_SERVER', 'http://localhost');
  define('HTTPS_SERVER', 'https://localhost');
  define('HTTP_CATALOG_SERVER', 'http://localhost');
  define('HTTPS_CATALOG_SERVER', 'https://localhost');

  // secure webserver for admin?  Valid choices are 'true' or 'false' (including quotes).
  define('ENABLE_SSL_ADMIN', 'false');

  // secure webserver for storefront?  Valid choices are 'true' or 'false' (including quotes).
  define('ENABLE_SSL_CATALOG', 'false');

  define('DIR_WS_ADMIN', preg_replace('#^' . str_replace('-', '\-', zen_parse_url(HTTP_SERVER, '/path')) . '#', '', dirname($_SERVER['SCRIPT_NAME'])) . '/');
  define('DIR_WS_CATALOG', '/zencart/');
  define('DIR_WS_HTTPS_ADMIN', preg_replace('#^' . str_replace('-', '\-', zen_parse_url(HTTPS_SERVER, '/path')) . '#', '', dirname($_SERVER['SCRIPT_NAME'])) . '/');
  define('DIR_WS_HTTPS_CATALOG', '/zencart/');

// NOTE: be sure to leave the trailing '/' at the end of these lines if you make changes!
// * DIR_WS_* = Webserver directories (virtual/URL)
  // these paths are relative to top of your webspace ... (ie: under the public_html or httpdocs folder)
  define('DIR_WS_IMAGES', 'images/');
  define('DIR_WS_ICONS', DIR_WS_IMAGES . 'icons/');
  define('DIR_WS_CATALOG_IMAGES', HTTP_CATALOG_SERVER . DIR_WS_CATALOG . 'images/');
  define('DIR_WS_CATALOG_TEMPLATE', HTTP_CATALOG_SERVER . DIR_WS_CATALOG . 'includes/templates/');
  define('DIR_WS_INCLUDES', 'includes/');
  define('DIR_WS_BOXES', DIR_WS_INCLUDES . 'boxes/');
  define('DIR_WS_FUNCTIONS', DIR_WS_INCLUDES . 'functions/');
  define('DIR_WS_CLASSES', DIR_WS_INCLUDES . 'classes/');
  define('DIR_WS_MODULES', DIR_WS_INCLUDES . 'modules/');
  define('DIR_WS_LANGUAGES', DIR_WS_INCLUDES . 'languages/');
  define('DIR_WS_CATALOG_LANGUAGES', HTTP_CATALOG_SERVER . DIR_WS_CATALOG . 'includes/languages/');

// * DIR_FS_* = Filesystem directories (local/physical)
  define('DIR_FS_ADMIN', preg_replace('#/includes/$#', '/', realpath(dirname(__FILE__) . '/../') . '/'));
  //the following path is a COMPLETE path to your Zen Cart files. eg: /var/www/vhost/accountname/public_html/store/
  define('DIR_FS_CATALOG', '/var/www/html/zencart/');

  //the following path is a COMPLETE path to the /logs/ folder  eg: /var/www/vhost/accountname/public_html/store/logs ... and no trailing slash
  define('DIR_FS_LOGS', '/var/www/html/zencart/logs');

  define('DIR_FS_CATALOG_LANGUAGES', DIR_FS_CATALOG . 'includes/languages/');
  define('DIR_FS_CATALOG_IMAGES', DIR_FS_CATALOG . 'images/');
  define('DIR_FS_CATALOG_MODULES', DIR_FS_CATALOG . 'includes/modules/');
  define('DIR_FS_CATALOG_TEMPLATES', DIR_FS_CATALOG . 'includes/templates/');
  define('DIR_FS_BACKUP', DIR_FS_ADMIN . 'backups/');
  define('DIR_FS_EMAIL_TEMPLATES', DIR_FS_CATALOG . 'email/');
  define('DIR_FS_DOWNLOAD', DIR_FS_CATALOG . 'download/');

// define our database connection
  define('DB_TYPE', 'mysql');
  define('DB_PREFIX', '');
  define('DB_CHARSET', 'utf8');
  define('DB_SERVER', 'localhost');
  define('DB_SERVER_USERNAME', 'user');
  define('DB_SERVER_PASSWORD', 'user');
  define('DB_DATABASE', 'zencart');

  // The next 2 "defines" are for SQL cache support.
  // For SQL_CACHE_METHOD, you can select from:  none, database, or file
  // If you choose "file", then you need to set the DIR_FS_SQL_CACHE to a directory where your apache 
  // or webserver user has write privileges (chmod 666 or 777). We recommend using the "cache" folder inside the Zen Cart folder
  // ie: /path/to/your/webspace/public_html/zen/cache   -- leave no trailing slash  
  define('SQL_CACHE_METHOD', 'none'); 
  define('DIR_FS_SQL_CACHE', '/var/www/html/zencart/cache');


// Define the webserver and path parameters
  // Main webserver: eg-http://www.your_domain.com - 
  // HTTP_SERVER is your Main webserver: eg-http://www.your_domain.com
  // HTTPS_SERVER is your Secure webserver: eg-https://www.your_domain.com
  // HTTP_CATALOG_SERVER is your Main webserver: eg-http://www.your_domain.com
  // HTTPS_CATALOG_SERVER is your Secure webserver: eg-https://www.your_domain.com
  /* 
   * URLs for your site will be built via:  
   *     HTTP_SERVER plus DIR_WS_ADMIN or
   *     HTTPS_SERVER plus DIR_WS_HTTPS_ADMIN or 
   *     HTTP_SERVER plus DIR_WS_CATALOG or 
   *     HTTPS_SERVER plus DIR_WS_HTTPS_CATALOG
   * ...depending on your system configuration settings
   */
// EOF