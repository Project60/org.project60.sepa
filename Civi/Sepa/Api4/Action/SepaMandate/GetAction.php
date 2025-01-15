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

namespace Civi\Sepa\Api4\Action\SepaMandate;

use Civi\Api4\Generic\DAOGetAction;
use Civi\Api4\Generic\Result;

class GetAction extends DAOGetAction {

  public function _run(Result $result) {
    // Add unique joins for permission checks of Financial ACLs.
    if ($this->getCheckPermissions()) {
      $contributionAlias = uniqid('contribution_');
      $contributionRecurAlias = uniqid('contribution_recur_');
      $this
        ->addJoin(
          'Contribution AS ' . $contributionAlias,
          'LEFT',
          ['entity_table', '=', '"civicrm_contribution"'],
          ['entity_id', '=', $contributionAlias . '.id']
        )
        ->addJoin(
          'ContributionRecur AS ' . $contributionRecurAlias,
          'LEFT',
          ['entity_table', '=', '"civicrm_contribution_recur"'],
          ['entity_id', '=', $contributionRecurAlias . '.id']
        )
        ->addClause(
          'OR',
          ['AND', [['type', '=', 'OOFF'], [$contributionAlias . '.id', 'IS NOT NULL']]],
          ['AND', [['type', '=', 'RCUR'], [$contributionRecurAlias . '.id', 'IS NOT NULL']]]
        );
    }
    return parent::_run($result);
  }

}
