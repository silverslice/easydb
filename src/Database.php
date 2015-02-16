<?php

namespace Silverslice\EasyDb;

class Database
{
    /**
     * @var \mysqli
     */
    protected $db;

    protected $args;

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
     * @param $q
     * @return bool | \mysqli_result
     */
    public function plainQuery($q)
    {
        return $this->conn()->query($q);
    }

    /**
     * Performs a query on the database
     *
     * param string    $query   Sql query
     * param mixed  ...$params  Values to match placeholders in the query
     *
     * @return bool | \mysqli_result
     *
     * @throws Exception
     */
    public function query()
    {
        $sql = $this->parse(func_get_args());
        $res = $this->conn()->query($sql);

        if (!$res) {
            throw new Exception($this->conn()->error, $this->conn()->errno);
        }

        return $res;
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

        return preg_replace_callback('#\?[isfaep]?#', function($m) use ($params) {
            if (!sizeof($params)) {
                return "''"; // no more params - insert ''
            }

            $value = array_shift($params);
            switch ($m[0]) {
                case '?': // smart mode
                    if (is_int($value)) {
                        return $value;
                    } elseif (is_null($value)) {
                        return 'null';
                    } elseif (is_array($value) && isset($value['db_expr'])) {
                        return $value['db_expr'];
                    } else {
                        return "'" . $this->escape($value) . "'";
                    }
                case '?i': return intval($value); // integer
                case '?s': return "'" . $this->escape($value) . "'"; // string
                case '?f': return str_replace(',', '.', floatval($value)); // float
                case '?e': return $this->escape($value); // escape
                case '?p': return $value; // sql part
                case '?a': // array
                    if (!$value) {
                        return 'NULL';
                    }
                    foreach ($value as &$e) {
                        if (!is_int($e)) {
                            $e = "'" . $this->escape($e) . "'";
                        }
                    }

                    return implode(',', $value);
            }

            return '';
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
     * Opens a connection to a mysql server
     *
     * @throws Exception
     */
    protected function connect()
    {
        $options = $this->options;
        $conn = mysqli_init();
        if ($this->mysqlOptions) {
            foreach ($this->mysqlOptions as $option => $value) {
                $conn->options($option, $value);
            }
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
     * Returns mysqli instance
     *
     * @return \mysqli
     * @throws \Exception
     */
    protected function conn()
    {
        if (!$this->db) {
            $this->connect();
        }

        return $this->db;
    }

}