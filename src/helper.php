<?php
/**
 * @package    PB Contact Map
 *
 * @author     Sebastian Brümmer <sebastian@produktivbuero.de>
 * @copyright  Copyright (C) 2018 *produktivbüro . All rights reserved
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\Utilities\ArrayHelper;

/**
 * Helper for PB Contact Map
 *
 * @since  0.9.0
 */
abstract class PlgContentPBContactMapHelper
{
  /**
   * SETTINGS
   * cf. https://wiki.openstreetmap.org/wiki/Nominatim
   */
  const ENDPOINT = 'https://nominatim.openstreetmap.org/search.php';

  /**
   * Request API
   *
   * @param   object  $row  A JTableContent object
   * @param   string  $format  response format [html|xml|json|jsonv2]
   *
   * @return  object
   *
   * @since   0.9.0
   */
  public static function getApiData($row, $format = 'json', $install = false)
  {
    $result = new stdClass();

    // required fields
    if (empty($row->postcode) || empty($row->suburb)) {
      $message = PlgContentPBContactMapHelper::formatMessage(JText::_('PLG_CONTENT_PBCONTACTMAP_ERROR_FIELDS'));
      JFactory::getApplication()->enqueueMessage($message, 'warning');

      return $result; // return empty
    }
    
    $street = preg_split('/\r\n|\r|\n/', $row->address);
    $street = end($street); // get last row of adress

    $request = array (
                'street' => $street,
                'city' => $row->suburb,
                'postalcode' => $row->postcode,
                'state' => $row->state,
                'country' => $row->country,
                'format' => $format,
                'limit' => 1
               );
    $params = http_build_query($request);

    $url = self::ENDPOINT.'?'.$params;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_REFERER, JUri::base()); // Requirement of Nominatim Usage Policy

    $output = curl_exec($ch);

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $response = self::parseStatusCode($status);

    if ( $output !== false && $status === 200 )
    {

      $place = json_decode($output);

      if (empty($place))
      {
        $address = $request;
        unset($address['format'], $address['limit']);
        
        if ($install)
        {
          $url = 'index.php?option=com_contact&view=contacts&filter[search]=' . urlencode($row->name);
          $name = $row->name;
        }
        else
        {
          $params = http_build_query($address);
          $url = self::ENDPOINT.'?'.$params;
          $name = parse_url($url)['host'];
        }

        $address = implode(', ', array_filter($address, function($value) { return $value !== ''; }));

        $message = PlgContentPBContactMapHelper::formatMessage(JText::sprintf('PLG_CONTENT_PBCONTACTMAP_ERROR_NOMATCH', $address, $url, $name));
        JFactory::getApplication()->enqueueMessage($message, 'warning');

        return $result; // no matches
      }

      $place = $place[0]; // get only first match

      $result->contact_id = $row->id;
      $result->query = $params;
      $place->boundingbox = @json_encode($place->boundingbox); // array to string

      $result = (object) array_merge((array) $result, (array) $place);
    }
    else
    {
      $errno = curl_errno($ch);
      $error = curl_strerror($errno);

      $message = array();
      array_push($message, JText::_('PLG_CONTENT_PBCONTACTMAP_ERROR_CURL'));
      array_push($message, 'HTTP: [' . $status . ']' . $response . ' ('.$url.')');
      if ($errno != 0) array_push($message, 'cURL: [' . $errno . '] '. $error);

      $message = self::formatMessage($message);
      JFactory::getApplication()->enqueueMessage($message, 'error');
    }

    curl_close($ch);

    return $result;
  }


  /**
   * Get database records
   *
   * @param   string  $contact_id  The contact item id
   *
   * @return  bool
   *
   * @since   0.9.0
   */
  public static function getRecord($contact_id)
  {
    $result = new stdClass();

    if ( $contact_id  )
    {
      $db = JFactory::getDbo();
      $query = $db->getQuery(true);

      $conditions = array(
        $db->quoteName('contact_id') . ' = ' . $contact_id
      );

      $query->select('*');
      $query->from($db->quoteName('#__contact_pbcontactmap'));
      $query->where($conditions);
      $db->setQuery($query);

      $result = $db->loadObject();
    }

    return $result;
  }

  /**
   * Insert or update database records
   *
   * @param   object  $place       Data object
   * @param   string  $contact_id  The contact item id
   * @param   bool    $isNew       Check if contact item was added
   *
   * @return  bool
   *
   * @since   0.9.0
   */
  public static function insertRecord($place, $contact_id, $isNew = false)
  {
    $db = JFactory::getDbo();

    if (empty((array) $place))
    {
      $result = self::deleteRecord($contact_id);
      return true;
    }
    elseif ($isNew)
    {
      return $db->insertObject('#__contact_pbcontactmap', $place);
    }
    else
    {
      $query = $db->getQuery(true);
      $query->select('COUNT(*)')->from($db->quoteName('#__contact_pbcontactmap'))->where($db->quoteName('contact_id')." = ".$contact_id);
      $count = $db->setQuery($query)->loadResult();

      if ($count > 0)
      {
        return $db->updateObject('#__contact_pbcontactmap', $place, 'contact_id');
      }
      else
      {
        return $db->insertObject('#__contact_pbcontactmap', $place);
      }
    }

  }


  /**
   * Delete database records
   *
   * @param   string  $contact_id  The contact item id
   *
   * @return  bool
   *
   * @since   0.9.0
   */
  public static function deleteRecord($contact_id)
  {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);

    $conditions = array(
      $db->quoteName('contact_id') . ' = ' . $contact_id
    );

    $query->delete($db->quoteName('#__contact_pbcontactmap'));
    $query->where($conditions);

    return $db->setQuery($query)->execute();
  }


  /**
   * Format system message
   *
   * @param   string|array  $message  Message
   *
   * @return  string
   *
   * @since   0.9.0
   */
  public static function formatMessage($message)
  {
    $plugin =  JText::_('PLG_CONTENT_PBCONTACTMAP');
    $message = (array) $message;
    $text = implode('</p><p>', $message);

    return '<p>' . $text . '</p>';
  }


  /**
   * Format system message
   *
   * @param   int  $id  Contact ID
   *
   * @return  string
   *
   * @since   0.9.0
   */
  public static function getShortcode($id = null)
  {
    $shortcode = '';

    if ($id) $shortcode .= '<div id="pbcontactmap.id.'. $id .'" data-id="'. $id .'" class="pbcontactmap is-loading">';
    else $shortcode .= '<div id="pbcontactmap.all" class="pbcontactmap is-loading">';
    $shortcode .= '<span>Open Street Map loading...</span>';
    $shortcode .= '</div>';

    return $shortcode;
  }

  /**
   * Get status code text
   *
   * @param   string  $code  HTTP status code
   *
   * @return  string
   *
   * @since   0.9.0
   */
  public static function parseStatusCode($code)
  {
    $http_codes = array (
      100 => 'Continue',
      101 => 'Switching Protocols',
      102 => 'Processing',
      200 => 'OK',
      201 => 'Created',
      202 => 'Accepted',
      203 => 'Non-Authoritative Information',
      204 => 'No Content',
      205 => 'Reset Content',
      206 => 'Partial Content',
      207 => 'Multi-Status',
      300 => 'Multiple Choices',
      301 => 'Moved Permanently',
      302 => 'Found',
      303 => 'See Other',
      304 => 'Not Modified',
      305 => 'Use Proxy',
      306 => 'Switch Proxy',
      307 => 'Temporary Redirect',
      400 => 'Bad Request',
      401 => 'Unauthorized',
      402 => 'Payment Required',
      403 => 'Forbidden',
      404 => 'Not Found',
      405 => 'Method Not Allowed',
      406 => 'Not Acceptable',
      407 => 'Proxy Authentication Required',
      408 => 'Request Timeout',
      409 => 'Conflict',
      410 => 'Gone',
      411 => 'Length Required',
      412 => 'Precondition Failed',
      413 => 'Request Entity Too Large',
      414 => 'Request-URI Too Long',
      415 => 'Unsupported Media Type',
      416 => 'Requested Range Not Satisfiable',
      417 => 'Expectation Failed',
      418 => 'I\'m a teapot',
      422 => 'Unprocessable Entity',
      423 => 'Locked',
      424 => 'Failed Dependency',
      425 => 'Unordered Collection',
      426 => 'Upgrade Required',
      449 => 'Retry With',
      450 => 'Blocked by Windows Parental Controls',
      500 => 'Internal Server Error',
      501 => 'Not Implemented',
      502 => 'Bad Gateway',
      503 => 'Service Unavailable',
      504 => 'Gateway Timeout',
      505 => 'HTTP Version Not Supported',
      506 => 'Variant Also Negotiates',
      507 => 'Insufficient Storage',
      509 => 'Bandwidth Limit Exceeded',
      510 => 'Not Extended'
    );

    return array_key_exists($code, $http_codes) ? '[' . $code . '] ' . $http_codes[$code] : $code;
  }
}
