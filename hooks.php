<?php

function sepa_civicrm_pre($op, $objectName, $id, &$params) {
  if ($objectName == 'Contribution') {
    //die('Calling sepa_civicrm_pre');
  }
}

