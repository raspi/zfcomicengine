<?php
// Bootstrap
define('VERSION', '1.0.0');

error_reporting(E_ALL | E_STRICT);  
ignore_user_abort(true);
ini_set('display_startup_errors', 1);  
ini_set('display_errors', 1);
ini_set('default_charset', 'utf-8'); 
date_default_timezone_set('Europe/Helsinki');

// Check PHP version
if (version_compare(PHP_VERSION, '5.2', '>=') === -1)
{
  printf("Too old PHP version (%s). Please update.", PHP_VERSION);
  die;
}

// List of required extensions
$required_extensions = array(
  'session', 'spl', 'standard', 'mbstring', 'gd', 'pdo', 'pdo_mysql', 'date', 'pcre', 'iconv'
);

// Get list of loaded extensions and lowercase all extension names
$loaded_extensions = array_map('strtolower', get_loaded_extensions());

// Check for required PHP extensions
for ($i = 0; $i < count($required_extensions); $i++)
{
  if (!in_array($required_extensions[$i], $loaded_extensions, false))
  {
    printf("PHP Extension '%s' not found!", $required_extensions[$i]);
    die;
  } // /if
} // /for

set_include_path(realpath(dirname(__FILE__) . '/../library') . PATH_SEPARATOR . get_include_path());  
 
require_once 'Zend/Loader.php'; 
Zend_Loader::registerAutoload(); 

set_include_path(realpath(dirname(__FILE__) . '/../classes') . PATH_SEPARATOR . get_include_path());

$language_config = new Zend_Config_Ini(dirname(__FILE__) . '/../config.ini', 'language');
$defaultlanguage = 'en';

$locale = new Zend_Locale();
Zend_Registry::set('Zend_Locale', $locale);

@$translate = new Zend_Translate('gettext', dirname(__FILE__) . '/../locales/' . $language_config->language . '.mo', 'en', array('scan' => Zend_Translate::LOCALE_FILENAME));
if (!$translate->isAvailable($locale->getLanguage()))
{
  @$translate->setLocale($defaultlanguage);
}
else
{
  @$translate->setLocale($language_config->language);
}

Zend_Registry::set('Zend_Translate', $translate);

set_include_path(realpath(dirname(__FILE__) . '/views/helpers') . PATH_SEPARATOR . get_include_path());

require_once 'db.php';
require_once 'controller.php';
require_once 'forms.php';
require_once 'function.mb_trim.php';
require_once 'mbtrim.php';
require_once 'zftc.php';

// Caching
$cache_config = new Zend_Config_Ini(dirname(__FILE__) . '/../config.ini', 'cache');

$frontendOptions = array(
  'caching' => true,
  'lifetime' => null,
  'automatic_serialization' => true
);

$backendOptions = array(
  'cache_dir' => $cache_config->directory,
  'cache_file_umask' => 0607
);

$cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
Zend_Registry::set('Cache', $cache);

$frontController = Zend_Controller_Front::getInstance(); 
$frontController->throwExceptions(true);
// @TODO Load from config.ini
$frontController->setBaseUrl('/');
//$frontController->setParam('useDefaultControllerAlways', true);
$frontController->addModuleDirectory(dirname(__FILE__) . '/modules');

$router = $frontController->getRouter();

// Set custom routes
$route = new Zend_Controller_Router_Route(
  ':controller/:action/*',
  array(
    'module' => 'default',
    'controller' => 'index',
    'action' => 'index'
  ),
  array(
    'controller' => '^\w+$',
    'action' => '^\w+$',
  )
);

$router->addRoute('public', $route);

$route = new Zend_Controller_Router_Route(
  'admin/:controller/:action/*',
  array(
    'module' => 'admin',
    'controller' => 'index',
    'action' => 'index'
  ),
  array(
  )
);

$router->addRoute('admin', $route);


$route = new Zend_Controller_Router_Route(
  'c/:id/:name/*',
  array(
    'module' => 'default',
    'controller' => 'comic',
    'action' => 'index',
    'id' => 0,
    'name' => ''
  ),
  array(
    'id' => '^\d+$',
    'name' => '^[^/]+$'
  )
);

$router->addRoute('comic', $route);


$route = new Zend_Controller_Router_Route(
  'comic-image/:id/:name',
  array(
    'module' => 'default',
    'controller' => 'comic',
    'action' => 'display-comic',
    'id' => 0,
    'name' => ''
   ),
  array(
    'id' => '^[\da-f]+$'
  )
);

$router->addRoute('comicimage', $route);


Zend_Layout::startMvc(array('layoutPath' => dirname(__FILE__) . '/views/layouts'));

$view = new Zend_View;
$view->addHelperPath(realpath(dirname(__FILE__) . '/views/helpers'));
$view->setEncoding('UTF-8');
$viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer($view);
Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);

Zend_Dojo::enableView($view);
$view->dojo()->setDjConfigOption('parseOnLoad', true);
$view->dojo()->enable();

$config = new Zend_Config_Ini(dirname(__FILE__) . '/../config.ini', 'database');

if (empty($config->salt))
{
  echo _('Database salt is not configured!');
  die;
}

$db_params = array(
  'host'             => $config->host,
  'username'         => $config->username,
  'password'         => $config->password,
  'dbname'           => $config->dbname,
  'adapterNamespace' => 'DB_Adapter',
  'charset'          => 'utf8',
  'profiler'         => true,
  'driver_options'   => array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8") 
);

Zend_Registry::set('DB', null);

try
{
  $db = new Zend_Db_Adapter_Pdo_Mysql($db_params);
  $conn = $db->getConnection();
  Zend_Db_Table_Abstract::setDefaultAdapter($db);
  Zend_Registry::set('DB', $db);

  if (!($cache->test('DBVersion')))
  {
    // Check MySQL version
    $select = $db->select();
    $select->from('', array('v' => 'VERSION()'));
    $sqlver = $db->fetchRow($select);
    $sqlver = $sqlver['v'];

    $cache->save($sqlver);
  }
  else
  {
    $sqlver = $cache->load('DBVersion');
  }

  if (version_compare($sqlver, '5.0') === -1)
  {
    echo _("Too old MySQL server. Please update.");
    die;
  }

}
catch (Zend_Db_Adapter_Exception $e)
{
  Zend_Layout::getMvcInstance()->setLayout('db_error_layout');
  echo nl2br($e->__toString());

}
catch (Zend_Exception $e)
{
  Zend_Layout::getMvcInstance()->setLayout('db_error_layout');
  echo nl2br($e->__toString());

}

$doctypeHelper = new Zend_View_Helper_Doctype();
$doctypeHelper->doctype('XHTML11');

// Run
try
{
  $frontController->dispatch();
}
catch(Exception $e)
{
  echo nl2br($e->__toString());
}
