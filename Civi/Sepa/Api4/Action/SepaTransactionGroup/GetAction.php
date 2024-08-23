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
use Civi\Api4\SepaContributionGroup;

class GetAction extends DAOGetAction {

  public function _run(Result $result) {
    if ($this->getCheckPermissions()) {
      // Count permissioned contributions (in the join) and the total number of contributions in the transaction group,
      // and extract those with matching counts, i.e. groups of which the user has permission to view all contributions.
      $fullyPermissionedTxgroups = SepaContributionGroup::get()
        ->addSelect(
          'txgroup_id',
          'COUNT(contribution.id) AS allowed_contributions',
          'COUNT(*) AS total_contributions'
        )
        ->addJoin('Contribution AS contribution', 'LEFT', ['contribution.id', '=', 'contribution_id'])
        ->addGroupBy('txgroup_id')
        ->addHaving('allowed_contributions', '=', 'total_contributions', TRUE)
        ->execute()
        ->column('txgroup_id');
      $this
      ->addWhere('id', 'IN', $fullyPermissionedTxgroups);
    }
    return parent::_run($result);
  }

}
