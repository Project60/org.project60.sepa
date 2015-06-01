<?php

return array (
  array (
    'name' => 'SEPA File Formats',
    'entity' => 'OptionGroup',
    'params' => array (
      'version' => 3,
      'name' => 'sepa_file_format',
      'title' => ts('SEPA XML File Format Variants'),
      'is_reserved' => 1,
      'is_active' => 1,
      'is_locked' => 1, /* Don't allow meddling with the values through the UI. */
      'api.OptionValue.create' => array (
        array(
          'name' => 'pain.008.001.02',
          'label' => ts('pain.008.001.02 (ISO 20022/official SEPA guidelines)'),
          'is_default' => 1,
          'is_reserved' => 1,
        ),
        array(
          'name' => 'pain.008.003.02',
          'label' => ts('pain.008.003.02 (German Sonderwurst)'),
          'is_reserved' => 1,
        ),
      ),
    )
  ),
);
