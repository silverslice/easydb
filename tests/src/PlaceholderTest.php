<?php

namespace Silverslice\EasyDb\Tests;

use Silverslice\EasyDb\Database;
use Silverslice\EasyDb\Exception;
use Silverslice\EasyDb\Expression;

class PlaceholderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Database
     */
    protected $db;

    public function setUp()
    {
        $options = include __DIR__ . '/../config.php';
        $this->db = new Database($options);
    }

    public function testDbPrepareInt()
    {
        $str = $this->db->prepare('test ?i', '1');
        $this->assertEquals("test 1", $str);
    }

    public function testDbPrepareString()
    {
        $str = $this->db->prepare('test ?s', '1');
        $this->assertEquals("test '1'", $str);
    }

    public function testDbPrepareFloat()
    {
        $str = $this->db->prepare('test ?f', '1.2');
        $this->assertEquals("test 1.2", $str);
    }

    public function testDbPrepareEscape()
    {
        $str = $this->db->prepare('test ?e', "1'2");
        $this->assertEquals("test 1\\'2", $str);
    }

    public function testDbPreparePart()
    {
        $str = $this->db->prepare('test ?p', "go");
        $this->assertEquals("test go", $str);
    }

    public function testDbPrepareArray()
    {
        $str = $this->db->prepare('test (?a)', [1, 2, 3]);
        $this->assertEquals("test (1,2,3)", $str);
    }

    public function testDbPrepareArrayEmpty()
    {
        $str = $this->db->prepare('test IN (?a)', []);
        $this->assertEquals("test IN (NULL)", $str);
    }

    public function testDbPrepareArrayString()
    {
        $str = $this->db->prepare('test (?a)', ['w', 'o', 'w']);
        $this->assertEquals("test ('w','o','w')", $str);
    }

    public function testDbPrepareSet()
    {
        $str = $this->db->prepare('test ?u', [
            'time' => new Expression('NOW()'),
            'field`2' => '5',
            'int' => 1
        ]);
        $this->assertEquals("test `time` = NOW(), `field``2` = '5', `int` = 1", $str);
    }

    public function testDbPrepareDefaultString()
    {
        $str = $this->db->prepare('test ?', "1'");
        $this->assertEquals("test '1\\''", $str);
    }

    public function testDbPrepareDefaultInt()
    {
        $str = $this->db->prepare('test ?', 1);
        $this->assertEquals("test 1", $str);
    }

    public function testDbPrepareDefaultNull()
    {
        $str = $this->db->prepare('test ?', null);
        $this->assertEquals("test null", $str);
    }

    public function testDbPrepareExpression()
    {
        $str = $this->db->prepare('test ?', $this->db->expression('NOW()'));
        $this->assertEquals("test NOW()", $str);
    }

    public function testDbPrepareUnknown()
    {
        $str = $this->db->prepare('test ?x', 1);
        $this->assertEquals("test 1x", $str);
    }

    public function testDbPrepareSeveral()
    {
        $str = $this->db->prepare('test ? OR ?i OR (?a)', '1', '2', ['3', '4']);
        $this->assertEquals("test '1' OR 2 OR ('3','4')", $str);
    }

    /**
     * @expectedException Exception
     */
    public function testNotEnoughParams()
    {
        $this->db->prepare('test ? OR ?', 1);
    }
}