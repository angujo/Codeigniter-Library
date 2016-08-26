<?php

class Form
{
	private static $CI;
	private static $model;
	public static  $POST            = array();
	public static  $GET             = array();
	private static $TABLES_METADATA = array();
	public static  $primaryID       = 0;
	public static  $actionUserId    = 0;

	function __construct()
	{
		self::$CI =& get_instance();
		self::$CI->load->model('flmodel');
		self::$model     = new Flmodel();
		self::$model     = self::$CI->flmodel->get();
		self::$GET       = self::$CI->input->get(NULL, TRUE);
		self::$POST      = self::$CI->input->post(NULL, TRUE);
		self::$primaryID = (int)@self::$POST['item_id'];
	}

	static function fullPostData($tableName, $externalFinish = FALSE, &$status = NULL)
	{
		if (!isset(self::$POST['item_id'])) {
			self::$primaryID = 0;
		} else {
			self::$primaryID = self::$POST['item_id'];
		}
		self::insertPostCheck($tableName);
		self::checkIfPostUnique($tableName);
		$table = self::$TABLES_METADATA[$tableName];
		if (self::$primaryID) {
			if (FALSE !== array_search('updated', $table->columns)) {
				self::$POST['updated']    = date('Y-m-d H:i:s');
				self::$POST['updated_by'] = self::$actionUserId;
			}
			$effected = self::$model->update($tableName, Form::$primaryID, self::postTable($tableName));
			if ($effected && FALSE == $externalFinish) {
				self::qSuccess('Item was successfully updated!', self::$primaryID);
			}
		} else {
			if (FALSE !== array_search('created', $table->columns)) {
				self::$POST['created']    = date('Y-m-d H:i:s');
				self::$POST['created_by'] = self::$actionUserId;
			}
			$effected        = self::$model->insert($tableName, self::postTable($tableName));
			self::$primaryID = $effected;
			if ($effected && FALSE == $externalFinish) {
				self::qSuccess('Item was successfully Saved!', self::$primaryID);
			}
		}
		if (!$externalFinish) {
			self::qError('An error was encountered while saving your entry! The item seem to have been deleted or does not exist!');
		}
		return self::$primaryID;
	}

	static function checkIfPostUnique($table_name,
	                                  $message = 'Some values you have provided have already setup. Enter unique values where necessary!')
	{
		if (self::$model->uniqueDetailsExist($table_name, self::uniqueTablePost($table_name), self::$primaryID)) {
			self::qError($message);
		}
		return TRUE;
	}

	static function insertPostCheck($table_name, $message = 'Some required values are missing!')
	{
		if ((count(self::nonNullTableColumns($table_name)) != count(self::nonNullTablePost($table_name))) ||
		    (count(self::uniqueTableColumns($table_name)) != count(self::uniqueTablePost($table_name)))
		) {
			self::qError($message);
		}
		return TRUE;
	}

	static function tableColumns($table_name)
	{
		if (array_key_exists($table_name, self::$TABLES_METADATA)) {
			return self::$TABLES_METADATA[$table_name];
		}
		if (!$table = self::$model->tableMeta($table_name)) return array();
		$uniqueColumns  = array();
		$nonNullColumns = array();
		$columns        = array();
		$dataTypes      = array();
		foreach ($table->result() as $_table) {
			if (FALSE !== strpos($_table->Extra, 'auto_increment')) {
				continue;
			}
			if ('uni' == strtolower(trim($_table->Key))) {
				$uniqueColumns[] = $_table->Field;
			}
			if ('no' == strtolower(trim($_table->Null)) && (NULL === $_table->Default)) {
				$nonNullColumns[] = $_table->Field;
			}
			$columns[]   = $_table->Field;
			$dataTypes[] = self::columnDataType($_table->Type);
		}
		self::$TABLES_METADATA[$table_name] = (object)array('columns'  => $columns, 'data_types' => $dataTypes,
		                                                    'non_null' => $nonNullColumns, 'unique' => $uniqueColumns);
		return self::$TABLES_METADATA[$table_name];
	}

	private static function columnDataType($raw_data_type)
	{
		if ('int(' == substr($raw_data_type, 0, 4)) return 'int';
		if ('float' == substr($raw_data_type, 0, 5)) return 'float';
		return $raw_data_type;
	}

	static function uniqueTableColumns($table_name)
	{
		$table = self::tableColumns($table_name);
		return $table->unique;
	}

	static function nonNullTableColumns($table_name)
	{
		$table = self::tableColumns($table_name);
		return $table->non_null;
	}

	static function postTable($table_name)
	{
		$data     = array();
		$table    = self::tableColumns($table_name);
		$dataType = $table->data_types;
		//var_dump($table->columns);
		foreach ($table->columns as $index => $column) {
			if (isset(self::$POST[$column])) {
				if (isset($dataType[$index])) {
					$data[$column] = self::$POST[$column];
					if ('int' == $dataType[$index]) {
						$data[$column] = (int)self::$POST[$column];
					}
					if ('float' == $dataType[$index]) {
						$data[$column] = (float)self::$POST[$column];
					}
				} else {
					$data[$column] = self::$POST[$column];
				}
			}
		}
		return $data;
	}

	static function uniqueTablePost($table_name)
	{
		$data    = array();
		$columns = self::uniqueTableColumns($table_name);
		foreach ($columns as $column) {
			if (isset(self::$POST[$column])) {
				$data[$column] = self::$POST[$column];
			}
		}
		return $data;
	}

	static function nonNullTablePost($table_name)
	{
		$data    = array();
		$columns = self::nonNullTableColumns($table_name);
		foreach ($columns as $column) {
			if (isset(self::$POST[$column]) && trim(self::$POST[$column])) {
				$data[$column] = self::$POST[$column];
			}
		}
		return $data;
	}


	static function qOutput($code, $result = '', $success_msg = '', $error_msg = '', $redirect_code = 0)
	{
		/*
		 * Check if it is an ajax request
		 */
		if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
		) {
			self::jsonOutput($code, $result, $success_msg, $error_msg);
		} else {
			self::redirectOutput($redirect_code);
		}
	}

	static function qError($message = '', $result = '', $redirect_code = 0)
	{
		self::qOutput(0, $result, '', $message, $redirect_code);
	}

	static function qSuccess($message = '', $result = '', $redirect_code = 0)
	{
		self::qOutput(999, $result, $message, '', $redirect_code);
	}

	private static function jsonOutput($code, $result = '', $success_msg = '', $error_msg = '')
	{
		$code        = is_numeric($code) && 0 < $code ? $code : 0;
		$success_msg = trim($success_msg) ? $success_msg : 'Your Actions have been successfully implemented!';
		$error_msg   = trim($error_msg) ? $error_msg : 'An error was encountered performing the given action!';

		$output['code']    = $code;
		$output['result']  = $result;
		$output['message'] = 'Unable to provide feedback message';

		if (0 >= $code) {
			$output['message'] = $error_msg;
		} else {
			$output['message'] = $success_msg;
		}
		header('Content-Type:application/json;charset=utf-8;');
		echo json_encode($output);
		die();
	}

	static function lastQuery()
	{
		return self::$model->last_query();
	}

	static function delete($tableName, $condition)
	{
		return self::$model->delete($tableName, $condition,
		                            array('deleted' => date('Y-m-d H:i:s'), 'deleted_by' => self::$actionUserId));
	}

	private static function redirectOutput($code)
	{
		/*switch ($code) {
			case RQC_LOGIN_ERROR:
			break;
			case RQC_LOGIN_SUCCESS:
			break;
			case RQC_REGISTRATION_ERROR:
			break;
			case RQC_REGISTRATION_SUCCESS:
			break;
		}*/
	}
	

	private static function getDataType($dt)
	{
		$r=array('points'=>0,'type'=>'');
		preg_match('/(\(([\d+\,\s+]+)\))/i',$dt, $matches);
		if($matches){
			$matches=explode(',', trim($matches,'() '));
			$r['points']=1<count($matches)?$matches[1]:$matches[0];
		}
		$r['type']= trim(preg_replace('/(\()([\d+\,\s+]+)(\))//', '', str_ireplace(' unsigned', '', $dt)));
		return $r;
	}
	
	private function valueIntegrity($columnType,$value){
		$type=self::getDataType($columnType);
		switch ($type->type) {
			case 'int':
			case 'tinyint':
			case 'smallint':
			case 'bigint':
				return (int)$value;
				break;
			case 'datetime':
			case 'timestamp':
				return date('Y-m-d H:i:s',strtotime($value));
				break;
			case 'float':
			case 'double':
			case 'decimal':
				return (float)$value;
				break;
			case 'date':
				return date('Y-m-d',strtotime($value));
				break;
			case 'time':
				return date('H:i:s',strtotime($value));
				break;
		}
		return $value;
	}
}
