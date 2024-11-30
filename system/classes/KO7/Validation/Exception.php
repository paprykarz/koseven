<?php
/**
 * @package    KO7
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Validation_Exception extends KO7_Exception {

	/**
	 * @var  object  Validation instance
	 */
	public $array;

	/**
	 * @param  Validation   $array      Validation object
	 * @param  string       $message    error message
	 * @param  array        $values     translation variables
	 * @param  int          $code       the exception code
	 */
	public function __construct(Validation $array, $message = 'Failed to validate array', ?array $values = NULL, $code = 0, ?Exception $previous = NULL)
	{
		$this->array = $array;

		parent::__construct($message, $values, $code, $previous);
	}

}
