<?php

# Tom Shehan
# 3/12/2013


# CacheDatabase
# a very simple database abstraction layer using memcached and PDO
class CacheDatabase extends PDO{

	protected $cache ;	# the memcached object
	protected $exp ;	# the expiry time for memcached

	# constructor
	function __construct($dbhost, $dbuser, $dbpassword, $db, $memcachedhost, $memcachedport, $exp = 10){

		# connect to memcached, set cache expiry time	
		$this->cache = new Memcached();
		$this->cache->addServer($memcachedhost, $memcachedport);
		$this->exp = $exp ;

		# connect to mySQL
		parent::__construct("mysql:host=$dbhost;dbname=$db;charset=utf8", $dbuser, $dbpassword);

	}

	# parameterized, cached query
	function pc_query($query,$params){

		# for SELECT queries, check for a cached result
		if(stristr($query,'select')){

			# generate caching key
			$key = md5($query . implode(',', $params));

			# return cached result if it exists
			$begin = microtime();
			$result	= $this->cache->get($key);
			if(!empty($result)){
				print('The cached query took ' . (microtime() - $begin) . "ms\n");
				return $result;
			}

			# get db result, cache it, return it
			$begin = microtime();
			$stmt = $this->prepare($query);
			$stmt->execute($params);
			$result = $stmt->fetchAll();
			print('The uncached query took ' . (microtime() - $begin) . "ms\n");
			$this->cache->set($key, $result, $this->exp );
			return $result ;
		}

		# for all other types of queries, just run the query regularly
		$stmt = $this->prepare($query);
		$stmt->execute($params);
		return ;
	}

}

# test it out on the todo database. The second query will be cached and seems to run about 3 times faster 
$db = new CacheDatabase('localhost','root','','todo','localhost','11211');
$result = $db->pc_query("SELECT * FROM todo WHERE name=:name ORDER BY id DESC",array(':name'=>'abc'));
$result = $db->pc_query("SELECT * FROM todo WHERE name=:name ORDER BY id DESC",array(':name'=>'abc'));
print_r($result[0]);


?>
