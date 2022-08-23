<?php
/**
 * Created by PhpStorm.
 * User: duncan
 * Date: 26/10/15
 * Time: 15:58
 */

namespace DuncanOgle\BooleanSearchParser;

use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function testSimpleParsing() {
        $parser = new Parser();

        $this->assertEquals('+ict', $parser->parse('ict'));
        $this->assertEquals('ict', $parser->parse('ict', 'OR'));
        $this->assertEquals('+ict +it', $parser->parse('ict it'));
        $this->assertEquals('ict it', $parser->parse('ict it', 'OR'));
        $this->assertEquals('ict it', $parser->parse('ict OR it'));
        $this->assertEquals('+ict +it', $parser->parse('ict AND it'), 'OR');
        $this->assertEquals('-ict', $parser->parse('NOT ict'));
        $this->assertEquals('-ict', $parser->parse('NOT ict'), 'OR');
        $this->assertEquals('+it -ict', $parser->parse('it NOT ict'));
        $this->assertEquals('+it -ict', $parser->parse('it NOT ict'), 'OR');


    }


    public function testFailing() {
        $parser = new Parser();

        $this->assertEquals(null, $parser->parse('"Business Development" or "IT sales" and ("Danish" or "Dutch" or "Italian" or" Denmark" or "Holland or "Netherlands" or "Italy")'));
        $this->assertEquals(null, $parser->parse('"Business Development" or "IT sales" and ("Danish" or "Dutch" or "Italian" or" Denmark" or "Holland or "Netherlands" or "Italy")', 'OR'));
        $this->assertEquals(null, $parser->parse('("Digital Transformation")) OR ("Innovation Lead"))'));
        $this->assertEquals(null, $parser->parse('("Digital Transformation")) OR ("Innovation Lead"))'), "OR");
        $this->assertEquals(null, $parser->parse('title: Customer Experience AND ("Insight Experience" OR "Marketing Strategy)'));
        $this->assertEquals(null, $parser->parse('title: Customer Experience AND ("Insight Experience" OR "Marketing Strategy)', 'OR'));
        $this->assertEquals(null, $parser->parse('"ict'));
        $this->assertEquals(null, $parser->parse('"ict', 'OR'));
    }

    public function testComplexParsing() {
        $parser = new Parser();

        $this->assertEquals('+("project assistant" "project supervisor") +retail -construction', $parser->parse('(title:"project assistant" OR title:"project supervisor") AND retail  -construction'));
        $this->assertEquals('+("project assistant" "project supervisor") +retail -construction', $parser->parse('(title:"project assistant" OR title:"project supervisor") AND retail  -construction', 'OR'));
        $this->assertEquals('+"john-paul caffery" +"john-paul" +caffery', $parser->parse('"john-paul caffery" john-paul caffery'));
        $this->assertEquals('+"john-paul caffery" "john-paul" caffery', $parser->parse('"john-paul caffery" john-paul caffery', 'OR'));
        $this->assertEquals('+"procurement" +"source to pay" "supplier relationship management" "srm" +"vetting" +"compliance"', $parser->parse('"Procurement" and "source to pay" and "Supplier relationship management" or "SRM"  and "vetting" and "compliance"'));
        $this->assertEquals('+"procurement" +"source to pay" "supplier relationship management" "srm" +"vetting" +"compliance"', $parser->parse('"Procurement" and "source to pay" and "Supplier relationship management" or "SRM"  and "vetting" and "compliance"', 'OR'));
        $this->assertEquals('(+"nursing home" +(manager supervisor)) (+commercial +sales +(manager management "team leader"))', $parser->parse('("Nursing Home" and (Manager OR Supervisor)) OR (commercial AND sales AND (manager OR management OR "team leader"))'));
        $this->assertEquals('(+"nursing home" +(manager supervisor)) (+commercial +sales +(manager management "team leader"))', $parser->parse('("Nursing Home" and (Manager OR Supervisor)) OR (commercial AND sales AND (manager OR management OR "team leader"))', 'OR'));
        $this->assertEquals('(+"it" +security*) "security engineer*" (+financial +analyst* +german)', $parser->parse('(“IT” AND security*) OR “security engineer*” OR (financial AND analyst* AND german)'));
        $this->assertEquals('(+"it" +security*) "security engineer*" (+financial +analyst* +german)', $parser->parse('(“IT” AND security*) OR “security engineer*” OR (financial AND analyst* AND german)', 'OR'));
        $this->assertEquals('+"john-paul-ringo-starr caffery" +"john-paul" +caffery', $parser->parse('"john-paul-ringo-starr caffery" john-paul caffery'));
        $this->assertEquals('+"john-paul caffery-had-many-ancestral-hypenated-surnames" "john-paul" caffery', $parser->parse('"john-paul caffery-had-many-ancestral-hypenated-surnames" john-paul caffery', 'OR'));

    }

    public function testInvalidOperatorPosition() {
        $parser = new Parser();

        $this->assertEquals('+gevenducha', $parser->parse('and gevenducha'));
        $this->assertEquals('+gevenducha', $parser->parse('and gevenducha'), 'OR');
        $this->assertEquals('+permonik', $parser->parse('not or permonik and'));
        $this->assertEquals('permonik', $parser->parse('not or permonik and', 'OR'));
        $this->assertEquals('-ochechula', $parser->parse('not ochechula and or not'));
        $this->assertEquals('-ochechula', $parser->parse('not ochechula and or not', 'OR'));
        $this->assertEquals('-(-pacmagos)', $parser->parse('not (not pacmagos)'));
        $this->assertEquals('-(-pacmagos)', $parser->parse('not (not pacmagos)', 'OR'));
        $this->assertEquals('-(+fidlikant)', $parser->parse('not (or not and fidlikant) not'));
        $this->assertEquals('-(+fidlikant)', $parser->parse('not (or not and fidlikant) not', 'OR'));
        $this->assertEquals('+(+fuchtla)', $parser->parse('(fuchtla and not)'));
        $this->assertEquals('(fuchtla)', $parser->parse('(fuchtla and not)', 'OR'));
        $this->assertEquals('(+svabliky) cinter', $parser->parse('(svabliky and not) or cinter'));
        $this->assertEquals('(svabliky) cinter', $parser->parse('(svabliky and not) or cinter', 'OR'));

    }
}
