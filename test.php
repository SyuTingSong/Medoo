<?php

require_once('medoo.php');

class TestProtected extends medoo {
    public function column_quote($string) {
        return parent::column_quote($string);
    }

    public function where_clause($where) {
        return parent::where_clause($where);
    }

    public function setDatabaseType($type) {
        $this->database_type = $type;
    }
}

/**
 * Created by IntelliJ IDEA.
 * User: rek
 * Date: 2015/11/17
 * Time: 15:42
 */
class MedooTest extends PHPUnit_Framework_TestCase {
    /**
     * @var TestProtected
     */
    var $db;

    public function setUp() {
        $this->db = new TestProtected(array(
            'database_type' => 'SQLite',
            'database_file' => '/tmp/_medoo_test.db',
            'charset' => 'utf-8',
        ));
    }

    /**
     * @param $column
     * @param $expect
     * @dataProvider columnQuoteProvider
     */
    public function testColumnQuote($column, $expect) {

        $this->assertEquals($expect, $this->db->column_quote($column));
    }

    public function columnQuoteProvider() {
        return array(
            array('abc', '"abc"'),
            array('table.col', '"table"."col"'),
            array('t1.*', '"t1".*'),
            array('*', '*'),
        );
    }

    /**
     * @param $expect
     * @param $where
     * @dataProvider whereDataProvider
     */
    public function testWhere($expect, $where, $skip = false) {
        if ($skip) {
            $this->markTestSkipped('Skipped test ' . $expect);
            return;
        }
        $this->assertEquals($expect, $this->db->where_clause($where));
    }

    public function whereDataProvider() {
        return array(
            // basic condition
            array(
                ' WHERE "email" = \'foo@bar.com\'',
                array('email' => 'foo@bar.com'),
            ),
            array(
                ' WHERE "user_id" = 200',
                array('user_id' => 200),
            ),
            array(
                ' WHERE "user_id" > 200',
                array('user_id[>]' => 200),
            ),
            array(
                ' WHERE "user_id" >= 200',
                array('user_id[>=]' => 200),
            ),
            array(
                ' WHERE "user_id" < 200',
                array('user_id[<]' => 200),
            ),
            array(
                ' WHERE "user_id" <= 200',
                array('user_id[<=]' => 200),
            ),
            array(
                ' WHERE "user_id" != 200',
                array('user_id[!]' => 200),
            ),

            // BETWEEN condition
            array(
                ' WHERE ("age" BETWEEN 200 AND 500)',
                array('age[<>]' => array(200, 500)),
            ),
            array(
                ' WHERE ("age" NOT BETWEEN 200 AND 500)',
                array('age[><]' => array(200, 500)),
            ),
            array(
                ' WHERE ("birthday" NOT BETWEEN \'2015-01-01\' AND \'2015-05-01\')',
                array('birthday[><]' => array(
                    date('Y-m-d', mktime(0, 0, 0, 1, 1, 2015)),
                    date('Y-m-d', mktime(0, 0, 0, 5, 1, 2015)),
                ))
            ),
            // IN condition
            array(
                ' WHERE "user_id" IN (2,123,234,54)',
                array('user_id' => array(2, 123, 234, 54)),
            ),
            array(
                ' WHERE "email" IN (\'foo@bar.com\',\'cat@dog.com\',\'admin@medoo.in\')',
                array('email' => array('foo@bar.com', 'cat@dog.com', 'admin@medoo.in')),
            ),
            // IS NULL
            array(
                ' WHERE "city" IS NULL',
                array('city' => null),
            ),
            // Negative condition
            array(
                ' WHERE "user_name" != \'foo\'',
                array('user_name[!]' => 'foo'),
            ),
            array(
                ' WHERE "user_id" != 1024',
                array('user_id[!]' => 1024),
            ),
            array(
                ' WHERE "city" IS NOT NULL',
                array('city[!]' => null),
            ),
            array(
                ' WHERE "promoted" != 1',
                array('promoted[!]' => true),
            ),
            //Relativity Condition
            array(
                ' WHERE "user_id" > 200 AND ("age" BETWEEN 18 AND 25) AND "gender" = \'female\'',
                array('AND' => array(
                    'user_id[>]' => 200,
                    'age[<>]' => array(18, 25),
                    'gender' => 'female',
                )),
            ),
            array(
                ' WHERE "user_id" > 200 OR ("age" BETWEEN 18 AND 25) OR "gender" = \'female\'',
                array('OR' => array(
                    'user_id[>]' => 200,
                    'age[<>]' => array(18, 25),
                    'gender' => 'female',
                )),
            ),
            array(
                ' WHERE ("user_name" = \'foo\' OR "email" = \'foo@bar.com\') AND "password" = \'12345\'',
                array('AND' => array(
                    'OR' => array(
                        'user_name' => 'foo',
                        'email' => 'foo@bar.com',
                    ),
                    'password' => '12345',
                )),
            ),
            // Combine with comments
            array(
                ' WHERE ("user_name" = \'foo\' OR "email" = \'foo@bar.com\') AND ("user_name" = \'bar\' OR "email" = \'bar@foo.com\')',
                array('AND' => array(
                    'OR #the first condition' => array(
                        'user_name' => 'foo',
                        'email' => 'foo@bar.com',
                    ),
                    'OR #the second condition' => array(
                        'user_name' => 'bar',
                        'email' => 'bar@foo.com',
                    ),
                )),
            ),
            // LIKE condition
            array(
                ' WHERE "city" LIKE \'%lon%\'',
                array('city[~]' => 'lon'),
            ),
            array(
                ' WHERE "city" LIKE \'%lon%\' OR "city" LIKE \'%foo%\' OR "city" LIKE \'%bar%\'',
                array('city[~]' => array(
                    'lon',
                    'foo',
                    'bar',
                )),
            ),
            array(
                ' WHERE "city" NOT LIKE \'%lon%\'',
                array('city[!~]' => 'lon'),
            ),
            array(
                ' WHERE "city" LIKE \'%stan\'',
                array('city[~]' => 'stan%'), // Kazakhstan,  Uzbekistan, Türkmenistan...
                true, // skipped
            ),
            array(
                ' WHERE "city" LIKE \'Londo_\'',
                array('city[~]' => 'Londo_'), // London, Londox, Londos...
                true, // skipped
            ),
            array(
                ' WHERE "name" LIKE \'[BCR]at\'',
                array('name[~]' => '[BCR]at'), // Bat, Cat, Rat
                true, // skipped
            ),
            array(
                ' WHERE "name" LIKE \'[^BCR]at\'',
                array('city[~]' => '[!BCR]at'),// Eat, Fat, Hat
                true, // skipped
            ),
            //Order Condition
            array(
                ' ORDER BY "age"',
                array('ORDER' => 'age'),
            ),
            array(
                ' ORDER BY "user_name" DESC,"user_id" ASC',
                array('ORDER' => array('user_name DESC', 'user_id ASC')),
            ),
            // Order by field
            array(
                ' WHERE "user_id" IN (1,12,43,57,98,144) ORDER BY FIELD("user_id", 43,12,57,98,144,1)',
                array(
                    'user_id' => array(1, 12, 43, 57, 98, 144),
                    'ORDER' => array("user_id", array(43, 12, 57, 98, 144, 1)),
                )
            ),
            // Full Text Searching
            array(
                ' WHERE MATCH ("content", "title") AGAINST (\'foo\')',
                array('MATCH' => array(
                    'columns' => array('content', 'title'),
                    'keyword' => 'foo',
                ))
            ),
            //Using SQL Functions
            array(
                ' WHERE "datetime" = NOW()',
                array('#datetime' => 'NOW()'),
            ),
            array(
                ' WHERE "datetime1" = \'now()\' AND "datetime2" = \'NOW()\' AND "datetime3" = \'NOW\'',
                array('AND' => array(
                    '#datetime1' => 'now()',
                    'datetime2' => 'NOW()',
                    '#datetime3' => 'NOW',
                )), // checking bad case for compatibility
            ),
            // LIMIT
            array(
                ' LIMIT 10',
                array('LIMIT' => 10),
            ),
            array(
                ' LIMIT 10,10',
                array('LIMIT' => array(10, 10)),
            ),
            array(
                ' LIMIT 10',
                array('LIMIT' => '10'),
            ),
            // Additional condition
            array(
                ' GROUP BY "type" HAVING "user_id" > 500 LIMIT 20,100',
                array(
                    'GROUP' => 'type',
                    'HAVING' => array('user_id[>]' => 500),
                    'LIMIT' => array(20, 100),
                )
            ),
            // Pass SQL partial directly
            array(
                ' WHERE name = \'foo\' AND email = \'foo@bar.com\' ORDER BY id ASC',
                'WHERE name = \'foo\' AND email = \'foo@bar.com\' ORDER BY id ASC',
            )
        );
    }
    public function testPgLimit() {
        $this->db->setDatabaseType('pgsql');

        $this->assertEquals(
            ' LIMIT 10',
            $this->db->where_clause(array(
                'LIMIT' => 10
            )
        ));
        $this->assertEquals(
            ' OFFSET 10 LIMIT 10',
            $this->db->where_clause(array(
                'LIMIT' => array(10, 10)
            )
        ));

        $this->db->setDatabaseType('sqlite');
    }
}
