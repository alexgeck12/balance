<?php
namespace App\Core;

class Config
{
	protected static $instance;
	private $config = [];

	private function __clone(){}
	private function __construct(){}

	public static function getInstance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function set($file)
	{
		$this->config = array_replace_recursive($this->config, parse_ini_file($file, true));
	}

	public function get($key = false)
	{
		if ($key) {
            try {
                if (!isset($this->config[$key])) {
                    throw new \Exception("no section $key in config");
                }
            } catch (\Exception $e) {
                error_log("Config error " . $e->getMessage());
            }
			return $this->config[$key];
		} else {
			return $this->config;
		}
	}
}