<?php

namespace Silverslice\EasyDb\Tests;
use Silverslice\EasyDb\Database;
use Silverslice\EasyDb\Exception;

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
             PRIMARY KEY (`id`),
             KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            INSERT INTO test (id, code, name, price)
            VALUES
              (1, '001', 'Cup', 20.00),
              (2, '002', 'Plate', 30.50),
              (3, '003', 'Pan', 40.00)
        ");
    }

    public function tearDown()
    {
        $this->db->query('DROP TABLE IF EXISTS `test`;');
    }

    /**
     * @expectedException Exception
     */
    public function testQueryException()
    {
        $this->db->multiQuery("
            INSERT INTO test2 (code, name)
            VALUES ('003', 'Cup2');
        ");
    }

    public function testMultiQuery()
    {
        $this->db->multiQuery("
            INSERT INTO test (code, name)
            VALUES ('003', 'Cup2');
            INSERT INTO test (code, name)
            VALUES ('004', 'Plate2')
        ");
        $this->assertEquals(5, $this->getRowCount());

        // test multiQuery correctly flushes queries
        $this->db->query("
            INSERT INTO test (code, name)
            VALUES ('005', 'Pan2');
        ");
        $this->assertEquals(6, $this->getRowCount());
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

    public function testGetOne()
    {
        $name = $this->db->getOne('SELECT name FROM test WHERE id = 1');
        $this->assertEquals('Cup', $name);
    }

    public function testGetOneNotExists()
    {
        $name = $this->db->getOne('SELECT name FROM test WHERE id = 100');
        $this->assertNull($name);
    }

    public function testGetAssoc()
    {
        $res = $this->db->getAssoc('SELECT code, name FROM test WHERE id = 1');
        $expect = [
            'code' => '001',
            'name' => 'Cup'
        ];
        $this->assertEquals($expect, $res);
    }

    public function testGetAssocNotExists()
    {
        $res = $this->db->getAssoc('SELECT code, name FROM test WHERE id = 100');
        $this->assertNull($res);
    }

    public function testGetAll()
    {
        $res = $this->db->getAll('SELECT code, name FROM test WHERE id IN (1, 2)');
        $expect = [
            [
                'code' => '001',
                'name' => 'Cup'
            ],
            [
                'code' => '002',
                'name' => 'Plate'
            ],
        ];
        $this->assertEquals($expect, $res);
    }

    public function testGetAllEmpty()
    {
        $res = $this->db->getAll('SELECT code, name FROM test WHERE id = 100');
        $this->assertSame([], $res);
    }


    protected function getRowCount()
    {
        return $this->db->getOne('SELECT count(*) FROM test');
    }
}