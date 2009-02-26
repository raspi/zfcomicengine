<?php

/**
 * Zend_Service_Team_Cymru
 * Get IP address, Autonymous System and RIR + LIR info
 *
 * Uses DNS
 * http://www.team-cymru.org/Services/ip-to-asn.html#dns
 *
 * - Local Internet Registry (LIR=ISP) IP Block
 * - Regional Internet Registry (RIR) Name
 * - AS Peers
 * - Country
 * - AS/IP Block/LIR registered date
 *
 * http://en.wikipedia.org/wiki/Autonomous_system_(Internet)
 * http://en.wikipedia.org/wiki/Regional_Internet_Registry
 *
 * <example>
 * <code>
 *   $ip = $this->getRequest()->getServer('REMOTE_ADDR');
 *
 *   $tc = new Zend_Service_Team_Cymru();
 *   $tcinfo = $tc->getIpInfo($ip);
 *   print_r($tcinfo);
 * </code>
 * </example>
 *
 * @author Pekka Järvinen 2009
 * @package Zend_Service_Team_Cymru
 * @version $Id$
 */
class Zend_Service_Team_Cymru extends Zend_Service_Abstract
{
  /**
   * address suffix for IPv4 queries
   * @var string
   */
  protected $_ipv4 = '.origin.asn.cymru.com';

  /**
   * address suffix for IPv6 queries
   * @var string 
   */
  protected $_ipv6 = '.origin6.asn.cymru.com';

  /**
   * address suffix for AS peer queries
   * @var string
   */
  protected $_peer = '.peer.asn.cymru.com';

  /**
   * address suffix for AS information queries
   * @var string
   */
  protected $_asn = '.asn.cymru.com';
  
  /**
   * @return void
   */
  public function __construct()
  {
  }
  
  /**
   * Resolve IPv4 address to dotted in-addr.arpa format
   *
   * Example: 1.2.3.4 becomes 4.3.2.1
   *
   * @param string IPv4 Address
   * @return string
   */
  private function _resolveIpv4($ip)
  {
    return (string) join('.', array_reverse(explode('.', $ip)));
  }
  
  /**
   * Resolve IPv6 address to dotted in-addr.arpa format
   * Example:
   * 2001:4860:b002::68
   *   becomes 8.6.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.2.0.0.b.0.6.8.4.1.0.0.2
   *
   * @param string IPv6 Address
   * @return string
   */
  private function _resolveIpv6($ip)
  {
    if(!function_exists('inet_pton'))
    {
      throw new Zend_Service_Exception('Error: function inet_pton() not found.');
    }

    return (string) join('.', str_split(strrev(bin2hex(inet_pton($ip)))));
  }

  /**
   * DNS Query
   *
   * @param string Address
   * @return string result of TXT query
   */
  private function _ask($address)
  {
    if(!function_exists('dns_get_record'))
    {
      throw new Zend_Service_Exception('Error: function dns_get_record() not found.');
    }

    $result = dns_get_record($address, DNS_TXT);

    if (is_array($result))
    {
      return (string)trim($result[0]['txt']);
    }

    return '';
  }

  /**
   * Simple Whois Client
   *
   * @param string Server address
   * @param string Query string
   * @return string
   */
  private function _whoisClient($server, $query = null)
  {
    // Connect to the given whois server
    if (false === ($fp = @fsockopen($server, 43, $errno, $errstr, 5)))
    {
      return '';
    }

    fputs($fp, "$query\n");

    $response = '';

    while (false === feof($fp))
    {
      $response .= fread($fp, 128);
    }

    fclose($fp);
    
    return (string) preg_replace("\r|\r\n\|\n\r", "\n", $response);
  }

  /**
   * Get more accurate country information from RIPE NCC Whois server
   *
   * @param string
   * @return string countrycode
   */
  private function _whoisRIPE ($ip)
  {
      $result = $this->_whoisClient('whois.ripe.net', $ip);
      
      foreach(explode("\n", $result) as $line)
      {
        $line = trim($line);
        
        // Ignore
        if (substr($line, 0, 1) == "%" || empty($line))
        {
          continue;
        }

        if (preg_match("/country:\s+([^\s]+)/i", $line, $m))
        {
          return (string) strtolower($m[1]);
        }
      }

    return '';
  }

  /**
   * Get's information about IP address
   * - AS Number
   * - IP Block
   * - Country
   * - RIR
   * - Registered Year
   * - Registered Month
   * - Registered Day
   *
   * @param string IPv6 or IPv6 address
   * @return array
   */
  public function getIpInfo($ip)
  {
    $validator = new Zend_Validate_Ip();

    if (!$validator->isValid($ip))
    {
      throw new Zend_Service_Exception('Invalid IP Address');
    }

    // IPv6 address
    if (strpos($ip, ':') !== false && strpos($ip, '.') !== true)
    {
      $ask = $this->_resolveIpv6($ip) . $this->_ipv6;
    }
    else
    {
      // IPv4 address

      // May be "::1.2.3.4" style
      $ip = str_replace(':', '', $ip);

      $ask = $this->_resolveIpv4($ip) . $this->_ipv4;
    }

    $result = $this->_ask($ask);

    if (preg_match('/^(\d+) \| ([\da-f\.\/:]+) \| (\w+) \| (\w+) \| (\d+)-(\d+)-(\d+)$/i', $result, $m))
    {
      list(, $asnumber, $ipblock, $country, $rir, $year, $month, $day) = $m;
      $country = strtolower($country);
      $rir = strtolower($rir);

      if ($country === 'eu' && $rir === 'ripencc')
      {
        $whois_country = $this->_whoisRIPE ($ip);
        $country = !empty($whois_country) ? $whois_country : $country;
      }

      return array('as' => (int)$asnumber, 'country' => $country, 'ipblock' => $ipblock, 'rir' => $rir, 'year' => (int)$year, 'month' => (int)$month, 'day' => (int)$day);
    }
    else
    {
      return array();
    }
      
  }
  
  /**
   * Get AS information
   *
   * @param int AS Number
   */
  public function getAsInfo($as)
  {
    $ask = "AS{$as}{$this->_asn}";

    $result = $this->_ask($ask);

    if (preg_match('/^(\d+) \| (\w+) \| (\w+) \| (\d+)-(\d+)-(\d+) \| (.*)$/i', $result, $m))
    {
      list(, $asnumber, $country, $rir, $year, $month, $day, $name) = $m;
      $country = strtolower($country);
      $rir = strtolower($rir);
      
      return array('as' => (int)$asnumber, 'country' => $country, 'rir' => $rir, 'year' => (int)$year, 'month' => (int)$month, 'day' => (int)$day, 'name' => $name);
    }
    else
    {
      return array();
    }
  }

  /**
   * Get AS peers
   *
   * @param string IP address
   * @return array
   */
  public function getPeerInfo($ip)
  {
    $validator = new Zend_Validate_Ip();

    if (!$validator->isValid($ip))
    {
      throw new Zend_Service_Exception("Invalid IP Address");
    }

    // IPv6 address
    if (strpos($ip, ':') !== false && strpos($ip, '.') !== true)
    {
      $ask = $this->_resolveIpv6($ip) . $this->_peer;
    }
    else
    {
      // IPv4 address

      // May be "::1.2.3.4" style
      $ip = str_replace(':', '', $ip);

      $ask = $this->_resolveIpv4($ip) . $this->_peer;
    }

    $result = $this->_ask($ask);
    
    if (preg_match('/^([^\|]+)\| ([\da-f\.\/:]+) \| (\w+) \| (\w+) \| (\d+)-(\d+)-(\d+)$/i', $result, $m))
    {
      list(, $asnumbers, $ipblock, $country, $rir, $year, $month, $day) = $m;
      $asnumbers = explode(' ', trim($asnumbers));
      $country = strtolower($country);
      $rir = strtolower($rir);

      if ($country === 'eu' && $rir === 'ripencc')
      {
        $whois_country = $this->_whoisRIPE($ip);
        $country = !empty($whois_country) ? $whois_country : $country;
      }

      return array('as' => $asnumbers, 'country' => $country, 'rir' => $rir, 'year' => (int)$year, 'month' => (int)$month, 'day' => (int)$day);
    }
    else
    {
      return array();
    }

  }

}