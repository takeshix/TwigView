<?php

App::uses('CakeEmail', 'Network/Email');
App::uses('TwigView', 'TwigView.View');
App::uses('TwigConfig', 'TwigView.Lib');
App::uses('Validation', 'Utility');

/**
 * @author takeshix
 *
 */
class TwigEmail extends CakeEmail {

	/**
	 * Render View Class
	 * @var string
	 */
	protected $_viewRender = 'TwigView.Twig';

	/**
	 * HTML autoescape value
	 * @var boolean|string
	 */
	static $autoEscape = false;

	/* (non-PHPdoc)
	 * @see CakeEmail::__construct()
	 */
	public function __construct($config = null) {
		if (empty($config) && $this->hasConfig("default")) {
			$config = "default";
		}
		parent::__construct($config);
	}

	// protected hasConfig($config = null) {{{
	/**
	 * hasConfig
	 *
	 * @param string $config
	 * @access protected
	 * @return bool
	 */
	protected function hasConfig($config = null) {
		if (is_string($config)) {
			if (!class_exists($this->_configClass) && !config('email')) {
				return false;
			}
			$configs = new $this->_configClass();
			if (!isset($configs->{$config})) {
				return false;
			}
		}
		return true;
	}
	// }}}

	/* (non-PHPdoc)
	 * @see CakeEmail::reset()
	 */
	public function reset() {
		parent::reset();
		$this->_viewRender = "TwigView.Twig";
		return $this;
	}

	/* (non-PHPdoc)
	 * @see CakeEmail::send()
	 */
	public function send($content = null) {
		$config = $this->config();
		if (empty($config)) {
			$this->config("default");
		}
		return parent::send($content);
	}

	/* (non-PHPdoc)
	 * @see CakeEmail::_renderTemplates()
	 */
	protected function _renderTemplates($content) {
		$org = TwigConfig::getAutoescape();

		TwigConfig::setAutoescape(self::$autoEscape);

		$ret = parent::_renderTemplates($content);
		TwigConfig::setAutoescape($org);
		return $ret;
	}

	/* (non-PHPdoc)
	 * @see CakeEmail::_setEmail()
	 */
	protected function _setEmail($varName, $email, $name) {
		if (!is_array($email)) {
			if (!$this->validateEmail($email)) {
				throw new SocketException(__d('cake_dev', 'Invalid email: "%s"', $email));
			}
			if ($name === null) {
				$name = $email;
			}
			$this->{$varName} = array($email => $name);
			return $this;
		}
		$list = array();
		foreach ($email as $key => $value) {
			if (is_int($key)) {
				$key = $value;
			}
			if (!$this->validateEmail($key)) {
				throw new SocketException(__d('cake_dev', 'Invalid email: "%s"', $key));
			}
			$list[$key] = $value;
		}
		$this->{$varName} = $list;
		return $this;
	}

	/**
	 * Validates email address(rfc & japanese feature phone)
	 * @param string $email
	 * @return number
	 */
	protected function validateEmail($email) {
		$pattern = '/^[a-z0-9\._-]{3,30}@(?:[a-z0-9][-a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,4})$/i';

		return preg_match($pattern, $email) || Validation::email($email);
	}
}
