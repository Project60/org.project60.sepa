<?php
/*-------------------------------------------------------+
| Project 60 - SEPA direct debit - PHPUnit tests         |
| Copyright (C) 2019 SYSTOPIA                            |
| Author: B. Zschiedrich (zschiedrich@systopia.de)       |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * Constraint that a mandate reference is valid.
 */
class CRM_Sepa_Constraints_MandateReferenceIsValid extends PHPUnit_Framework_Constraint
{
    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     * @param mixed $other Value or object to evaluate.
     * @return bool
     */
    protected function matches($other): bool
    {
        $isValid = is_string($other) &&
            (strlen($other) > 0) &&
            (strlen($other) <= 35) &&
            !preg_match("/[^0-9A-Za-z\+\?\/\-\:\(\)\.\,\' ]/", $other); // There must be no invalid character found.

        return $isValid;
    }

    /**
     * We do not need this here because we have failureDescription.
     */
    public function toString() : string
    {
        return '';
    }

    /**
     * Returns the description of the failure
     *
     * The beginning of failure messages is "Failed asserting that" in most
     * cases. This method should return the second part of that sentence.
     *
     * To provide additional failure information additionalFailureDescription
     * can be used.
     *
     * @param mixed $other Evaluated value or object.
     * @return string
     */
    protected function failureDescription($other): string
    {
        return "$other is a valid SEPA mandate reference.";
    }
}
