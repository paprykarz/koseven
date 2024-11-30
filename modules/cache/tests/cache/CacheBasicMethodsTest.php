<?php
/**
 * @package    KO7/Cache
 * @group      ko7
 * @group      ko7.cache
 * @category   Test
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
abstract class KO7_CacheBasicMethodsTest extends Unittest_TestCase {

	/**
	 * @var     Cache driver for this test
	 */
	protected $_cache_driver;

	/**
	 * This method MUST be implemented by each driver to setup the `Cache`
	 * instance for each test.
	 *
	 * This method should do the following tasks for each driver test:
	 *
	 *  - Test the Cache instance driver is available, skip test otherwise
	 *  - Setup the Cache instance
	 *  - Call the parent setup method, `parent::setUp()`
	 *
	 * @return  void
	 */
	public function setUp(): void
	{
		parent::setUp();
	}

	/**
	 * Accessor method to `$_cache_driver`.
	 *
	 * @return  Cache
	 * @return  self
	 */
	public function cache(?Cache $cache = NULL)
	{
		if ($cache === NULL)
			return $this->_cache_driver;

		$this->_cache_driver = $cache;
		return $this;
	}

	/**
	 * Data provider for test_set_get()
	 *
	 * @return  array
	 */
	public function provider_set_get()
	{
		$object = new StdClass;
		$object->foo = 'foo';
		$object->bar = 'bar';

		$html_text = <<<TESTTEXT
<!doctype html>  
<head> 
</head> 

<body>
</body>
</html>
TESTTEXT;

		return [
			[
				[
					'id'      => 'string',    // Key to set to cache
					'value'   => 'foobar',    // Value to set to key
					'ttl'     => 0,           // Time to live
					'wait'    => FALSE,       // Test wait time to let cache expire
					'type'    => 'string',    // Type test
                    'tags'    => ['tag_str'],   // Tag for item
					'default' => NULL         // Default value get should return
				],
				'foobar'
			],
			[
				[
					'id'      => 'integer',
					'value'   => 101010,
					'ttl'     => 0,
					'wait'    => FALSE,
					'type'    => 'int',
                    'tags'    => ['tag_int'],
					'default' => NULL
				],
				101010
			],
			[
				[
					'id'      => 'float',
					'value'   => 10.00,
					'ttl'     => 0,
					'wait'    => FALSE,
					'type'    => 'float',
                    'tags'    => ['tag_float'],
					'default' => NULL
				],
				10.00
			],
			[
				[
					'id'      => 'array',
					'value'   => [
						'key'   => 'foo',
						'value' => 'bar'
					],
					'ttl'     => 0,
					'wait'    => FALSE,
					'type'    => 'array',
                    'tags'    => ['tag_arr'],
					'default' => NULL
				],
				[
					'key'   => 'foo',
					'value' => 'bar'
				]
			],
			[
				[
					'id'      => 'boolean',
					'value'   => TRUE,
					'ttl'     => 0,
					'wait'    => FALSE,
					'type'    => 'bool',
                    'tags'    => ['tag_bool'],
					'default' => NULL
				],
				TRUE
			],
			[
				[
					'id'      => 'null',
					'value'   => NULL,
					'ttl'     => 0,
					'wait'    => FALSE,
					'type'    => 'null',
                    'tags'    => ['tag_null'],
					'default' => NULL,
				],
				NULL
			],
			[
				[
					'id'      => 'object',
					'value'   => $object,
					'ttl'     => NULL,
					'wait'    => FALSE,
					'type'    => 'object',
                    'tags'    => ['tag_obj'],
					'default' => NULL
				],
				$object
			],
			[
				[
					'id'      => 'bar\\ with / troublesome key',
					'value'   => 'foo bar snafu',
					'ttl'     => 0,
					'wait'    => FALSE,
					'type'    => 'string',
                    'tags'    => ['tag_mult', 'tag_mult2'],
					'default' => NULL
				],
				'foo bar snafu'
			],
			[
				[
					'id'      => 'test ttl 0 means never expire',
					'value'   => 'cache value that should last',
					'ttl'     => 0,
					'wait'    => 1,
					'type'    => 'string',
                    'tags'    => ['tag_ttl'],
					'default' => NULL
				],
				'cache value that should last'
			],
			[
				[
					'id'      => 'bar',
					'value'   => 'foo',
					'ttl'     => 3,
					'wait'    => 5,
					'type'    => 'null',
                    'tags'    => ['tag_bar'],
					'default' => NULL
				],
				NULL
			],
			[
				[
					'id'      => 'snafu',
					'value'   => 'fubar',
					'ttl'     => 3,
					'wait'    => 5,
					'type'    => 'string',
                    'tags'    => ['tag_snafu'],
					'default' => 'something completely different!'
				],
				'something completely different!'
			],
			[
				[
					'id'      => 'new line test with HTML',
					'value'   => $html_text,
					'ttl'     => 10,
					'wait'    => FALSE,
					'type'    => 'string',
                    'tags'    => ['tag_html', 'tag_code'],
					'default' => NULL,
				],
				$html_text
			],
			[
				[
					'id'      => 'test with 60*5',
					'value'   => 'blabla',
					'ttl'     => 60*5,
					'wait'    => FALSE,
					'type'    => 'string',
                    'tags'    => ['tag_ttl'],
					'default' => NULL,
				],
				'blabla'
			],
			[
				[
					'id'      => 'test with 60*50',
					'value'   => 'bla bla',
					'ttl'     => 60*50,
					'wait'    => FALSE,
					'type'    => 'string',
                    'tags'    => ['tag_ttl'],
					'default' => NULL,
				],
				'bla bla'
			]
		];
	}

	/**
	 * Tests the [Cache::set()] method, testing;
	 *
	 *  - The value is cached
	 *  - The lifetime is respected
	 *  - The returned value type is as expected
	 *  - The default not-found value is respected
	 *
	 * @dataProvider provider_set_get
	 *
	 * @param   array    data
	 * @param   mixed    expected
	 * @return  void
	 */
	public function test_set_get(array $data, $expected)
	{
		$cache = $this->cache();
		extract($data);

		$this->assertTrue($cache->set($id, $value, $ttl));

		if ($wait !== FALSE)
		{
			// Lets let the cache expire
			sleep($wait);
		}

		$result = $cache->get($id, $default);
		$this->assertEquals($expected, $result);

		$assertIsType = 'assertIs'.ucfirst($type);
		$assertIsType = $assertIsType === 'assertIsNull' ? 'assertNull' : $assertIsType;
		$this->$assertIsType($result);

		unset($id, $value, $ttl, $wait, $type, $default);
	}

	/**
	 * Tests the [Cache::delete()] method, testing;
	 *
	 *  - The a cached value is deleted from cache
	 *  - The cache returns a TRUE value upon deletion
	 *  - The cache returns a FALSE value if no value exists to delete
     *
     * @depends test_set_get
     *
     * @dataProvider provider_set_get
	 *
     * @param   array   $data
     * @param   mixed   $expected
     * @throws  Cache_Exception
	 * @return  void
	 */
	public function test_delete(array $data, $expected)
	{
		// Init
		$cache = $this->cache();
		$cache->delete_all();
		extract($data);

		// Test deletion of real cached value
        $cache->set($id, $value, 3600);

		// Test delete returns TRUE and check the value is gone
		$this->assertTrue($cache->delete($id));
		$this->assertNull($cache->get($id));

		// Test non-existent cache value returns FALSE if no error
		$this->assertFalse($cache->delete($id));
	}

	/**
	 * Tests [Cache::delete_all()] works as specified
	 *
	 * @return  void
	 * @uses    KO7_CacheBasicMethodsTest::provider_set_get()
	 */
	public function test_delete_all()
	{
		// Init
		$cache = $this->cache();
		$data = $this->provider_set_get();

		foreach ($data as $key => $values)
		{
			extract($values[0]);
			if ( ! $cache->set($id, $value))
			{
				$this->fail('Unable to set: '.$key.' => '.$value.' to cache');
			}
			unset($id, $value, $ttl, $wait, $type, $default);
		}

		// Test delete_all is successful
		$this->assertTrue($cache->delete_all());

		foreach ($data as $key => $values)
		{
			// Verify data has been purged
			$this->assertSame('Cache Deleted!', $cache->get($values[0]['id'],
				'Cache Deleted!'));
		}
	}

} // End KO7_CacheBasicMethodsTest
