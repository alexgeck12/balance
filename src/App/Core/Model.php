<?php
namespace App\Core;

abstract class Model
{
	/**
	 * @property Database $db
	 */

	public function __get($var)
	{
		switch ($var) {
			case 'db':
				$this->db = Database::getInstance();
				return $this->db;
		}
	}
}