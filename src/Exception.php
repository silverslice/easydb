<?php

namespace Silverslice\EasyDb;

/**
 * Base Exception for package
 *
 * @package Silverslice\EasyDb
 */
class Exception extends \Exception 
{
    protected $query;

    /**
     * @param string  $message
     * @param int     $code
     * @param string  $query
     */
    public function __construct($message, $code = 0, $query = '')
    {
        $this->query = $query;
        if ($query) {
            $message = 'Error ' . $code.': "' . $message . '"; Query = "' . $query . '"';
        }

        parent::__construct($message, $code);
    }

    /**
     * Returns query that caused the error
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }
}