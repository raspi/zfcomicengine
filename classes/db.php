<?php
class DBTable extends Zend_Db_Table_Abstract
{
  /**
   * @var Zend_Cache
   */
  protected $_cache = null;

  /**
   * @var bool
   */
  public $cache_result = true;

  /**
   * Initialize
   */
  public function init()
  {
    $this->_cache = Zend_Registry::get('Cache');
  } // /function

  /**
   * Reset cache
   */
  public function _purgeCache()
  {
    $this->_cache->clean(Zend_Cache::CLEANING_MODE_ALL);
  } // /function

  /**
   * update
   */
  public function update(array $data, $where)
  {
    parent::update($data, $where);
    $this->_purgeCache();
  } // /function

  /**
   * insert
   */
  public function insert(array $data)
  {
    parent::insert($data);
    $this->_purgeCache();
  } // /function
  
  /**
   * delete
   */
  public function delete($where)
  {
    parent::delete($where);
    $this->_purgeCache();
  } // /function

  /**
   * Fetch all
   */
  public function fetchAll($where = null, $order = null, $count = null, $offset = null)
  {
    $id = md5($where->__toString());

    if ((!($this->_cache->test($id))) || (!$this->cache_result))
    {
      $result = parent::fetchAll($where, $order, $count, $offset);
      $this->_cache->save($result);

      return $result;
    }
    else
    {
      return $this->_cache->load($id);
    }

  } // /function

  /**
   * Fetch one result
   */
  public function fetchRow($where = null, $order = null)
  {
    $id = md5($where->__toString());

    if ((!($this->_cache->test($id))) || (!$this->cache_result))
    {
      $result = parent::fetchRow($where, $order);
      $this->_cache->save($result);

      return $result;
    }
    else
    {
      return $this->_cache->load($id);
    }

  } // /function

} // /class

/**
 * Pages
 */
class Pages extends DBTable
{
  /**
   * Table name
   * @var string
   */
  protected $_name = 'PAGES';

  /**
   * Primary key
   * @var string
   */
  protected $_primary = 'name';

  /**
   * Get given page contents
   * 
   * @param string
   * @return string
   */
  public function getPageContents($page = '')
  {
    $select = $this->select();
    $select->from($this, array('content'));
    $select->where('name = ?', $page);
    $info = $this->fetchRow($select);
    if(!is_null($info))
    {
      $info = $info->toArray();
      return (string)$info['content'];
    }

    return '';
  } // /function

} // /class

/**
 * Guestbook
 */
class Guestbook extends DBTable
{
  /**
   * Table name
   * @var string
   */
  protected $_name = 'GUESTBOOK';

  /**
   * Primary key
   * @var string
   */
  protected $_primary = 'id';

} // /class

/**
 * Authors
 */
class Authors extends DBTable
{
  /**
   * Table name
   * @var string
   */
  protected $_name = 'AUTHORS';

  /**
   * Primary key
   * @var string
   */
  protected $_primary = 'id';

  /**
   * @param string e-mail address
   * @return bool
   */
  public function emailExists($email)
  {
    $select = $this->select();
    $select->from($this, array('c' => 'COUNT(id)'));
    $select->where('email = ?', $email);
    $result = $this->fetchRow($select)->toArray();
    return (bool) ( (int) $result['c'] == 1 ? true : false);
  } // /function

  /**
   * @param string e-mail address
   * @return int ID number
   */
  public function emailToID($email)
  {
    $select = $this->select();
    $select->from($this, array('id'));
    $select->where('email = ?', $email);
    $result = $this->fetchRow($select)->toArray();
    return (int) $result['id'];
  } // /function

} // /class

/**
 * Comics
 */
class Comics extends DBTable
{
  /**
   * Table name
   * @var string
   */
  protected $_name = 'COMICS';

  /**
   * Primary key
   * @var string
   */
  protected $_primary = 'id';

  /**
   * @var array
   */
  protected $_dependentTables = array('Authors');

  /**
   * @var array
   */
  protected $_referenceMap = array(

    'Author' => array(
      'refTableClass' => 'Authors',
      'refColumns'    => array('id'),
      'columns'       => array('authorid'),
    ),

    'Idea' => array(
      'refTableClass' => 'Authors',
      'refColumns'    => array('id'),
      'columns'       => array('ideaid'),
    )

  );
  
  /**
   * @param int
   * @return bool
   */
  public function idExists($id)
  {
    $select = $this->select();
    $select->from($this, array('c' => 'COUNT(id)'));
    $select->where('id = ?', $id);
    $result = $this->fetchRow($select)->toArray();
    return (bool) ( (int) $result['c'] == 1 ? true : false );
  } // /function

} // /class

/**
 * Comic comments
 */
class Comments extends DBTable
{
  /**
   * Table name
   * @var string
   */
  protected $_name = 'COMMENTS';

  /**
   * Primary key
   * @var string
   */
  protected $_primary = 'id';

  /**
   * @var array
   */
  protected $_dependentTables = array('Comics');

  /**
   * @var array
   */
  protected $_referenceMap = array(

    'Comic' => array(
      'refTableClass' => 'Comics',
      'refColumns'    => array('id'),
      'columns'       => array('comicid'),
    )

  );

} // /class

/**
 * Blog posts
 */
class Posts extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'POSTS';

  /**
   * @var string
   */
  protected $_primary = 'id';

  /**
   * @var array
   */
  protected $_dependentTables = array('Authors');

  /**
   * @var array
   */
  protected $_referenceMap = array(

    'Author' => array(
      'refTableClass' => 'Authors',
      'refColumns'    => array('id'),
      'columns'       => array('authorid'),
    )

  );
} // /class

/**
 * Blog posts
 */
class Bans extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'BANS';

  /**
   * @var string
   */
  protected $_primary = 'id';
} // /class

/**
 * Characters
 */
class Characters extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'CHARACTERS';

  /**
   * @var string
   */
  protected $_primary = 'id';
} // /class

/**
 * Character appearances in comics
 */
class CharacterAppearances extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'CHARACTER_APPEARANCES';

  /**
   * @var string
   */
  protected $_primary = 'id';
} // /class

/*
Database VIEWs
*/

/**
 * Blog posts, with author informatien etc
 */
class VIEW_Posts extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'VIEW_POSTS';

  /**
   * @var string
   */
  protected $_primary = 'id';
} // /class

/**
 * Comics with author and avg rating information
 */
class VIEW_Comics extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'VIEW_COMICS';

  /**
   * @var string
   */
  protected $_primary = 'id';
} // /class

/**
 * Comics with author and avg rating information
 */
class VIEW_Comments extends DBTable
{
  /**
   * @var string
   */
  protected $_name = 'VIEW_COMMENTS';

  /**
   * @var string
   */
  protected $_primary = 'id';
} // /class
