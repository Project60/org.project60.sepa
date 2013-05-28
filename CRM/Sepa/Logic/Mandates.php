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
  public static function createMandateReference( &$ref = null, $type = "R") {
    return 'MANDATE-R-' . sprintf("%08d", rand(0, 999999));
  }
  
  /**
   * Handle the creation of a mandate
   * By default, there is an initial contribution which is created for a recurring contrib. Its status is set
   * to pending. We want to make sure the mandate is valid before we activate this payment, so we need to give it
   * a certain status. This obviously depends on the options set for the PP (make active immediately etc).
   * 
   * @param type $id
   */
  public static function mandateCreated( $id ) {
    
  }

}