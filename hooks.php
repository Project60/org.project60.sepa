<?php

function sepa_civicrm_pre($op, $objectName, $id, &$params) {
  $parts = array(
      'hook',
      'pre',
      strtolower($objectName),
      strtolower($op)
  );
  $methodName = implode('_', $parts);
  if (method_exists('CRM_Sepa_Logic_Mandates', $methodName))
    CRM_Sepa_Logic_Mandates::$methodName($id, $params);
}


