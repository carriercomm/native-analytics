<?
	function db_open()
	{
		assert(defined('DB_USER') && 
		       defined('DB_PASS') &&
		       defined('DB_NAME'));

		return db_open_ex(DB_USER, DB_PASS, DB_NAME);
	}
	
	function db_open_ex($user, $pass, $name)
	{
		try
		{
			$db = new PDO('mysql:host=localhost;dbname=' . $name, 
		                      $user, $pass,
				      array(PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
					    PDO::ATTR_PERSISTENT => true,
					    PDO::MYSQL_ATTR_FOUND_ROWS => true));
		}
		catch (PDOException $x)
		{
			logf("pdo error: " . $x->getMessage());
			return false;
		}
		return $db;
	}

	function db_close(&$db)
	{
		$db = null;
	}

	function db_query($db, $q, $v)
	{
		$st = $db->prepare($q);
		if (! $st)
		{
			logf("pdo error preparing query [$q]");
			return null;
		}

		if (! $st->execute($v))
		{
			logf("pdo error for [$q]: " . 
				implode(' | ', $st->errorInfo()) );
			return null;
		}
		return $st;
	}

	function db_call($db, $func)
	{
		$st = db_query($db, "select $func", array());
		if (! $st)
			return null;

		$row = $st->fetch(PDO::FETCH_NUM);
		return $row[0];
	}

	function db_get_time($db)
	{
		return db_call($db, "now()");
	}

	/*
	 *
	 */
	function db_find_string_id($db, $table, $md5)
	{
		$rc = db_query($db, 
			"select " .
			"  id " . 
			"from $table where " .
			"  hash = unhex(:h) " .
			"limit 1", 
			array(':h' => $md5));

		if ($rc && $rc->rowCount() > 0)
		{
			$row = $rc->fetch(PDO::FETCH_ASSOC);
			return $row['id'];
		}

		return 0;
	}

	function db_add_string_id($db, $table, $str, $md5)
	{
		assert($md5);
			
		$rc = db_query($db,
			"insert ignore into $table ".
			"  (hash, val) " .
			"values " .
			"  (unhex(:h), :v)",
			array(':h' => $md5, ':v' => $str));

		if (! $rc || $rc->rowCount() == 0)
			return 0;

		return $db->lastInsertId();
	}

	function db_get_string_id($db, $table, $str)
	{
		if ($str == '')
			return 0;

		$md5 = md5($str); /* HEX */

		$id = db_find_string_id($db, $table, $md5);
		if ($id)
			return $id;

		$id = db_add_string_id($db, $table, $str, $md5);
		if ($id)
			return $id;

		// it's a race
		return db_find_string_id($db, $table, $md5);
	}

	/*
	 *
	 */
	function db_get_string($db, $table, $id)
	{
		if ($id == 0)
			return '';

		$st = db_query($db,
			"select val from $table where id = :i",
			array(':i' => $id));

		if (! $st || $st->rowCount() == 0)
		{
			logf("string #$id doesn't exist");
			return '?';
		}

		$row = $st->fetch(PDO::FETCH_NUM);
		return $row[0];
	}
?>