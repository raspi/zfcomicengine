<?php
class GuestbookController extends Controller
{
  public function indexAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    $guestbook = new Guestbook();

    $select = $guestbook->select();
    $select->from($guestbook, array('name', 'email', 'question', 'answer', 'uadded' => 'UNIX_TIMESTAMP(added)', 'country'));
    $select->order(array('id DESC'));
    $result = $guestbook->fetchAll($select);
    $this->view->entries = array();
    if(!is_null($result))
    {
      $this->view->entries = $result->toArray();
    }

    // Form
    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/guestbook');

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add new guestbook entry'));

    $email = new Zend_Form_Element_Text('email');
    $email->setRequired(true);
    $email->setLabel($this->tr->_('Email address'));
    $email->addFilter('StringTrim');
    $email->addFilter('StringToLower');
    $email->addValidator('StringLength', false, array(7));
    $email->addValidator('EmailAddress');

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Name'));
    $name->addFilter('StringTrim');
    $name->addValidator('NotEmpty', true);
    $name->addValidator('StringLength', false, array(2, 30));

    $comment = new Zend_Form_Element_Textarea('comment');
    $comment->setRequired(true);
    $comment->setLabel($this->tr->_('Comment'));
    $comment->addFilter('StringTrim');
    $comment->addValidator('NotEmpty', true);
    $comment->addValidator('StringLength', false, array(2, 2000));

    // For spam bots
    $email2 = new Zend_Form_Element_Text('email2');
    $email2->setRequired(false);
    $email2->setLabel($this->tr->_('Email address'));
    $email2->addFilter('StringTrim');
    $email2->addFilter('StringToLower');
    $email2->addValidator('StringLength', false, array(7));
    $email2->addValidator('EmailAddress');

    // For spambots
    $form->addElement($email2);

    $form->addElement($email);
    $form->addElement($name);
    $form->addElement($comment);

    $form->addElement($submit);

    $form->addDisplayGroup(array('email2'), 'mail', array('legend' => $this->tr->_('Do not fill fields in this fieldset'), 'class' => 'not-visible'));
    $form->addDisplayGroup(array('name', 'email', 'comment', 'submit'), 'add');

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        if(!empty($values['email2']))
        {
          return $this->_helper->redirector->gotoUrl("/guestbook");
        }

        $useragent = $this->getRequest()->getHeader('User-Agent');
        $ip = $this->getRequest()->getServer('REMOTE_ADDR');

        try
        {
          $tc = new Zend_Service_Team_Cymru();
          $tcinfo = $tc->getIpInfo($ip);

          $country = $tcinfo['country'];
        }
        catch(Exception $e)
        {
          $country = 'unknown';
        }

        $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');

        $is_spam = 0;

        if (!empty($config->plugin->akismet->key))
        {
          $data = array(
            'user_ip'              => $ip,
            'user_agent'           => $useragent,
            'comment_type'         => 'comment',
            'comment_author'       => $values['name'],
            'comment_author_email' => $values['email'],
            'comment_content'      => $values['comment']
          );

          $akismet = new Zend_Service_Akismet($config->plugin->akismet->key, $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost());

          if ($akismet->isSpam($data))
          {
            $is_spam = 1;
          }
        }

        $insert = array(
          'name' => $values['name'],
          'question' => $values['comment'],
          'email' => $values['email'],
          'added' => new Zend_Db_Expr('NOW()'),
          'country' => $country,
          'useragent' => $useragent,
          'ipaddr' => $ip,
          'isspam' => $is_spam
        );

        $this->_db->beginTransaction();

        try
        {
          $guestbook->insert($insert);

          $this->_db->commit();

          return $this->_helper->redirector->gotoUrl("/guestbook");

        }
        catch (Exception $e)
        {
          $this->_db->rollBack();
          var_dump($e);
          die;
        }

      }
    }

    $this->view->gbform = $form;

  } // /function

} // /class
