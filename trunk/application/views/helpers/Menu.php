<?php
class Zend_View_Helper_Menu extends Zend_View_Helper_Abstract
{
  protected function _getAdminMenu()
  {
    $tr = Zend_Registry::get('Zend_Translate');

    $m = array
    (
      array ('title' => $tr->_("Posts"),          'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'index')),
      array ('title' => $tr->_("Comic"),          'url' => array('module' => 'admin', 'controller' => 'comic', 'action' => 'index')),
      array ('title' => $tr->_("Comic comments"), 'url' => array('module' => 'admin', 'controller' => 'comic', 'action' => 'comments')),
      array ('title' => $tr->_("Guestbook"),      'url' => array('module' => 'admin', 'controller' => 'guestbook', 'action' => 'index')),
      array ('title' => $tr->_("Pages"),          'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'pages')),
      array ('title' => $tr->_("Authors"),        'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'authors')),
      array ('title' => $tr->_("Bans"),           'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'bans')),
      array ('title' => $tr->_("Logout"),         'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'logout'), 'id' => 'logout')
    );

    return $m;
  } // /function

  protected function _getMenu()
  {
    $tr = Zend_Registry::get('Zend_Translate');

    $m = array
    (
      array ('title' => $tr->_("Front page"),    'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'index')),
      array ('title' => $tr->_("Comic"),         'url' => array('module' => 'default', 'controller' => 'comic', 'action' => 'index')),
      array ('title' => $tr->_("Comic archive"), 'url' => array('module' => 'default', 'controller' => 'comic', 'action' => 'archive')),
      array ('title' => $tr->_("About"),         'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'about')),
      array ('title' => $tr->_("Feedback"),      'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'feedback')),
      array ('title' => $tr->_("Guest book"),    'url' => array('module' => 'default', 'controller' => 'guestbook', 'action' => 'index')),
      array ('title' => $tr->_("Links"),         'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'links'))
    );

    return $m;
  } // /function

  protected function _createMenu($arr)
  {
    $o = null;

    $ctrl = Zend_Controller_Front::getInstance();
    $module = $ctrl->getRequest()->getModuleName();
    $controller = $ctrl->getRequest()->getControllerName();
    $action = $ctrl->getRequest()->getActionName();
    
    $l = new Zend_View_Helper_Url();
    
    for($i=0; $i<count($arr); $i++)
    {
      $class = null;
      $id = isset($arr[$i]['id']) ? $arr[$i]['id'] : null;

      $title = $arr[$i]['title'];
      $url = $arr[$i]['url'];
      
      if ($url['module'] == $module && $url['controller'] == $controller && $url['action'] == $action)
      {
        $class = 'selected';
      }
      
      $o .= '<li ' . (!is_null($id) ? 'id="' . $id . '" ' : '') . 'class="' . $class . '"><a href="' . $l->url($url, '', true) . '">' . $title . '</a></li>';
    }
    
    return $o;
  }

  public function menu()
  {
    $tr = Zend_Registry::get('Zend_Translate');
    $auth = Zend_Auth::getInstance();

    $o = null;

    if ($auth->hasIdentity())
    {
      $o .= '<div id="admin-menu">';
      $o .= sprintf($tr->_("Logged in as %s"), $auth->getIdentity()->name);
      $o .= '<ul>';
      $o .= $this->_createMenu($this->_getAdminMenu());
      $o .= '</ul></div>';
    }
    
    $o .= '<div id="menu"><ul>';
    $o .= $this->_createMenu($this->_getMenu());
    $o .= '</ul></div>';
    
    return $o;
  } // /function
} // /class