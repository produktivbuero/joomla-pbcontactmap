<?php
/**
 * @package    PB Contact Map
 *
 * @author     Sebastian Brümmer <sebastian@produktivbuero.de>
 * @copyright  Copyright (C) 2018 *produktivbüro . All rights reserved
 * @license    GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * PB Contact Map plugin
 *
 * @package  PB Contact Map
 * 
 * @since    0.9.0
 */
class plgContentPbContactMap extends CMSPlugin
{
  /**
   * SETTINGS
   */
  const LEAFLET = '1.3.4'; // Version of https://leafletjs.com

  /**
   * Application object
   *
   * @var    CMSApplication
   * 
   * @since  0.9.0
   */
  protected $app;

  /**
   * Database object
   *
   * @var    DatabaseDriver
   * 
   * @since  0.9.0
   */
  protected $db;

  /**
   * Load the language file on instantiation
   *
   * @var    boolean
   * 
   * @since  0.9.0
   */
  protected $autoloadLanguage = true;

  /**
   * This function is called on initialization
   *
   * @return  void.
   *
   * @since   0.9.0
   */

  public function __construct(&$subject, $config = array())
  {
    parent::__construct($subject, $config);

    $this->contactmap = array();
    $this->allowed_context = array('com_contact.category', 'com_contact.contact');

    // plugin parameters
    $params = new JRegistry($config['params']);
    $this->contactmap['single'] = $params->get('single', 1);
    $this->contactmap['category'] = $params->get('category', 0);
    $this->contactmap['layer'] = $params->get('layer', 1);
    $this->contactmap['showon'] = $params->get('showon', 'all');
    $this->contactmap['link'] = $params->get('link', 1);
    $this->contactmap['name'] = $params->get('name', 1);


    // global settings object
    $doc = JFactory::getDocument();
    
    $settings = array(
      'layer' => $this->contactmap['layer']
    );
    
    $doc->addScriptOptions('plg_content_pbcontactmap', $settings);

    // helper
    JLoader::register('PlgContentPBContactMapHelper', __DIR__ . '/helper.php');
  }


  /**
   * Event that is called after the content is saved into the database
   *
   * @param   string  $context  The context of the content passed to the plugin (added in 1.6)
   * @param   object  $row      The content object
   * @param   bool    $isNew    If the content has just been created
   *
   * @return  void.
   *
   * @since   0.9.0
   */
  public function onContentAfterSave($context, $row, $isNew)
  {
    // fast fail
    if ( !$this->app->isAdmin() || $context != 'com_contact.contact' ) {
      return;
    }

    // geocode
    $place = PlgContentPBContactMapHelper::getApiData($row);

    // insert/update database record
    $result = PlgContentPBContactMapHelper::insertRecord($place, $row->id, $isNew);

    return;
  }


  /**
   * This is an event that is called right after the content is deleted
   *
   * @param   string  $context  The context of the content passed to the plugin (added in 1.6)
   * @param   object  $row      The content object
   *
   * @return  void.
   *
   * @since   0.9.0
   */
  public function onContentAfterDelete($context, $row)
  {
    // fast fail
    if ( !$this->app->isAdmin() || $context != 'com_contact.contact' ) {
      return;
    }

    // delete from database record
    $result = PlgContentPBContactMapHelper::deleteRecord($row->id);
  }

  /**
   * This is a request for information that should be placed
   * immediately before the generated content.
   *
   * @param   string   $context  The context of the content being passed to the plugin
   * @param   object   &$row     The content object
   * @param   mixed    &$params  The row params
   * @param   integer  $page     The 'page' number
   *
   * @return  string
   *
   * @since   0.9.0
   */
  public function onContentBeforeDisplay($context, &$row, &$params, $page=0)
  {
    // fast fail
    if ( $this->app->isAdmin() || !in_array($context, $this->allowed_context) || $context == 'com_contact.categories' ) {
      return '';
    }

    // check settings
    if ( ($context == 'com_contact.category' && $this->contactmap['category'] != 1) || ($context == 'com_contact.contact' && $this->contactmap['single'] != 1) || ($this->contactmap['showon'] == 'featured' && $row->featured == 0) ) {
      return '';
    }

    // check data
    if ( empty(JFactory::getDocument()->getScriptOptions('plg_content_pbcontactmap_places')) ) {
      return '';
    }
    
    $id = $row->id;
    return PlgContentPBContactMapHelper::getShortcode($id);
  }

  /**
   * This is a request for information that should be placed immediately
   * after the generated content.
   *
   * @param   string   $context  The context of the content being passed to the plugin
   * @param   object   &$row     The content object
   * @param   object   &$params  The row params
   * @param   integer  $page     The 'page' number
   *
   * @return  string
   *
   * @since   0.9.0
   */
  public function onContentAfterDisplay($context, &$row, &$params, $page=0)
  {
    // fast fail
    if ( $this->app->isAdmin() || !in_array($context, $this->allowed_context) || $context == 'com_contact.categories' ) {
      return '';
    }

    // check settings
    if ( ($context == 'com_contact.category' && $this->contactmap['category'] != 2) || ($context == 'com_contact.contact' && $this->contactmap['single'] != 2) || ($this->contactmap['showon'] == 'featured' && $row->featured == 0) ) {
      return '';
    }

    // check data
    if ( empty(JFactory::getDocument()->getScriptOptions('plg_content_pbcontactmap_places')) ) {
      return '';
    }
    
    $id = $row->id;
    return PlgContentPBContactMapHelper::getShortcode($id);
  }


  /**
   * This is the first stage in preparing content for output
   * 
   * @param   string   $context  The context of the content being passed to the plugin.
   * @param   object   &$row     The content object
   * @param   mixed    &$params  The row params
   * @param   integer  $page     The 'page' number
   *
   * @return  void.
   *
   * @since   0.9.0
   */
  public function onContentPrepare($context, &$row, &$params, $page = 0)
  {
    // replace shortcode
    if ( JString::strpos($row->text, '{plg_content_contactmap') !== false ) {
      $insert = PlgContentPBContactMapHelper::getShortcode();
      
      $regex = '/{plg_content_contactmap}/im';
      $row->text = preg_replace($regex, $insert, $row->text);
    }

    // fast fail
    if ( $this->app->isAdmin() || !in_array($context, $this->allowed_context) ) {
      return;
    }

    // check settings
    if ( $this->contactmap['showon'] == 'featured' && isset($row->featured) && $row->featured == 0 ) {
      return;
    }

    $doc = JFactory::getDocument();

    // get place
    if ( isset($row->id) )
    {
      $place = PlgContentPBContactMapHelper::getRecord($row->id);

      if ($place === null) {
        return;
      }

      $link = '';
      if ($this->contactmap['link']) {
        $link = JRoute::_('index.php?option=com_contact&view=contact&id='.$row->slug.'&catid='.$row->catid);
      }

      $name = '';
      if ($this->contactmap['name']) {
        $name = $row->name;
      }

      $data = array(
        'contact_id' => $place->contact_id,
        'lat' => $place->lat,
        'lon' => $place->lon,
        'boundingbox' => $place->boundingbox,
        'osm_id' => $place->osm_id,
        'osm_type' => $place->osm_type,
        'class' => $place->class,
        'link' => $link,
        'name' => $name
      );


      // global data object
      $options = $doc->getScriptOptions('plg_content_pbcontactmap_places');
      array_push($options, $data);
      $doc->addScriptOptions('plg_content_pbcontactmap_places', $options);
    }

    // assets
    $doc->addScript(JURI::base(true).'/media/plg_content_pbcontactmap/js/leaflet.js', array('version' => self::LEAFLET));
    $doc->addStyleSheet(JURI::base(true).'/media/plg_content_pbcontactmap/css/leaflet.css', array('version' => self::LEAFLET));

    $doc->addScript(JURI::base(true).'/media/plg_content_pbcontactmap/js/basics.js', array('version' => 'auto'));
    $doc->addStyleSheet(JURI::base(true).'/media/plg_content_pbcontactmap/css/basics.css', array('version' => 'auto'));
  }
}
