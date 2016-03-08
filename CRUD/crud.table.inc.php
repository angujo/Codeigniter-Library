<?php

/**
 * Created by PhpStorm.
 * User: Angujo Barrack
 * Date: 18-Feb-16
 * Time: 11:40 AM
 */
class Ctable
{
	private $DB_TABLES = array();
	private $DB        = NULL;

	function setDB($db)
	{
		$this->DB = $db;
		return $this;
	}

	function __call($name, $arguments)
	{
		$items     = preg_split('/(?=[A-Z])/', $name);
		$items     = explode('-', trim(implode('-', $items), '- '));
		$function  = array_shift($items);
		$tableName = implode('_', array_map(function ($v) {
			return strtolower(trim($v));
		}, $items));
		if (!method_exists($this, $function) || !is_callable(array($this, $function))) {
			$function = $function . '_' . $tableName;
			if (!method_exists($this, $function) || !is_callable(array($this, $function))) {
				$function = $name;
				if (!method_exists($this, $function) || !is_callable(array($this, $function))) {
					return NULL;
				}
			}
		}
		array_unshift($arguments, $tableName);
		return call_user_func_array(array($this, $function), $arguments);
	}

	function lastQuery()
	{
		return $this->DB->last_query();
	}

	private function update($tableName, $conditions, array $details)
	{
		$out = $this->prepareDetails($tableName, $details);
		if ($out->error) {
			return $out;
		}
		if (is_array($conditions)) {
			foreach ($conditions as $column => $value) {
				if (FALSE === $value || NULL === $value) {
					$this->DB->where($column, NULL, FALSE);
				} else {
					$this->DB->where($column, $value);
				}
			}
		} else {
			$this->DB->where($tableName . '.id', (int)$conditions);
		}
		$this->DB->update($tableName, $out->details);
		$out->data = $this->DB->affected_rows();
		return $out;
	}

	private function insert($tableName, array $details)
	{
		$out = $this->prepareDetails($tableName, $details);
		if ($out->error) {
			return $out;
		}
		$this->DB->insert($tableName, $out->details);
		$out->data = $this->DB->insert_id();
		return $out;
	}

	private function delete($tableName, $conditions)
	{
		if (is_array($conditions)) {
			foreach ($conditions as $column => $value) {
				if (FALSE === $value || NULL === $value) {
					$this->DB->where($column, NULL, FALSE);
				} else {
					$this->DB->where($column, $value);
				}
			}
		} else {
			$this->DB->where($tableName . '.id', (int)$conditions);
		}
		$this->DB->delete($tableName);
		return $this->DB->affected_rows();
	}

	private function row($tableName, $conditions, array $search = array())
	{
		if (!empty($search) && 2 <= count($search) && !is_array($search[0]) && is_array($search[1])) {
			$this->DB->group_start();
			foreach ($search[1] as $column) {
				$this->DB->or_like($column, $search[0]);
			}
			$this->DB->group_end();
		}
		if (is_array($conditions)) {
			foreach ($conditions as $column => $value) {
				if (FALSE === $value || NULL === $value) {
					$this->DB->where($column, NULL, FALSE);
				} else {
					$this->DB->where($column, $value);
				}
			}
		} else {
			$this->DB->where($tableName . '.id', (int)$conditions);
		}
		$res = $this->DB->get($tableName, 1);
		return 0 < $res->num_rows() ? $res->row() : FALSE;
	}

	private function rows($tableName, $conditions, $limit = 30, $start_at = 0, array $search = array())
	{
		if (!empty($search) && 2 <= count($search) && !is_array($search[0]) && is_array($search[1])) {
			$this->DB->group_start();
			foreach ($search[1] as $column) {
				$this->DB->or_like($column, $search[0]);
			}
			$this->DB->group_end();
		}
		if (is_array($conditions)) {
			foreach ($conditions as $column => $value) {
				if (FALSE === $value || NULL === $value) {
					$this->DB->where($column, NULL, FALSE);
				} else {
					$this->DB->where($column, $value);
				}
			}
		} else {
			$this->DB->where($tableName . '.id', (int)$conditions);
		}
		$res = $this->DB->get($tableName, $limit, $start_at);
		return 0 < $res->num_rows() ? $res : FALSE;
	}

	private function count($tableName, $conditions, array $search = array())
	{
		if (!empty($search) && 2 <= count($search) && !is_array($search[0]) && is_array($search[1])) {
			$this->DB->group_start();
			foreach ($search[1] as $column) {
				$this->DB->or_like($column, $search[0]);
			}
			$this->DB->group_end();
		}
		if (is_array($conditions)) {
			foreach ($conditions as $column => $value) {
				if (FALSE === $value || NULL === $value) {
					$this->DB->where($column, NULL, FALSE);
				} else {
					$this->DB->where($column, $value);
				}
			}
		} else {
			$this->DB->where($tableName . '.id', (int)$conditions);
		}
		return $this->DB->count_all_Results($tableName);
	}

	function prepareDetails($tableName, array $posts, $skipScrutiny = TRUE)
	{
		$DB           = $this->DB->hostname . $this->DB->database;
		$out          = new stdClass();
		$out->error   = FALSE;
		$out->details = array();
		$set          = FALSE;
		foreach ($this->DB_TABLES as $db => $item) {
			if ($db == $DB) {
				$set = TRUE;
				break;
			}
		}
		if (!$set) {
			$this->DB_TABLES[$DB]         = new stdClass();
			$this->DB_TABLES[$DB]->tables = array();
		}
		if (!$this->DB->table_exists($tableName)) {
			throw new Exception("SQL ERROR: Table '$tableName' does not exist!");
		}
		if (!isset($this->DB_TABLES[$DB]->tables[$tableName])) {
			$this->DB_TABLES[$DB]->tables[$tableName]                 = new stdClass();
			$this->DB_TABLES[$DB]->tables[$tableName]->allColumns     = array();
			$this->DB_TABLES[$DB]->tables[$tableName]->nonNullColumns = array();
			$this->setTable($this->DB, $tableName, $this->DB_TABLES[$DB]->tables[$tableName]);
		}
		if (!$skipScrutiny) {
			foreach ($this->DB_TABLES[$DB]->tables[$tableName]->nonNullColumns as $columnName) {
				if (!isset($posts[$columnName]) || (isset($posts['$columnName']) &&
				                                    (!strlen($posts['$columnName']) || 0 != $posts['$columnName'] ||
				                                     !trim($posts['$columnName'])))
				) {
					$out->error = 'Some required values are missing!';
					break;
				}
			}
		}
		if (!$out->error) {
			foreach ($this->DB_TABLES[$DB]->tables[$tableName]->allColumns as $columnName) {
				if (!isset($posts[$columnName])) continue;
				$out->details[$columnName] = $posts[$columnName];
			}
			if (empty($out->details)) $out->error = 'No data sent for changes to be effected!';
		}
		return $out;
	}

	private function setTable($DB, $tableName, &$instance)
	{
		$res = $DB->query("SHOW COLUMNS FROM {$tableName};");
		if (0 >= $res->num_rows()) {
			unset($instance);
			throw new Exception("SQL ERROR: The table $tableName does not seem to have any column!");
		}
		$columns        = array();
		$nonNullColumns = array();
		foreach ($res->result() as $_table) {
			if ('no' == strtolower(trim($_table->Null)) && (NULL === $_table->Default)) {
				$nonNullColumns[] = $_table->Field;
			}
			$columns[] = $_table->Field;
		}
		$instance->allColumns     = $columns;
		$instance->nonNullColumns = $nonNullColumns;
	}

	public function __get($name)
	{
		if (!property_exists($this, $name)) {
			return NULL;
		}
		return $this->$name;
	}
}
