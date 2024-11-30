<?php
/**
 * MySQLi database result.   See [Results](/database/results) for usage and examples.
 *
 * @package    KO7/Database
 * @category   Query/Result
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Database_MySQLi_Result extends Database_Result {

	protected $_internal_row = 0;

	public function __construct($result, $sql, $as_object = FALSE, ?array $params = NULL)
	{
		parent::__construct($result, $sql, $as_object, $params);

		// Find the number of rows in the result
		$this->_total_rows = $result->num_rows;
	}

	public function __destruct()
	{
		if (is_resource($this->_result))
		{
			$this->_result->free();
		}
	}

	public function seek(int $offset): void
	{
		if ($this->offsetExists($offset) AND $this->_result->data_seek($offset))
			$this->_current_row = $this->_internal_row = $offset;
	}

	public function current(): mixed
	{
		if ($this->_current_row !== $this->_internal_row AND ! $this->seek($this->_current_row))
			return NULL;

		// Increment internal row for optimization assuming rows are fetched in order
		$this->_internal_row++;

		if ($this->_as_object === TRUE)
		{
			// Return an stdClass
			return $this->_result->fetch_object();
		}
		elseif (is_string($this->_as_object))
		{
			// Return an object of given class name
			return $this->_result->fetch_object($this->_as_object, (array) $this->_object_params);
		}
		else
		{
			// Return an array of the row
			return $this->_result->fetch_assoc();
		}
	}

} // End Database_MySQLi_Result_Select
