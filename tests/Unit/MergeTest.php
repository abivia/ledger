<?php

namespace Abivia\Ledger\Tests\Unit;

use Abivia\Ledger\Helpers\Merge;
use PHPUnit\Framework\TestCase;
use stdClass;

class MergeTest extends TestCase
{
    public function testArrays()
    {
        $test = [1];
        $source = [1];
        Merge::arrays($test, $source);
        $this->assertEquals([1, 1], $test);
    }

    public function testMergeFlatSimple()
    {
        $obj = new stdClass();
        $obj->a = 1;
        $source = new stdClass();
        $source->b = 2;
        Merge::objects($obj, $source);
        $expect = new stdClass();
        $expect->a = 1;
        $expect->b = 2;
        $this->assertEquals($expect, $obj);
    }

    public function testMergeFlatOverwrite()
    {
        $obj = new stdClass();
        $obj->a = 1;
        $source = new stdClass();
        $source->a = 2;
        Merge::objects($obj, $source);
        $expect = new stdClass();
        $expect->a = 2;
        $this->assertEquals($expect, $obj);
    }

    public function testMergeFlatArrayAppend()
    {
        $obj = new stdClass();
        $obj->a = [1, 2];
        $source = new stdClass();
        $source->a = 3;
        Merge::objects($obj, $source);
        $expect = new stdClass();
        $expect->a = [1, 2, 3];
        $this->assertEquals($expect, $obj);
    }

    public function testMergeFlatArrayMerge()
    {
        $obj = new stdClass();
        $obj->a = ['one' => 1, 'two' => 2];
        $source = new stdClass();
        $source->a = ['two' => 2.1, 'three' =>3];
        Merge::objects($obj, $source);
        $expect = new stdClass();
        $expect->a = ['one' => 1, 'two' => 2.1, 'three' =>3];
        $this->assertEquals($expect, $obj);
    }

    public function testRecursive()
    {
        $obj = json_decode('{
            "partA": {"a": "tpa-spa", "b": "tpa-spb"},
            "partB": {"a": "tpb-spa", "c": "tpb-spc"}
        }');

        $source = json_decode('{
            "partA": {"b": "spa-spb"},
            "partB": {"a": "spb-spa", "d": "spb-spd"},
            "partC": {"a": "spc-spa", "c": "spc-spc"}
        }');

        Merge::objects($obj, $source);
        $expect = json_decode('{
            "partA": {"a": "tpa-spa", "b": "spa-spb"},
            "partB": {"a": "spb-spa", "c": "tpb-spc", "d": "spb-spd"},
            "partC": {"a": "spc-spa", "c": "spc-spc"}
        }');
        $this->assertEquals($expect, $obj);
    }

}
