<?php

require_once('medoo.php');

class TestProtected extends medoo {
    public function select_ctx($table, $join, $columns = null, $where = null, $column_fn = null) {
        return parent::select_context($table, $join, $columns, $where, $column_fn);
    }

    public function column_quote($string) {
        return parent::column_quote($string);
    }

    public function where_clause($where) {
        return parent::where_clause($where);
    }

    public function setDatabaseType($type) {
        $this->database_type = $type;
    }
    public function getProp($name) {
        return $this->$name;
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
            ),
            array(
                ' WHERE "city" LIKE \'Londo%\'',
                array('city[~]' => 'Londo_'), // London, Londox, Londos...
            ),
            array(
                ' WHERE "name" LIKE \'[BCR]at\'',
                array('name[~]' => '[BCR]at'), // Bat, Cat, Rat
                true, // skipped
            ),
            array(
                ' WHERE "name" LIKE \'[^BCR]at\'',
                array('name[~]' => '[!BCR]at'),// Eat, Fat, Hat
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
            // Multi-Cols GROUP BY
            array(
                ' GROUP BY "type", "name"',
                array(
                    'GROUP' => array('type', 'name')
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

    /**
     * @param $expected
     * @param $args
     * @dataProvider selectDataProvider
     */
    public function testSelect($expected, $args) {
        $this->assertEquals(
            $expected, call_user_func_array(
            array($this->db, 'select_ctx'),
            $args
        ));
    }

    public function selectDataProvider() {
        return array(
            // Table column
            array(
                'SELECT * FROM "account"',
                array('account', '*'),
            ),
            array(
                'SELECT "user_name","email" FROM "account"',
                array('account', array(
                    'user_name',
                    'email',
                )),
            ),
            array(
                'SELECT "user_name","email" FROM "account" ORDER BY "user_name" ASC LIMIT 10',
                array(
                    'account',
                    array('user_name', 'email'),
                    array(
                        'ORDER' => 'user_name ASC',
                        'LIMIT' => 10
                    )
                )
            ),
            // Table join
            array(
                'SELECT "post".*,"account"."user_id" FROM "post" LEFT JOIN "account" ON "post"."author_id" = "account"."user_id"',
                array(
                    'post',
                    array('[>]account' => array('author_id' => 'user_id')),
                    array('post.*', 'account.user_id'),
                ),
            ),
            array(
                'SELECT * FROM "account" INNER JOIN "album" USING ("user_id")',
                array(
                    'account',
                    array('[><]album' => 'user_id'),
                    '*',
                )
            ),
            array(
                'SELECT * FROM "account" FULL JOIN "photo" USING ("user_id", "avatar_id")',
                array(
                    'account',
                    array('[<>]photo' => array('user_id', 'avatar_id')),
                    '*',
                )
            ),
            array(
                'SELECT * FROM "account" RIGHT JOIN "account" AS "b" ON "account"."user_id" = "b"."promotor_id"',
                array(
                    'account',
                    array(
                        '[<]account(b)' => array('user_id' => 'promotor_id'),
                    ),
                    '*'
                )
            ),
            array(
                'SELECT * FROM "post" INNER JOIN "account" ON "post"."author_id" = "account"."user_id" INNER JOIN "album" ON "account"."user_id" = "album"."user_id"',
                array(
                    'post',
                    array(
                        '[><]account' => array('author_id' => 'user_id'),
                        '[><]album' => array('account.user_id' => 'user_id'),
                    ),
                    '*'
                )
            ),
            array(
                'SELECT * FROM "album" LEFT JOIN "account" ON "album"."user_id" = "account"."user_id" AND "album"."user_type" = "account"."type"',
                array(
                    'album',
                    array(
                        '[>]account' => array(
                            'user_id' => 'user_id',
                            'user_type' => 'type',
                        )
                    ),
                    '*'
                )
            ),
            //Column Alias
            array(
                'SELECT "user_id","nickname" AS "my_nickname" FROM "account" LIMIT 20',
                array(
                    'account',
                    array(
                        'user_id',
                        'nickname(my_nickname)'
                    ),
                    array('LIMIT' => 20),
                )
            ),
            // one string column
            array(
                'SELECT "user_id" FROM "account"',
                array(
                    'account',
                    'user_id',
                )
            ),
            // Column Function
            array(
                'SELECT COUNT(*) FROM "account"',
                array(
                    'account',
                    null,
                    '*',
                    null,
                    'COUNT'
                )
            ),
            array(
                'SELECT MAX("age") FROM "account" LEFT JOIN "photo" USING ("user_id") WHERE "gender" = \'female\'',
                array(
                    'account',
                    array('[>]photo' => array('user_id')),
                    'age',
                    array('gender' => 'female'),
                    'MAX'
                )
            ),
            array(
                'SELECT COUNT(*) FROM "account" WHERE "gender" = \'female\'',
                array(
                    'account',
                    array(
                        'gender' => 'female'
                    ),
                    null,
                    null,
                    'COUNT'
                )
            ),
            array(
                'SELECT COUNT(*) FROM "account" WHERE "gender" = \'female\'',
                array(
                    'account',
                    array(
                        'gender' => 'female'
                    ),
                    null,
                    array(),
                    'COUNT'
                )
            ),
            // for has()
            array(
                'SELECT 1 FROM "account"',
                array(
                    'account',
                    null,
                    null,
                    null,
                    '1'
                )
            )
        );
    }

    public function testFetchClass() {
        $r = $this->db->fetch_class('abc', array('const'));
        $this->assertInstanceOf('medoo', $r);
        $fc = $r->getProp('fetch_class');
        $this->assertTrue(is_array($fc));
        $this->assertEquals('abc', $fc['name']);
        $this->assertEquals(1, count($fc['ctorargs']));
        $this->assertTrue($fc['once']);
    }
    public function testDisableFetchClass() {
        $r = $this->db->fetch_class(false);
        $this->assertInstanceOf('medoo', $r);
        $fc = $r->getProp('fetch_class');
        $this->assertNull($fc);
    }
    public function testDistinct() {
        $sql = $this->db->distinct()->select_ctx('account', 'age', array('gender' => 'female'));
        $this->assertEquals(
            'SELECT DISTINCT "age" FROM "account" WHERE "gender" = \'female\'',
            $sql
        );
        // make sure distinct auto reset
        $sql = $this->db->select_ctx('account', 'age', array('gender' => 'female'));
        $this->assertEquals(
            'SELECT "age" FROM "account" WHERE "gender" = \'female\'',
            $sql,
            'DISTINCT status havn\'t reset automatically'
        );
    }
    public function testDistinctCount() {
        $this->db->debug()->distinct()->count('account', 'age');
        $this->expectOutputString('SELECT COUNT(DISTINCT "age") FROM "account"');
    }
    public function testOracleLimit() {
        $this->db->setDatabaseType('oracle');

        $this->assertEquals(
            ' FETCH FIRST 10 ROWS ONLY',
            $this->db->where_clause(array(
                'LIMIT' => 10
            )
        ));
        $this->assertEquals(
            ' OFFSET 10 ROWS FETCH NEXT 10 ROWS ONLY',
            $this->db->where_clause(array(
                'LIMIT' => array(10, 10)
            )
        ));

        $this->db->setDatabaseType('sqlite');
    }
}
