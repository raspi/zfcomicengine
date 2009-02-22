<?php
class Controller extends Zend_Controller_Action
{
  /**
   * Database
   */
  protected $_db;

  /**
   * Authorization
   */
  protected $_auth;

  /**
   * Translation
   */
  protected $tr;

  /**
   * Cache
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
