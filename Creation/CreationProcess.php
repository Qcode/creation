<?php

require_once 'PEAR.php';
require_once 'MDB2.php';
require_once 'SwatDB/SwatDB.php';
require_once 'SwatDB/exceptions/SwatDBException.php';
require_once 'Swat/exceptions/SwatException.php';
require_once 'Creation/CreationFile.php';

class CreationProcess
{
	public $dsn = null;
	public $db = null;

	private $objects = array();
	private $processed_objects = array();
	private $stack = array();

	// {{{ public function run()

	public function run()
	{
		$this->connectDB();

		foreach ($this->objects as $object)
			$this->createObject($object);
	}

	// }}}
	// {{{ public function addFile()

	public function addFile($filename)
	{
		echo "Adding file ", $filename, "\n";
		$file = new CreationFile($filename);
		$objects = $file->getObjects();
		echo '    ', implode(', ', array_keys($objects)), "\n";
		$this->objects = array_merge($this->objects, $objects);
	}

	// }}}
	// {{{ private function createObject()

	private function createObject(CreationObject $object)
	{
		if (in_array($object->name, $this->processed_objects))
			return;

		if (in_array($object->name, $this->stack)) {
			ob_start();
			echo 'Circular dependency on object ', $object->name, ".\n";
			print_r($object->deps);
			$message = ob_get_clean();
			throw new SwatException($message);
		}

		array_push($this->stack, $object->name);

		foreach ($object->deps as $dep) {
			$dep_object = $this->findObject($dep);

			if ($dep_object === null)
				printf("Warning: dependent object '$dep' not found, skipping\n");
			else
				$this->createObject($dep_object);
		}

		array_pop($this->stack);

		echo "Creating object ", $object->name, "\n";
		SwatDB::exec($this->db, $object->sql);

		$this->processed_objects[] = $object->name;
	}

	// }}}
	// {{{ protected function connectDB()

	protected function connectDB()
	{
		printf("Connecting to DB (%s)... ", $this->dsn);

		if ($this->dsn === null)
			throw new SwatException('No DSN specified.');

		$this->db = MDB2::connect($this->dsn);

		if (PEAR::isError($this->db))
			throw new SwatDBException($this->db);

		$this->db->options['result_buffering'] = false;

		echo "success\n";
	}

	// }}}
	// {{{ private function findObject()

	private function findObject($name)
	{
		if (isset($this->objects[$name]))
			return $this->objects[$name];

		return null;
	}

	// }}}
}

?>