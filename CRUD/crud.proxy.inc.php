<?php

/**
 * Created by PhpStorm.
 * User: Angujo Barrack
 * Date: 18-Feb-16
 * Time: 1:31 PM
 */
class Oproxy
{
	public $object;

	public function __construct(&$object)
	{
		$this->object = &$object;
	}

	public function __get($name)
	{
		return $this->object->$name;
	}

	public function __call($name, $args)
	{
		return call_user_func_array(array($this->object, $name), $args);
	}
}