<?php
class GuestbookController extends Controller
{
  public function indexAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    $guestbook = new Guestbook();

    $select = $guestbook->select();
    $select->from($guestbook, array('c' => 'COUNT(*)'));
    $result = $guestbook->fetchRow($select)->toArray();
    $count = (int)$result['c'];
    
    $per_page = 50;

    try
    {
      $pages_count = 1 + ceil($count / $per_page);
    }
    catch(Exception $e)
    {
      $pages_count = 1;
    }

    $this->view->page_count = $pages_count;

    $page_number = (int)$this->getRequest()->getParam('page', $pages_count);
    $this->view->page_number = $page_number;

    $select = $guestbook->select();
    $select->from($guestbook, array('name', 'email', 'question', 'answer', 'uadded' => 'UNIX_TIMESTAMP(added)', 'country'));
    $select->order(array('id DESC'));
    $select->limitPage($pages_count - $page_number, $per_page);
    $result = $guestbook->fetchAll($select);
    $this->view->entries = array();
    if(!is_null($result))
    {
      $this->view->entries = $result->toArray();
    }

  } // /function
  
  /**
   * RSS feed of latest guestbook entries
   */
  public function feedAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');

    // Disable main layout
    $this->_helper->layout->disableLayout();

    $guestbook = new Guestbook();

    $select = $guestbook->select();
    $select->from($guestbook, array('id', 'question', 'added', 'country', 'name'));
    $select->order(array('id DESC'));
    $select->limit(10);
    $result = $guestbook->fetchAll($select);

    if(!is_null($result))
    {
      $result = $result->toArray();
    }
    else
    {
      $result = array();
    }

    $entries = array();

    if(!empty($result))
    {
      for($i=0; $i<count($result); $i++)
      {
        $id = $result[$i]['id'];
        $question = strip_tags($result[$i]['question']);
        $added = $result[$i]['added'];
        $link = $this->view->url(array('controller' => 'guestbook', 'action' => 'index', 'via' => 'feed'), '', true);

        $entries[] = array(
          'title' => $question,
          'link' => $link,
          'description' => strip_tags($question),
          'content' => $question
        );
      } // /for
    } // /if

    $feed = array(
      'charset' => 'UTF-8',
      'title' => $config->name,
      'link' => 'http://' . $_SERVER['HTTP_HOST'],
      'entries' => $entries
    );

    $feeddata = Zend_Feed::importArray($feed, 'rss');
    $feeddata->send();

  } // /function
  
  /**
   * Add new guestbook entry
   */
  public function addAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');

    $guestbook = new Guestbook();

    // Form
    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/guestbook/add');

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

        // Spam bot added values to wrong field
        if(!empty($values['email2']))
        {
          return $this->_helper->redirector->gotoRoute(array('controller' => 'guestbook', 'action' => 'index'), '', true);
        }

        require_once 'commenthandler.php';
        $values['comment'] = linkify($values['comment']);

        $useragent = $this->getRequest()->getHeader('User-Agent');
        $ip = $this->getRequest()->getServer('REMOTE_ADDR');

        try
        {
          $tc = new Zend_Service_TeamCymru();
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

          return $this->_helper->redirector->gotoRoute(array('controller' => 'guestbook', 'action' => 'index'), '', true);
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
  }

} // /class
