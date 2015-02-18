<?php

namespace Silverslice\EasyDb\Tests;

use Silverslice\EasyDb\Database;

/**
 * Tests for selection methods
 *
 * @package Silverslice\EasyDb\Tests
 */
class SelectTest extends \PHPUnit_Framework_TestCase
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

    public function testGetColumn()
    {
        $res = $this->db->getColumn('SELECT code FROM test WHERE id IN (1, 2)');
        $expect = ['001', '002'];
        $this->assertEquals($expect, $res);
    }

    public function testGetPairs()
    {
        $res = $this->db->getPairs('SELECT code, name FROM test WHERE id IN (1, 2)');
        $expect = [
            '001' => 'Cup',
            '002' => 'Plate'
        ];
        $this->assertEquals($expect, $res);
    }

    public function testGetAllKeyed()
    {
        $res = $this->db->getAllKeyed('SELECT code, name, price FROM test WHERE id IN (1, 2)');
        $expect = [
            '001' => [
                'name' => 'Cup',
                'price' => '20.00'
            ],
            '002' => [
                'name' => 'Plate',
                'price' => '30.50'
            ],
        ];
        $this->assertEquals($expect, $res);
    }
}