<?php
class ComicController extends Controller
{

  /**
   * Comic page
   */
  public function indexAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    if (isset($_COOKIE['zfce-settings']))
    {
      $cookie_settings = unserialize(base64_decode($_COOKIE['zfce-settings']));
    }
    else
    {
      $cookie_settings = array();
    }
    
    if(!is_array($cookie_settings))
    {
      $cookie_settings = array();
    }

    $comics = new Comics();
    $view_comics = new VIEW_Comics();
    $comments = new Comments();
    $comments_raw = new Comments();
    $comments_raw->cache_result = false;
    

    $comic_view_session = new Zend_Session_Namespace('comic_view');

    if (!isset($comic_view_session->ok) || $comic_view_session->ok === false)
    {
      $comic_view_session->ok = true;
    }

    // Get latest comic ID
    $select = $comics->select();
    $select->from($comics, array('id', 'name'));
    $select->order(array('id DESC'));
    $select->limit(1);
    $result = $comics->fetchRow($select);

    // There's no comics
    if(is_null($result))
    {
      // User is logged in
      if($this->_auth->hasIdentity())
      {
        // Redirect to "Add comic page"
        return $this->_helper->redirector->gotoRoute(array('module' => 'admin', 'controller' => 'comic', 'action' => 'index'), '', true);
      }
      else
      {
        // Redirect to login page
        return $this->_helper->redirector->gotoRoute(array('module' => 'admin', 'controller' => 'index', 'action' => 'login'), '', true);
      }
    }
    else
    {
      $result->toArray();
    }

    $iLatestID = $result['id'];
    $this->view->lname = $result['name'];
    
    $this->view->latest = $iLatestID;
    
    // Get first comic ID
    $select = $comics->select();
    $select->from($comics, array('id', 'name'));
    $select->order(array('id ASC'));
    $select->limit(1);
    $result = $comics->fetchRow($select)->toArray();
    $iFirstID = $result['id'];
    $this->view->fname = $result['name'];
    
    $this->view->first = $iFirstID;

    // Get comic ID from URL parameter
    // If it isn't set, use latest comic ID
    $iComicID = $this->getRequest()->getParam('id', $iLatestID);
   
    // Does the comic exist?
    $comicExists = $comics->idExists($iComicID);

    // No such ID, redirect to latest comic id
    if (!$comicExists)
    {
      return $this->_helper->redirector->gotoRoute(array('id' => $iLatestID, 'name' => $this->view->lname), 'comic', false);
    }
    
    // Get comic information
    $select = $view_comics->select();
    $select->from($view_comics, array('author', 'name', 'md5sum', 'upublished', 'idea', 'imgwidth', 'imgheight', 'avgrate'));
    $select->where('id = ?', $iComicID);
    $this->view->info = $view_comics->fetchRow($select)->toArray();

    $idtest = $this->getRequest()->getParam('id', false);
    $nametest = $this->getRequest()->getParam('name', '');

    if ($idtest === false || empty($nametest))
    {
      return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => $this->view->info['name']), 'comic', false);
    }

    $this->view->selected = $iComicID;

    // Previous comic ID
    $select = $comics->select();
    $select->from($comics, array('id', 'name'));
    $select->where('id < ?', $iComicID);
    $select->order(array('id DESC'));
    $select->limit(1);
    $result = $comics->fetchRow($select);
    if(!is_null($result))
    {
      $result = $result->toArray();
      $iPreviousID = $result['id'];
      $this->view->prevname = $result['name'];
    }
    else
    {
      $iPreviousID = $iFirstID;
    }
    $this->view->previous = $iPreviousID;

    // Next comic ID
    $select = $comics->select();
    $select->from($comics, array('id', 'name'));
    $select->where('id > ?', $iComicID);
    $select->order(array('id ASC'));
    $select->limit(1);
    $result = $comics->fetchRow($select);
    if(!is_null($result))
    {
      $result = $result->toArray();
      $iNextID = $result['id'];
      $this->view->nextname = $result['name'];
    }
    else
    {
      $iNextID = $iLatestID;
    }

    $this->view->next = $iNextID;

    $showcomments = $this->getRequest()->getParam('comments', 'show');
    $showcomments = $showcomments == 'hide' ? false : true;
    $this->view->showcomments = $showcomments;

    $urlhelper = new Zend_Controller_Action_Helper_Url();

    // Comment form
    $form = new comicForm();
    $form->add_asterisk = false;
    $form->setMethod(Zend_Form::METHOD_POST);
    $form->setAction($this->_request->getBaseUrl() . $urlhelper->url(array('id' => $iComicID, 'name' => $this->view->info['name'], 'comments' => null), 'comic', true));

    $submit = new Zend_Form_Element_Submit('submit');
    $submit->setLabel($this->tr->_('Add comment'));

    // Stupid spam bots will fill this fake email field
    // This leads to ignoring comment
    $stupid_spam_bots = new Zend_Form_Element_Text('email');
    $stupid_spam_bots->setRequired(false);
    $stupid_spam_bots->setLabel($this->tr->_('Email'));
    $stupid_spam_bots->addFilter('StringTrim');
    $stupid_spam_bots->addFilter('StringToLower');
    $stupid_spam_bots->addValidator('StringLength', false, array(7));
    $stupid_spam_bots->addValidator('EmailAddress');

    $name = new Zend_Form_Element_Text('name');
    $name->setRequired(true);
    $name->setLabel($this->tr->_('Nick'));
    $name->addFilter('StringTrim');
    $name->addValidator('NotEmpty', true);
    $name->addValidator('StringLength', false, array(3, 20));

    if(isset($cookie_settings['name']))
    {
      $name->setValue($cookie_settings['name']);
    }

    $comment = new Zend_Form_Element_Text('comment');
    $comment->setRequired(true);
    $comment->setLabel($this->tr->_('Comment'));
    $comment->addFilter('StringTrim');
    $comment->addValidator('NotEmpty', true);
    $comment->addValidator('StringLength', false, array(3, 300));

    $rates = array();
    $rates['-'] = '-';
    for($i=1; $i<6; $i++)
    {
      $rates[$i] = $i;
    }

    $rate = new Zend_Form_Element_Select('rate');
    $rate->setRequired(true);
    $rate->setLabel($this->tr->_('Rate comic'));
    $rate->addMultiOptions($rates);


    $form->addElement($stupid_spam_bots);

    $form->addElement($name);
    $form->addElement($comment);
    $form->addElement($rate);
    $form->addElement($submit);

    $form->populate(array('rate' => '-'));

    $form->addDisplayGroup(array('email'), 'mail', array('legend' => $this->tr->_('Do not fill fields in this fieldset'), 'class' => 'not-visible'));
    $form->addDisplayGroup(array('name', 'comment', 'rate', 'submit'), 'add');

    // Form POSTed
    if ($this->getRequest()->isPost())
    {
      if ($form->isValid($_POST))
      {
        $values = $form->getValues();

        if(!empty($values['email']) || !$comic_view_session->ok)
        {
          return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => $this->view->info['name']), 'comic', false);
        }

        if ($comicExists)
        {
          $useragent = $this->getRequest()->getHeader('User-Agent');
          $ip = $this->getRequest()->getServer('REMOTE_ADDR');
          
          // User posted less than 3 seconds ago from this IP?
          $select = $comments_raw->select();
          $select->from($comments_raw, array('c' => 'COUNT(*)'));
          $select->where('ipaddr = ?', $ip);
          $select->where('added BETWEEN (NOW() - INTERVAL 3 SECOND) AND NOW()');

          $result = $comments_raw->fetchRow($select)->toArray();
          if ((int)$result['c'] > 0)
          {
            return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => $this->view->info['name']), 'comic', false);
          }

          $bans = new Bans();
          $bans->cache_result = false;

          $select = $bans->select();
          $select->from($bans, array('c' => 'COUNT(id)'));
          $select->limit(1);

          // IPv6 address
          if (strpos($ip, ':') !== false)
          {
            $select->where('typeid = 6');
            $qip = "HEX('" . bin2hex(inet_pton($ip)) . "')";
          }
          else
          {
            $select->where('typeid = 4');
            $qip = "INET_ATON('" . $ip . "')";
          }

          $select->where("startip >= $qip");
          $select->where("endip <= $qip");
          
          $is_banned = $bans->fetchRow($select)->toArray();
          $is_banned = (bool)((int)$is_banned['c'] > 0 ? true : false);
          
          if ($is_banned)
          {
            return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => $this->view->info['name']), 'comic', false);
          }

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

          $is_spam = 0;

          if(!$this->_auth->hasIdentity())
          {
            $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
            
            require_once 'commenthandler.php';
            
            $values['comment'] = linkify($values['comment']);

            // This comic contains exact same nick and comment already?
            $select = $comments_raw->select();
            $select->from($comments_raw, array('c' => 'COUNT(*)'));
            $select->where('nick = ?', $values['name']);
            $select->where('comment = ?', $values['comment']);
            $select->where('comicid = ?', $iComicID);

            $result = $comments_raw->fetchRow($select)->toArray();
            if ((int)$result['c'] > 0)
            {
              return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => $this->view->info['name']), 'comic', false);
            }

            if (!empty($config->plugin->akismet->key))
            {
              $data = array(
                'user_ip'              => $ip,
                'user_agent'           => $useragent,
                'comment_type'         => 'comment',
                'comment_author'       => $values['name'],
                'comment_author_email' => '',
                'comment_content'      => $values['comment']
              );

              $akismet = new Zend_Service_Akismet($config->plugin->akismet->key, $this->getRequest()->getScheme() . '://' . $this->getRequest()->getHttpHost());

              if ($akismet->isSpam($data))
              {
                $is_spam = 1;
              }

            }

          }

          $rate = $values['rate'];

          if ($rate == '-')
          {
            $rate = new Zend_Db_Expr('NULL');
          }

          $insert = array(
            'nick' => $values['name'],
            'comment' => $values['comment'],
            'comicid' => $iComicID,
            'added' => new Zend_Db_Expr('NOW()'),
            'isstaff' => $this->_auth->hasIdentity() ? '1' : '0',
            'rate' => $rate,
            'country' => $country,
            'useragent' => $useragent,
            'ipaddr' => $ip,
            'isspam' => $is_spam
          );

          $this->_db->beginTransaction();

          try
          {
            $comments->insert($insert);

            $this->_db->commit();
            
            $cookie_settings['name'] = $values['name'];

            // Memorize user's name
            setcookie('zfce-settings', base64_encode(serialize($cookie_settings)), mktime(0, 0, 0, 1, 1, date('Y') + 30), '/');

            return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => $this->view->info['name']), 'comic', false);
          }
          catch (Exception $e)
          {
            $this->_db->rollBack();
            var_dump($e);
            die;
          }

        }
        else
        {
          return $this->_helper->redirector->gotoRoute(array('id' => 0, 'name' => ''), 'comic', false);
        }
      }
    }

    $this->view->commentform = $form;

    if ($showcomments)
    {
      // Get comic comments
      $select = $comments->select();
      $select->from($comments, array('nick', 'comment', 'uadded' => 'UNIX_TIMESTAMP(added)', 'rate', 'isstaff', 'country'));
      $select->where('comicid = ?', $iComicID);
      $select->order(array('added ASC', 'id ASC'));
      $result = $comments->fetchAll($select);

      $this->view->comments = array();
      if(!is_null($result))
      {
        $this->view->comments = $result->toArray();
      }

    }

  } // /function

  /**
   * Displays comic
   */
  public function displayComicAction()
  {
    // This session is used for against image leeching
    // If user doesn't have this session data he will be redirected to comic page
    $comic_view_session = new Zend_Session_Namespace('comic_view');

    if (!isset($comic_view_session->ok))
    {
      $comic_view_session->ok = false;
    }

    $comics = new Comics();

    // Get MD5 checksum from URL
    // It is used for anti-leeching because user can't guess it
    $iComicID = $this->getRequest()->getParam('id', false);

    // Get comic information
    $select = $comics->select();
    $select->from($comics, array('id'));
    $select->where('md5sum = ?', $iComicID);
    
    $result = $comics->fetchRow($select);
    
    if(!is_null($result))
    {
      $result->toArray();
      $iComicID = $result['id'];
      $comicExists = true;
    }
    else
    {
      $comicExists = false;
    }

    $response = $this->getResponse();

    // Comic doesn't exist
    if (!$comicExists)
    {
      $response->setHeader('HTTP/1.1', '404 Not Found');
      $response->setHeader('Status', '404 File not found');

      $this->view->data = '404';
    }
    else if($comic_view_session->ok === false)
    {
      return $this->_helper->redirector->gotoRoute(array('id' => $iComicID, 'name' => ''), 'comic', false);
    }
    else
    {
      // Show comic

      // Disable main layout
      $this->_helper->layout->disableLayout();

      // Get comic information
      $select = $comics->select();
      $select->from($comics, array('filemime', 'filedata', 'filesize', 'md5sum'));
      $select->where('id = ?', $iComicID);
      
      $result = $comics->fetchRow($select)->toArray();

      $response->setHeader('Content-Type', $result['filemime'], true);
      
      $this->view->data = $result['filedata'];
    }

  } // /function
  
  public function archiveAction()
  {
    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');
    $this->view->dateformat = $config->dateformat;

    $comics = new VIEW_Comics();

    // Year list
    $select = $comics->select();
    $select->from($comics, array('years' => 'YEAR(published)'));
    $select->order(array('years DESC'));
    $select->group(array('years'));
    $result = $comics->fetchAll($select);

    if(!is_null($result))
    {
      $result = $result->toArray();
      unset($select);

      $ylist = array();
      $yearswithoutall = array();

      $ylist[] = 'all';

      for ($i=0; $i < count($result); $i++)
      {
        $y = (int)$result[$i]['years'];
        $yearswithoutall[] = $y;
        $ylist[] = $y;
      } // /for

      $defaultYear = min($yearswithoutall);

      $this->view->years = $ylist;


      $by = $this->getRequest()->getParam('by', 'id');
      $by = strtolower($by);

      switch($by)
      {
        default:
          $by = 'id';
        break;

        case 'id':
        case 'published':
        case 'idea':
        case 'name':
        case 'author':
        break;

        case 'rating':
          $by = 'avgrate';
        break;
      } // /switch

      $ord = $this->getRequest()->getParam('order', 'desc');
      $ord = $ord == 'asc' ? 'ASC' : 'DESC';
      $this->view->order = $ord;

      $year = $this->getRequest()->getParam('year', $defaultYear);
      $this->view->selected_year = $year;

      $author = $this->getRequest()->getParam('author', null);
      $this->view->selected_author = $author;

      $idea = $this->getRequest()->getParam('idea', null);
      $this->view->selected_idea = $idea;

      // Get archive list
      $select = $comics->select();
      $select->from($comics, array('id', 'aid', 'name', 'upublished', 'idea', 'author', 'avgrate'));
      $select->order(array("$by $ord", 'id DESC'));

      if (in_array($year, $yearswithoutall))
      {
        $select->where('YEAR(published) = ?', $year);
      }

      if (ctype_digit($author))
      {
        $select->where('aid = ?', $author);
      }
      
      if(!empty($idea))
      {
        $select->where('idea = ?', $idea);
      }

      $result = $comics->fetchAll($select);

      if(!is_null($result))
      {
        $this->view->clist = $result->toArray();
      }
      else
      {
        $this->view->clist = array();
      }
    }
    else
    {
      $this->view->clist = array();
    }

  } // /function

  /**
   * Comic related RSS feeds
   */
  public function feedAction()
  {
    // Disable main layout
    $this->_helper->layout->disableLayout();

    $config = new Zend_Config_Ini(dirname(__FILE__) . '/../../../../config.ini', 'site');

    $category = $this->getRequest()->getParam('cat', 'comic');

    $entries = array();

    switch($category)
    {
      default:
      case 'comic':
        $comics = new Comics();

        $select = $comics->select();
        $select->from($comics, array('id', 'name', 'published'));
        $select->order(array('id DESC'));
        $select->limit(10);
        $result = $comics->fetchAll($select);


        if(!is_null($result))
        {
          $result = $result->toArray();

          for($i=0; $i<count($result); $i++)
          {
            $id = $result[$i]['id'];
            $name = $result[$i]['name'];
            $published = $result[$i]['published'];

            $link = $this->view->url(array('id' => $id, 'name' => $name, 'via' => 'feed'), 'comic', true);

            $entries[] = array(
              'title' => $name,
              'link' => 'http://' . $_SERVER['HTTP_HOST'] . $link,
              'description' => $name
            );
          } // /for

        }
      break;

      case 'comments':
        $comments = new VIEW_Comments();

        $select = $comments->select();
        $select->from($comments, array('nick', 'comment', 'uadded', 'comicid', 'rate', 'title', 'country'));
        $select->limit(10);
        $result = $comments->fetchAll($select);


        if(!is_null($result))
        {
          $result = $result->toArray();

          for($i=0; $i<count($result); $i++)
          {
            $nick = $result[$i]['nick'];
            $title = $result[$i]['title'];
            $comment = strip_tags($result[$i]['comment']);
            $comicid = $result[$i]['comicid'];
            $rate = $result[$i]['rate'];
            $country = $result[$i]['country'];

            $link = $this->view->url(array('id' => $comicid, 'name' => $title, 'via' => 'feed'), 'comic', true);

            $entries[] = array(
              'title' => "$title: [$country] $nick",
              'link' => 'http://' . $this->getRequest()->getServer('HTTP_HOST') . $link . '#comments',
              'description' => $comment,
              'content' => sprintf("%s [%s] %s (%s): %s", $title, $country, $nick, $rate, $comment)
            );
          } // /for

        }

      break;

      case 'last-comic-comments':
        $comments = new VIEW_Comments();
        $comics = new Comics();

        // Get latest comic ID
        $select = $comics->select();
        $select->from($comics, array('id', 'name'));
        $select->order(array('id DESC'));
        $select->limit(1);
        $result = $comics->fetchRow($select);

        if(!is_null($result))
        {
          $cinfo = $result->toArray();
          unset($result);
          unset($select);

          $select = $comments->select();
          $select->from($comments, array('nick', 'comment', 'uadded', 'comicid', 'rate', 'title', 'country'));
          $select->where('comicid = ?', $cinfo['id']);
          $select->limit(10);
          $result = $comments->fetchAll($select);


          if(!is_null($result))
          {
            $result = $result->toArray();

            for($i=0; $i<count($result); $i++)
            {
              $nick = $result[$i]['nick'];
              $title = $result[$i]['title'];
              $comment = strip_tags($result[$i]['comment']);
              $comicid = $result[$i]['comicid'];
              $rate = $result[$i]['rate'];
              $country = $result[$i]['country'];

              $link = $this->view->url(array('id' => $cinfo['id'], 'name' => $cinfo['name'], 'via' => 'feed'), 'comic', true);

              $entries[] = array(
                'title' => "$title: [$country] $nick",
                'link' => 'http://' . $this->getRequest()->getServer('HTTP_HOST') . $link . '#comments',
                'description' => $comment,
                'content' => sprintf("%s [%s] %s (%s): %s", $title, $country, $nick, $rate, $comment)
              );
            } // /for

          } // /if

        } // /if

      break;
    } // /switch

    $feed = array(
      'charset' => 'UTF-8',
      'title' => $config->name,
      'link' => 'http://' . $this->getRequest()->getServer('HTTP_HOST'),
      'entries' => $entries
    );

    $feeddata = Zend_Feed::importArray($feed, 'rss');
    $feeddata->send();

  } // /function
  
  /**
   * Get random comic
   */
  public function randomAction()
  {
    // Disable main layout
    $this->_helper->layout->disableLayout();

    $comics = new Comics();
    $comics->cache_result = false;

    $select = $comics->select();
    $select->from($comics, array('id', 'name'));
    $select->order(array(new Zend_Db_Expr('RAND()')));
    $select->limit(1);
    $result = $comics->fetchRow($select)->toArray();

    return $this->_helper->redirector->gotoRoute(array('id' => $result['id'], 'name' => $this->view->escape($result['name'])), 'comic', false);
  } // /function

  /**
   * Character listing
   */
  public function charactersAction()
  {
    $pages = new Pages();
    $this->view->text = $pages->getPageContents('characters');

    $characters = new Characters();

    $select = $characters->select();
    $select->from($characters, array('name', 'descr', 'md5sum'));
    $result = $characters->fetchAll($select);

    if (!is_null($result))
    {
      $result = $result->toArray();
      $this->view->characters = $result;
    }
    else
    {
      $this->view->characters = array();
    }

  } // /function

  /**
   * Display character image
   */
  public function displayCharacterAction()
  {
    $characters = new Characters();

    $id = $this->getRequest()->getParam('id', '');

    $select = $characters->select();
    $select->from($characters, array('filesize', 'filemime', 'filedata'));
    $select->where('md5sum = ?', $id);
    $result = $characters->fetchRow($select);

    $response = $this->getResponse();

    if (!is_null($result))
    {
      $result->toArray();

      // Disable main layout
      $this->_helper->layout->disableLayout();

      $response->setHeader('Content-Type', $result['filemime'], true);

      $this->view->data = $result['filedata'];
    }
    else
    {
      $response->setHeader('HTTP/1.1', '404 Not Found');
      $response->setHeader('Status', '404 File not found');
      $this->view->data = '404';
    }

  } // /function

} // /class
