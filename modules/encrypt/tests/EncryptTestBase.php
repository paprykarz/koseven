<?php
/**
 * Test Base for Encryption Tests. Encryption tests need to extend this Class.
 * Checks simple encode and decode function and Provides Keys
 *
 * @group      ko7
 * @group      ko7.encrypt
 *
 * @package    KO7/Encrypt
 * @category   Test
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2018 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
abstract class EncryptTestBase extends Unittest_TestCase {

	/**
	 * 128 bit encryption key
	 * @var string
	 */
	const KEY16 = '0123456789012345';

	/**
	 * 256 bit encryption key
	 * @var string
	 */
	const KEY32 = '01234567890123456789012345678901';

	/**
	 * Should be called from every test
	 *
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();

		// clear instances
		Encrypt::$instances = [];
	}

	/**
	 * Checks if encoded string is same after decoding
	 * WARNING: This could also pass if encryption is not
	 * implemented correctly! Please ensure you do KAT tests!
	 *
	 * @dataProvider provider_encode_and_decode
	 *
	 * @param string $plaintext
	 *
	 * @return void
	 * @throws KO7_Exception
	 */
	public function test_encode_and_decode(string $plaintext)
	{
		$instance = Encrypt::instance();
		$this->assertEquals($plaintext, $instance->decode($instance->encode($plaintext)));
	}

	/**
	 * Test Encrypt class initialization
	 *
	 * @return void
	 * @throws KO7_Exception
	 */
	public function test_initialization()
	{
		$this->assertCount(0, Encrypt::$instances);
		Encrypt::instance();
		Encrypt::instance(Encrypt::$default);
		$this->assertCount(1, Encrypt::$instances);
		$this->assertArrayHasKey('default', Encrypt::$instances);

		$this->set_config(KO7::$config->load('encrypt')->default, 'secondary');
		Encrypt::instance('secondary');
		$this->assertCount(2, Encrypt::$instances);
		$this->assertArrayHasKey('secondary', Encrypt::$instances);
	}

	/**
	 * Overwrites Configuration Values
	 *
	 * @param array       $config
	 * @param string|null $name
	 *
	 * @return void
	 * @throws KO7_Exception
	 */
	public function set_config(array $config, ?string $name = NULL)
	{
		if ($name === NULL) {
			$name = Encrypt::$default;
		}
		KO7::$config->load('encrypt')->set($name, $config);
	}

	/**
	 * Data source for encode_and_decode test
	 *
	 * @return array
	 */
	public function provider_encode_and_decode(): array
	{
		return [
			[
				'abcdefghijklm',
			],
			[
				'777888000',
			],
			[
				'verylongTEXTwithSTUFFandnumbers0123456789',
			],
		];
	}
}
