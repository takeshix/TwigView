<?php
/**
 * TwigView for CakePHP
 *
 * About Twig
 *  http://www.twig-project.org/
 *
 * @version 0.5
 * @package TwigView
 * @subpackage TwigView.View
 * @author Kjell Bublitz <m3nt0r.de@gmail.com>
 * @link http://github.com/m3nt0r My GitHub
 * @link http://twitter.com/m3nt0r My Twitter
 * @link https://github.com/kozo/Partial
 * @author Graham Weldon (http://grahamweldon.com)
 * @license The MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::uses('Folder', 'Utility');
if (!defined('TWIG_VIEW_CACHE')) {
	define('TWIG_VIEW_CACHE', CakePlugin::path('TwigView') . 'tmp' . DS . 'views');
}

$twigPath = CakePlugin::path('TwigView');

// Load Twig Lib and start auto loader
require_once($twigPath . 'Vendor' . DS . 'Twig' . DS . 'lib' . DS . 'Twig' . DS . 'Autoloader.php');
Twig_Autoloader::register();

// overwrite twig classes (thanks to autoload, no problem)
require_once($twigPath . 'Lib' . DS . 'Twig_Node_Element.php');
require_once($twigPath . 'Lib' . DS . 'Twig_Node_Trans.php');
require_once($twigPath . 'Lib' . DS . 'Twig_TokenParser_Trans.php');

// my custom cake extensions
require_once($twigPath . 'Lib' . DS . 'Twig_Extension_I18n.php');
require_once($twigPath . 'Lib' . DS . 'Twig_Extension_Ago.php');
require_once($twigPath . 'Lib' . DS . 'Twig_Extension_Basic.php');
require_once($twigPath . 'Lib' . DS . 'Twig_Extension_Number.php');

// get twig core extension (overwrite trans block)
require_once($twigPath . 'Lib' . DS . 'CoreExtension.php');
require_once($twigPath . 'Lib' . DS . 'TwigConfig.php');

/**
 * TwigView for CakePHP
 *
 * @version 0.5
 * @author Kjell Bublitz <m3nt0r.de@gmail.com>
 * @link http://github.com/m3nt0r/cakephp-twig-view GitHub
 * @package app.views
 * @subpackage app.views.twig
 */
class TwigView extends View {

/**
 * File extension
 *
 * @var string
 */
	public $ext = '.tpl';

/**
 * Twig Environment Instance
 *
 * @var Twig_Environment
 */
	public $Twig;

/**
 * Collection of paths.
 * These are stripped from $___viewFn.
 *
 * @todo overwrite getFilename()
 * @var array
 */
	public $templatePaths = array();

	public $partialCache = 'partial';
/**
 * Constructor
 * Overridden to provide Twig loading
 *
 * @param Controller $Controller Controller
 */
	public function __construct(Controller $Controller = null) {

		$this->templatePaths = App::path('View');
		$loader = new Twig_Loader_Filesystem($this->templatePaths[0]);
		$this->Twig = new Twig_Environment($loader, array(
			'cache' => TWIG_VIEW_CACHE,
			'charset' => strtolower(Configure::read('App.encoding')),
			'auto_reload' => Configure::read('debug') > 0,
			'autoescape' => TwigConfig::getAutoescape(),
			'debug' => Configure::read('debug') > 0
		));;

		$this->Twig->addExtension(new CoreExtension);
		$this->Twig->addExtension(new Twig_Extension_I18n);
		$this->Twig->addExtension(new Twig_Extension_Ago);
		$this->Twig->addExtension(new Twig_Extension_Basic);
		$this->Twig->addExtension(new Twig_Extension_Number);

		parent::__construct($Controller);

		if (isset($Controller->theme)) {
			$this->theme = $Controller->theme;
		}
		$this->ext = TwigConfig::getExtension();
	}

/**
 * Render the view
 *
 * @param string $_viewFn
 * @param string $_dataForView
 * @return void
 */
	protected function _render($_viewFn, $_dataForView = array()) {
		$isCtpFile = (substr($_viewFn, -3) === 'ctp');

		if (empty($_dataForView)) {
			$_dataForView = $this->viewVars;
		}

		if ($isCtpFile) {
			return parent::_render($_viewFn, $_dataForView);
		}

		ob_start();
		// Setup the helpers from the new Helper Collection
		$helpers = array();
		$loaded_helpers = $this->Helpers->attached();
		foreach($loaded_helpers as $helper) {
			$name = Inflector::variable($helper);
			$helpers[$name] = $this->loadHelper($helper);
		}

		if (!isset($_dataForView['cakeDebug'])) {
			$_dataForView['cakeDebug'] = null;
		}
		$data = array_merge($_dataForView, $helpers);
		$data['_view'] = $this;

		$relativeFn = str_replace($this->templatePaths, '', $_viewFn);
		$template = $this->Twig->loadTemplate($relativeFn);
		echo $template->render($data);
		return ob_get_clean();
	}

/**
 * Render an element
 *
 * @param string $name Element Name
 * @param array $params Parameters
 * @param boolean $callbacks Fire callbacks
 * @return string
 */
	public function element($name, $params = array(), $callbacks = false) {
		// email hack
		// if (substr($name, 0, 5) != 'email') {
		// 	$this->ext = '.ctp'; // not an email, use .ctp
		// }

		$return = parent::element($name, $params, $callbacks);
		//$this->ext = '.tpl';
		return $return;
	}

	function partial($name, $data = array(), $options = array(), $loadHelpers = true) {
		$file = $plugin = $key = null;

		// キャッシュの設定(フォルダがない場合は新規作成)
		$cachePath = TMP . 'cache' . DS . 'partial' . DS;
		$obj = new Folder($cachePath, true, 0777);
		Cache::config($this->partialCache, array('engine'=>'File', 'path' => $cachePath));

		$plugin = $this->plugin;
		if (isset($options['plugin'])) {
			$plugin = Inflector::camelize($options['plugin']);
		}

		if (isset($options['cache'])) {
			$underscored = null;
			if ($plugin) {
				$underscored = Inflector::underscore($plugin);
			}
			$keys = array_merge(array($this->viewPath, $this->action, $underscored, $name), array_keys($options), array_keys($data));
			$caching = array(
				'config' => $this->partialCache,
				'key' => implode('_', $keys)
			);
			if (is_array($options['cache'])) {
				$defaults = array(
					'config' => $this->partialCache,
					'key' => $caching['key']
				);
				$caching = array_merge($defaults, $options['cache']);
			}
			$key = 'partial_' . $caching['key'];
			$contents = Cache::read($key, $caching['config']);
			if ($contents !== false) {
				return $contents;
			}
		}

		// 「_」をつける
		$buf = explode(DS, $name);
		$buf[count($buf)-1] = '_' . $buf[count($buf)-1];
		$name = implode(DS, $buf);

		// ファイルパス取得
		$fullPath = $this->_getPartialFileName($name, $plugin);
		if ($fullPath !== false) {
			if ($loadHelpers === true) {
				$this->loadHelpers();
			}

			$partial = $this->_render($fullPath, array_merge($this->viewVars, $data));

			if (isset($options['cache'])) {
				Cache::write($key, $partial, $caching['config']);
			}
			return $partial;
		}
		$file = $this->viewPath . DS . $name . $this->ext;

		if (Configure::read('debug') > 0) {
			return "Partial Not Found: " . $file;
		}
	}

	/**
	 * ファイル名を取得する
	 *
	 * @access private
	 * @author sakuragawa
	 */
	private function _getPartialFileName($name, $plugin) {
		$paths = App::path('View', $plugin);
		$exts = $this->_getExtensions();

		foreach ($exts as $ext) {
			foreach ($paths as $path) {
				if (file_exists($path . $this->viewPath . DS . $name . $ext)) {
					return $path . $this->viewPath . DS . $name . $ext;
				}
			}
		}
		return false;
	}
}
