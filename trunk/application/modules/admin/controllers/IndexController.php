<?php
class Admin_IndexController extends Controller
{ 
  public function preDispatch()
  {
    $action = $this->getRequest()->getActionName();

    // User is not logged in
    if (!$this->_auth->hasIdentity())
    {
     switch ($action)
      {
        case 'login': break; // /login
        case 'reset-password': break; // /reset-password

        default:
          return $this->_redirect('/admin/index/login/');
        break;
      } // /switch
    }
    else
    {
      switch ($action)
      {
        // Forbidden pages for logged in user
        case 'login':
        case 'reset-password':
          return $this->_redirect('/admin/index');
        break;

        default: break;
      } // /switch
    }

  } // /function

  /**
   * Admin front page
   */
  public function indexAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', array('site', 'language', 'contact', 'cache'));
    $this->view->config = $config;
    
    
    $this->view->akismet_valid = false;
    if(!empty($config->plugin->akismet->key))
    {
      $akismet = new Zend_Service_Akismet($config->plugin->akismet->key, $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost());
      $this->view->akismet_valid = $akismet->verifyKey($config->plugin->akismet->key);
    }


  }

  /**
   * Posts
   */
  public function postsAction()
  {
    $posts = new Posts();
    $posts->cache_result = false;
    
    $select = $posts->select();
    $select->from($posts, array('id', 'subject', 'added'));
    $select->order(array('added DESC', 'id'));
    $result = $posts->fetchAll($select);
    
    $this->view->posts = array();
    if (!is_null($result))
    {
      $this->view->posts = $result->toArray();
    }

  } // /function

  /**
   * Reset password
   */
  public function resetPasswordAction()
  {

    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', array('database', 'contact'));

    $users = new Authors();
    $users->cache_result = false;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/index/reset-password');

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Reset password'));

    $email = new Zend_Form_Element_Text('email');
    $email->setRequired(true);
    $email->setLabel($this->tr->_('E-Mail'));
    $email->addFilter('StringTrim');
    $email->addFilter('StringToLower');
    $email->addValidator('StringLength', false, array(7));
    $email->addValidator('EmailAddress');

    $form->addElement($email);
    $form->addElement($submit);

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
        $email = $values['email'];
        $exists = $users->emailExists($email);

        if($exists)
        {
          // Get user ID
          $user_id = $users->emailToID($email);

          // Generate new password
          $new_password = uniqid(md5(mt_rand()), true);

          // Translate it to SQL format: md5 checksum (salt + new password)
          $pw = md5($config->salt . $new_password);

          $users->update(array('password' => $pw), $users->getAdapter()->quoteInto('id = ?', $user_id));

          $body = sprintf($this->tr->_("Your password has been reseted.\nNew password: %s\n\n"), $new_password);

          $mail = new Zend_Mail('UTF-8');
          $mail->setBodyText($body);
          $mail->setFrom($config->sender, 'zfComicEngine');
          $mail->addTo($email, $email);
          $mail->setSubject($this->tr->_('Reset password'));
          $mail->send();

          // Redirect to index page
          return $this->_helper->redirector('index');
        }
        else
        {
          $form->getElement('email')->markAsError();
          $err = $this->tr->_("Check email address");
          $form->getElement('email')->addError($err);
        }


      }
    }

    $this->view->form = $form;

  } // /function


  /**
   * Login
   */
  public function loginAction()
  {
    $authors = new Authors();
    $authors->cache_result = false;

    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'database');

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/index/login');

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Log in'));

    $email = new Zend_Form_Element_Text('email');
    $email->setRequired(true);
    $email->setLabel($this->tr->_('E-Mail'));
    $email->addFilter('StringTrim');
    $email->addFilter('StringToLower');
    $email->addValidator('StringLength', false, array(7));
    $email->addValidator('EmailAddress');

    $pass = new Zend_Form_Element_Password('password');
    $pass->setRequired(true);
    $pass->setLabel($this->tr->_('Password'));
    $pass->addFilter('StringTrim');

    $form->addElement($email);
    $form->addElement($pass);
    $form->addElement($submit);

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        $pw = md5($config->salt . $values['password']);

        $select = $authors->select();
        $select->from($authors, array('c' => 'COUNT(*)'));
        $info = $authors->fetchRow($select)->toArray();
        $usercount = (int)$info['c'];
        
        // No users, create first user
        if ($usercount === 0)
        {
          $insert = array(
            'name' => 'Not set',
            'email' => $values['email'],
            'password' => $pw
          );

          $this->_db->beginTransaction();

          try
          {
            $authors->insert($insert);

            $this->_db->commit();
          }
          catch (Exception $e)
          {
            $this->_db->rollBack();
            echo $e->getMessage();
            var_dump($e);
            die;
          }

        } // /if

        // Login
        $authAdapter = new Zend_Auth_Adapter_DbTable($this->_db);
        $authAdapter->setTableName('AUTHORS');
        $authAdapter->setIdentityColumn('email');
        $authAdapter->setCredentialColumn('password');

        $authAdapter->setIdentity($values['email']);
        $authAdapter->setCredential($pw);

        $result = $this->_auth->authenticate($authAdapter);

        if ($result->isValid())
        {
          Zend_Session::rememberMe(60*60*24*7*4);

          $this->_auth->getStorage()->write($authAdapter->getResultRowObject(null, 'password'));

          return $this->_helper->redirector('index');

        }
        else
        {
          $form->getElement('email')->markAsError();
          $err = $this->tr->_("Check email address");
          $form->getElement('email')->addError($err);

          $form->getElement('password')->markAsError();
          $err = $this->tr->_("Check password");
          $form->getElement('password')->addError($err);

        }

      }

    }

    $this->view->form = $form;

  } // /function

  /**
   * Logout
   */
  public function logoutAction()
  {
    if ($this->_auth->hasIdentity())
    {
      $this->_auth->clearIdentity();
    }

    return $this->_helper->redirector->gotoUrl("/");
  } // /function

  /**
   * Add new post
   */
  public function addAction()
  {
    require_once 'filter_html.php';
  
    $posts = new Posts();
    $posts->cache_result = false;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . "/admin/index/add/");

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add post'));

    $subject = new Zend_Form_Element_Text('subject');
    $subject->setRequired(true);
    $subject->setLabel($this->tr->_('Subject'));
    $subject->addFilter('StringTrim');
    $subject->addValidator('StringLength', false, array(2));

    $text = new Zend_Dojo_Form_Element_Editor('text');
    $text->setRequired(true);
    $text->setLabel($this->tr->_('Content'));
    $text->addFilter('StringTrim');
    $text->addFilter(new Zend_Filter_MBStringTrim());
    $text->addFilter(new Zend_Filter_PurifyHTML());
    $text->addValidator('NotEmpty', false);
    $text->addValidator('StringLength', false, array(5, 65535));
    //$text->addValidator(new validate_XML(), false);
    $text->setPlugins(array(
      'undo', 'redo', '|',
      'cut', 'copy', 'paste', '|',
      'removeFormat', 'bold', 'italic', 'underline', '|',
      'insertOrderedList', 'insertUnorderedList', '|',
      'createLink', 'unlink', 'formatBlock'
    ));

    $form->addElement($subject);
    $form->addElement($text);

    $form->addElement($submit);

    // POST
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
      
        $insert = array(
          'authorid' => $this->_auth->getIdentity()->id,
          'subject' => $values['subject'],
          'content' => $values['text'],
          'added' => new Zend_Db_Expr('NOW()')
        );

        $this->_db->beginTransaction();

        try
        {
          $posts->insert($insert);

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/index/posts");

        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /Valid

    } // /POST

    $this->view->form = $form;

  } // /function


  /**
   * Edit post
   */
  public function editAction()
  {
    require_once 'filter_html.php';

    $ID = $this->getRequest()->getParam('id', false);

    $posts = new Posts();
    $posts->cache_result = false;

    $select = $posts->select();
    $select->from($posts, array('subject', 'added', 'content'));
    $select->where('id = ?', $ID);
    $info = $posts->fetchRow($select)->toArray();
    
    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . "/admin/index/edit/id/" . $ID);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Edit post'));

    $subject = new Zend_Form_Element_Text('subject');
    $subject->setRequired(true);
    $subject->setLabel($this->tr->_('Subject'));
    $subject->addFilter('StringTrim');
    $subject->addValidator('StringLength', false, array(2));

    $text = new Zend_Dojo_Form_Element_Editor('text');
    $text->setRequired(true);
    $text->setLabel($this->tr->_('Content'));
    $text->addFilter('StringTrim');
    $text->addFilter(new Zend_Filter_MBStringTrim());
    $text->addFilter(new Zend_Filter_PurifyHTML());
    $text->addValidator('NotEmpty', true);
    $text->addValidator('StringLength', false, array(5, 65535));
    //$text->addValidator(new validate_XML(), false);
    $text->setPlugins(array(
      'undo', 'redo', '|',
      'cut', 'copy', 'paste', '|',
      'removeFormat', 'bold', 'italic', 'underline', '|',
      'insertOrderedList', 'insertUnorderedList', '|',
      'createLink', 'unlink', 'formatBlock'
    ));

    $form->addElement($subject);
    $form->addElement($text);

    $form->addElement($submit);
    
    $form->populate(
      array(
        'subject' => $info['subject'],
        'text' => $info['content']
      )
    );

    // POST
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        $update = array(
          'authorid' => $this->_auth->getIdentity()->id,
          'subject' => $values['subject'],
          'content' => $values['text'],
          'added' => new Zend_Db_Expr('NOW()')
        );

        $this->_db->beginTransaction();

        try
        {
          $posts->update($update, $posts->getAdapter()->quoteInto('id = ?', $ID));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/index/posts");

        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /Valid

    } // /POST

    $this->view->form = $form;

  } // /function
  
  public function pagesAction()
  {
    require_once 'filter_html.php';

    $p = array('about', 'links', 'frontpage', 'characters');
    $this->view->pages = $p;

    $page = $this->getRequest()->getParam('page', '-');

    if (in_array($page, $p))
    {
      $pages = new Pages();
      $pages->cache_result = false;

      $select = $pages->select();
      $select->from($pages, array('content'));
      $select->where('name = ?', $page);
      $info = $pages->fetchRow($select);
      if(!is_null($info))
      {
        $info = $info->toArray();
      }
      else
      {
        // Add new page
        $pages->insert(array('name' => $page, 'added' => new Zend_Db_Expr('NOW()'), 'updated' => new Zend_Db_Expr('NOW()')));
      }

      $form = new comicForm();
      $form->setMethod(Zend_Form::METHOD_POST);
      $form->setAction($this->_request->getBaseUrl() . "/admin/index/pages/page/" . $page);

      $submit = new Zend_Form_Element_Submit('submit');
      $submit->setLabel($this->tr->_('Edit post'));

      $text = new Zend_Dojo_Form_Element_Editor('text');
      $text->setRequired(true);
      $text->setLabel($this->tr->_('Content'));
      $text->addFilter('StringTrim');
      $text->addFilter(new Zend_Filter_MBStringTrim());
      $text->addFilter(new Zend_Filter_PurifyHTML());
      $text->addValidator('NotEmpty', true);
      $text->addValidator('StringLength', false, array(5, 65535));
      //$text->addValidator(new validate_XML(), false);
      $text->setPlugins(array(
        'undo', 'redo', '|',
        'cut', 'copy', 'paste', '|',
        'removeFormat', 'bold', 'italic', 'underline', '|',
        'insertOrderedList', 'insertUnorderedList', '|',
        'createLink', 'unlink', 'formatBlock'
      ));

      $form->addElement($text);

      $form->addElement($submit);
      
      $form->populate(
        array(
          'text' => $info['content']
        )
      );

      // POST
      if ($this->getRequest()->isPost())
      {
        if ($form->isValid($_POST))
        {
          $values = $form->getValues();

          $update = array(
            'content' => $values['text'],
            'updated' => new Zend_Db_Expr('NOW()')
          );

          $this->_db->beginTransaction();

          try
          {
            $pages->update($update, $pages->getAdapter()->quoteInto('name = ?', $page));

            $this->_db->commit();

            return $this->_helper->redirector->gotoUrl("/admin/index/pages/page/$page");

          }
          catch (Exception $e)
          {
            $this->_db->rollBack();
            echo $e->getMessage();
            var_dump($e);
            die;
          }

        } // /Valid

      } // /POST

      $this->view->form = $form;

    } // /if

  } // /function

  /**
   * Bans
   */
  public function bansAction()
  {
    $bans = new Bans();
    $bans->cache_result = false;

    $select = $bans->select();
    $select->from($bans, array('id', 'typeid', 'host', 'startip', 'endip'));
    $list = $bans->fetchAll($select);

    if(!is_null($list))
    {
      $this->view->bans = $list->toArray();
    }
    else
    {
      $this->view->bans = array();
    }

  } // /function

  /**
   * Add new ban
   */
  public function addBanAction()
  {
    $bans = new Bans();
    $bans->cache_result = false;
    
    $type = $this->getRequest()->getParam('type', 'ip');
    $this->view->type = $type;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . "/admin/index/add-ban/type/" . $type);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add new ban'));

    $ip = new Zend_Form_Element_Text('ip');
    $ip->setRequired(true);
    $ip->setLabel($this->tr->_('IP Address'));
    $ip->addFilter('StringTrim');
    $ip->addValidator('Ip', false);

    $cidr = new Zend_Form_Element_Text('cidr');
    $cidr->setRequired(true);
    $cidr->setLabel($this->tr->_('CIDR'));
    $cidr->addFilter('StringTrim');
    $cidr->addValidator('Digits', false, array('min' => 0, 'max' => 128));

    $host = new Zend_Form_Element_Text('host');
    $host->setRequired(true);
    $host->setLabel($this->tr->_('Hostname'));
    $host->addFilter('StringTrim');
    $host->addValidator('Hostname', false);

    switch($type)
    {
      case 'ip':
        $form->addElement($ip);
        $form->addElement($cidr);
      break;

      case 'host':
        $form->addElement($host);
      break;
    }

    $form->addElement($submit);

    // POST
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
        $ip = $values['ip'];
        $cidr = (int)$values['cidr'];

        switch($type)
        {
          case 'ip':
            // IPv6 address
            if (strpos($ip, ':') !== false && strpos($ip, '.') !== true)
            {
              $ipaddrtype = 6;
              $ip = bin2hex(inet_pton($ip));
              
              $mask = "(-1 << (128 - {$cidr}))";

              $start = new Zend_Db_Expr("HEX('$ip') & $mask");
              $end = new Zend_Db_Expr("HEX('$ip') | (~$mask & HEX('ffffffffffffffffffffffffffffffff'))");
            }
            else
            {
              // IPv4 address
              // May be "::1.2.3.4" style
              $ip = str_replace(':', '', $ip);
              $ipaddrtype = 4;
              
              $mask = "(-1 << (32 - {$cidr}))";
              
              if ($cidr >= 0 && $cidr <= 32)
              {
                $start = new Zend_Db_Expr("INET_ATON('$ip') & $mask");
                $end = new Zend_Db_Expr("INET_ATON('$ip') | (~$mask & INET_ATON('255.255.255.255'))");
              }
              else
              {
                echo 'invalid IPv4 CIDR';
                die;
              }

            }

            $insert = array(
              'typeid' => $ipaddrtype,
              'startip' => $start,
              'endip' => $end
            );
          break;

          case 'host':
            $insert = array(
              'typeid' => 1,
              'host' => $values['host']
            );
          break;
        }

        $this->_db->beginTransaction();

        try
        {
          $bans->insert($insert);

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/index/bans");
        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      }
    }

    $this->view->form = $form;

  } // /function

  
  public function authorsAction()
  {
    $users = new Authors();
    $users->cache_result = false;

    $select = $users->select();
    $select->from($users, array('id', 'name', 'email'));
    $this->view->userlist = $users->fetchAll($select)->toArray();

  } // /function

  public function addAuthorAction()
  {
    $users = new Authors();
    $users->cache_result = false;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . "/admin/index/add-author/");

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add new author'));

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Name'));
    $name->addFilter('StringTrim');
    $name->addValidator('StringLength', false, array(2));

    $email = new Zend_Form_Element_Text('email');
    $email->setRequired(true);
    $email->setLabel($this->tr->_('Email'));
    $email->addFilter('StringTrim');
    $email->addFilter('StringToLower');
    $email->addValidator('StringLength', false, array(7));
    $email->addValidator('EmailAddress');

    $form->addElement($name);
    $form->addElement($email);

    $form->addElement($submit);

    // POST
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        $select = $users->select();
        $select->from($users, array('c' => 'COUNT(*)'));
        $select->where('email = ?', $values['email']);
        $result = $users->fetchRow($select)->toArray();
        $emailExists = (bool) ((int)$result['c'] > 0 ? true : false);

        if (!$emailExists)
        {
          $insert = array(
            'name' => $values['name'],
            'email' => $values['email']
          );

          $this->_db->beginTransaction();

          try
          {
            $users->insert($insert);

            $this->_db->commit();

            return $this->_helper->redirector->gotoUrl("/admin/index/authors");
          }
          catch (Exception $e)
          {
            $this->_db->rollBack();
            echo $e->getMessage();
            var_dump($e);
            die;
          }

        }

      } // /Valid

    } // /POST

    $this->view->form = $form;


  } // /function


  /**
   * Edit author information
   */
  public function editAuthorAction()
  {
    $users = new Authors();
    $users->cache_result = false;

    $id = $this->getRequest()->getParam('id', false);

    $select = $users->select();
    $select->from($users, array('name', 'email'));
    $select->where('id = ?', $id);
    $info = $users->fetchRow($select)->toArray();

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . "/admin/index/edit-author/id/" . $id);

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Edit post'));

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Name'));
    $name->addFilter('StringTrim');
    $name->addValidator('StringLength', false, array(2));

    $email = new Zend_Form_Element_Text('email');
    $email->setRequired(true);
    $email->setLabel($this->tr->_('Email'));
    $email->addFilter('StringTrim');
    $email->addFilter('StringToLower');
    $email->addValidator('StringLength', false, array(7));
    $email->addValidator('EmailAddress');

    $form->addElement($name);
    $form->addElement($email);

    $form->addElement($submit);

    $form->populate(
      array(
        'name' => $info['name'],
        'email' => $info['email']
      )
    );

    // POST
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        $update = array(
          'name' => $values['name'],
          'email' => $values['email']
        );

        $this->_db->beginTransaction();

        try
        {
          $users->update($update, $users->getAdapter()->quoteInto('id = ?', $id));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/index/authors");
        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /Valid

    } // /POST

    $this->view->form = $form;


  } // /function

  /**
   * Change password
   */
  public function changePasswordAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', array('database', 'contact'));

    $users = new Authors();
    $users->cache_result = false;

    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/admin/index/login');

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Change password'));

    $npass = new Zend_Form_Element_Password('newpassword');
    $npass->setRequired(true);
    $npass->setLabel($this->tr->_('New password'));
    $npass->addFilter('StringTrim');
    $npass->addValidator('StringLength', false, array(6));

    $npass2 = new Zend_Form_Element_Password('newpassword2');
    $npass2->setRequired(true);
    $npass2->setLabel($this->tr->_('New password again'));
    $npass2->addFilter('StringTrim');
    $npass2->addValidator('StringLength', false, array(6));

    $opass = new Zend_Form_Element_Password('oldpassword');
    $opass->setRequired(true);
    $opass->setLabel($this->tr->_('Current password'));
    $opass->addFilter('StringTrim');

    $form->addElement($npass);
    $form->addElement($npass2);
    $form->addElement($opass);
    $form->addElement($submit);


    // POST
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();
        
        if ($values['newpassword'] === $values['newpassword2'])
        {
          $new_password = $values['newpassword'];
        }
        else
        {
          // Fixme
          throw new Exception($this->tr->_("New password mismatch."));
        }
        
        if ($new_password === $values['oldpassword'])
        {
          // Fixme
          throw new Exception($this->tr->_("Password can't be same as before."));
        }

        $select = $users->select();
        $select->from($users, array('password'));
        $select->where('id=?', $this->_auth->getIdentity()->id);
        $result = $users->fetchRow($select);

        if (!is_null($result))
        {
          $result = $result->toArray();
          $result = $result['password']; // Salted + MD5 of current user password
        }
        else
        {
          throw new Exception($this->tr->_("User doesn't exist!"));
        }


        if (md5($config->salt . $values['oldpassword']) === $result)
        {
          $new_password = md5($config->salt . $new_password);
          
          $update = array(
            'password' => $new_password
          );
        }
        else
        {
          throw new Exception($this->tr->_("Wrong password entered."));
        }

        $this->_db->beginTransaction();

        try
        {
          $users->update($update, $users->getAdapter()->quoteInto('id = ?', $this->_auth->getIdentity()->id));

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/admin/index");
        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          echo $e->getMessage();
          var_dump($e);
          die;
        }

      } // /Valid

    } // /POST

    $this->view->form = $form;

  } // /function


} // /class
