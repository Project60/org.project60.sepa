<?php
/*
 * Copyright (C) 2026 SYSTOPIA GmbH
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types = 1);

namespace Civi\Sepa\ActionProvider\Action;

use Civi\ActionProvider\Action\AbstractAction;
use Civi\Api4\SepaCreditor;

abstract class AbstractSepaActionProviderAction extends AbstractAction {

  /**
   * Get a list of all creditors
   *
   * @return array<int, string>
   *   Mapping of creditor ID to creditor name.
   *
   * @throws \CRM_Core_Exception
   */
  protected function getCreditors(): array {
    return SepaCreditor::get(FALSE)
      ->addSelect('id', 'name')
      ->execute()
      ->indexBy('id')
      ->column('name');
  }

}
