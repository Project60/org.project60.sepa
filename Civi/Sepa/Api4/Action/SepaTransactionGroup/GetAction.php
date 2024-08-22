<?php
/*
 * Copyright (C) 2024 SYSTOPIA GmbH
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

namespace Civi\Sepa\Api4\Action\SepaTransactionGroup;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;

class GetAction extends DAOGetAction {

  public function _run(Result $result) {
    // Add unique joins for permission checks of Financial ACLs.
    if ($this->getCheckPermissions()) {
      $contributionAlias = uniqid('contribution_');
      $this
        ->addJoin(
          'Contribution AS ' . $contributionAlias,
          'INNER',
          'SepaContributionGroup'
        );
    }
    return parent::_run($result);
  }

}
