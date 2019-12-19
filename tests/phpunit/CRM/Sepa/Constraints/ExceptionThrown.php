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
 * Constraint that an acception is thrown.
 */
class CRM_Sepa_Constraints_ExceptionThrown extends PHPUnit_Framework_Constraint
{
    protected $exceptionType;

    /**
     * @param string $exceptionType
     * @throws PHPUnit_Framework_Exception
     */
    public function __construct(string $exceptionType)
    {
        parent::__construct();

        $this->exceptionType = $exceptionType;
    }

    /**
     * Evaluates the constraint for parameter $other. Returns true if the
     * constraint is met, false otherwise.
     * @param mixed $other Value or object to evaluate.
     * @return bool
     */
    protected function matches($other)
    {
        $matched = is_subclass_of($other, $this->exceptionType);

        return $matched;
    }

    /**
     * We do not need this here because we have failureDescription.
     */
    public function toString()
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
    protected function failureDescription($other)
    {
        if(!$other)
        {
            return 'exception ' . $this->exceptionType . ' is thrown';
        }
        else
        {
            $exceptionType = '';

            if (is_string($other))
            {
                $exceptionType = $other;
            }
            else
            {
                $exceptionType = get_class($other);
            }

            return "thrown exception $exceptionType is " . $this->exceptionType;
        }
    }
}
