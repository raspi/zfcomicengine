<?php
/**
 * Pages
 */
class Pages extends Zend_Db_Table_Abstract
{
  /**
   *
   */
  protected $_name = 'PAGES';
  
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
class Guestbook extends Zend_Db_Table_Abstract
{
  /**
   *   
   */
  protected $_name = 'GUESTBOOK';
}

/**
 * Authors
 */
class Authors extends Zend_Db_Table_Abstract
{
  protected $_name = 'AUTHORS';

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
class Comics extends Zend_Db_Table_Abstract
{
  protected $_name = 'COMICS';
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
class Comments extends Zend_Db_Table_Abstract
{
  protected $_name = 'COMMENTS';
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
class Posts extends Zend_Db_Table_Abstract
{
  protected $_name = 'POSTS';
  protected $_dependentTables = array('Authors');

  protected $_referenceMap = array(

    'Author' => array(
      'refTableClass' => 'Authors',
      'refColumns'    => array('id'),
      'columns'       => array('authorid'),
    )

  );
}
