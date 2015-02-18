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
     * @return bool | \mysqli_result
     *
     * @throws Exception
     */
    public function rawQuery($query)
    {
        $res = $this->conn()->query($query);

        if (!$res) {
            throw new Exception($this->conn()->error, $this->conn()->errno);
        }

        return $res;
    }

    /**
     * Performs a query on the database
     *
     * param string    $query   Sql query
     * param mixed  ...$params  Values to match placeholders in the query
     *
     * @return bool | \mysqli_result
     */
    public function query()
    {
        $sql = $this->parse(func_get_args());

        return $this->rawQuery($sql);
    }

    /**
     * Returns string with replaced placeholders
     *
     * @param mixed  ...$params  Sql query and values to match placeholders in the query
     * @return string
     */
    public function parse($params)
    {
        if (!is_array($params)) {
            $params = func_get_args();
        }

        $query = array_shift($params);

        return preg_replace_callback('#\?[isfaep]?#', function($m) use (&$params) {
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
                    $str = str_replace(',', '.', floatval($value)); // float
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
     * Peforms query and fetchs first cell for the first result row
     *
     * @return string|null
     */
    public function getOne()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->rawQuery($sql);
        $row = $res->fetch_row();
        if (is_null($row)) {
            return null;
        }

        return $row[0];
    }

    /**
     * Peforms query and fetchs first result row as an associative array
     *
     * @return array|null
     */
    public function getAssoc()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->rawQuery($sql);

        return $res->fetch_assoc();
    }

    /**
     * Peforms query and fetchs all result rows as an associative array
     *
     * @return array
     */
    public function getAll()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->rawQuery($sql);

        $rows = array();
        while ($ar = $res->fetch_assoc()) {
            $rows[] = $ar;
        }

        return $rows;
    }

    /**
     * Peforms query and fetchs one column in result set as an enumerate array
     *
     * @return array
     */
    public function getColumn()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->rawQuery($sql);

        $rows = array();
        while ($ar = $res->fetch_row()) {
            $rows[] = $ar[0];
        }

        return $rows;
    }

    /**
     * Peforms query and fetchs key-value pairs in result set.
     * The key of the associative array is taken from the first column returned by the query.
     * The value is taken from the second column returned by the query
     *
     * @return array
     */
    public function getPairs()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->rawQuery($sql);

        $rows = array();
        while ($ar = $res->fetch_row()) {
            $rows[$ar[0]] = $ar[1];
        }

        return $rows;
    }

    /**
     * Peforms query and fetchs key-values pairs in result set.
     * The key of the associative array is taken from the first column returned by the query.
     * The value is an array combined from the other columns
     *
     * @return array|bool
     */
    public function getAllKeyed()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->rawQuery($sql);

        $rows = array();
        while ($ar = $res->fetch_assoc()) {
            $key = array_shift($ar);
            $rows[$key] = $ar;
        }

        return $rows;
    }

    /**
     * Inserts row into table
     *
     * @param string $table  Table name
     * @param array  $params Column-value pairs
     * @return mixed Inserted row id or true if table hasn't autoincrement field
     */
    public function insert($table, $params)
    {
        $sql = "INSERT INTO `$table` SET `". join('` = ?,`', array_keys($params)) ."` = ?";
        $args[0] = $sql;
        $args = array_merge($args, array_values($params));

        $sql = $this->parse($args);
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
        $sql = "UPDATE `$table` SET `". join('` = ?,`', array_keys($params)) ."` = ?";
        if ($where) {
            $whereParts = array();
            foreach ($where as $key => $value) {
                $whereParts[] = "{$key} = ?";
            }
            $sql .= "WHERE " . join(' AND ', $whereParts);
        }
        $args[0] = $sql;
        $args = array_merge($args, array_values($params), array_values($where));
        $sql = $this->parse($args);
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

        $sql = "INSERT INTO `$table` SET `". join('` = ?, `', array_keys($insert)) ."` = ? ON DUPLICATE KEY UPDATE `" .
            join('` = ?, `', array_keys($update)) . '` = ?';
        $args[0] = $sql;
        $args = array_merge($args, array_values($insert), array_values($update));
        $sql = $this->parse($args);
        $this->rawQuery($sql);

        return $this->affectedRows();
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
     * @param $value
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
     * @param $value
     * @return string
     */
    protected function quoteString($value)
    {
        return "'" . $this->escape($value) . "'";
    }
}