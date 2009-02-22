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

  }


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

          $userid = $this->_auth->getIdentity()->id;

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

  }

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
  }

  /**
   * Add new post
   */
  public function addAction()
  {
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

          return $this->_helper->redirector->gotoUrl("/admin/index/");

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

          return $this->_helper->redirector->gotoUrl("/admin/index/");

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

    $p = array('about', 'links', 'frontpage');
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

    }

  } // /function

  /**
   * Bans
   */
  public function bansAction()
  {
  }

} // /class
