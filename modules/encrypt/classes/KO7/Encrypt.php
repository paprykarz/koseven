<?php
/**
 * Encryption Class for KO7.
 * - Handles Encryption instances and proxies encrypt/decrypt functions
 *
 * Example Usage:
 *        $engine = Encrypt::instance();
 *        $msg    = $engine->encode('Crypt this!');    -> will return an encrypted string
 *        $text    = $engine->decode($msg);            -> will return "Crypt this!"
 *
 * @package    KO7/Encrypt
 * @category   Security
 *
 * @copyright  (c) 2007-2012 Kohana Team
 * @copyright  (c) 2016-2018 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Encrypt {

	/**
	 * Name of default config instance
	 * @var string
	 */
	public static $default = 'default';

	/**
	 * Name of default engine to use
	 * @var string
	 */
	public static $default_engine = 'openssl';

	/**
	 * Encrypt class instances
	 * @var array
	 */
	public static $instances = [];

	/**
	 * Engine Name
	 * @var string
	 */
	private $_name;

	/**
	 * Encryption engine
	 * @var KO7_Encrypt_Engine
	 */
	private $_engine;

	/**
	 * Creates a new Encrypt Engine instance.
	 *
	 * @param string $name   Engine Name
	 * @param array  $config Configuration
	 *
	 * @throws KO7_Exception
	 */
	public function __construct(string $name, array $config)
	{
		// Get Driver Type
		$config['type'] = $config['type'] ?? self::$default_engine;

		// Set the engine class name
		$engine_name = 'Encrypt_Engine_'.ucfirst($config['type']);

		// Create the engine class
		$this->_name = $name;

		// Since user can define class via string we need to check if it exists
		if (!class_exists($engine_name)) {
			throw new KO7_Exception('Encryption type: :name defined in the encryption configuratin does not exist.', [
				':name' => $engine_name,
			]);
		}

		$this->_engine = new $engine_name($config);

		// make sure class is instance of KO7_Encrypt_Engine
		if (!$this->_engine instanceof KO7_Encrypt_Engine) {
			// @codeCoverageIgnoreStart
			throw new KO7_Exception('Encryption type: :name defined in the encryption configuration is not a valid type/driver class.', [
				':name' => $engine_name,
			]);
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Returns a singleton instance of Encrypt. An encryption key must be
	 * provided in your "encrypt" configuration file.
	 *     $encrypt = Encrypt::instance();
	 *
	 * @param  string     $name   Configuration group name
	 * @param  array|null $config Configuration
	 *
	 * @return KO7_Encrypt
	 * @throws KO7_Exception
	 */
	public static function instance(?string $name = NULL, ?array $config = NULL)
	{
		if ($name === NULL) {
			// Use the default instance name
			$name = self::$default;
		}

		if (!isset(self::$instances[$name])) {
			if ($config === NULL) {
				// Load the configuration data
				$config = KO7::$config->load('encrypt')->$name;
			}

			if (!isset($config['key']) || empty($config['key'])) {
				// No default encryption key is provided!
				throw new KO7_Exception('No encryption key is defined in the encryption configuration group: :group',
					[':group' => $name]);
			}

			// Create a new instance
			self::$instances[$name] = new self($name, $config);
		}

		return self::$instances[$name];
	}

	/**
	 * Encrypts a string and returns an encrypted string that can be decoded.
	 *     $data = $encrypt->encode($message);
	 * The encrypted binary data is encoded using [base64](http://php.net/base64_encode)
	 * to convert it to a string. This string can be stored in a database,
	 * displayed, and passed using most other means without corruption.
	 * WARNING! The $iv variable is for testing purposes only, do not use this variable
	 * unless you REALLY know what you are doing.
	 *
	 * @param  string      $message Message to be encrypted
	 * @param  string|null $iv      Initialization Vector
	 *
	 * @return null|string
	 * @throws Exception
	 */
	public function encode(string $message, ?string $iv = NULL)
	{
		if ($iv === NULL) {
			$iv = $this->_engine->create_iv();
		}
		return $this->_engine->encrypt($message, $iv);
	}

	/**
	 * Decrypts an encoded string back to its original value.
	 *     $data = $encrypt->decode($ciphertext);
	 *
	 * @param  string $ciphertext Text to decrypt
	 *
	 * @return null|string
	 */
	public function decode(string $ciphertext)
	{
		return $this->_engine->decrypt($ciphertext);
	}

	/**
	 * Returns text representation of Encrypt class
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return get_class($this->_engine).' ('.$this->_name.')';
	}
}
