<?php

namespace Silverslice\EasyDb\Tests;

use Silverslice\EasyDb\Database;
use Silverslice\EasyDb\Exception;

/**
 * Tests for transactions
 *
 * @package Silverslice\EasyDb\Tests
 */
class TransactionTest extends \PHPUnit_Framework_TestCase
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
        ");
    }

    public function tearDown()
    {
        $this->db->query('DROP TABLE IF EXISTS `test`;');
    }

    public function testTransactionCommit()
    {
        $this->db->beginTransaction();
        $this->db->query("INSERT INTO test (id, code, price) VALUES (3000, '1', 1)");
        $this->db->commit();
        $id = $this->db->getOne("SELECT id FROM test WHERE id = 3000");
        
        $this->assertEquals(3000, $id);
    }

    public function testTransactionRollback()
    {
        $this->db->beginTransaction();
        $this->db->query("INSERT INTO test (id, code, price) VALUES (3001, '1', 1)");
        $this->db->rollback();
        $id = $this->db->getOne("SELECT id FROM test WHERE id = 3001");
        
        $this->assertNull($id);
    }

    /**
     * @expectedException Exception
     */
    public function testTransactionFail()
    {
        $this->db->query("INSERT INTO test (id, code, price) VALUES (3002, '1', 1)");
        $this->db->beginTransaction();
        $this->db->query("INSERT INTO test (id, code, price) VALUES (3002, '1', 1)");
    }

    public function testTransactionWrapper()
    {
        $this->db->transaction(function () {
            $this->db->query("INSERT INTO test (id, code, price) VALUES (3003, '1', 1)");
            $this->db->query("INSERT INTO test (id, code, price) VALUES (3004, '1', 1)");
        });
        $col = $this->db->getColumn('SELECT id FROM test WHERE id IN (3003, 3004)');

        $this->assertEquals([3003, 3004], $col);
    }

    public function testTransactionWrapperFail()
    {
        $this->db->query("INSERT INTO test (id, code, price) VALUES (3004, '1', 1)");
        $res = $this->db->transaction(function () {
            $this->db->query("INSERT INTO test (id, code, price) VALUES (3004, '1', 1)");
        });

        $this->assertEquals(false, $res);
    }

    /**
     * @expectedException Exception
     */
    public function testTransactionWrapperInvalidArgument()
    {
        $this->db->transaction('1+1');
    }
}