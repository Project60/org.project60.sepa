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
      'api.OptionValue.create' => array (
        array(
          'name' => 'pain.008.001.02',
          'label' => ts('pain.008.001.02 (ISO 20022/official SEPA guidelines)'),
          'is_default' => 1,
          'is_reserved' => 1,
        ),
        array(
          'name' => 'pain.008.003.02',
          'label' => ts('pain.008.003.02 container core direct debit (CDC EBICS-2.7)'),
          'is_reserved' => 1,
        ),
        array(
          'name' => 'pain.008.003.02 COR1',
          'label' => ts('pain.008.003.02 COR1 direct debit (CD1 EBICS-2.7)'),
          'is_reserved' => 1,
        ),
      ),
    )
  ),
);
