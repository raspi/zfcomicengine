<?php
class DBTable extends Zend_Db_Table_Abstract
{
  protected $_cache = null;
  public $cache_result = true;

  public function init()
  {
    $this->_cache = Zend_Registry::get('Cache');
  } // /function

  public function _purgeCache()
  {
    $this->_cache->clean(Zend_Cache::CLEANING_MODE_ALL);
  } // /function

  public function update(array $data, $where)
  {
    parent::update($data, $where);
    $this->_purgeCache();
  } // /function

  public function insert(array $data)
  {
    parent::insert($data);
    $this->_purgeCache();
  } // /function
  
  public function delete($where)
  {
    parent::delete($where);
    $this->_purgeCache();
  } // /function

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

  }

} // /class

/**
 * Pages
 */
class Pages extends DBTable
{
  /**
   *
   */
  protected $_name = 'PAGES';
  protected $_primary = 'name';

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
  }
}

/**
 * Guestbook
 */
class Guestbook extends DBTable
{
  /**
   *   
   */
  protected $_name = 'GUESTBOOK';
  protected $_primary = 'id';

}

/**
 * Authors
 */
class Authors extends DBTable
{
  protected $_name = 'AUTHORS';
  protected $_primary = 'id';

  public function emailExists($email)
  {
    $select = $this->select();
    $select->from($this, array('c' => 'COUNT(id)'));
    $select->where('email = ?', $email);
    $result = $this->fetchRow($select)->toArray();
    return (bool) ( (int) $result['c'] == 1 ? true : false);
  } // /function

  public function emailToID($email)
  {
    $select = $this->select();
    $select->from($this, array('id'));
    $select->where('email = ?', $email);
    $result = $this->fetchRow($select)->toArray();
    return (string) $result['id'];
  } // /function

}

/**
 * Comics
 */
class Comics extends DBTable
{
  protected $_name = 'COMICS';
  protected $_primary = 'id';
  protected $_dependentTables = array('Authors');

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
  
  public function idExists($id)
  {
    $select = $this->select();
    $select->from($this, array('c' => 'COUNT(id)'));
    $select->where('id = ?', $id);
    $result = $this->fetchRow($select)->toArray();
    return (bool) ( (int) $result['c'] == 1 ? true : false );
  } // /function

}

/**
 * Comic comments
 */
class Comments extends DBTable
{
  protected $_name = 'COMMENTS';
  protected $_primary = 'id';

  protected $_dependentTables = array('Comics');

  protected $_referenceMap = array(

    'Comic' => array(
      'refTableClass' => 'Comics',
      'refColumns'    => array('id'),
      'columns'       => array('comicid'),
    )

  );

}

/**
 * Blog posts
 */
class Posts extends DBTable
{
  protected $_name = 'POSTS';
  protected $_primary = 'id';

  protected $_dependentTables = array('Authors');

  protected $_referenceMap = array(

    'Author' => array(
      'refTableClass' => 'Authors',
      'refColumns'    => array('id'),
      'columns'       => array('authorid'),
    )

  );
}

/**
 * Blog posts
 */
class Bans extends DBTable
{
  protected $_name = 'BANS';
  protected $_primary = 'id';
}

/*
VIEWs
*/

/**
 * Blog posts, with author informatien etc
 */
class VIEW_Posts extends DBTable
{
  protected $_name = 'VIEW_POSTS';
  protected $_primary = 'id';
}
