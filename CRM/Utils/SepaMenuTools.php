<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit                         |
| Copyright (C) 2013-2014 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * This class extends the current CiviCRM lock
 * by a security mechanism to prevent a process from
 * acquiring two or more locks.
 * This, due to the nature of the underlying implementation
 * would RELEASE the previously acquired lock
 */
class CRM_Utils_SepaMenuTools {

  /**
   * creates a new, unique navID for the CiviCRM menu
   *
   * It will consider the IDs from the database,
   *  as well as the 'volatile' ones already injected into the menu
   */
  static function createUniqueNavID($menu) {
    $max_stored_navId = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
    $max_current_navId = self::getMaxNavID($menu);
    return max($max_stored_navId, $max_current_navId) + 1;
  }

  /**
   * crawls the menu tree to find the (currently) biggest navID
   */
  static function getMaxNavID($menu) {
    $max_id = 1;
    foreach ($menu as $entry) {
      $max_id = max($max_id, $entry['attributes']['navID']);
      if (!empty($entry['child'])) {
        $max_id_children = self::getMaxNavID($entry['child']);
        $max_id = max($max_id, $max_id_children);
      }
    }
    return $max_id;
  }


  /**
   * Add the given menu item to the CiviCRM navigation menu if it does not exist yet.
   * @param array parent_params the params array into whose 'child' attribute the new item will be added.
   * @param array $attributes the attributes array to be added to the navigation menu
   */
  static function addNavigationMenuEntry(&$parent_params, $menu_entry_attributes) {
    // see if it is already in the menu...
    $menu_item_search = array('url' => $menu_entry_attributes['url']);
    $menu_items = array();
    CRM_Core_BAO_Navigation::retrieve($menu_item_search, $menu_items);
    
    if (empty($menu_items)) {
      // it's not already contained, so we want to add it to the menu

      // insert at the bottom
      $parent_params['child'][] = array(
          'attributes' => $menu_entry_attributes);
    }
  }  
}