<?php
class navigation
{
  public $selected = null;
  
  public function getMenu()
  {
    $tr = Zend_Registry::get('Zend_Translate');
    
    $m = array();

    $m['admin'] = array
    (
      array ('title' => $tr->_("Admin front page"), 'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'index'), 'router' => 'admin'),
      array ('title' => $tr->_("Posts"),            'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'posts'), 'router' => 'admin'),
      array ('title' => $tr->_("Comic"),            'url' => array('module' => 'admin', 'controller' => 'comic', 'action' => 'index'), 'router' => 'admin'),
      array ('title' => $tr->_("Comic comments"),   'url' => array('module' => 'admin', 'controller' => 'comic', 'action' => 'comments'), 'router' => 'admin'),
      array ('title' => $tr->_("Guestbook"),        'url' => array('module' => 'admin', 'controller' => 'guestbook', 'action' => 'index'), 'router' => 'admin'),
      array ('title' => $tr->_("Pages"),            'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'pages'), 'router' => 'admin'),
      array ('title' => $tr->_("Authors"),          'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'authors'), 'router' => 'admin'),
      array ('title' => $tr->_("Bans"),             'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'bans'), 'router' => 'admin'),
      array ('title' => $tr->_("Logout"),           'url' => array('module' => 'admin', 'controller' => 'index', 'action' => 'logout'), 'id' => 'logout', 'router' => 'admin')
    );

    $m['public'] = array
    (
      array ('title' => $tr->_("Front page"),    'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'index'), 'router' => 'public'),
      array ('title' => $tr->_("Comic"),         'url' => array('module' => 'default', 'controller' => 'comic', 'action' => 'index'), 'router' => 'public'),
      array ('title' => $tr->_("Comic archive"), 'url' => array('module' => 'default', 'controller' => 'comic', 'action' => 'archive'), 'router' => 'public'),
      array ('title' => $tr->_("About"),         'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'about'), 'router' => 'public'),
      array ('title' => $tr->_("Characters"),    'url' => array('module' => 'default', 'controller' => 'comic', 'action' => 'characters'), 'router' => 'public'),
      array ('title' => $tr->_("Feedback"),      'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'feedback'), 'router' => 'public'),
      array ('title' => $tr->_("Guest book"),    'url' => array('module' => 'default', 'controller' => 'guestbook', 'action' => 'index'), 'router' => 'public'),
      array ('title' => $tr->_("Links"),         'url' => array('module' => 'default', 'controller' => 'index', 'action' => 'links'), 'router' => 'public')
    );

    return $m;
  } // /function

  public function _createMenu($arr)
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
      $router = $arr[$i]['router'];

      if ($url['module'] == $module && $url['controller'] == $controller && $url['action'] == $action)
      {
        $class = 'selected';
        $this->selected = $title;
      }

      unset($url['module']);

      $o .= '<li ' . (!is_null($id) ? 'id="' . $id . '" ' : '') . 'class="' . $class . '"><a href="' . $l->url($url, $router, true) . '">' . $title . '</a></li>';
    }

    return $o;
  }

  public function menu()
  {
    $tr = Zend_Registry::get('Zend_Translate');
    $auth = Zend_Auth::getInstance();

    $menu = $this->getMenu();
    $o = null;

    if ($auth->hasIdentity())
    {
      $o .= '<div id="admin-menu">';
      $o .= sprintf($tr->_("Logged in as %s"), $auth->getIdentity()->name);
      $o .= '<ul>';
      $o .= $this->_createMenu($menu['admin']);
      $o .= '</ul></div>';
    }

    $o .= '<div id="menu"><ul>';
    $o .= $this->_createMenu($menu['public']);
    $o .= '</ul></div>';

    return $o;
  } // /function


}