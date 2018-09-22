<?php

declare(strict_types=1);

/**
 * Simple Sqlite abstraction class
 */

class Sqlite {
	protected $connection = false;
	protected $file;

	protected $command;
	protected $fields;
	protected $insert_data;
	protected $conditions;
	protected $table;
	protected $order_by;

	// Last query result
	protected $result;

	// Array that logs all performed queries
	protected $log = [];

	protected $options = [
		// Decide if errors should be thrown
		'debug' => false,

		// Create database file if it does not exist
		'autocreate' => true,
	];


	/** ----------------------------------------------------------------------------
	 * Constructor
	 */

	public function __construct(string $file, array $options = []) {
		$this->file = $file;

		if (count($options) > 0) {
			$this->options = array_merge($this->options, $options);
		}

		if (!$this->options['autocreate'] && !is_file($file)) {
			throw new Error('Database file does not exist');
		}
	}


	/** ----------------------------------------------------------------------------
	 * Connect to database file
	 * This method can be performed manually or it will be run automatically
	 * at first use of method `all()`
	 */

	public function connect() {
		$this->connection = new PDO('sqlite:./' . $this->file);
		$this->connection->setAttribute(
			PDO::ATTR_ERRMODE,
			$this->options['debug'] ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT
		);
	}


	/** ----------------------------------------------------------------------------
	 * SELECT
	 * @param string|array $fields
	 */

	public function select($fields = '*') : object {
		$this->query_type = 'select';
		$this->fields = $fields;
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * INSERT
	 */

	public function insert(array $arr) : object {
		if (!is_array($arr) || count($arr) < 1) {
			throw new Exception("There are no valid data passed to method `insert` that can be inserted into database.");
		}

		$this->query_type = 'insert';
		$this->insert_data = $arr;
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * COUNT
	 * Shortcut to 'SELECT'
	 */

	public function count() : int {
		return $this->select('COUNT(*) as count');
	}


	/** ----------------------------------------------------------------------------
	 * FROM
	 * @param string $table
	 */

	public function from(string $table) : object {
		$this->table = $table;
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * WHERE
	 */

	public function where(string $conditions) : object {
		$this->conditions = $conditions;
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * ORDER BY
	 */

	public function orderBy(string $order, string $dir = 'ASC') {
		$this->order_by = $order;
		$this->order_dir = strtoupper($dir);
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * Perform query
	 */

	public function query(string $query_string) : object {
		$this->result = $this->connection->query($query_string);
		$this->log[] = $query_string;
		$this->reset();

		return $this->result;
	}


	/** ----------------------------------------------------------------------------
	 * Perform query and return array of elements
	 */

	public function all() : array {
		// Autoconnect to DB if there is no open connection
		if (!$this->connection) {
			$this->connect();
		}

		// Perform query
		$this->query($this->prepareQuery());

		// Fetch and return result
		return $this->result->fetchAll(PDO::FETCH_ASSOC);
	}


	/** ----------------------------------------------------------------------------
	 * Perform insertion
	 */

	public function into(string $table) : bool {
		if ($this->query_type != 'insert') {
			throw new Exception("Method `into` can be used only with `insert` query type.");
		}

		// Autoconnect to DB if there is no open connection
		if (!$this->connection) $this->connect();

		$this->table = $table;

		// Perform query
		return $this->query($this->prepareQuery());
	}


	/** ----------------------------------------------------------------------------
	 * Prepare query
	 */

	protected function prepareQuery() : string {
		switch ($this->query_type) {

			/**
			 * Select data from database
			 */
			case 'select':
				$fields = '';

				if (is_array($this->fields)) {
					$fields_num = count($this->fields) - 1;
					foreach($this->fields as $i => $field) {
						$fields .= "`{$field}`";
						if ($i < $fields_num) $fields .= ", ";
					}
				}
				else $fields = $this->fields;

				$query = "SELECT {$fields} FROM `{$this->table}`";

				if (!empty($this->conditions)) {
					$query .= " WHERE {$this->conditions}";
				}

				if (!empty($this->order_by)) {
					$query .= " ORDER BY `{$this->order_by}` {$this->order_dir}";
				}

				break;

			/**
			 * Insert data into database table
			 */
			case 'insert':
				$columns = "'" . implode("', '", array_keys($this->insert_data)) . "'";
				$values  = "'" . implode("', '", array_values($this->insert_data)) . "'";

				$query = "INSERT INTO {$this->table}({$columns}) VALUES({$values});";
				break;

			default:
				throw new Exception('Unknown');
		}

		return $query;
	}


	/** ----------------------------------------------------------------------------
	 * Reset prepared query data
	 */

	public function reset() {
		$this->query_type = null;
		$this->fields = null;
		$this->insert_data = null;
		$this->conditions = null;
		$this->table = null;
		$this->order_by = null;
		$this->order_dir = 'ASC';
	}


	/** ----------------------------------------------------------------------------
	 * Get queries log
	 */

	public function getLog() : array {
		return $this->log;
	}
}