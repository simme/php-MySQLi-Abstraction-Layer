<?php
/**
 * MySQL abstraction
 */
class DatabaseException extends Exception {}

class Database {
	
	/**
	 * Configuration
	 * @var 	array
	 * @access	private
	 */
	private $config			= array();
	
	/**
	 * Link
	 * @var		resource/ibject
	 * @access	private
	 */
	private $link 			= NULL;
	
	/**
	 * Is connected?
	 * @var		boolean
	 * @access	private
	 */
	private $connected		= false;
	
	/**
	 * Number of performed queries
	 * @var		int
	 * @access	private
	 */
	private $numberOfQuries	= 0;
	
	/**
	 * Benchmark results
	 * @var		array
	 * @access	private
	 */
	private $benchmarks		= array();
	
	/**
	 * Prepared statement
	 * @var		string
	 * @access	private
	 */
	private $statement		= '';
	
	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	array		config
	 * @return	void
	 */
	public function __construct($config) {
		// Set defaults
		$this->config['host']	= 'localhost';
		$this->config['user']	= 'root';
		$this->config['pass']	= '';
		$this->config['data']	= 'database';
		$this->config['port']	= ini_get("mysqli.default_port");
		$this->config['sock']	= ini_get("mysqli.default_socket");
		$this->config['mark']	= true;
		$this->config['pref']	= '';
		
		// Read config
		if(is_array($config)) {
			foreach($config as $item => $value) {
				if(array_key_exists($item, $this->config)) {
					$this->config[$item] = $value;
				}
			}
		}
	}
	
	/**
	 * Create the link / connect
	 *
	 * @access	public
	 * @return	object	link
	 */
	public function connect() {
		if(!$this->connected) {
			$this->link = new mysqli(
				$this->config['host'],
				$this->config['user'],
				$this->config['pass'],
				$this->config['data'],
				$this->config['port'],
				$this->config['sock']
			);
			
			// As stated in the manual, this is not the "real"
			// way to do it, but we need combatibility
			if(mysqli_connect_error()) {
				throw new DatabaseException('Connection error: ' . $this->link->connect_error, $this->link->connect_errno);
			} else {
				$this->connected = true;
			}
		}
	}
	
	/**
	 * Add table prefix
	 * If the submited string does not contain {table}
	 * this method will add the prefix to the entire string.
	 *
	 * @access	public
	 * @param	string		query/table
	 * @return	string		query/table prefixed
	 */
	public function prefixTable($str) {
		if(preg_match('/\{(.*)\}/', $str)) {
			return preg_replace('/\{([a-zA-Z]*)\}/', $this->config['pref'].'\1', $str);
		} else {
			return $this->config['pref'] . $str;
		}
	}
	
	/**
	 * Prepares a query
	 *
	 * @access	public
	 * @param	string 	query
	 * @param	mixed	undefined number of parameters to bind to query
	 * @return	string 	query
	 */
	public function prepare($query) {
		// Prefix table
		$query = $this->prefixTable($query);
		
		$params = array_splice(func_get_args(), 1);
		$params = array_map(array($this->link, 'real_escape_string'), $params);
		array_unshift($params, $query);
		$query = call_user_func_array('sprintf', $params);
		
		$this->statement = $query;
		
		// Return self to enable method chaining
		return $this;
	}
	
	/**
	 * Execute statement
	 *
	 * @access	public
	 * @param	mixed	if any parameters are passed this function will try to prepare a statement
	 * @return	mysqlresult
	 */
	public function execute() {
		if(func_num_args() > 0) {
			call_user_func_array(array($this, 'prepare'), func_get_args());
		}
		
		if(empty($this->statement)) {
			return false;
		}
		
		$r = $this->query($this->statement);
		$this->statement = '';
		
		return $r;
	}
	
	/**
	 * Execute a query
	 *
	 * @access	public
	 * @param	string		query
	 * @param	bool		return as array - could impact performance if results are many
	 * @return	object		mysql result
	 */
	public function query($sql, $returnAsArray = false) {
		if(empty($sql)) {
			return false;
		}
		
		$this->numberOfQueries++;
		
		$this->startBenchmark(md5($sql), $sql);
		$result = $this->link->query($sql);
		$this->endBenchmark(md5($sql));
		
		// Turn results into array
		if($returnAsArray) {
			$r = array();
			while($res = $result->fetch_object()) {
				$r[] = $res;
			}
			return $r;
		} else {
			return $result;
		}
	}
	
	/**
	 * Are we connected?
	 *
	 * @access	public
	 * @return	boolean		connected
	 */
	public function isConnected() {
		return $this->connected;
	}
	
	/**
	 * Get number of queries
	 *
	 * @access	public
	 * @return	int			number of queries
	 */
	public function getNumberOfQueries() {
		return $this->numberOfQueries;
	}
	
	/**
	 * Get prepared statement
	 *
	 * @access	public
	 * @return	string		statement
	 */
	public function getStatement() {
		return $this->statement;
	}
	
	/**
	 * Start benchmarking
	 *
	 * @access	private
	 * @private	string 	identifier
	 * @private	string	sql
	 * @return	void
	 */
	public function startBenchmark($md5, $sql) {
		$this->benchmarks[$md5]['sql'] = $sql;
		$this->benchmarks[$md5]['start'] = microtime(true);
	}
	
	/**
	 * End benchmark
	 *
	 * @access	private
	 * @private	string 	identifier
	 * @return	void
	 */
	public function endBenchmark($md5) {
		$this->benchmarks[($md5)]['end'] = microtime(true);
		// yeeeeeah.. this line could be prettier
		$this->benchmarks[($md5)]['diff'] = $this->benchmarks[($md5)]['end'] - $this->benchmarks[($md5)]['start'];
	}
	
	/**
	 * Get benchmarks
	 *
	 * @access	private
	 * @return	array		benchmarks
	 */
	public function getBenchmarks() {
		return $this->benchmarks;
	}
}