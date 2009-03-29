<?php
/*
 * Base controller
 */
class Controller extends Zend_Controller_Action
{
  /**
   * Database
   * @var Zend_Db
   */
  protected $_db;

  /**
   * Authorization
   * @var Zend_Auth
   */
  protected $_auth;

  /**
   * Translation
   * @var Zend_Translate
   */
  protected $tr;

  /**
   * Cache
   * @var Zend_Cache
   */
  protected $_cache;


  /**
   * Initialize
   */
  public function init()
  {
    $this->_cache = Zend_Registry::get('Cache');

    $this->_db = Zend_Registry::get('DB');

    $this->_auth = Zend_Auth::getInstance();

    $this->tr = Zend_Registry::get('Zend_Translate');
  } // /function

} // /class
