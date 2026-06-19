<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 */
class CRM_Sepa_BAO_SEPAContributionGroup extends CRM_Sepa_DAO_SEPAContributionGroup {

  /**
   * @param array $params
   *
   * @return \CRM_Sepa_DAO_SEPAContributionGroup
   * @access public
   * @static
   */
  public static function add(&$params) {
    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, 'SepaContributionGroup', $params['id'] ?? NULL, $params);

    $dao = new CRM_Sepa_DAO_SEPAContributionGroup();
    $dao->copyValues($params);
    $dao->save();

    CRM_Utils_Hook::post($hook, 'SepaContributionGroup', (int) $dao->id, $dao);
    /** @var \CRM_Sepa_DAO_SEPAContributionGroup $dao */
    return $dao;
  }

}
