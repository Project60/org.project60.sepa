<?php

class CRM_Sepa_Logic_Mandates {

  /**
   * Handle creation of mandate references. 
   * TODO: make this modifiable using a hook
   * 
   * @param type $ref
   * @param type $type
   * @return type
   */
  public static function createMandateReference(&$ref = null, $type= "R") {
    $r = "MANDATE";
    if ($ref) {
      $r .="-".$ref["entity_id"];
    } else {
      $r .= "-RAND".sprintf("%08d", rand(0, 999999));
    }
    return $r;
  }

  /**
   * Handle the creation of a mandate
   * By default, there is an initial contribution which is created for a recurring contrib. Its status is set
   * to pending. We want to make sure the mandate is valid before we activate this payment, so we need to give it
   * a certain status. This obviously depends on the options set for the PP (make active immediately etc).
   * 
   * @param type $id
   */
  public static function fix_initial_contribution(CRM_Sepa_BAO_SEPAMandate $bao) {
    // for now, assume we can set the status to 'Pending' -- until we decide what the SEPA status should be
    // as this is the current status which is created, do nothing
  }

  //hook which batches the contribution when it is created (using the hook magic function)
  public static function hook_post_contribution_create($objectId, $objectRef) {
    self::post_contribution_modify( $objectId, $objectRef);
  }
  public static function hook_post_contribution_edit($objectId, $objectRef) {
    self::post_contribution_modify( $objectId, $objectRef);
  }
  public static function post_contribution_modify($objectId, $objectRef) {
    // check whether this is a SDD contribution
//    echo '<pre>';
//    die(print_r($objectRef));
  }

}
