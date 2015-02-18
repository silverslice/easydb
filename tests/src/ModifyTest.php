<?php

namespace Silverslice\EasyDb\Tests;

use Silverslice\EasyDb\Database;

/**
 * Tests for modification methods
 *
 * @package Silverslice\EasyDb\Tests
 */
class ModifyTest extends \PHPUnit_Framework_TestCase
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
        $this->db->query('DROP TABLE IF EXISTS `test_no_ai`;');
    }

    public function testInsert()
    {
        $data = [
            'code' => '003',
            'name' => 'Pan',
            'price' => '22.9',
            'order' => 1,
        ];
        $id = $this->db->insert('test', $data);
        $res = $this->db->getAssoc('SELECT code, name, price, `order` FROM test WHERE code = ?', $data['code']);

        $this->assertEquals($data, $res);
        $this->assertEquals(3, $id);
    }

    public function testInsertNoAutoincrement()
    {
        $this->db->multiQuery("
            DROP TABLE IF EXISTS `test_no_ai`;
            CREATE TABLE `test_no_ai` (
             `id` int(11) unsigned NOT NULL,
             `code` char(15) NOT NULL,
             `name` varchar(200) NOT NULL,
             `price` decimal(10,2) unsigned DEFAULT NULL,
             PRIMARY KEY (`id`),
             KEY `code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $data = [
            'code' => '003',
            'name' => 'Pan',
            'price' => '22.9'
        ];
        $id = $this->db->insert('test_no_ai', $data);
        $res = $this->db->getAssoc('SELECT code, name, price FROM test_no_ai WHERE code = ?', $data['code']);

        $this->assertEquals($data, $res);
        $this->assertTrue($id);
    }

    public function testInsertIgnore()
    {
        $data = [
            'id' => 1,
            'code' => '003',
            'name' => 'Pan',
            'price' => '22.9'
        ];
        $id = $this->db->insert('test', $data, true);
        $code = $this->db->getOne('SELECT code FROM test WHERE id = 1');

        $this->assertEquals('001', $code);
        $this->assertTrue($id);
    }

    public function testUpdate()
    {
        $data = [
            'code' => '002',
            'name' => 'Pan',
            'order' => 2,
        ];
        $num = $this->db->update('test', $data, ['id' => 2]);
        $res = $this->db->getAssoc('SELECT code, name, `order` FROM test WHERE id = 2');

        $this->assertEquals($data, $res);
        $this->assertEquals(1, $num);
    }

    public function testUpdateMultipleWhere()
    {
        $data = [
            'code' => '002',
            'name' => 'Pan',
        ];
        $num = $this->db->update('test', $data, ['id' => 2, 'code' => '002', 'order' => 0]);
        $res = $this->db->getAssoc('SELECT code, name FROM test WHERE id = 2');

        $this->assertEquals($data, $res);
        $this->assertEquals(1, $num);
    }

    public function testUpdateEmptyWhere()
    {
        $data = [
            'code' => '002',
            'name' => 'Pan',
        ];
        $num = $this->db->update('test', $data);

        $this->assertEquals(2, $num);
    }

    public function testInsertUpdate()
    {
        $insert = [
            'id' => 3,
            'code' => '003',
            'name' => 'Pan',
            'order' => 5,
        ];
        $res = $this->db->insertUpdate('test', $insert);
        $row = $this->db->getAssoc('SELECT id, code, name, `order` FROM test WHERE id = 3');

        $this->assertEquals($insert, $row);
        $this->assertEquals(1, $res);

        $update = ['price' => 5, 'order' => 1];
        $res = $this->db->insertUpdate('test', $insert, $update);
        $row = $this->db->getAssoc('SELECT price, `order` FROM test WHERE id = 3');

        $this->assertEquals($update, $row);
        $this->assertEquals(2, $res);
    }

    public function testMultiInsert()
    {
        $fields = ['code', 'name', 'order'];
        $values = [
            ['003', 'Pan', 7],
            ['004', 'Spoon', 8],
        ];

        $res = $this->db->multiInsert('test', $fields, $values);
        $rows = $this->db->getAll("SELECT code, name, `order` FROM test WHERE code IN ('003', '004')");
        $expected = [
            ['code' => '003', 'name' => 'Pan',   'order' => 7],
            ['code' => '004', 'name' => 'Spoon', 'order' => 8],
        ];

        $this->assertEquals($expected, $rows);
        $this->assertEquals(2, $res);
    }
}