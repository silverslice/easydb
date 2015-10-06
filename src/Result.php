<?php

namespace Silverslice\EasyDb;

/**
 * Represents the result set obtained from a query against the database
 *
 * @package Silverslice\EasyDb
 */
class Result
{
    protected $result;

    public function __construct(\mysqli_result $result)
    {
        $this->result = $result;
    }

    /**
     * Fetchs first cell from the first result row
     *
     * @return string|null
     */
    public function fetchOne()
    {
        $row = $this->result->fetch_row();
        $this->result->free();
        if (is_null($row)) {
            return null;
        }

        return $row[0];
    }

    /**
     * Fetches one row of data from the result set and returns it as an enumerated array
     *
     * @return array|null
     */
    public function fetchRow()
    {
        return $this->result->fetch_row();
    }

    /**
     * Returns an associative array that corresponds to the fetched row
     *
     * @return array|null
     */
    public function fetchAssoc()
    {
        return $this->result->fetch_assoc();
    }

    /**
     * Fetches all result rows from the result set as an associative array
     *
     * @return array
     */
    public function fetchAll()
    {
        $rows = [];
        while ($ar = $this->result->fetch_assoc()) {
            $rows[] = $ar;
        }
        $this->result->free();

        return $rows;
    }

    /**
     * Fetches a single column from the result set
     *
     * @return array
     */
    public function fetchColumn()
    {
        $rows = [];
        while ($ar = $this->result->fetch_row()) {
            $rows[] = $ar[0];
        }
        $this->result->free();

        return $rows;
    }

    /**
     * Fetchs key-value pairs from the result set.
     * The key of the associative array is taken from the first column returned by the query.
     * The value is taken from the second column returned by the query
     *
     * @return array
     */
    public function fetchPairs()
    {
        $rows = array();
        while ($ar = $this->result->fetch_row()) {
            $rows[$ar[0]] = $ar[1];
        }
        $this->result->free();

        return $rows;
    }

    /**
     * Fetchs key-values pairs from the result set.
     * The key of the associative array is taken from the first column returned by the query.
     * The value is an array combined from the other columns
     *
     * @return array
     */
    public function fetchAllKeyed()
    {
        $rows = array();
        while ($ar = $this->result->fetch_assoc()) {
            $key = array_shift($ar);
            $rows[$key] = $ar;
        }
        $this->result->free();

        return $rows;
    }

    /**
     * Frees the memory associated with a result
     */
    public function free()
    {
        $this->result->free();
    }
}
