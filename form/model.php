<?php

/**
 * Created by PhpStorm.
 * User: aphrc
 * Date: 19/04/2016
 * Time: 2:30 PM
 */
class Flmodel extends MY_Model
{
	function __construct()
	{
		parent::__construct();
	}

	function get()
	{
		return $this;
	}

	function tableMeta($tableName)
	{
		if (!$this->DB->table_exists($tableName)) {
			throw new Exception('The table [<i>' . $tableName .
			                    '</i>] does not exist!');
		}
		$res = $this->DB->query("SHOW COLUMNS FROM {$tableName};");
		return 0 < $res->num_rows() ? $res : FALSE;
	}

	function uniqueDetailsExist($tableName, array $entries, $id = 0)
	{
		if (empty($entries)) return FALSE;
		$this->DB->group_start();
		foreach ($entries as $column => $val) {
			$this->DB->or_where($column, $val);
		}
		$this->DB->group_end();
		if ((int)$id) {
			$this->DB->where('id !=', (int)$id);
		}
		return 0 < $this->DB->count_all_results($tableName);
	}

	private function actionedItems($tableName, $condition)
	{
		if (is_array($condition)) {
			$this->DB->group_start();
			foreach ($condition as $column => $value) {
				if (NULL === $value || FALSE === $value) {
					$this->DB->where($column);
				} else {
					$this->DB->where($column, $value);
				}
			}
			$this->DB->group_end();
		} else {
			$this->DB->where('id', (int)$condition);
		}
		$res = $this->DB->where('deleted IS NULL')->select('id')->get($tableName);
		$out = array();
		foreach ($res->result() as $item) {
			$out[] = $item->id;
		}
		return $out;
	}

	function insert($tableName, array  $details)
	{
		$this->DB->insert($tableName, $details);
		return $this->DB->insert_id();
	}

	function update($tableName, $condition, array  $details)
	{

		$items = $this->actionedItems($tableName, $condition);
		if (empty($items)) return 0;
		$this->DB->where_in('id', $items)->update($tableName, $details);
		return $this->DB->affected_rows();
	}

	function delete($tableName, $condition,array $details)
	{
		if (!is_array($condition)) {
			$this->DB->where_in('id', (int)$condition);
		} else {
			$items = $this->actionedItems($tableName, $condition);
			if (empty($items)) return 0;
		}
		$this->DB->update($tableName,$details);
		return $this->DB->affected_rows();
	}

	function last_query()
	{
		return $this->DB->last_query();
	}
}
