<?php
/**
 * Database query builder for SELECT statements. See [Query Builder](/database/query/builder) for usage and examples.
 *
 * @package    KO7/Database
 * @category   Query
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) since 2016 Koseven Team
 * @license    https://koseven.dev/LICENSE
 */
class KO7_Database_Query_Builder_Select extends Database_Query_Builder_Where {

	// SELECT ...
	protected $_select = [];

	// DISTINCT
	protected $_distinct = FALSE;

	// FROM ...
	protected $_from = [];

	// JOIN ...
	protected $_join = [];

	// GROUP BY ...
	protected $_group_by = [];

	// HAVING ...
	protected $_having = [];

	// OFFSET ...
	protected $_offset = NULL;

    // UNION ...
    protected $_union = [];

	// The last JOIN statement created
	protected $_last_join;

	/**
	 * Sets the initial columns to select from.
	 *
	 * @param   array  $columns  column list
	 * @return  void
	 */
	public function __construct(?array $columns = NULL)
	{
		if ( ! empty($columns))
		{
			// Set the initial columns
			$this->_select = $columns;
		}

		// Start the query with no actual SQL statement
		parent::__construct(Database::SELECT, '');
	}

	/**
	 * Enables or disables selecting only unique columns using "SELECT DISTINCT"
	 *
	 * @param   boolean  $value  enable or disable distinct columns
	 * @return  $this
	 */
	public function distinct($value)
	{
		$this->_distinct = (bool) $value;

		return $this;
	}

	/**
	 * Choose the columns to select from.
	 *
	 * @param   mixed  $columns  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function select($columns = NULL)
	{
		$columns = func_get_args();

		$this->_select = array_merge($this->_select, $columns);

		return $this;
	}

	/**
	 * Choose the columns to select from, using an array.
	 *
	 * @param   array  $columns  list of column names or aliases
	 * @return  $this
	 */
	public function select_array(array $columns)
	{
		$this->_select = array_merge($this->_select, $columns);

		return $this;
	}

	/**
	 * Choose the tables to select "FROM ..."
	 *
	 * @param   mixed  $table  table name or array($table, $alias) or object
	 * @return  $this
	 */
	public function from($tables)
	{
		$tables = func_get_args();

		$this->_from = array_merge($this->_from, $tables);

		return $this;
	}

	/**
	 * Adds addition tables to "JOIN ...".
	 *
	 * @param   mixed   $table  column name or array($column, $alias) or object
	 * @param   string  $type   join type (LEFT, RIGHT, INNER, etc)
	 * @return  $this
	 */
	public function join($table, $type = NULL)
	{
		$this->_join[] = $this->_last_join = new Database_Query_Builder_Join($table, $type);

		return $this;
	}

	/**
	 * Adds "ON ..." conditions for the last created JOIN statement.
	 *
	 * @param   mixed   $c1  column name or array($column, $alias) or object
	 * @param   string  $op  logic operator
	 * @param   mixed   $c2  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function on($c1, $op, $c2)
	{
		$this->_last_join->on($c1, $op, $c2);

		return $this;
	}

	/**
	 * Adds "USING ..." conditions for the last created JOIN statement.
	 *
	 * @param   string  $columns  column name
	 * @return  $this
	 */
	public function using($columns)
	{
		$columns = func_get_args();

		call_user_func_array([$this->_last_join, 'using'], $columns);

		return $this;
	}

	/**
	 * Creates a "GROUP BY ..." filter.
	 *
	 * @param   mixed   $columns  column name or array($column, $alias) or object
	 * @return  $this
	 */
	public function group_by($columns)
	{
		$columns = func_get_args();

		$this->_group_by = array_merge($this->_group_by, $columns);

		return $this;
	}

	/**
	 * Alias of and_having()
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function having($column, $op, $value = NULL)
	{
		return $this->and_having($column, $op, $value);
	}

	/**
	 * Creates a new "AND HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function and_having($column, $op, $value = NULL)
	{
		$this->_having[] = ['AND' => [$column, $op, $value]];

		return $this;
	}

	/**
	 * Creates a new "OR HAVING" condition for the query.
	 *
	 * @param   mixed   $column  column name or array($column, $alias) or object
	 * @param   string  $op      logic operator
	 * @param   mixed   $value   column value
	 * @return  $this
	 */
	public function or_having($column, $op, $value = NULL)
	{
		$this->_having[] = ['OR' => [$column, $op, $value]];

		return $this;
	}

	/**
	 * Alias of and_having_open()
	 *
	 * @return  $this
	 */
	public function having_open()
	{
		return $this->and_having_open();
	}

	/**
	 * Opens a new "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_open()
	{
		$this->_having[] = ['AND' => '('];

		return $this;
	}

	/**
	 * Opens a new "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_open()
	{
		$this->_having[] = ['OR' => '('];

		return $this;
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function having_close()
	{
		return $this->and_having_close();
	}

	/**
	 * Closes an open "AND HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function and_having_close()
	{
		$this->_having[] = ['AND' => ')'];

		return $this;
	}

	/**
	 * Closes an open "OR HAVING (...)" grouping.
	 *
	 * @return  $this
	 */
	public function or_having_close()
	{
		$this->_having[] = ['OR' => ')'];

		return $this;
	}

	/**
	 * Adds an other UNION clause.
	 *
	 * @param mixed $select  if string, it must be the name of a table. Else
	 *  must be an instance of Database_Query_Builder_Select
	 * @param boolean $all  decides if it's an UNION or UNION ALL clause
	 * @return $this
	 */
	public function union($select, $all = TRUE)
	{
		if (is_string($select))
		{
			$select = DB::select()->from($select);
		}
		if ( ! $select instanceof Database_Query_Builder_Select)
			throw new KO7_Exception('first parameter must be a string or an instance of Database_Query_Builder_Select');
		$this->_union []= ['select' => $select, 'all' => $all];
		return $this;
	}

	/**
	 * Start returning results after "OFFSET ..."
	 *
	 * @param   integer   $number  starting result number or NULL to reset
	 * @return  $this
	 */
	public function offset($number)
	{
		$this->_offset = ($number === NULL) ? NULL : (int) $number;

		return $this;
	}

	/**
	 * Compile the SQL query and return it.
	 *
	 * @param   mixed  $db  Database instance or name of instance
	 * @return  string
	 */
	public function compile($db = NULL)
	{
		if ( ! is_object($db))
		{
			// Get the database instance
			$db = Database::instance($db);
		}

		// Callback to quote columns
		$quote_column = [$db, 'quote_column'];

		// Callback to quote tables
		$quote_table = [$db, 'quote_table'];

		// Start a selection query
		$query = 'SELECT ';

		if ($this->_distinct === TRUE)
		{
			// Select only unique results
			$query .= 'DISTINCT ';
		}

		if (empty($this->_select))
		{
			// Select all columns
			$query .= '*';
		}
		else
		{
			// Select all columns
			$query .= implode(', ', array_unique(array_map($quote_column, $this->_select)));
		}

		if ( ! empty($this->_from))
		{
			// Set tables to select from
			$query .= ' FROM '.implode(', ', array_unique(array_map($quote_table, $this->_from)));
		}

		if ( ! empty($this->_join))
		{
			// Add tables to join
			$query .= ' '.$this->_compile_join($db, $this->_join);
		}

		if ( ! empty($this->_where))
		{
			// Add selection conditions
			$query .= ' WHERE '.$this->_compile_conditions($db, $this->_where);
		}

		if ( ! empty($this->_group_by))
		{
			// Add grouping
			$query .= ' '.$this->_compile_group_by($db, $this->_group_by);
		}

		if ( ! empty($this->_having))
		{
			// Add filtering conditions
			$query .= ' HAVING '.$this->_compile_conditions($db, $this->_having);
		}

		if ( ! empty($this->_order_by))
		{
			// Add sorting
			$query .= ' '.$this->_compile_order_by($db, $this->_order_by);
		}

		if ($this->_limit !== NULL)
		{
			// Add limiting
			$query .= ' LIMIT '.$this->_limit;
		}

		if ($this->_offset !== NULL)
		{
			// Add offsets
			$query .= ' OFFSET '.$this->_offset;
		}

		if ( ! empty($this->_union))
		{
			$query = '('.$query.')';
			foreach ($this->_union as $u) {
				$query .= ' UNION ';
				if ($u['all'] === TRUE)
				{
					$query .= 'ALL ';
				}
				$query .= '('.$u['select']->compile($db).')';
			}
		}

		$this->_sql = $query;

		return parent::compile($db);
	}

	public function reset()
	{
		$this->_select   =
		$this->_from     =
		$this->_join     =
		$this->_where    =
		$this->_group_by =
		$this->_having   =
		$this->_order_by =
		$this->_union = [];

		$this->_distinct = FALSE;

		$this->_limit     =
		$this->_offset    =
		$this->_last_join = NULL;

		$this->_parameters = [];

		$this->_sql = NULL;

		return $this;
	}

} // End Database_Query_Select
