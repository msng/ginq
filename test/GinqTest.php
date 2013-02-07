<?php
require_once dirname(dirname(__FILE__)) . "/src/Ginq.php";

class Person
{
    public $id;
    public $name;
    public $city;
    public function __construct($id, $name, $city)
    {
        $this->id = $id;
        $this->name = $name;
        $this->city = $city;
    }
}

/**
 * Test class for Ginq.
 */
class GinqTest extends PHPUnit_Framework_TestCase
{
    /**
     * Runs the test methods of this class.
     *
     * @access public
     * @static
     */
    public static function main()
    {
        $suite  = new PHPUnit_Framework_TestSuite("GinqTest");
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        //Ginq::useIterator();
        //Ginq::useGenerator();
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    }

    /**
     * testGetIterator().
     */
    public function testGetIterator()
    {
        $iter = Ginq::from(array(1,2,3,4,5))->getIterator();
        $this->assertTrue($iter instanceof Iterator);
        $arr = array();
        foreach ($iter as $x) {
            $arr[] = $x;
        }
        $this->assertEquals(array(1,2,3,4,5), $arr);
    }

    /**
     * testToArray().
     */
    public function testToArray()
    {
        $actual = Ginq::from(array(1,2,3,4,5))->toArray();
        $this->assertEquals(array(1,2,3,4,5), $actual);

        // key conflict
        $data = array('apple' => 1, 'orange' => 2, 'grape' => 3);
        $expected = array('apple' => 1, 'orange' => 2, 'grape' => 3);
        $actual = Ginq::cycle($data)->take(8)->toArray();
        $this->assertEquals($expected, $actual);
    }

    /**
     * testToArrayRec()
     */
    public function testToArrayRec()
    {
        $arr = Ginq::from(array(
            new ArrayIterator(array(1,2,3)),
            new ArrayObject(array(4,5,6))
        ))->toArrayRec();
        $this->assertEquals(array(array(1,2,3),array(4,5,6)), $arr);

        $data = Ginq::cycle(array(
            'foo' => new ArrayIterator(array('apple' => 1, 'orange' => 2)),
            'bar' => new ArrayObject(array('cat' => 3, 'dog' => 5)),
            'baz' => Ginq::cycle(array('car' => 6, 'plane' => array(1, 2)))->take(5)
        ))->take(6);
        $expected = array(
            'foo' => array('apple' => 1, 'orange' => 2),
            'bar' => array('cat' => 3, 'dog' => 5),
            'baz' => array('car' => 6, 'plane' => array(1, 2))
        );
        $actual = Ginq::from($data)->toArrayRec();
        $this->assertEquals($expected, $actual);
    }

    /**
     * testToArray().
     */
    public function testToAssoc()
    {
        $expected = array(
            array(0, 1),
            array(1, 2),
            array(2, 3),
            array(3, 4),
            array(4, 5)
        );
        $actual = Ginq::from(array(1,2,3,4,5))->toAssoc();
        $this->assertEquals($actual, $expected);
    }

    /**
     * testToArrayRec().
     */
    public function testToAssocRec()
    {
        $expected = array(
            array(0,
                array(
                    array(0, 1),
                    array(1, 2),
                    array(2, 3)
                )
            ),
            array(1,
                array(
                    array(0, 4),
                    array(1, 5),
                    array(2, 6)
                )
            )
        );
        $actual = Ginq::from(array(
            new ArrayIterator(array(1,2,3)),
            new ArrayObject(array(4,5,6))
        ))->toAssocRec();
        $this->assertEquals($actual, $expected);
    }

    /**
     * testToDictionary().
     */
    public function testToDictionary()
    {
        $data = array(
             array('id' => 1, 'name' => 'Taro',    'city' => 'Takatsuki')
            ,array('id' => 2, 'name' => 'Atsushi', 'city' => 'Ibaraki')
            ,array('id' => 3, 'name' => 'Junko',   'city' => 'Sakai')
        );

        // key
        $dict = Ginq::from($data)->toDictionary(
            function($x, $k) { return $x['name']; }
        );

        $this->assertEquals(
            array(
                'Taro' =>
                    array('id' => 1, 'name' => 'Taro', 'city' => 'Takatsuki'),
                'Atsushi' =>
                    array('id' => 2, 'name' => 'Atsushi', 'city' => 'Ibaraki'),
                'Junko' =>
                    array('id' => 3, 'name' => 'Junko', 'city' => 'Sakai')
            ), $dict
        );
        
        
        // key and value
        $dict = Ginq::from($data)->toDictionary(
            'name', // it means `function($x, $k) { return $x['name']; }`
            function($x, $k) { return "{$x['city']}"; }
        );
        $this->assertEquals(
            array(
                'Taro' => "Takatsuki",
                'Atsushi' => "Ibaraki",
                'Junko' => "Sakai"
            ), $dict
        );
    }

    /**
     * testToDictionary().
     */
    public function testToDictionaryWith()
    {
        // key conflict
        $data = array('apple' => array(1), 'orange' => array(2), 'grape' => array(3));
        $expected = array('apple' => array(1,1,1), 'orange' => array(2,2,2), 'grape' => array(3,3,3));
        $actual = Ginq::cycle($data)->take(9)
            ->toDictionaryWith(
                function($exist, $v, $k) {
                    return array_merge($exist, $v);
                }
            );
        $this->assertEquals($expected, $actual);
    }

    /**
     * testAny().
     */
    public function testAny()
    {
        $this->assertTrue(
            Ginq::from(array(1,2,3,4,5,6,7,8,9,10))
                ->any(function($x, $k) { return 5 <= $x; })
        );

        $this->assertFalse(
            Ginq::from(array(1,2,3,4,5,6,7,8,9,10))
                ->any(function($x, $k) { return 100 <= $x; })
        );

        $this->assertTrue(
            Ginq::from(array('foo'=>18, 'bar'=>42, 'baz'=> 7))
                ->any(function($x, $k) { return 'bar' == $k; })
        );

        // infinite sequence
        $this->assertTrue(
            Ginq::range(1)->any(function($x, $k) { return 5 <= $x; })
        );
    }

    /**
     * testAll().
     */
    public function testAll()
    {
        $this->assertTrue(
            Ginq::from(array(2,4,6,8,10))
                ->all(function($x, $k) { return $x % 2 == 0; })
        );

        $this->assertFalse(
            Ginq::from(array(1,2,3,4,5,6,7,8,9,10))
                ->all(function($x, $k) { return $x < 10; })
        );

        // infinite sequence
        $this->assertFalse(
            Ginq::range(1)->all(function($x, $k) { return $x <= 10; })
        );
    }

    /**
     * testFirst
     */
    public function testFirst()
    {
        // without default value (just)
        $x = Ginq::from(array('apple', 'orange', 'grape'))
                ->first();
        $this->assertEquals($x, 'apple');

        // without default value (nothing)
        $x = Ginq::zero()->first();
        $this->assertEquals($x, null);

        // with default value (just)
        $x = Ginq::from(array('apple', 'orange', 'grape'))
                ->first('none');
        $this->assertEquals($x, 'apple');

        // with default value (nothing)
        $x = Ginq::zero()->first('none');
        $this->assertEquals($x, 'none');
    }

    /**
     * testRest
     *
     * @expectedException InvalidArgumentException
     */
    public function testRest()
    {
        // without default value (just)
        $xs = Ginq::from(array(1,2,3,4,5))->rest()->toArray();
        $this->assertEquals(array(1=>2,2=>3,3=>4,4=>5), $xs);

        // without default value (nothing)
        $xs = Ginq::zero()->rest()->toArray();
        $this->assertEquals(array(), $xs);

        // with default value (just)
        $xs = Ginq::from(array(1,2,3,4,5))->rest(array(42))->toArray();
        $this->assertEquals(array(1=>2,2=>3,3=>4,4=>5), $xs);

        // with default value (nothing)
        $xs = Ginq::zero()->rest(array(42))->toArray();
        $this->assertEquals(array(42), $xs);

        // invalid default value.
        $xs = Ginq::zero()->rest(42);
    }

    /**
     * testContains
     */
    public function testContains()
    {
        $this->assertTrue(
            Ginq::from(array('apple', 'orange', 'grape'))
                ->contains('orange')
        );

        $this->assertFalse(
            Ginq::from(array('apple', 'orange', 'grape'))
                ->contains('meow!')
        );
    }

    /**
     * testContainsKey
     */
    public function testContainsKey()
    {
        $this->assertTrue(
            Ginq::from(array('apple' => 1, 'orange' => 2, 'grape' => 3))
                ->containsKey('orange')
        );

        $this->assertFalse(
            Ginq::from(array('apple' => 1, 'orange' => 2, 'grape' => 3))
                ->containsKey('meow!')
        );
    }

    /**
     * testFind
     */
    public function testFind() {
        $isOrange = function($x, $k) { return $x == "orange"; };

        // without default value (just)
        $x = Ginq::from(array('apple', 'orange', 'grape'))
                ->find($isOrange);
        $this->assertEquals($x, 'orange');

        // without default value (nothing)
        $x = Ginq::zero()->find($isOrange);
        $this->assertEquals($x, null);

        // with default value (just)
        $x = Ginq::from(array('apple', 'orange', 'grape'))
                ->find($isOrange, 'none');
        $this->assertEquals($x, 'orange');

        // with default value (nothing)
        $x = Ginq::zero()->find($isOrange, 'none');
        $this->assertEquals($x, 'none');
    }

    /**
     * testFold
     */
    public function testFoldLeft()
    {
        $actual = Ginq::range(1, 10)->foldLeft(0, function($acc, $v, $k) {
            return $acc - $v;
        });
        $this->assertEquals(-55, $actual);
    }

    /**
     * testFold
     */
    public function testFoldRight()
    {
        $actual = Ginq::range(1, 10)->foldRight(0, function($acc, $v, $k) {
            return $v - $acc;
        });
        $this->assertEquals(-5, $actual);
    }

    /**
     * testFold
     */
    public function testReduceLeft()
    {
        $actual = Ginq::range(0, 10)->reduceLeft(function($acc, $v, $k) {
            return $acc - $v;
        });
        $this->assertEquals(-55, $actual);
    }

    /**
     * testFold
     */
    public function testReduceRight()
    {
        $actual = Ginq::range(1, 10)->reduceRight(function($acc, $v, $k) {
            return $v - $acc;
        });
        $this->assertEquals(-5, $actual);
    }

    /**
     * testZero().
     */
    public function testZero()
    {
        $arr = Ginq::zero()->toArray();
        $this->assertEquals(array(), $arr);
    }

    /**
     * testRange().
     */
    public function testRange()
    {
        // finite sequence
        $xs = Ginq::range(1,10)->toArray();
        $this->assertEquals(array(1,2,3,4,5,6,7,8,9,10), $xs);

        // finite sequence with step
        $xs = Ginq::range(1,10, 2)->toArray();
        $this->assertEquals(array(1,3,5,7,9), $xs);

        // finite sequence with negative step
        $xs = Ginq::range(0,-9, -1)->toArray();
        $this->assertEquals(array(0,-1,-2,-3,-4,-5,-6,-7,-8,-9), $xs);

        // infinite sequence
        $xs = Ginq::range(1)->take(10)->toArray();
        $this->assertEquals(array(1,2,3,4,5,6,7,8,9,10), $xs);

        // infinite sequence with step
        $xs = Ginq::range(10, null, 5)->take(5)->toArray();
        $this->assertEquals(array(10,15,20,25,30), $xs);

        // infinite sequence with negative step
        $xs = Ginq::range(-10, null, -5)->take(5)->toArray();
        $this->assertEquals(array(-10,-15,-20,-25,-30), $xs);

        // contradict range
        $xs = Ginq::range(1, -10, 1)->toArray();
        $this->assertEquals(array(), $xs);

        $xs = Ginq::range(1, 10, -1)->toArray();
        $this->assertEquals(array(), $xs);
    }

    /**
     * testRepeat().
     */
    public function testRepeat()
    {
        // infinite repeat
        $xs = Ginq::repeat("foo")->take(3)->toArray();
        $this->assertEquals(array("foo","foo","foo"), $xs);
    }

    /**
     * testCycle().
     */
    public function testCycle()
    {
        $expected = array(
            array(0, 'Mon'),
            array(1, 'Tue'),
            array(2, 'Wed'),
            array(3, 'Thu'),
            array(4, 'Fri'),
            array(5, 'Sat'),
            array(6, 'Sun'),
            array(0, 'Mon'),
            array(1, 'Tue'),
            array(2, 'Wed')
        );
        $data = array('Mon','Tue','Wed','Thu','Fri','Sat','Sun');
        $actual = Ginq::cycle($data)->take(10)->toAssoc();
        $this->assertEquals($expected, $actual);
    }

    /**
     * testFrom().
     */
    public function testFrom()
    {
        // array
        $arr = Ginq::from(array(1,2,3,4,5))->toArray();
        $this->assertEquals(array(1,2,3,4,5), $arr);

        // Iterator
        $arr = Ginq::from(new ArrayIterator(array(1,2,3,4,5)))->toArray();
        $this->assertEquals(array(1,2,3,4,5), $arr);

        // IteratorAggregate
        $arr = Ginq::from(new ArrayObject(array(1,2,3,4,5)))->toArray();
        $this->assertEquals(array(1,2,3,4,5), $arr);

        // Ginq
        $arr = Ginq::from(Ginq::from(array(1,2,3,4,5)))->toArray();
        $this->assertEquals(array(1,2,3,4,5), $arr);
    }

    /**
     * testSequence().
     */
    public function testSequence()
    {
        $expected = array(2,4,6,8,10,12,14,16,18,20);
        $actual = Ginq::range(1,20)
                    ->where(function($x) { return $x % 2 == 0; })
                    ->sequence()
                    ->toArray();
        $this->assertEquals($expected, $actual);
    }



    /**
     * testSelect().
     *
     * @expectedException InvalidArgumentException
     */
    public function testSelect()
    {
        // selector function
        $xs = Ginq::from(array(1,2,3,4,5))
                   ->select(function($x, $k) { return $x * $x; })
                   ->toArray();
        $this->assertEquals(array(1,4,9,16,25), $xs);

        // key selector string
        $data = array(
             array('id' => 1, 'name' => 'Taro',    'city' => 'Takatsuki')
            ,array('id' => 2, 'name' => 'Atsushi', 'city' => 'Ibaraki')
            ,array('id' => 3, 'name' => 'Junko',   'city' => 'Sakai')
        );
        $xs = Ginq::from($data)->select("name")->toArray();
        $this->assertEquals(array('Taro','Atsushi','Junko'), $xs);

        // field selector string
        $data = array(
             new Person(1, 'Taro',    'Takatsuki')
            ,new Person(2, 'Atsushi', 'Ibaraki')
            ,new Person(3, 'Junko',   'Sakai')
        );
        $xs = Ginq::from($data)->select("name")->toArray();
        $this->assertEquals(array('Taro','Atsushi','Junko'), $xs);

        // key mapping
        $xs = Ginq::from(1,2,3,4,5)
            ->select(null, function($v, $k) { return $k * $k; })
            ->toArray();
        $this->assertEquals(array(0=>1,1=>2,4=>3,9=>4,16=>5), $xs);

        // invalid selector
        Ginq::from(array(1,2,3,4,5))->select(8);
    }

    /**
     * testWhere().
     */
    public function testWhere()
    {
        $xs = Ginq::from(array(1,2,3,4,5,6,7,8,9,10))
            ->where(function($x, $k) { return ($x % 2) == 0; })
            ->toArray();
        $this->assertEquals(array(1=>2,3=>4,5=>6,7=>8,9=>10), $xs);
    }

    /**
     * testReverse().
     */
    public function testReverse()
    {
        // reverse iterator
        $xs = Ginq::from(array(1,2,3,4,5))->reverse();

        // to array
        $expected = array(1,2,3,4,5);
        $actual = $xs->toArray();
        $this->assertEquals($expected, $actual);

        // with sequence
        $expected = array(5,4,3,2,1);
        $actual = $xs->sequence()->toArray();
        $this->assertEquals($expected, $actual);

        // to assoc
        $expected = array(array(4, 5),array(3, 4),array(2, 3),array(1, 2),array(0, 1));
        $actual = $xs->toAssoc();
        $this->assertEquals($expected, $actual);
    }

    /**
     * testTake().
     */
    public function testTake()
    {
        $xs = Ginq::from(array(1,2,3,4,5,6,7,8,9))->take(5)->toArray();
        $this->assertEquals(array(1,2,3,4,5), $xs);
    }

    /**
     * testDrop().
     */
    public function testDrop()
    {
        $xs = Ginq::from(array(1,2,3,4,5,6,7,8,9))->drop(5)->toArray();
        $this->assertEquals(array(5=>6,6=>7,7=>8,8=>9), $xs);
    }

    /**
     * testTakeWhile().
     */
    public function testTakeWhile()
    {
        $xs = Ginq::from(array(1,2,3,4,5,6,7,8,9,8,7,6,5,4,3,2,1))
            ->takeWhile(function($x, $k) { return $x <= 5; })
            ->toArray();
        $this->assertEquals(array(1,2,3,4,5), $xs);
    }

    /**
     * testDropWhile().
     */
    public function testDropWhile()
    {
        $xs = Ginq::from(array(1,2,3,4,5,6,7,8,9,8,7,6,5,4,3,2,1))
            ->dropWhile(function($x, $k) { return $x <= 5; })
            ->toArray();
        $this->assertEquals(array(
            5=>6, 6=>7, 7=>8, 8=>9, 9=>8, 10=>7,
            11=>6, 12=>5, 13=>4, 14=>3, 15=>2,16=>1
        ), $xs);
    }

     /**
     * testConcat().
     */
    public function testConcat()
    {
        $xs = Ginq::from(array(1,2,3,4,5))->concat(array(6,7,8,9))->toArray();
        $this->assertEquals(array(0=>1,1=>2,2=>3,3=>4,4=>5,0=>6,1=>7,2=>8,3=>9), $xs);
    }

    /**
     * testSelectMany().
     */
    public function testSelectMany()
    {
        $phoneBook = array(
            array(
                'name'   => 'Taro',
                'phones' => array(
                    '03-1234-5678',
                    '090-8421-9061'
                )
            ),
            array(
                'name'   => 'Atsushi',
                'phones' => array(
                    '050-1198-4458'
                )
            ),
            array(
                'name'   => 'Junko',
                'phones' => array(
                    '06-1111-3333',
                    '090-9898-1314',
                    '050-6667-2231'
                )
            )
         );

        $phones = Ginq::from($phoneBook)->selectMany('phones')->toAssoc();
        $this->assertEquals(array(
            array(0, '03-1234-5678'),
            array(1, '090-8421-9061'),
            array(0, '050-1198-4458'),
            array(0, '06-1111-3333'),
            array(1, '090-9898-1314'),
            array(2, '050-6667-2231')
        ), $phones);
    }

    /**
     * testSelectManyWith().
     */
    public function testSelectManyWith() {

        $phoneBook = array(
            array(
                'name'   => 'Taro',
                'phones' => array(
                    '03-1234-5678',
                    '090-8421-9061'
                )
            ),
            array(
                'name'   => 'Atsushi',
                'phones' => array(
                    '050-1198-4458'
                )
            ),
            array(
                'name'   => 'Junko',
                'phones' => array(
                    '06-1111-3333',
                    '090-9898-1314',
                    '050-6667-2231'
                )
            )
        );
        
        // without key join selector
        $phones = Ginq::from($phoneBook)
            ->selectManyWith(
                'phones',
                function($v0, $v1, $k0, $k1) {
                    return "{$v0['name']} : $v1";
                }
            )->toAssoc();
        $this->assertEquals(array(
            array(0,'Taro : 03-1234-5678'),
            array(1,'Taro : 090-8421-9061'),
            array(0,'Atsushi : 050-1198-4458'),
            array(0,'Junko : 06-1111-3333'),
            array(1,'Junko : 090-9898-1314'),
            array(2,'Junko : 050-6667-2231')
        ), $phones);


        $phones = Ginq::from($phoneBook)
            ->selectManyWith(
                'phones',
                function($v0, $v1, $k0, $k1) {
                    return "$v1";
                },
                function($v0, $v1, $k0, $k1) {
                    return "{$v0['name']}-$k1";
                }
            )->toAssoc();
        $this->assertEquals(array(
            array('Taro-0',    '03-1234-5678'),
            array('Taro-1',    '090-8421-9061'),
            array('Atsushi-0', '050-1198-4458'),
            array('Junko-0',   '06-1111-3333'),
            array('Junko-1',   '090-9898-1314'),
            array('Junko-2',   '050-6667-2231')
        ), $phones);
    }

    /**
     * testJoin().
     */
    public function testJoin()
    {
        $persons = array(
             array('id' => 1, 'name' => 'Taro')
            ,array('id' => 2, 'name' => 'Atsushi')
            ,array('id' => 3, 'name' => 'Junko')
        );

        $phones = array(
             array('id' => 1, 'owner' => 1, 'phone' => '03-1234-5678')
            ,array('id' => 2, 'owner' => 1, 'phone' => '090-8421-9061')
            ,array('id' => 3, 'owner' => 2, 'phone' => '050-1198-4458')
            ,array('id' => 4, 'owner' => 3, 'phone' => '06-1111-3333')
            ,array('id' => 5, 'owner' => 3, 'phone' => '090-9898-1314')
            ,array('id' => 6, 'owner' => 3, 'phone' => '050-6667-2231')
        );

        // key selector string
        $xs = Ginq::from($persons)->join($phones,
            'id', 'owner',
            function($outer, $inner, $outerKey, $innerKey) {
                return array($outer['name'], $inner['phone']);
            },
            Ginq::seq()
        )->toArray();
        $this->assertEquals(
            array(
                 array('Taro', '03-1234-5678')
                ,array('Taro', '090-8421-9061')
                ,array('Atsushi', '050-1198-4458')
                ,array('Junko', '06-1111-3333')
                ,array('Junko', '090-9898-1314')
                ,array('Junko', '050-6667-2231')
            ), $xs
        );

        // key selector function
        $xs = Ginq::from($persons)->join($phones,
            function($outer, $k) { return $outer['id']; },
            function($inner, $k) { return $inner['owner']; },
            function($outer, $inner, $outerKey, $innerKey) {
                return array($outer['name'], $inner['phone']);
            }
        )->sequence()->toArray();

        $this->assertEquals(
            array(
                 array('Taro', '03-1234-5678')
                ,array('Taro', '090-8421-9061')
                ,array('Atsushi', '050-1198-4458')
                ,array('Junko', '06-1111-3333')
                ,array('Junko', '090-9898-1314')
                ,array('Junko', '050-6667-2231')
            ), $xs
        );
    }

    /**
     * testZip().
     */
    public function testZip()
    {
        // without key selector
        $xs = Ginq::cycle(array("red", "green"))->zip(Ginq::range(1, 8),
            function($v0, $v1, $k0, $k1) { return "$v1 - $v0"; }
        )->toAssoc();
        $this->assertEquals(array(
            array(0, "1 - red"),
            array(1, "2 - green"),
            array(0, "3 - red"),
            array(1, "4 - green"),
            array(0, "5 - red"),
            array(1, "6 - green"),
            array(0, "7 - red"),
            array(1, "8 - green")
        ), $xs);
    }

    /**
     * testGroupBy().
     */
    public function testGroupBy()
    {
        $phones = array(
             array('id' => 1, 'owner' => 1, 'phone' => '03-1234-5678')
            ,array('id' => 2, 'owner' => 1, 'phone' => '090-8421-9061')
            ,array('id' => 3, 'owner' => 2, 'phone' => '050-1198-4458')
            ,array('id' => 4, 'owner' => 3, 'phone' => '06-1111-3333')
            ,array('id' => 5, 'owner' => 3, 'phone' => '090-9898-1314')
            ,array('id' => 6, 'owner' => 3, 'phone' => '050-6667-2231')
        );

        $xss = Ginq::from($phones)->groupBy(
            function($x, $k) { return $x['owner']; },
            function($x, $k) { return $x['phone']; }
        )->toArrayRec();

        $this->assertEquals(array(
            1 => array('03-1234-5678', '090-8421-9061'),
            2 => array('050-1198-4458'),
            3 => array('06-1111-3333', '090-9898-1314', '050-6667-2231')
        ), $xss);

        $xss = Ginq::from($phones)
            ->groupBy('owner', 'phone')
            ->toDictionary(function($x, $k) { return $k; });

        $this->assertEquals(array(
            1 => array('03-1234-5678', '090-8421-9061'),
            2 => array('050-1198-4458'),
            3 => array('06-1111-3333', '090-9898-1314', '050-6667-2231')
        ), $xss);

        $count = function ($acc, $x) { return $acc + 1; };

        $xss = Ginq::from($phones)
                ->groupBy('owner')
                ->select(function($gr) use ($count) {
                    return $gr->fold(0, $count);
                })->toArray();

        $this->assertEquals(array(
            1 => 2,
            2 => 1,
            3 => 3
        ), $xss);
    }
 }

// Call GinqTest::main() if this source file is executed directly.
if (defined('PHPUnit_MAIN_METHOD') && PHPUnit_MAIN_METHOD == "GinqTest::main") {
    GinqTest::main();
}

