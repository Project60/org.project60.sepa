<?php
/*-------------------------------------------------------+
| SYSTOPIA CUSTOM DATA HELPER                            |
| Copyright (C) 2018-2020 SYSTOPIA                       |
| Author: B. Endres (endres@systopia.de)                 |
| Source: https://github.com/systopia/Custom-Data-Helper |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

class CRM_Sepa_CustomData {
  const CUSTOM_DATA_HELPER_VERSION   = '0.8';
  const CUSTOM_DATA_HELPER_LOG_LEVEL = 0;
  const CUSTOM_DATA_HELPER_LOG_DEBUG = 1;
  const CUSTOM_DATA_HELPER_LOG_INFO  = 3;
  const CUSTOM_DATA_HELPER_LOG_ERROR = 5;

  /** caches custom field data, indexed by group name */
  protected static $custom_group2name       = NULL;
  protected static $custom_group2table_name = NULL;
  protected static $custom_group_cache      = array();
  protected static $custom_group_spec_cache = array();
  protected static $custom_field_cache      = array();

  protected $ts_domain = NULL;
  protected $version   = self::CUSTOM_DATA_HELPER_VERSION;

  public function __construct($ts_domain) {
    $this->ts_domain = $ts_domain;
  }

  /**
   * Log a message if the log level is high enough
   */
  protected function log($level, $message) {
    if ($level >= self::CUSTOM_DATA_HELPER_LOG_LEVEL) {
      CRM_Core_Error::debug_log_message("CustomDataHelper {$this->version} ({$this->ts_domain}): {$message}");
    }
  }

  /**
   * will take a JSON source file and synchronise the
   * generic entity data with those specs
   */
  public function syncEntities($source_file) {
    $data = json_decode(file_get_contents($source_file), TRUE);
    if (empty($data)) {
      throw new Exception("syncOptionGroup::syncOptionGroup: Invalid specs");
    }

    foreach ($data['_entities'] as $entity_data) {
      $this->translateStrings($entity_data);
      $entity = $this->identifyEntity($data['entity'], $entity_data);

      if (empty($entity)) {
        // create OptionValue
        $entity = $this->createEntity($data['entity'], $entity_data);
      } elseif ($entity == 'FAILED') {
        // Couldn't identify:
        $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update {$data['entity']}: " . json_encode($entity_data));
      } else {
        // update OptionValue
        $this->updateEntity($data['entity'], $entity_data, $entity);
      }
    }
  }

  /**
   * will take a JSON source file and synchronise the
   * OptionGroup/OptionValue data in the system with
   * those specs
   */
  public function syncOptionGroup($source_file) {
    $data = json_decode(file_get_contents($source_file), TRUE);
    if (empty($data)) {
      throw new Exception("syncOptionGroup::syncOptionGroup: Invalid specs");
    }

    // first: find or create option group
    $this->translateStrings($data);
    $optionGroup = $this->identifyEntity('OptionGroup', $data);
    if (empty($optionGroup)) {
      // create OptionGroup
      $optionGroup = $this->createEntity('OptionGroup', $data);
    } elseif ($optionGroup == 'FAILED') {
      // Couldn't identify:
      $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update OptionGroup: " . json_encode($data));
      return;
    } else {
      // update OptionGroup
      $this->updateEntity('OptionGroup', $data, $optionGroup, array('is_active'));
    }

    // now run the update for the OptionValues
    foreach ($data['_values'] as $optionValueSpec) {
      $this->translateStrings($optionValueSpec);
      $optionValueSpec['option_group_id'] = $optionGroup['id'];
      $optionValueSpec['_lookup'][] = 'option_group_id';
      $optionValue = $this->identifyEntity('OptionValue', $optionValueSpec);

      if (empty($optionValue)) {
        // create OptionValue
        $optionValue = $this->createEntity('OptionValue', $optionValueSpec);
      } elseif ($optionValue == 'FAILED') {
        // Couldn't identify:
        $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update OptionValue: " . json_encode($optionValueSpec));
      } else {
        // update OptionValue
        $this->updateEntity('OptionValue', $optionValueSpec, $optionValue, array('is_active'));
      }
    }
  }


  /**
   * will take a JSON source file and synchronise the
   * CustomGroup/CustomField data in the system with
   * those specs
   */
  public function syncCustomGroup($source_file) {
    $force_update = FALSE;
    $data = json_decode(file_get_contents($source_file), TRUE);
    if (empty($data)) {
      throw new Exception("CRM_Utils_CustomData::syncCustomGroup: Invalid custom specs");
    }

    // if extends_entity_column_value, make sure it's sensible data
    if (isset($data['extends_entity_column_value'])) {
      $force_update = TRUE; // this doesn't get returned by the API, so differences couldn't be detected
      if ($data['extends'] == 'Activity') {
        $extends_list = array();
        foreach ($data['extends_entity_column_value'] as $activity_type) {
          if (!is_numeric($activity_type)) {
            $activity_type = self::getOptionValue('activity_type', $activity_type, 'name');
          }
          if ($activity_type) {
            $extends_list[] = $activity_type;
          }
        }
        $data['extends_entity_column_value'] = $extends_list;
      }

      if (is_array($data['extends_entity_column_value'])) {
        $data['extends_entity_column_value'] = CRM_Utils_Array::implodePadded($data['extends_entity_column_value']);
      }
    }


    // first: find or create custom group
    $this->translateStrings($data);
    $customGroup = $this->identifyEntity('CustomGroup', $data);
    if (empty($customGroup)) {
      // create CustomGroup
      $customGroup = $this->createEntity('CustomGroup', $data);
    } elseif ($customGroup == 'FAILED') {
      // Couldn't identify:
      $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomGroup: " . json_encode($data));
      return;
    } else {
      // update CustomGroup
      $this->updateEntity('CustomGroup', $data, $customGroup, array('extends', 'style', 'is_active', 'title', 'extends_entity_column_value'), $force_update);
    }

    // now run the update for the CustomFields
    foreach ($data['_fields'] as $customFieldSpec) {
      $this->translateStrings($customFieldSpec);
      $customFieldSpec['custom_group_id'] = $customGroup['id'];
      $customFieldSpec['_lookup'][] = 'custom_group_id';
      if (!empty($customFieldSpec['option_group_id']) && !is_numeric($customFieldSpec['option_group_id'])) {
        // look up custom group id
        $optionGroup = $this->getEntityID('OptionGroup', array('name' => $customFieldSpec['option_group_id']));
        if ($optionGroup == 'FAILED' || $optionGroup==NULL) {
          $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomField, bad option_group: {$customFieldSpec['option_group_id']}");
          return;
        }
        $customFieldSpec['option_group_id'] = $optionGroup['id'];
      }
      $customField = $this->identifyEntity('CustomField', $customFieldSpec);
      if (empty($customField)) {
        // create CustomField
        $customField = $this->createEntity('CustomField', $customFieldSpec);
      } elseif ($customField == 'FAILED') {
        // Couldn't identify:
        $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomField: " . json_encode($customFieldSpec));
      } else {
        // update CustomField
        $this->updateEntity('CustomField', $customFieldSpec, $customField, array('in_selector', 'is_view', 'is_searchable', 'html_type', 'data_type', 'custom_group_id'));
      }
    }
  }

  /**
   * return the ID of the given entity (if exists)
   */
  protected function getEntityID($entity_type, $selector) {
    if (empty($selector)) return NULL;
    $selector['sequential'] = 1;
    $selector['options'] = array('limit' => 2);

    $lookup_result = civicrm_api3($entity_type, 'get', $selector);
    switch ($lookup_result['count']) {
      case 1:
        // found
        return $lookup_result['values'][0];
      default:
        // more than one found
        $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Bad {$entity_type} lookup selector: " . json_encode($selector));
        return 'FAILED';
      case 0:
        // not found
        return NULL;
    }
  }

  /**
   * see if a given entity does already exist in the system
   * the $data blob should have a '_lookup' parameter listing the
   * lookup attributes
   */
  protected function identifyEntity($entity_type, $data) {
    $lookup_query = array(
        'sequential' => 1,
        'options'    => array('limit' => 2));

    foreach ($data['_lookup'] as $lookup_key) {
      $lookup_query[$lookup_key] = CRM_Utils_Array::value($lookup_key, $data, '');
    }

    $this->log(self::CUSTOM_DATA_HELPER_LOG_DEBUG, "LOOKUP {$entity_type}: " . json_encode($lookup_query));
    $lookup_result = civicrm_api3($entity_type, 'get', $lookup_query);
    switch ($lookup_result['count']) {
      case 0:
        // not found
        return NULL;

      case 1:
        // found
        return $lookup_result['values'][0];

      default:
        // bad lookup selector
        $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Bad {$entity_type} lookup selector: " . json_encode($lookup_query));
        return 'FAILED';
    }
  }

  /**
   * create a new entity
   */
  protected function createEntity($entity_type, $data) {
    // first: strip fields starting with '_'
    foreach (array_keys($data) as $field) {
      if (substr($field, 0, 1) == '_') {
        unset($data[$field]);
      }
    }

    // then run query
    CRM_Core_Error::debug_log_message("CustomDataHelper ({$this->ts_domain}): CREATE {$entity_type}: " . json_encode($data));
    return civicrm_api3($entity_type, 'create', $data);
  }

  /**
   * create a new entity
   */
  protected function updateEntity($entity_type, $requested_data, $current_data, $required_fields = array(), $force = FALSE) {
    $update_query = array();

    // first: identify fields that need to be updated
    foreach ($requested_data as $field => $value) {
      // fields starting with '_' are ignored
      if (substr($field, 0, 1) == '_') {
        continue;
      }

      if (isset($current_data[$field]) && $value != $current_data[$field]) {
        $update_query[$field] = $value;
      }
    }

    // if _no_override list is set, remove those fields from the update
    if (isset($requested_data['_no_override']) && is_array($requested_data['_no_override'])) {
      foreach ($requested_data['_no_override'] as $field_name) {
        if (isset($update_query[$field_name])) {
          unset($update_query[$field_name]);
        }
      }
    }

    // run update if required
    if ($force || !empty($update_query)) {
      $update_query['id'] = $current_data['id'];

      // add required fields
      foreach ($required_fields as $required_field) {
        if (isset($requested_data[$required_field])) {
          $update_query[$required_field] = $requested_data[$required_field];
        } elseif (isset($current_data[$required_field])) {
          $update_query[$required_field] = $current_data[$required_field];
        } else {
          // nothing we can do...
        }
      }

      $this->log(self::CUSTOM_DATA_HELPER_LOG_INFO, "UPDATE {$entity_type}: " . json_encode($update_query));
      return civicrm_api3($entity_type, 'create', $update_query);
    } else {
      return NULL;
    }
  }

  /**
   * translate all fields that are listed in the _translate list
   */
  protected function translateStrings(&$data) {
    if (empty($data['_translate'])) return;
    foreach ($data['_translate'] as $translate_key) {
      $value = $data[$translate_key];
      if (is_string($value)) {
        $data[$translate_key] = ts($value, array('domain' => $this->ts_domain));
      }
    }
  }

  /**
   * function to replace custom_XX notation with the more
   * stable "<custom_group_name>.<custom_field_name>" format
   *
   * @param $data   array  key=>value data, keys will be changed
   * @param $depth  int    recursively follow arrays
   */
  public static function labelCustomFields(&$data, $depth=1) {
    if ($depth == 0) return;

    $custom_fields_used = array();
    foreach ($data as $key => $value) {
      if (preg_match('#^custom_(?P<field_id>\d+)$#', $key, $match)) {
        $custom_fields_used[] = $match['field_id'];
      }
    }

    // cache fields
    self::cacheCustomFields($custom_fields_used);

    // replace names
    foreach ($data as $key => &$value) {
      if (preg_match('#^custom_(?P<field_id>\d+)$#', $key, $match)) {
        $new_key = self::getFieldIdentifier($match['field_id']);
        $data[$new_key] = $value;
        unset($data[$key]);
      }

      // recursively look into that array
      if (is_array($value) && $depth > 0) {
        self::labelCustomFields($value, $depth-1);
      }
    }
  }

  public static function getFieldIdentifier($field_id) {
    // just to be on the safe side
    self::cacheCustomFields(array($field_id));

    // get custom field
    $custom_field = self::$custom_field_cache[$field_id];
    if ($custom_field) {
      $group_name = self::getGroupName($custom_field['custom_group_id']);
      return "{$group_name}.{$custom_field['name']}";
    } else {
      return 'FIELD_NOT_FOUND_' . $field_id;
    }
  }

  /**
   * Get the specs/definition of the field
   * @param int $field_id
   * @return array field specs
   */
  public static function getFieldSpecs($field_id) {
    // just to be on the safe side
    self::cacheCustomFields(array($field_id));

    // get custom field
    $custom_field = self::$custom_field_cache[$field_id];
    if ($custom_field) {
      return $custom_field;
    } else {
      return NULL;
    }
  }

  /**
   * Get the specs/definition of the group
   *
   * @param int|string $group_id group id or string
   * @return array group specs
   */
  public static function getGroupSpecs($group_id) {
    // just to be on the safe side
    self::cacheCustomGroupSpecs([$group_id]);

    // get custom field
    $custom_group = self::$custom_group_spec_cache[$group_id];
    if ($custom_group) {
      return $custom_group;
    } else {
      return NULL;
    }
  }


  /**
   * internal function to replace "<custom_group_name>.<custom_field_name>"
   * in the data array with the custom_XX notation.
   *
   * @param $data          array  key=>value data, keys will be changed
   * @param $customgroups  array  if given, restrict to those groups
   *
   */
  public static function resolveCustomFields(&$data, $customgroups = NULL) {
    // first: find out which ones to cache
    $customgroups_used = array();
    foreach ($data as $key => $value) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match)) {
        if ($match['group_name'] == 'option' || $match['group_name'] == 'options') {
          // exclude API options
          continue;
        }

        if (empty($customgroups) || in_array($match['group_name'], $customgroups)) {
          $customgroups_used[$match['group_name']] = 1;
        }
      }
    }

    // cache the groups used
    self::cacheCustomGroups(array_keys($customgroups_used));

    // now: replace stuff
    foreach (array_keys($data) as $key) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match)) {
        if (empty($customgroups) || in_array($match['group_name'], $customgroups)) {
          if (isset(self::$custom_group_cache[$match['group_name']][$match['field_name']])) {
            $custom_field = self::$custom_group_cache[$match['group_name']][$match['field_name']];
            $custom_key = 'custom_' . $custom_field['id'];
            $data[$custom_key] = $data[$key];
            unset($data[$key]);
          } else {
            // TODO: unknown data field $match['group_name'] . $match['field_name']
          }
        }
      }
    }
  }


  /**
   * Get CustomField entity (cached)
   */
  public static function getCustomFieldKey($custom_group_name, $custom_field_name) {
    $field = self::getCustomField($custom_group_name, $custom_field_name);
    if ($field) {
      return 'custom_' . $field['id'];
    } else {
      return NULL;
    }
  }

  /**
   * Get CustomField entity (cached)
   */
  public static function getCustomField($custom_group_name, $custom_field_name) {
    self::cacheCustomGroups(array($custom_group_name));

    if (isset(self::$custom_group_cache[$custom_group_name][$custom_field_name])) {
      return self::$custom_group_cache[$custom_group_name][$custom_field_name];
    } else {
      return NULL;
    }
  }

  /**
   * Precache a list of custom groups
   */
  public static function cacheCustomGroups($custom_group_names) {
    foreach ($custom_group_names as $custom_group_name) {
      if (!isset(self::$custom_group_cache[$custom_group_name])) {
        // set to empty array to indicate our intentions
        self::$custom_group_cache[$custom_group_name] = array();
        $fields = civicrm_api3('CustomField', 'get', array(
            'custom_group_id' => $custom_group_name,
            'option.limit'    => 0));
        foreach ($fields['values'] as $field) {
          self::$custom_group_cache[$custom_group_name][$field['name']] = $field;
          self::$custom_group_cache[$custom_group_name][$field['id']]   = $field;
        }
      }
    }
  }

  /**
   * Precache a list of custom fields
   */
  public static function cacheCustomFields($custom_field_ids) {
    // first: check if they are already cached
    $fields_to_load = array();
    foreach ($custom_field_ids as $field_id) {
      if (!array_key_exists($field_id, self::$custom_field_cache)) {
        $fields_to_load[] = $field_id;
      }
    }

    // load missing fields
    if (!empty($fields_to_load)) {
      $loaded_fields = civicrm_api3('CustomField', 'get', array(
          'id'           => array('IN' => $fields_to_load),
          'option.limit' => 0,
      ));
      foreach ($loaded_fields['values'] as $field) {
        self::$custom_field_cache[$field['id']] = $field;
      }
    }
  }

  /**
   * Precache a list of custom fields
   *
   * @param array $custom_group_ids list of custom group IDs or names
   */
  public static function cacheCustomGroupSpecs($custom_group_ids) {
    // first: check if they are already cached
    $fields_to_load = array();
    foreach ($custom_group_ids as $group_id) {
      if (!array_key_exists($group_id, self::$custom_group_spec_cache)) {
        $groups_to_load[] = $group_id;
      }
    }

    // load missing fields
    if (!empty($groups_to_load)) {
      $loaded_groups = civicrm_api3('CustomGroup', 'get', array(
          'id'           => array('IN' => $groups_to_load),
          'option.limit' => 0,
      ));
      foreach ($loaded_groups['values'] as $group) {
        self::$custom_group_spec_cache[$group['id']] = $group;
        self::$custom_group_spec_cache[$group['name']] = $group;
      }
    }
  }


  /**
   * Get a mapping: custom_group_id => custom_group_name
   *
   * @return array
   *   mapping custom_group_id => custom_group_name
   */
  public static function getGroup2Name() {
    if (self::$custom_group2name === NULL) {
      self::loadGroups();
    }
    return self::$custom_group2name;
  }

  /**
   * Get a mapping: custom_group_id => table_name
   */
  public static function getGroup2TableName() {
    if (self::$custom_group2table_name === NULL) {
      self::loadGroups();
    }
    return self::$custom_group2table_name;
  }


  /**
   * Load group data (all groups)
   */
  protected static function loadGroups() {
    self::$custom_group2name = array();
    self::$custom_group2table_name = array();
    $group_search = civicrm_api3('CustomGroup', 'get', array(
        'return'       => 'name,table_name',
        'option.limit' => 0,
    ));
    foreach ($group_search['values'] as $customGroup) {
      self::$custom_group2name[$customGroup['id']]       = $customGroup['name'];
      self::$custom_group2table_name[$customGroup['id']] = $customGroup['table_name'];
    }
  }

  /**
   * Get the internal name of a custom gruop
   */
  public static function getGroupName($custom_group_id) {
    $group2name = self::getGroup2Name();
    return $group2name[$custom_group_id];
  }

  /**
   * If an API call is received via REST, the notation
   * used by this tool:
   *   "<custom_group_name>.<custom_field_name>"
   * can be mangled to
   *   "<custom_group_name>_<custom_field_name>"
   *
   * This function reverses this in the array itself
   *
   * Also, REST calls struggle with complex data structures,
   *  such as arrays. If you add html encoded json_strings
   *  (e.g. '%5B6%2C7%2C8%5D' for '[6,7,8]')
   *  they will be unpacked as well.
   *
   * @todo make it more efficient?
   *
   * @param array $params
   *   the parameter array as used by the API
   *
   * @param array $group_names
   *   list of group names to process. Default is: all
   */
  public static function unREST(&$params, $group_names = NULL) {
    if ($group_names == NULL || !is_array($group_names)) {
      $groups = self::getGroup2Name();
      $group_names = array_values($groups);
    }

    // look for all group names in all variables
    foreach ($group_names as $group_name) {
      foreach (array_keys($params) as $key) {
        $new_key = preg_replace("#^{$group_name}_#", "{$group_name}.", $key);
        if ($new_key != $key) {
          $params[$new_key] = $params[$key];
        }
      }
    }

    // also, unpack 'flattened' arrays
    foreach ($params as $key => &$value) {
      if (!is_array($value)) {
        $first_character = substr($value, 0, 1);
        if ($first_character == '[' || $first_character == '{') {
          $unpacked_value = json_decode($value, TRUE);
          if ($unpacked_value) {
            if (is_array($unpacked_value) && empty($unpacked_value)) {
              // this is a strange behaviour in the API,
              //   but empty arrays are not processed properly
              $value = '';
            } else {
              $value = $unpacked_value;
            }
          }
        }
      }
    }
  }

  /**
   * Get the table of a certain group
   */
  public static function getGroupTable($group_name) {
    $id2name = self::getGroup2Name();
    $name2id = array_flip($id2name);
    if (isset($name2id[$group_name])) {
      $group_id = $name2id[$group_name];
      $id2table = self::getGroup2TableName();
      if (isset($id2table[$group_id])) {
        return $id2table[$group_id];
      }
    }
    return NULL;
  }

  /**
   * Get group ID
   */
  public static function getGroupID($group_name) {
    $id2name = self::getGroup2Name();
    $name2id = array_flip($id2name);
    if (isset($name2id[$group_name])) {
      return $name2id[$group_name];
    }
    return NULL;
  }

  /**
   * Generates the following SQL join statment:
   * "LEFT JOIN {$group_table_name} AS {$table_alias} ON {$table_alias}.entity_id = {$join_entity_id}"
   */
  public static function createSQLJoin($group_name, $table_alias, $join_entity_id) {
    // cache the groups used
    $group_table_name = self::getGroupTable($group_name);
    return "LEFT JOIN `{$group_table_name}` AS {$table_alias} ON {$table_alias}.entity_id = {$join_entity_id}";
  }



  /**
   * Get the current field value from CiviCRM's pre-hook structure
   *
   * @param $params array
   *   pre-hook data
   *
   * @param $field_id string
   *   custom field ID
   *
   * @return mixed
   *   the current value
   */
  public static function getPreHookCustomDataValue($params, $field_id) {
    if ($field_id) {
      if (!empty($params['custom'][$field_id][-1])) {
        $field_data = $params['custom'][$field_id][-1];
        return $field_data['value'];
      } else {
        // unlikely, but worth a shot:
        return CRM_Utils_Array::value("custom_{$field_id}", $params, NULL);
      }
    }
    return NULL;
  }


  /**
   * Set a field value in CiviCRM's pre-hook structure right in the pre hook data
   *
   * @param $params array
   *    pre-hook data
   *
   * @param $field_id string
   *    custom field ID
   *
   * @param $value mixed
   *    the new value
   */
  public static function setPreHookCustomDataValue(&$params, $field_id, $value) {
    if ($field_id) {
      if (isset($params['custom'])) {
        if (!empty($params['custom'][$field_id][-1])) {
          // update custom field data record
          $params['custom'][$field_id][-1]['value'] = $value;
        } else {
          // add custom field data record
          $params['custom'][$field_id][-1] = self::generatePreHookCustomDataRecord($field_id, $value);
        }
      } else {
        // this shouldn't happen based on the pre_hook...
        // not likely to succeed, but worth a shot:
        $params["custom_{$field_id}"] = $value;
      }
    }
  }

  /**
   * @param $field_id
   * @param $value
   * @return array
   */
  protected static function generatePreHookCustomDataRecord($field_id, $value) {
    if ($field_id) {
      $field_specs = self::getFieldSpecs($field_id);
      $group_specs = self::getGroupSpecs($field_specs['custom_group_id']);
      return               [
          'value'           => $value,
          'type'            => CRM_Utils_Array::value('data_type', $field_specs, 'String'),
          'custom_field_id' => $field_id,
          'custom_group_id' => CRM_Utils_Array::value('custom_group_id', $field_specs, NULL),
          'table_name'      => CRM_Utils_Array::value('table_name', $group_specs, NULL),
          'column_name'     => CRM_Utils_Array::value('column_name', $field_specs, NULL),
          'is_multiple'     => CRM_Utils_Array::value('is_multiple', $group_specs, 0),
      ];
    } else {
      return NULL;
    }
  }

  /**
   * Get CustomField entity (cached)
   */
  public static function getCustomFieldsForGroups($custom_group_names) {
    self::cacheCustomGroups($custom_group_names);
    $fields = [];
    foreach ($custom_group_names as $custom_group_name) {
      foreach (self::$custom_group_cache[$custom_group_name] as $field_id => $field) {
        if (is_numeric($field_id)) {
          $fields[] = $field;
        }
      }
    }
    return $fields;
  }

  /**
   * Get an option value from an option group
   *
   * This function was specifically introduced as 1:1 replacement
   *  for the deprecated CRM_Core_OptionGroup::getValue function
   *
   * @param string $groupName
   *   name of the group
   *
   * @param $label
   *   label/name of the requested option value
   *
   * @param string $label_field
   *   field to look in for the label, e.g. 'label' or 'name'
   *
   * @param string $label_type
   *   *ignored*
   *
   * @param string $value_field
   *   *ignored*
   *
   * @return string
   *   value of the OptionValue entity if found
   *
   * @throws Exception
   */
  public static function getOptionValue($group_name, $label, $label_field = 'label', $label_type = 'String', $value_field = 'value')
  {
    if (empty($label) || empty($group_name)) {
      return NULL;
    }

    // build/run API query
    $value = civicrm_api3('OptionValue', 'getvalue', [
      'option_group_id' => $group_name,
      $label_field => $label,
      'return' => $value_field
    ]);

    // anything else to do here?
    return (string) $value;
  }
}
