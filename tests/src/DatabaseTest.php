<?php

namespace Silverslice\EasyDb\Tests;

use Silverslice\EasyDb\Database;
use Silverslice\EasyDb\Exception;

/**
 * Common tests for Database class
 *
 * @package Silverslice\EasyDb\Tests
 */
class DatabaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Database
     */
    protected $db;

    public function setUp()
    {
        $options = include __DIR__ . '/../config.php';
        $this->db = new Database($options);

        $this->db->multiQuery("
            DROP TABLE IF EXISTS `test`;
            CREATE TABLE `test` (
             `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
             `code` char(15) NOT NULL,
             `name` varchar(200) NOT NULL,
             `price` decimal(10,2) unsigned DEFAULT NULL,
             `order` int(11) unsigned DEFAULT 0,
             PRIMARY KEY (`id`),
             KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            INSERT INTO test (id, code, name, price)
            VALUES
              (1, '001', 'Cup', 20.00),
              (2, '002', 'Plate', 30.50)
        ");
    }

    public function tearDown()
    {
        $this->db->query('DROP TABLE IF EXISTS `test`;');
    }

    /**
     * @expectedException Exception
     */
    public function testConnectFail()
    {
        $options = include __DIR__ . '/../config.php';
        $options['password'] = uniqid();
        $conn = new Database($options);
        $conn->query('SELECT 1');
    }

    public function testSetMysqlOptions()
    {
        $options = include __DIR__ . '/../config.php';
        $mysql = [MYSQLI_INIT_COMMAND => 'SET AUTOCOMMIT = 0'];
        $conn = new Database($options, $mysql);
        $autocommit = $conn->getOne('SELECT @@autocommit');
        $this->assertEquals(0, $autocommit);
    }

    public function testEscape()
    {
        $str = $this->db->escape("test '");
        $this->assertEquals("test \\'", $str);

        $str = $this->db->escape('test "');
        $this->assertEquals('test \\"', $str);

        $str = $this->db->escape('test \\');
        $this->assertEquals('test \\\\', $str);
    }

    /**
     * @expectedException Exception
     */
    public function testQueryFail()
    {
        $this->db->query("
            INSERT INTO test2 (code, name)
            VALUES ('003', 'Cup2');
        ");
    }

    public function testRawQuery()
    {
        $this->db->rawQuery("
            INSERT INTO test (code, name)
            VALUES ('003', 'Cup2');
        ");
        $this->assertEquals(3, $this->getRowCount());
    }

    public function testMultiQuery()
    {
        $this->db->multiQuery("
            INSERT INTO test (code, name)
            VALUES ('003', 'Cup2');
            INSERT INTO test (code, name)
            VALUES ('004', 'Plate2')
        ");
        $this->assertEquals(4, $this->getRowCount());

        // test multiQuery correctly flushes queries
        $this->db->query("
            INSERT INTO test (code, name)
            VALUES ('005', 'Pan2');
        ");

        // test multiQuery correctly free results
        $this->db->multiQuery("
            SELECT * FROM test;
            SELECT name FROM test;
            INSERT INTO test (code, name)
            VALUES ('006', 'Pan3');
            SELECT code FROM test;
        ");

        $this->db->query("
            INSERT INTO test (code, name)
            VALUES ('007', 'Pan4');
        ");

        $this->assertEquals(7, $this->getRowCount());


        // test multiQuery correctly free results with error in query
        try {
            $this->db->multiQuery("
                SELECT * FROM test;
                SELECT name1 FROM test;
                INSERT INTO test (code, name)
                VALUES ('008', 'Pan8');
                SELECT code FROM test;
            ");
        } catch (Exception $e) {
            $this->db->query("
                INSERT INTO test (code, name)
                VALUES ('008', 'Pan8');
            ");
        }

        $this->assertEquals(8, $this->getRowCount());
    }

    /**
     * @expectedException Exception
     */
    public function testMultiQueryExceptionOnErrorInFirstQuery()
    {
        $this->db->multiQuery("
            INSERT INTO test2 (code, name)
            VALUES ('003', 'Cup2');
            INSERT INTO test (code, name)
            VALUES ('004', 'Plate2')
        ");
    }

    /**
     * @expectedException Exception
     */
    public function testMultiQueryExceptionOnErrorInSecondQuery()
    {
        $this->db->multiQuery("
            INSERT INTO test (code, name)
            VALUES ('003', 'Cup2');
            INSERT INTO test2 (code, name)
            VALUES ('004', 'Plate2')
        ");
    }

    public function testInsertId()
    {
        $this->db->query("
            INSERT INTO test (code, name)
            VALUES ('005', 'Pan2');
        ");
        $this->assertEquals(3, $this->db->insertId());
    }

    public function testAffectedRows()
    {
        $this->db->query("DELETE FROM test");
        $this->assertEquals(2, $this->db->affectedRows());
    }

    public function testExceptionData()
    {
        $query = '';
        try {
            $this->db->query("SELECTT 1");
        } catch (Exception $e) {
            $code = $e->getCode();
            $message = $e->getMessage();
            $query = $e->getQuery();
        }

        $this->assertEquals(1064, $code);
        $this->assertEquals('Error 1064: "You have an error in your SQL syntax; check the manual that corresponds ' .
            'to your MySQL server version for the right syntax to use near ' .
            '\'SELECTT 1\' at line 1"; Query = "SELECTT 1"', $message);
        $this->assertEquals('SELECTT 1', $query);
    }

    protected function getRowCount()
    {
        return $this->db->getOne('SELECT count(*) FROM test');
    }
}
