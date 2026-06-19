<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 * @property string $id
 * @property string $creditor_id
 * @property string $identifier
 * @property string $name
 * @property string $label
 * @property string $address
 * @property string $country_id
 * @property string $iban
 * @property string $bic
 * @property string $mandate_prefix
 * @property string $currency
 * @property string $payment_processor_id
 * @property string $category
 * @property string $tag
 * @property bool|string $mandate_active
 * @property string $sepa_file_format_id
 * @property string $creditor_type
 * @property string $pi_ooff
 * @property string $pi_rcur
 * @property bool|string $uses_bic
 * @property string $cuc
 */
class CRM_Sepa_DAO_SEPACreditor extends CRM_Sepa_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_sdd_creditor';

}
