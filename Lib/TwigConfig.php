<?php

/**
 * @author takeshix
 *
 */
class TwigConfig {
	const KEY_AUTOESCAPE = "TwigView.autoescape";
	const KEY_EXTENSION = "TwigView.ext";

	static public function getExtension() {
		return self::_readConfigure(self::KEY_EXTENSION, ".tpl");
	}

	static public function getAutoescape() {
		return self::_readConfigure(self::KEY_AUTOESCAPE, true);
	}

	static public function setExtension($val) {
		Configure::write(self::KEY_EXTENSION, $val);
	}

	static public function setAutoescape($val) {
		Configure::write(self::KEY_AUTOESCAPE, $val);
	}

	// protected _readConfigure($key, $default_value) {{{
	/**
	 * _readConfigure
	 *
	 * @param string $key
	 * @param mixed $default_value
	 * @access protected
	 * @return mixed
	 */
	static protected function _readConfigure($key, $default_value) {
		$val = Configure::read($key);
		return is_null($val) ? $default_value : $val;
	}
	// }}}
}