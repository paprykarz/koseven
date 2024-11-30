<?php
/**
 * @package    Kohana
 * @category   Exceptions
 * @author     Kohana Team
 * @copyright  (c) Kohana Team
 * @license    https://koseven.ga/LICENSE.md
 */
class Kohana_Validation_Exception extends Kohana_Exception {

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
