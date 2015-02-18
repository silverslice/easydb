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
        $this->assertEquals(5, $this->getRowCount());
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


    protected function getRowCount()
    {
        return $this->db->getOne('SELECT count(*) FROM test');
    }
}