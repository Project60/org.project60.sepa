<?php
/*
 * Copyright (C) 2022 SYSTOPIA GmbH
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation in version 3.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Sepa\Api4\Action\SepaMandate;

use Civi\Api4\Generic\AbstractCreateAction;
use Civi\Api4\Generic\DAOCreateAction;
use Civi\Api4\Generic\Result;
use Civi\Sepa\Util\ContributionUtil;

class CreatefullAction extends DAOCreateAction {

  public function __construct() {
    parent::__construct('SepaMandate', 'createfull');
  }

  public function entityFields() {
    $mandateFields = parent::entityFields();
    // TODO: Add contribution fields.
  }

  /**
   * @inheritDoc
   */
  public function _run(Result $result) {
    // TODO: Call CRM_Sepa_BAO_SEPAMandate::createfull().
  }

}
