<?php

namespace Silverslice\EasyDb\Tests;
use Silverslice\EasyDb\Database;

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

    public function testDbParseInt()
    {
        $str = $this->db->parse('test ?i', '1');
        $this->assertEquals("test 1", $str);
    }

    public function testDbParseString()
    {
        $str = $this->db->parse('test ?s', '1');
        $this->assertEquals("test '1'", $str);
    }

    public function testDbParseFloat()
    {
        $str = $this->db->parse('test ?f', '1.2');
        $this->assertEquals("test 1.2", $str);
    }

    public function testDbParseEscape()
    {
        $str = $this->db->parse('test ?e', "1'2");
        $this->assertEquals("test 1\\'2", $str);
    }

    public function testDbParsePart()
    {
        $str = $this->db->parse('test ?e', "go");
        $this->assertEquals("test go", $str);
    }

    public function testDbParseArray()
    {
        $str = $this->db->parse('test (?a)', [1, 2, 3]);
        $this->assertEquals("test (1,2,3)", $str);
    }

    public function testDbParseArrayString()
    {
        $str = $this->db->parse('test (?a)', ['w', 'o', 'w']);
        $this->assertEquals("test ('w','o','w')", $str);
    }

    public function testDbParseDefaultString()
    {
        $str = $this->db->parse('test ?', "1'");
        $this->assertEquals("test '1\\''", $str);
    }

    public function testDbParseDefaultInt()
    {
        $str = $this->db->parse('test ?', 1);
        $this->assertEquals("test 1", $str);
    }

    public function testDbParseDefaultNull()
    {
        $str = $this->db->parse('test ?', null);
        $this->assertEquals("test null", $str);
    }
}