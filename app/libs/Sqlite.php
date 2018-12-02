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
	 * Connect to database if its not connected
	 */

	private function autoConnect() {
		if (!$this->connection) {
			$this->connect();
		}
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
	 * UPDATE
	 */

	public function update(string $table) : object {
		$this->table = $table;
		$this->query_type = 'update';
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * DELETE
	 */

	public function delete() : object {
		$this->query_type = 'delete';
		return $this;
	}


	/** ----------------------------------------------------------------------------
	 * COUNT
	 * Shortcut to 'SELECT'
	 */

	public function count() : object {
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


	/**
	 * VALUES
	 * Used with 'UPDATE'
	 */

	public function values(array $arr) : object {
		$this->insert_data = $arr;
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
	 *
	 * @return Array|Boolean
	 */

	public function all() {
		$this->autoConnect();

		// Perform query
		$this->query($this->prepareQuery());

		// Fetch and return result
		return $this->result->fetchAll(PDO::FETCH_ASSOC);
	}


	/** ----------------------------------------------------------------------------
	 * Perform query and return one row as associative array
	 *
	 * @return Array|Boolean
	 */

	public function one() {
		$this->autoConnect();

		// Perform query
		$this->query($this->prepareQuery());

		// Fetch and return result
		return $this->result->fetch(PDO::FETCH_ASSOC);
	}


	/** ----------------------------------------------------------------------------
	 * Perform insertion
	 */

	public function into(string $table) : object {
		if ($this->query_type != 'insert') {
			throw new Exception("Method `into` can be used only with `insert` query type.");
		}

		$this->autoConnect();

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

			/**
			 * Update data in database table
			 * @todo
			 */
			case 'update':
				if (count($this->insert_data) < 1) {
					throw new Exceptions("There is no data set to insert. Please use method 'values' to add data.");
				}
				if (empty($this->conditions)) {
					throw new Exception("Conditions are required to perform UPDATE operation. Use method 'where()' to add conditions.");
				}

				$query = "UPDATE {$this->table} SET " . http_build_query($this->insert_data, '', ', ') . " WHERE {$this->conditions}";
				break;

			/**
			 * Delete rows from database table
			 */
			case 'delete':
				if (empty($this->conditions)) {
					throw new Exception("Conditions are required to perform DELETE operation. Use method 'where()' to add conditions.");
				}
				$query = "DELETE FROM {$this->table} WHERE {$this->conditions}";
				break;

			default:
				throw new Exception("Unknown query type '{$this->query_type}'");
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