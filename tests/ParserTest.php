<?php
/**
 * Created by PhpStorm.
 * User: duncan
 * Date: 26/10/15
 * Time: 15:58
 */

namespace DuncanOgle\BooleanSearchParser;


//require 'vendor/autoload.php';
//require '/Parser.php';

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleParsing() {
        $parser = new Parser();

        $this->assertEquals('+ict', $parser->parse('ict'));
        $this->assertEquals('+ict +it', $parser->parse('ict it'));
        $this->assertEquals('ict it', $parser->parse('ict OR it'));
        $this->assertEquals('-ict', $parser->parse('NOT ict'));
        $this->assertEquals('+it -ict', $parser->parse('it NOT ict'));
    }
}
