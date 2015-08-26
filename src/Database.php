<?php

namespace Silverslice\EasyDb;

/**
 * Easy wrapper for mysqli extension
 *
 * @package Silverslice\EasyDb
 */
class Database
{
    /**
     * @var \mysqli
     */
    protected $db;

    /**
     * @var array Connection options
     */
    protected $options = array(
        'host'     => 'localhost',
        'username' => 'root',
        'password' => '',
        'dbname'   => 'testdb',
        'charset'  => 'utf8',
        'port'     => null,
        'socket'   => null,
        'flags'    => null,
    );

    /**
     * @var array
     */
    protected $mysqlOptions = array();

    /**
     * Constructor
     *
     * @param array $options       Connect information: host, username, password, dbname, charset
     * @param array $mysqlOptions  Additional mysql options
     *
     * @see Database::$defaultOptions for all list available options
     */
    public function __construct($options, $mysqlOptions = array())
    {
        $this->options = $options;
        $this->mysqlOptions = $mysqlOptions;
    }

    /**
     * Sets the default client character set
     *
     * @param string $charset
     * @return bool
     */
    public function setCharset($charset)
    {
        return $this->conn()->set_charset($charset);
    }

    /**
     * Escapes special characters in a string
     *
     * @param string $str
     * @return string
     */
    public function escape($str)
    {
        return $this->conn()->real_escape_string($str);
    }

    /**
     * Performs a query on the database directly
     *
     * @param $query
     * @return bool|Result
     *
     * @throws Exception
     */
    public function rawQuery($query)
    {
        $res = $this->conn()->query($query);

        if (!$res) {
            throw new Exception($this->conn()->error, $this->conn()->errno, $query);
        }

        if ($res instanceof \mysqli_result) {
            return new Result($res);
        }

        return $res;
    }

    /**
     * Performs a query on the database
     *
     * param string    $query   Sql query
     * param mixed  ...$params  Values to match placeholders in the query
     *
     * @return bool|Result
     */
    public function query()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql);
    }

    /**
     * Returns string with replaced placeholders
     *
     * @param mixed  ...$params  Sql query and values to match placeholders in the query
     * @return string
     */
    public function prepare($params)
    {
        if (!is_array($params)) {
            $params = func_get_args();
        }

        $query = array_shift($params);

        return preg_replace_callback('#\?[isfaepu]?#', function($m) use (&$params) {
            if (!sizeof($params)) {
                throw new Exception("Count of parameters doesn't correspond to the count of placeholders");
            }

            $value = array_shift($params);
            $str = '';

            switch ($m[0]) {
                case '?':
                    $str = $this->quoteSmart($value); // automatically
                    break;
                case '?i':
                    $str = intval($value); // integer
                    break;
                case '?s':
                    $str = $this->quoteString($value); // string
                    break;
                case '?f':
                    $str = $this->quoteFloat($value); // float
                    break;
                case '?e':
                    $str = $this->escape($value); // escape
                    break;
                case '?p':
                    $str = $value; // sql part
                    break;
                case '?a': // array
                    $str = $this->quoteArray($value);
                    break;
                case '?u': // column = value separated by comma
                    $str = $this->createSet($value);
                    break;
            }

            return $str;
        }, $query);
    }

    /**
     * Executes one or multiple queries which are concatenated by a semicolon
     *
     * @param string $queries
     * @return bool
     * @throws Exception
     */
    public function multiQuery($queries)
    {
        $conn = $this->conn();
        $res = $conn->multi_query($queries);
        if (!$res) {
            throw new Exception($this->conn()->error, $this->conn()->errno);
        }
        while ($conn->more_results()) {
            $res = $conn->next_result();
            if (!$res) {
                throw new Exception($this->conn()->error, $this->conn()->errno);
            }
        }

        return true;
    }

    /**
     * Returns the auto generated id used in the last query
     *
     * @return mixed
     */
    public function insertId()
    {
        return $this->conn()->insert_id;
    }

    /**
     * Returns the number of affected rows in a previous MySQL operation
     *
     * @return mixed
     */
    public function affectedRows()
    {
        return $this->conn()->affected_rows;
    }

    /**
     * Peforms query and fetchs first cell from the first result row
     *
     * @return string|null
     */
    public function getOne()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql)->fetchOne();
    }

    /**
     * Peforms query and fetchs first result row as an associative array
     *
     * @return array|null
     */
    public function getAssoc()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql)->fetchAssoc();
    }

    /**
     * Peforms query and fetchs all result rows as an associative array
     *
     * @return array
     */
    public function getAll()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql)->fetchAll();
    }

    /**
     * Peforms query and fetchs one column from the result set as an enumerate array
     *
     * @return array
     */
    public function getColumn()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql)->fetchColumn();
    }

    /**
     * Peforms query and fetchs key-value pairs from the result set.
     * The key of the associative array is taken from the first column returned by the query.
     * The value is taken from the second column returned by the query
     *
     * @return array
     */
    public function getPairs()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql)->fetchPairs();
    }

    /**
     * Peforms query and fetchs key-values pairs from the result set.
     * The key of the associative array is taken from the first column returned by the query.
     * The value is an array combined from the other columns
     *
     * @return array|bool
     */
    public function getAllKeyed()
    {
        $sql = $this->prepare(func_get_args());

        return $this->rawQuery($sql)->fetchAllKeyed();
    }

    /**
     * Inserts row into table
     *
     * @param string $table   Table name
     * @param array  $params  Column-value pairs
     * @param bool   $ignore  Use or not IGNORE keyword
     * @return mixed Inserted row id or true if table hasn't autoincrement field
     */
    public function insert($table, $params, $ignore = false)
    {
        $table = $this->quoteIdentifier($table);
        $ignore = $ignore ? 'IGNORE' : '';
        $sql = "INSERT $ignore INTO $table SET " . $this->createSet($params);

        $this->rawQuery($sql);
        $res = $this->insertId();
        if ($res === 0) { // no autoincrement field
            $res = true;
        }

        return $res;
    }

    /**
     * Updates table rows
     *
     * @param string $table  Table name
     * @param array  $params Column-value pairs
     * @param array  $where  UPDATE WHERE clause(s). Several conditions will be concatenated with AND keyword
     * @return int   The number of affected rows
     */
    public function update($table, $params, $where = array())
    {
        $table = $this->quoteIdentifier($table);
        $sql = "UPDATE $table SET " . $this->createSet($params) . $this->createWhere($where);

        $this->rawQuery($sql);

        return $this->affectedRows();
    }

    /**
     * Inserts or updates table row using INSERT ... ON DUPLICATE KEY UPDATE clause
     *
     * @param string  $table   Table name
     * @param array   $insert  Column-value pairs to insert
     * @param array   $update  Column-value pairs to update if key already exists in table
     * @return int    The number of affected rows: 1 if row was inserted or 2 if row was updated
     */
    public function insertUpdate($table, $insert, $update = array())
    {
        if (!$update) {
            $update = $insert;
        }

        $table = $this->quoteIdentifier($table);
        $sql = "INSERT INTO $table SET " . $this->createSet($insert) .
               " ON DUPLICATE KEY UPDATE " . $this->createSet($update);

        $this->rawQuery($sql);

        return $this->affectedRows();
    }

    /**
     * Inserts multiple rows into table
     *
     * @param string $table   Table name
     * @param array  $fields  Field names
     * @param array  $data    Two-dimensional array with data to insert
     * @param bool   $ignore  Use or not IGNORE keyword
     * @return int   The number of affected rows
     */
    public function multiInsert($table, $fields, $data, $ignore = false)
    {
        $table = $this->quoteIdentifier($table);
        $ignore = $ignore ? 'IGNORE' : '';
        $fields = array_map(array($this, 'quoteIdentifier'), $fields);

        $sql = "INSERT $ignore INTO $table (" . join(', ', $fields) . ") VALUES ";
        foreach ($data as $i => $row) {
            foreach ($row as &$field) {
                $field = $this->quoteSmart($field);
            }
            $sql .= '(' . join(', ', $row) . '), ';
        }
        $sql = rtrim($sql, ', ');
        $this->rawQuery($sql);

        return $this->affectedRows();
    }

    /**
     * Deletes table rows
     *
     * @param string $table  Table name
     * @param array  $where  UPDATE WHERE clause(s). Several conditions will be concatenated with AND keyword
     * @return int   The number of affected rows
     */
    public function delete($table, $where = array())
    {
        $table = $this->quoteIdentifier($table);
        $sql = "DELETE FROM $table" . $this->createWhere($where);

        $this->rawQuery($sql);

        return $this->affectedRows();
    }

    /**
     * Starts a transaction
     */
    public function beginTransaction()
    {
        $this->rawQuery('START TRANSACTION');
    }

    /**
     * Commits the current transaction
     */
    public function commit()
    {
        $this->rawQuery('COMMIT');
    }

    /**
     * Rolls back current transaction
     */
    public function rollback()
    {
        $this->rawQuery('ROLLBACK');
    }

    /**
     * Runs code in transaction
     *
     * @param callable $process  Callback to process
     * @return bool    True if transaction was successful commited, false otherwise
     *
     * @throws Exception
     */
    public function transaction($process)
    {
        if (!is_callable($process)) {
            throw new Exception('Invalid argument for process, callable expected');
        }
        try {
            $this->beginTransaction();
            $process();
            $this->commit();
            return true;

        } catch (Exception $e) {
            $this->rollback();
            return false;
        }
    }


    /**
     * Opens a connection to a mysql server
     *
     * @throws Exception
     */
    protected function connect()
    {
        $options = $this->options;
        $conn = mysqli_init();
        if ($this->mysqlOptions) {
            $this->setOptions($conn, $this->mysqlOptions);
        }
        $res = @$conn->real_connect($options['host'], $options['username'], $options['password'],
            $options['dbname'], $options['port'], $options['socket'], $options['flags']);
        if ($res === false) {
            throw new Exception($conn->connect_errno . ': ' . $conn->connect_error);
        }

        $this->db = $conn;
        $this->setCharset($options['charset']);
    }

    /**
     * Set mysql options
     *
     * @param \mysqli $conn
     * @param array   $options
     */
    protected function setOptions(\mysqli $conn, $options)
    {
        foreach ($options as $option => $value) {
            $conn->options($option, $value);
        }
    }

    /**
     * Returns mysqli instance
     *
     * @return \mysqli
     */
    protected function conn()
    {
        if (!$this->db) {
            $this->connect();
        }

        return $this->db;
    }

    /**
     * Quotes value automatically
     *
     * @param mixed $value
     * @return string
     */
    protected function quoteSmart($value)
    {
        if (is_int($value)) {
            return $value;
        } elseif (is_null($value)) {
            return 'null';
        } elseif ($value instanceof Expression) {
            return $value;
        }

        return $this->quoteString($value);
    }

    /**
     * Quotes array
     *
     * @param array $value
     * @return string
     */
    protected function quoteArray($value)
    {
        if (!$value) {
            return 'NULL';
        }
        foreach ($value as &$e) {
            if (!is_int($e)) {
                $e = $this->quoteString($e);
            }
        }

        return implode(',', $value);
    }

    /**
     * Quotes string
     *
     * @param string $value
     * @return string
     */
    protected function quoteString($value)
    {
        return "'" . $this->escape($value) . "'";
    }

    /**
     * Quotes float value
     *
     * @param string $value
     * @return string
     */
    protected function quoteFloat($value)
    {
        return str_replace(',', '.', floatval($value));
    }

    /**
     * Quotes an identifier
     *
     * @param string $value The identifier
     * @return string The quoted identifier
     */
    protected function quoteIdentifier($value)
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * Creates SET clause
     *
     * @param array $pairs  Column-value pairs
     * @return string
     */
    protected function createSet($pairs)
    {
        $parts = array();
        foreach ($pairs as $field => $value) {
            $parts[] = $this->quoteIdentifier($field) . ' = ' . $this->quoteSmart($value);
        }

        return implode(', ', $parts);
    }

    /**
     * Creates WHERE clause with placeholders
     *
     * @param array $where
     * @return string
     */
    protected function createWhere($where)
    {
        if (!$where) {
            return '';
        }

        $parts = array();
        foreach ($where as $field => $value) {
            $parts[] = $this->quoteIdentifier($field) . ' = ' . $this->quoteSmart($value);
        }

        return " WHERE " . join(' AND ', $parts);
    }
}