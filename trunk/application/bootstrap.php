<?php
// Bootstrap
error_reporting(E_ALL | E_STRICT);  
ignore_user_abort(true);
ini_set('display_startup_errors', 1);  
ini_set('display_errors', 1);
ini_set('default_charset', 'utf-8'); 
date_default_timezone_set('Europe/Helsinki');

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

$frontController = Zend_Controller_Front::getInstance(); 
$frontController->throwExceptions(true);
$frontController->setBaseUrl('/');
//$frontController->setParam('useDefaultControllerAlways', true);
$frontController->addModuleDirectory(dirname(__FILE__) . '/modules');
/*
$frontController->setControllerDirectory(
  array('default' => dirname(__FILE__) . '/default/controllers')
);
*/

Zend_Layout::startMvc(array('layoutPath' => dirname(__FILE__) . '/views/layouts'));

$view = new Zend_View;
$view->setEncoding('UTF-8');
$viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer($view);
Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);

Zend_Dojo::enableView($view);

$config = new Zend_Config_Ini(dirname(__FILE__) . '/../config.ini', 'database');

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

  // Check MySQL version
  $select = $db->select();
  $select->from('', array('v' => 'VERSION()'));
  $sqlver = $db->fetchRow($select);
  if (version_compare($sqlver['v'], '5.0') === -1)
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
