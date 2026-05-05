<?php
/**
 * +--------------------------------------------------------+
 * | SYSTOPIA CUSTOM DATA HELPER                            |
 * | Copyright (C) 2018-2024 SYSTOPIA                       |
 * | Author: B. Endres (endres@systopia.de)                 |
 * | Source: https://github.com/systopia/Custom-Data-Helper |
 * +--------------------------------------------------------+
 * | This program is released as free software under the    |
 * | Affero GPL license. You can redistribute it and/or     |
 * | modify it under the terms of this license which you    |
 * | can read by viewing the included agpl.txt or online    |
 * | at www.gnu.org/licenses/agpl.html. Removal of this     |
 * | copyright header is strictly prohibited without        |
 * | written permission from the original author(s).        |
 * +--------------------------------------------------------+
 */

declare(strict_types = 1);

class CRM_Sepa_CustomData {
  public const CUSTOM_DATA_HELPER_VERSION   = '0.13.1';
  public const CUSTOM_DATA_HELPER_LOG_LEVEL = 0;
  public const CUSTOM_DATA_HELPER_LOG_DEBUG = 1;
  public const CUSTOM_DATA_HELPER_LOG_INFO  = 3;
  public const CUSTOM_DATA_HELPER_LOG_ERROR = 5;

  /**
   * caches custom field data, indexed by group name
   *
   * @var array<int, string>
   */
  protected static array $custom_group2name = [];

  /**
   * @var array<int, string>
   */
  protected static array $custom_group2table_name = [];

  /**
   * @var array<string, array<int, array<string, mixed>>>
   *   Mapping of group name to mapping of field ID to field.
   */
  protected static array $custom_group_cache = [];

  /**
   * @var array<int|string, array<string, mixed>>
   *   Mapping of group ID and group name to group.
   */
  protected static array $custom_group_spec_cache = [];

  /**
   * @var array<int, array<string, mixed>>
   *   Mapping of field ID to field.
   */
  protected static array $custom_field_cache = [];

  protected ?string $ts_domain = NULL;
  protected string $version   = self::CUSTOM_DATA_HELPER_VERSION;

  public function __construct(string $ts_domain) {
    $this->ts_domain = $ts_domain;
  }

  /**
   * Log a message if the log level is high enough
   */
  protected function log(int $level, string $message): void {
    if ($level >= self::CUSTOM_DATA_HELPER_LOG_LEVEL) {
      Civi::log()->debug("CustomDataHelper {$this->version} ({$this->ts_domain}): {$message}");
    }
  }

  /**
   * will take a JSON source file and synchronise the
   * generic entity data with those specs
   */
  public function syncEntities(string $source_file): void {
    $data = self::loadJson($source_file);
    if (!is_array($data['_entities'])) {
      throw new InvalidArgumentException('syncOptionGroup::syncOptionGroup: Invalid specs');
    }

    assert(is_string($data['entity']));

    /** @var array<string, mixed> $entity_data */
    foreach ($data['_entities'] as $entity_data) {
      $this->translateStrings($entity_data);
      $entity = $this->identifyEntity($data['entity'], $entity_data);

      if (NULL === $entity) {
        // create OptionValue
        $entity = $this->createEntity($data['entity'], $entity_data);
      }
      elseif ($entity === 'FAILED') {
        // Couldn't identify:
        $this->log(
          self::CUSTOM_DATA_HELPER_LOG_ERROR,
          "Couldn't create/update {$data['entity']}: " . json_encode($entity_data)
        );
      }
      else {
        // update OptionValue
        $this->updateEntity($data['entity'], $entity_data, $entity);
      }
    }
  }

  /**
   * will take a JSON source file and synchronize the
   * OptionGroup/OptionValue data in the system with
   * those specs
   */
  public function syncOptionGroup(string $source_file): void {
    /** @var array<string, mixed> $data */
    $data = self::loadJson($source_file);
    if ([] === $data) {
      throw new InvalidArgumentException('syncOptionGroup::syncOptionGroup: Invalid specs');
    }

    // first: find or create option group
    $this->translateStrings($data);

    $data['_values'] ??= [];
    if (!is_array($data['_values'])) {
      throw new InvalidArgumentException('syncOptionGroup::syncOptionGroup: Invalid specs');
    }

    $optionGroup = $this->identifyEntity('OptionGroup', $data);
    if (NULL === $optionGroup) {
      // create OptionGroup
      $optionGroup = $this->createEntity('OptionGroup', $data);
    }
    elseif ($optionGroup === 'FAILED') {
      // Couldn't identify:
      $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update OptionGroup: " . json_encode($data));
      return;
    }
    else {
      // update OptionGroup
      $this->updateEntity('OptionGroup', $data, $optionGroup, ['is_active']);
    }

    // now run the update for the OptionValues
    /** @var array<string, mixed> $optionValueSpec */
    foreach ($data['_values'] as $optionValueSpec) {
      $this->translateStrings($optionValueSpec);
      $optionValueSpec['option_group_id'] = $optionGroup['id'];
      // @phpstan-ignore offsetAccess.nonOffsetAccessible
      $optionValueSpec['_lookup'][] = 'option_group_id';
      $optionValue = $this->identifyEntity('OptionValue', $optionValueSpec);

      if (NULL === $optionValue) {
        // create OptionValue
        $optionValue = $this->createEntity('OptionValue', $optionValueSpec);
      }
      elseif ($optionValue === 'FAILED') {
        // Couldn't identify:
        $this->log(
          self::CUSTOM_DATA_HELPER_LOG_ERROR,
          "Couldn't create/update OptionValue: " . json_encode($optionValueSpec)
        );
      }
      else {
        // update OptionValue
        $this->updateEntity('OptionValue', $optionValueSpec, $optionValue, ['is_active']);
      }
    }
  }

  /**
   * will take a JSON source file and synchronize the
   * CustomGroup/CustomField data in the system with
   * those specs
   *
   * phpcs:disable Generic.Metrics.CyclomaticComplexity.TooHigh
   */
  public function syncCustomGroup(string $source_file): void {
    $force_update = FALSE;
    /** @var array<string, mixed> $data */
    $data = self::loadJson($source_file);
    if ([] === $data) {
      throw new InvalidArgumentException('CRM_Utils_CustomData::syncCustomGroup: Invalid custom specs');
    }

    // if extends_entity_column_value, make sure it's sensible data
    if (isset($data['extends_entity_column_value'])) {
      // this doesn't get returned by the API, so differences couldn't be detected
      $force_update = TRUE;
      if ($data['extends'] === 'Activity') {
        $extends_list = [];
        /** @var string|int $activity_type */
        // @phpstan-ignore foreach.nonIterable
        foreach ($data['extends_entity_column_value'] as $activity_type) {
          if (!is_numeric($activity_type)) {
            $activity_type = self::getOptionValue('activity_type', $activity_type, 'name');
          }
          if (NULL !== $activity_type) {
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

    $data['_fields'] ??= [];
    if (!is_array($data['_fields'])) {
      throw new InvalidArgumentException('CRM_Utils_CustomData::syncCustomGroup: Invalid custom specs');
    }

    $customGroup = $this->identifyEntity('CustomGroup', $data);
    if (NULL === $customGroup) {
      // create CustomGroup
      $customGroup = $this->createEntity('CustomGroup', $data);
    }
    elseif ($customGroup === 'FAILED') {
      // Couldn't identify:
      $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Couldn't create/update CustomGroup: " . json_encode($data));
      return;
    }
    else {
      // update CustomGroup
      $this->updateEntity(
        'CustomGroup',
        $data,
        $customGroup,
        ['extends', 'style', 'is_active', 'title', 'extends_entity_column_value'],
        $force_update
      );
    }

    // now run the update for the CustomFields
    /** @var array<string, mixed> $customFieldSpec */
    foreach ($data['_fields'] as $customFieldSpec) {
      $this->translateStrings($customFieldSpec);
      $customFieldSpec['custom_group_id'] = $customGroup['id'];
      // @phpstan-ignore offsetAccess.nonOffsetAccessible
      $customFieldSpec['_lookup'][] = 'custom_group_id';
      if (isset($customFieldSpec['option_group_id']) && !is_numeric($customFieldSpec['option_group_id'])) {
        // look up custom group id
        $optionGroup = $this->getEntity('OptionGroup', ['name' => $customFieldSpec['option_group_id']]);
        if ($optionGroup === 'FAILED' || $optionGroup === NULL) {
          $this->log(
            self::CUSTOM_DATA_HELPER_LOG_ERROR,
            // @phpstan-ignore encapsedStringPart.nonString
            "Couldn't create/update CustomField, bad option_group: {$customFieldSpec['option_group_id']}"
          );
          return;
        }
        $customFieldSpec['option_group_id'] = $optionGroup['id'];
      }
      $customField = $this->identifyEntity('CustomField', $customFieldSpec);
      if (NULL === $customField) {
        // create CustomField
        $customField = $this->createEntity('CustomField', $customFieldSpec);
      }
      elseif ($customField === 'FAILED') {
        // Couldn't identify:
        $this->log(
          self::CUSTOM_DATA_HELPER_LOG_ERROR,
          "Couldn't create/update CustomField: " . json_encode($customFieldSpec)
        );
      }
      else {
        // update CustomField
        $this->updateEntity(
          'CustomField',
          $customFieldSpec,
          $customField,
          ['in_selector', 'is_view', 'is_searchable', 'html_type', 'data_type', 'custom_group_id']
        );
      }
    }
  }

  /**
   * @param array<string, mixed> $selector
   *
   * @return array<string, mixed>|"FAILED"|null
   *   The ID of the given entity (if exists).
   */
  protected function getEntity(string $entity_type, array $selector): array|string|null {
    if ([] === $selector) {
      return NULL;
    }
    $selector['sequential'] = 1;
    $selector['options'] = ['limit' => 2];

    /** @var array<string, mixed> $lookup_result */
    $lookup_result = civicrm_api3($entity_type, 'get', $selector);
    switch ($lookup_result['count'] ?? 0) {
      case 0:
        // not found
        return NULL;

      case 1:
        // found
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, return.type
        return $lookup_result['values'][0];

      default:
        // more than one found
        $this->log(self::CUSTOM_DATA_HELPER_LOG_ERROR, "Bad {$entity_type} lookup selector: " . json_encode($selector));
        return 'FAILED';
    }
  }

  /**
   * see if a given entity does already exist in the system
   * the $data blob should have a '_lookup' parameter listing the
   * lookup attributes
   *
   * @param array<string, mixed> $data
   *
   * @return array<string, mixed>|"FAILED"|null
   */
  protected function identifyEntity(string $entity_type, array $data): array|string|null {
    $lookup_query = [
      'sequential' => 1,
      'options'    => ['limit' => 2],
    ];

    $data['_lookup'] ??= [];
    if (!is_array($data['_lookup'])) {
      throw new InvalidArgumentException('Invalid "_lookup" parameter');
    }

    /** @var string $lookup_key */
    foreach ($data['_lookup'] as $lookup_key) {
      $lookup_query[$lookup_key] = $data[$lookup_key] ?? '';
    }

    $this->log(self::CUSTOM_DATA_HELPER_LOG_DEBUG, "LOOKUP {$entity_type}: " . json_encode($lookup_query));
    /** @var array<string, mixed> $lookup_result */
    $lookup_result = civicrm_api3($entity_type, 'get', $lookup_query);
    switch ($lookup_result['count'] ?? 0) {
      case 0:
        // not found
        return NULL;

      case 1:
        // found
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, return.type
        return $lookup_result['values'][0];

      default:
        // bad lookup selector
        $this->log(
          self::CUSTOM_DATA_HELPER_LOG_ERROR,
          "Bad {$entity_type} lookup selector: " . json_encode($lookup_query)
        );
        return 'FAILED';
    }
  }

  /**
   * create a new entity
   *
   * @param array<string, mixed> $data
   *
   * @return array<string, mixed>
   */
  protected function createEntity(string $entity_type, array $data): array {
    // first: strip fields starting with '_'
    foreach (array_keys($data) as $field) {
      if (substr($field, 0, 1) === '_') {
        unset($data[$field]);
      }
    }

    // then run query
    Civi::log()->debug("CustomDataHelper ({$this->ts_domain}): CREATE {$entity_type}: " . json_encode($data));
    // @phpstan-ignore return.type
    return civicrm_api3($entity_type, 'create', $data);
  }

  /**
   * create a new entity
   *
   * @param array<string, mixed> $requested_data
   * @param array<string, mixed> $current_data
   * @param list<string> $required_fields
   *
   * @return array<string, mixed>|null
   */
  protected function updateEntity(
    string $entity_type,
    array $requested_data,
    array $current_data,
    array $required_fields = [],
    bool $force = FALSE
  ): ?array {
    $update_query = [];

    // first: identify fields that need to be updated
    foreach ($requested_data as $field => $value) {
      // fields starting with '_' are ignored
      if (substr($field, 0, 1) === '_') {
        continue;
      }

      // @phpstan-ignore notEqual.notAllowed
      if (isset($current_data[$field]) && $value != $current_data[$field]) {
        $update_query[$field] = $value;
      }
    }

    // if _no_override list is set, remove those fields from the update
    if (isset($requested_data['_no_override']) && is_array($requested_data['_no_override'])) {
      /** @var string $field_name */
      foreach ($requested_data['_no_override'] as $field_name) {
        if (isset($update_query[$field_name])) {
          unset($update_query[$field_name]);
        }
      }
    }

    // run update if required
    if ($force || [] !== $update_query) {
      $update_query['id'] = $current_data['id'];

      // add required fields
      foreach ($required_fields as $required_field) {
        if (isset($requested_data[$required_field])) {
          $update_query[$required_field] = $requested_data[$required_field];
        }
        elseif (isset($current_data[$required_field])) {
          $update_query[$required_field] = $current_data[$required_field];
        }
        // else: There's nothing we can do...
      }

      $this->log(self::CUSTOM_DATA_HELPER_LOG_INFO, "UPDATE {$entity_type}: " . json_encode($update_query));
      // @phpstan-ignore return.type
      return civicrm_api3($entity_type, 'create', $update_query);
    }
    else {
      return NULL;
    }
  }

  /**
   * translate all fields that are listed in the _translate list
   *
   * @param array<string, mixed> $data
   */
  protected function translateStrings(array &$data): void {
    if (!is_array($data['_translate'] ?? NULL)) {
      return;
    }
    foreach ($data['_translate'] as $translate_key) {
      assert(is_string($translate_key));
      $value = $data[$translate_key] ?? NULL;
      if (is_string($value)) {
        $data[$translate_key] = ts($value, ['domain' => $this->ts_domain]);
      }
    }
  }

  /**
   * Flush all internal caches
   */
  public static function flushCashes(): void {
    self::$custom_group2name = [];
    self::$custom_group2table_name = [];
    self::$custom_group_cache = [];
    self::$custom_group_spec_cache = [];
    self::$custom_field_cache = [];
  }

  /**
   * function to replace custom_XX notation with the more
   * stable "<custom_group_name>.<custom_field_name>" format
   *
   * @param array<string|int, mixed> $data key=>value data, keys will be changed.
   * @param int $depth recursively follow arrays.
   * @param string $separator separator to be used.
   *   examples are '.' (default) or '__' to avoid drupal form field id issues.
   */
  public static function labelCustomFields(array &$data, int $depth = 1, string $separator = '.'): void {
    if ($depth <= 0) {
      return;
    }

    $custom_fields_used = [];
    foreach ($data as $key => $value) {
      if (preg_match('#^custom_(?P<field_id>\d+)$#', (string) $key, $match) === 1) {
        $custom_fields_used[] = $match['field_id'];
      }
    }

    // cache fields
    self::cacheCustomFields($custom_fields_used);

    // replace names
    foreach ($data as $key => &$value) {
      if (preg_match('#^custom_(?P<field_id>\d+)$#', (string) $key, $match) === 1) {
        $new_key = self::getFieldIdentifier($match['field_id'], $separator);
        $data[$new_key] = $value;
        unset($data[$key]);
      }

      // recursively look into that array
      if (is_array($value)) {
        self::labelCustomFields($value, $depth - 1, $separator);
      }
    }
  }

  /**
   * Function to render a unified field identifier of the compatible
   *  "<custom_group_name>.<custom_field_name>"
   * format
   *
   * @note This is intended for use with APIv3, since APIv4 already has a similar thing built in
   *
   * @param int|string $field_id the field ID
   * @param string $separator the separator to used, by default '.'
   *
   * @see getFieldIdentifier
   */
  public static function getFieldIdentifier(int|string $field_id, string $separator = '.'): string {
    // just to be on the safe side
    self::cacheCustomFields([$field_id]);

    // get custom field
    $custom_field = self::$custom_field_cache[$field_id] ?? NULL;
    if (NULL !== $custom_field) {
      // @phpstan-ignore argument.type
      $group_name = self::getGroupName($custom_field['custom_group_id']);
      // @phpstan-ignore encapsedStringPart.nonString
      return "{$group_name}{$separator}{$custom_field['name']}";
    }
    else {
      return 'FIELD_NOT_FOUND_' . $field_id;
    }
  }

  /**
   * Get the specs/definition of the field
   * @param int|string $field_id
   * @return array<string, mixed>|null field specs
   */
  public static function getFieldSpecs(int|string $field_id): ?array {
    // just to be on the safe side
    self::cacheCustomFields([$field_id]);

    // get custom field
    return self::$custom_field_cache[$field_id] ?? NULL;
  }

  /**
   * Get the specs/definition of the group
   *
   * @param int|string $group_id group id
   * @return array<string, mixed>|null group specs
   */
  public static function getGroupSpecs(int|string $group_id): ?array {
    // just to be on the safe side
    self::cacheCustomGroupSpecs([$group_id]);

    // get custom field
    return self::$custom_group_spec_cache[$group_id] ?? NULL;
  }

  /**
   * internal function to replace "<custom_group_name>.<custom_field_name>"
   * in the data array with the custom_XX notation.
   *
   * @param array<string, mixed> $data key=>value data, keys will be changed
   * @param list<string>|null $customgroups if given, restrict to those groups
   *
   */
  public static function resolveCustomFields(array &$data, ?array $customgroups = NULL): void {
    $customgroups ??= [];
    // first: find out which ones to cache
    $customgroups_used = [];
    foreach ($data as $key => $value) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match) === 1) {
        if ($match['group_name'] === 'option' || $match['group_name'] === 'options') {
          // exclude API options
          continue;
        }

        if ([] === $customgroups || in_array($match['group_name'], $customgroups, TRUE)) {
          $customgroups_used[$match['group_name']] = 1;
        }
      }
    }

    // cache the groups used
    self::cacheCustomGroups(array_keys($customgroups_used));

    // now: replace stuff
    foreach (array_keys($data) as $key) {
      if (preg_match('/^(?P<group_name>\w+)[.](?P<field_name>\w+)$/', $key, $match) === 1) {
        if ([] === $customgroups || in_array($match['group_name'], $customgroups, TRUE)) {
          if (isset(self::$custom_group_cache[$match['group_name']][$match['field_name']])) {
            $custom_field = self::$custom_group_cache[$match['group_name']][$match['field_name']];
            // @phpstan-ignore binaryOp.invalid
            $custom_key = 'custom_' . $custom_field['id'];
            $data[$custom_key] = $data[$key];
            unset($data[$key]);
          }
          // phpcs:disable Generic.CodeAnalysis.EmptyStatement.DetectedElse, Squiz.PHP.CommentedOutCode.Found
          else {
            // TODO: unknown data field $match['group_name'] . $match['field_name']
          }
          // phpcs:enable
        }
      }
    }
  }

  /**
   * Get CustomField entity (cached)
   */
  public static function getCustomFieldKey(string $custom_group_name, string $custom_field_name): ?string {
    $field = self::getCustomField($custom_group_name, $custom_field_name);
    if (NULL !== $field) {
      // @phpstan-ignore binaryOp.invalid
      return 'custom_' . $field['id'];
    }
    else {
      return NULL;
    }
  }

  /**
   * Get CustomField entity (cached)
   *
   * @return array<string, mixed>|null
   */
  public static function getCustomField(string $custom_group_name, string $custom_field_name): ?array {
    self::cacheCustomGroups([$custom_group_name]);

    if (isset(self::$custom_group_cache[$custom_group_name][$custom_field_name])) {
      return self::$custom_group_cache[$custom_group_name][$custom_field_name];
    }
    else {
      return NULL;
    }
  }

  /**
   * Precache a list of custom groups
   *
   * @param list<string> $custom_group_names
   */
  public static function cacheCustomGroups(array $custom_group_names): void {
    foreach ($custom_group_names as $custom_group_name) {
      if (!isset(self::$custom_group_cache[$custom_group_name])) {
        // set to empty array to indicate our intentions
        self::$custom_group_cache[$custom_group_name] = [];
        /** @var array{values: array<array<string, mixed>>} $fields */
        $fields = civicrm_api3('CustomField', 'get', [
          'custom_group_id' => $custom_group_name,
          'option.limit'    => 0,
        ]);
        foreach ($fields['values'] as $field) {
          // @phpstan-ignore offsetAccess.invalidOffset
          self::$custom_group_cache[$custom_group_name][$field['name']] = $field;
          // @phpstan-ignore offsetAccess.invalidOffset
          self::$custom_group_cache[$custom_group_name][$field['id']] = $field;
        }
      }
    }
  }

  /**
   * Precache a list of custom fields
   *
   * @param list<int|string> $custom_field_ids
   */
  public static function cacheCustomFields(array $custom_field_ids): void {
    // first: check if they are already cached
    $fields_to_load = [];
    foreach ($custom_field_ids as $field_id) {
      if (!array_key_exists($field_id, self::$custom_field_cache)) {
        $fields_to_load[] = $field_id;
      }
    }

    // load missing fields
    if ([] !== $fields_to_load) {
      /** @var array{values: array<array<string, mixed>>} $loaded_fields */
      $loaded_fields = civicrm_api3('CustomField', 'get', [
        'id'           => ['IN' => $fields_to_load],
        'option.limit' => 0,
      ]);
      foreach ($loaded_fields['values'] as $field) {
        // @phpstan-ignore offsetAccess.invalidOffset
        self::$custom_field_cache[$field['id']] = $field;
      }
    }
  }

  /**
   * Precache a list of custom fields
   *
   * @param list<int|string> $custom_group_ids list of custom group IDs
   */
  public static function cacheCustomGroupSpecs(array $custom_group_ids): void {
    // first: check if they are already cached
    $groups_to_load = [];
    foreach ($custom_group_ids as $group_id) {
      if (!array_key_exists($group_id, self::$custom_group_spec_cache)) {
        $groups_to_load[] = $group_id;
      }
    }

    // load missing fields
    if ([] !== $groups_to_load) {
      /** @var array{values: array<array<string, mixed>>} $loaded_groups */
      $loaded_groups = civicrm_api3('CustomGroup', 'get', [
        'id'           => ['IN' => $groups_to_load],
        'option.limit' => 0,
      ]);
      foreach ($loaded_groups['values'] as $group) {
        // @phpstan-ignore offsetAccess.invalidOffset
        self::$custom_group_spec_cache[$group['id']] = $group;
        // @phpstan-ignore offsetAccess.invalidOffset
        self::$custom_group_spec_cache[$group['name']] = $group;
      }
    }
  }

  /**
   * Get a mapping: custom_group_id => custom_group_name
   *
   * @return array<int, string>
   *   mapping custom_group_id => custom_group_name
   */
  public static function getGroup2Name(): array {
    if (self::$custom_group2name === []) {
      self::loadGroups();
    }
    return self::$custom_group2name;
  }

  /**
   * Get a mapping: custom_group_id => table_name
   *
   * @return array<int, string>
   */
  public static function getGroup2TableName(): array {
    if (self::$custom_group2table_name === []) {
      self::loadGroups();
    }
    return self::$custom_group2table_name;
  }

  /**
   * Load group data (all groups)
   */
  protected static function loadGroups(): void {
    self::$custom_group2name = [];
    self::$custom_group2table_name = [];
    /** @var array{values: array<array{id: string, name: string, table_name: string}>} $group_search */
    $group_search = civicrm_api3('CustomGroup', 'get', [
      'return'       => 'name,table_name',
      'option.limit' => 0,
    ]);
    foreach ($group_search['values'] as $customGroup) {
      self::$custom_group2name[(int) $customGroup['id']] = $customGroup['name'];
      self::$custom_group2table_name[(int) $customGroup['id']] = $customGroup['table_name'];
    }
  }

  /**
   * Get the internal name of a custom group
   */
  public static function getGroupName(int|string $custom_group_id): ?string {
    $group2name = self::getGroup2Name();
    return $group2name[$custom_group_id] ?? NULL;
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
   * @param array<string, mixed> $params
   *   the parameter array as used by the API
   *
   * @param list<string>|null $group_names
   *   list of group names to process. Default is: all
   */
  public static function unREST(array &$params, ?array $group_names = NULL): void {
    if ($group_names === NULL) {
      $groups = self::getGroup2Name();
      $group_names = array_values($groups);
    }

    // look for all group names in all variables
    foreach ($group_names as $group_name) {
      foreach (array_keys($params) as $key) {
        $new_key = preg_replace("#^{$group_name}_#", "{$group_name}.", $key);
        if ($new_key !== $key) {
          $params[$new_key] = $params[$key];
        }
      }
    }

    // also, unpack 'flattened' arrays
    foreach ($params as &$value) {
      if (is_string($value)) {
        $first_character = substr($value, 0, 1);
        if ($first_character === '[' || $first_character === '{') {
          $unpacked_value = json_decode($value, TRUE);
          if ((bool) $unpacked_value) {
            if (is_array($unpacked_value)) {
              // this is a strange behavior in the API,
              //   but empty arrays are not processed properly
              $value = '';
            }
            else {
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
  public static function getGroupTable(string $group_name): ?string {
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
  public static function getGroupID(string $group_name): ?int {
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
  public static function createSQLJoin(string $group_name, string $table_alias, int|string $join_entity_id): string {
    // cache the groups used
    $group_table_name = self::getGroupTable($group_name);
    return "LEFT JOIN `{$group_table_name}` AS {$table_alias} ON {$table_alias}.entity_id = {$join_entity_id}";
  }

  /**
   * Get the current field value from CiviCRM's pre-hook structure
   *
   * @param array<string, mixed> $params
   *   pre-hook data
   *
   * @param int|string $field_id
   *   custom field ID
   *
   * @return mixed
   *   the current value
   */
  public static function getPreHookCustomDataValue(array $params, int|string $field_id): mixed {
    if ((bool) $field_id) {
      // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
      if (isset($params['custom'][$field_id][-1])) {
        $field_data = $params['custom'][$field_id][-1];
        // @phpstan-ignore offsetAccess.nonOffsetAccessible
        return $field_data['value'] ?? NULL;
      }
      else {
        // unlikely, but worth a shot:
        return $params["custom_{$field_id}"] ?? NULL;
      }
    }
    return NULL;
  }

  /**
   * Set a field value in CiviCRM's pre-hook structure right in the pre hook data
   *
   * @param array<string, mixed> $params
   *    pre-hook data
   *
   * @param int|string $field_id
   *    custom field ID
   *
   * @phpstan-param mixed $value
   *    the new value
   */
  public static function setPreHookCustomDataValue(array &$params, int|string $field_id, mixed $value): void {
    if ((bool) $field_id) {
      if (isset($params['custom'])) {
        // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
        if (isset($params['custom'][$field_id][-1])) {
          // update custom field data record
          // @phpstan-ignore offsetAccess.nonOffsetAccessible
          $params['custom'][$field_id][-1]['value'] = $value;
        }
        else {
          // add custom field data record
          // @phpstan-ignore offsetAccess.nonOffsetAccessible, offsetAccess.nonOffsetAccessible
          $params['custom'][$field_id][-1] = self::generatePreHookCustomDataRecord($field_id, $value);
        }
      }
      else {
        // this shouldn't happen based on the pre_hook...
        // not likely to succeed, but worth a shot:
        $params["custom_{$field_id}"] = $value;
      }
    }
  }

  /**
   * @param int|string $field_id
   * @phpstan-param mixed $value
   * @return array<string, mixed>|null
   */
  protected static function generatePreHookCustomDataRecord(int|string $field_id, mixed $value): ?array {
    if ($field_id > 0) {
      $field_specs = self::getFieldSpecs($field_id);
      if (NULL !== $field_specs) {
        // @phpstan-ignore argument.type
        $group_specs = self::getGroupSpecs($field_specs['custom_group_id']);

        return [
          'value' => $value,
          'type' => $field_specs['data_type'] ?? 'String',
          'custom_field_id' => $field_id,
          'custom_group_id' => $field_specs['custom_group_id'] ?? NULL,
          'table_name' => $group_specs['table_name'] ?? NULL,
          'column_name' => $field_specs['column_name'] ?? NULL,
          'is_multiple' => $group_specs['is_multiple'] ?? 0,
        ];
      }
    }

    return NULL;
  }

  /**
   * Get CustomField entity (cached)
   *
   * @param list<string> $custom_group_names
   *
   * @return list<array<string, mixed>>
   */
  public static function getCustomFieldsForGroups(array $custom_group_names): array {
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
   * @param string $group_name
   *   name of the group
   *
   * @param string $label
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
   * @return string|null
   *   value of the OptionValue entity if found
   *
   * @throws Exception
   */
  public static function getOptionValue(
    string $group_name,
    string $label,
    string $label_field = 'label',
    string $label_type = 'String',
    string $value_field = 'value'
  ): ?string {
    if ('' === $label || '' === $group_name) {
      return NULL;
    }

    // build/run API query
    try {
      $value = civicrm_api3('OptionValue', 'getvalue', [
        'option_group_id' => $group_name,
        $label_field => $label,
        'return' => $value_field,
      ]);
    }
    catch (CRM_Core_Exception $exception) {
      return NULL;
    }

    // anything else to do here?
    // @phpstan-ignore cast.string
    return (string) $value;
  }

  /**
   * @return array<int|string, mixed>
   */
  private static function loadJson(string $filename): array {
    $content = file_get_contents($filename);
    if (FALSE === $content) {
      throw new RuntimeException("Unable to read file $filename");
    }

    $data = json_decode($content, TRUE, flags: JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
      throw new RuntimeException("$filename contains invalid content");
    }

    return $data;
  }

}
