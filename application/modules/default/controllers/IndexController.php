<?php
class IndexController extends Controller
{ 
  public function indexAction() 
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    $posts = new VIEW_Posts();

    $pages = new Pages();
    $this->view->introtext = $pages->getPageContents('frontpage');

    $select = $posts->select();
    $select->from($posts, array('id', 'name', 'subject', 'content', 'uadded'));
    $result = $posts->fetchAll($select);

    $this->view->posts = array();

    if(!is_null($result))
    {
      $this->view->posts = $result->toArray();
    }

  } // /function

  public function feedAction()
  {
    // Disable main layout
    $this->_helper->layout->disableLayout();
    
    $posts = new Posts();

    $select = $posts->select();
    $select->from($posts, array('id', 'subject', 'content', 'added'));
    $select->order(array('id DESC'));
    $select->limit(10);
    $result = $posts->fetchAll($select);

    $posts = array();

    if(!is_null($result))
    {
      $posts = $result->toArray();
    }
    
    $entries = array();

    if(!empty($posts))
    {
      for($i=0; $i<count($posts); $i++)
      {
        $id = $posts[$i]['id'];
        $subject = $posts[$i]['subject'];
        $content = $posts[$i]['content'];
        $added = $posts[$i]['added'];
        $link = $this->view->url(array('controller' => 'index', 'action' => 'index', 'via' => 'feed'), '', true);
        
        $entries[] = array(
          'title' => $subject,
          'link' => $link,
          'description' => strip_tags($content),
          'content' => $content
        );
      } // /for
    } // /if

    $feed = array(
      'charset' => 'UTF-8',
      'title' => 'My Comic',
      'link' => 'http://' . $_SERVER['HTTP_HOST'],
      'entries' => $entries
    );
    
    $feeddata = Zend_Feed::importArray($feed, 'rss');
    $feeddata->send();

  } // /function

  /**
   * About
   */
  public function aboutAction()
  {
    $pages = new Pages();
    $this->view->content = $pages->getPageContents('about');
  } // /function

  /**
   * Links
   */
  public function linksAction()
  {
    $pages = new Pages();
    $this->view->content = $pages->getPageContents('links');
  } // /function

  /**
   * Send feedback via email
   */
  public function feedbackAction()
  {
    $form = new comicForm();
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . '/index/feedback');

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add comment'));

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
    $name->addValidator('StringLength', false, array(3, 20));

    $comment = new Zend_Form_Element_Textarea('comment');
    $comment->setRequired(true);
    $comment->setLabel($this->tr->_('Comment'));
    $comment->addFilter('StringTrim');
    $comment->addValidator('NotEmpty', true);
    $comment->addValidator('StringLength', false, array(50, 4000));

    $form->addElement($email);
    $form->addElement($name);
    $form->addElement($comment);
    $form->addElement($submit);

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

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

        $body = '';
        $body .= sprintf($this->tr->_('IP Address: %s [%s]'), $ip, $country) . "\n";
        $body .= sprintf($this->tr->_('User-Agent: %s'), $useragent) . "\n";

        if ($is_spam)
        {
          $body .= $this->tr->_('Note: Akismet plugin has flagged this message as spam') . "\n";
        }

        $body .= "\n";
        $body .= $values['comment'];

        $mail = new Zend_Mail('UTF-8');
        $mail->setBodyText($body);
        $mail->setFrom($values['email'], $values['name']);
        $mail->addTo($config->sender);
        $mail->setSubject($this->tr->_('Feedback') . ($is_spam ? ' (SPAM)' : ''));
        $mail->addHeader('X-Unixtimestamp', time());
        $mail->addHeader('X-Send-By', 'zfComicEngine');

        if ($is_spam)
        {
          $mail->addHeader('X-Spam-Status', 'Yes');
        }
        
        $mail->send();

      } // /if VALID

    } // /if POST

    $this->view->feedbackform = $form;

  } // /function

} // /class
