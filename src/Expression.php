<?php

namespace Silverslice\EasyDb;

/**
 * Represents SQL fragment
 *
 * @package Silverslice\EasyDb
 */
class Expression
{
    /**
     * Storage for expression
     *
     * @var string
     */
    protected $value;

    /**
     * Instantiates an expression
     *
     * @param string $expression The string containing a SQL expression
     */
    public function __construct($expression)
    {
        $this->value = (string) $expression;
    }

    /**
     * @return string The string of the SQL expression stored in this object
     */
    public function __toString()
    {
        return $this->value;
    }
}