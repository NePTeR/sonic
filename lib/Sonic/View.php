<?php
namespace Sonic;

/**
 * View
 *
 * @package Sonic
 * @subpackage View
 * @author Craig Campbell
 */
class View
{
    /**
     * name of current controller
     *
     * @var string
     */
    protected $_active_controller;

    /**
     * name of current action
     *
     * @var string
     */
    protected $_action;

    /**
     * @var string
     */
    protected $_path;

    /**
     * @var string
     */
    protected $_title;

    /**
     * @var string
     */
    protected $_html;

    /**
     * @var bool
     */
    protected $_disabled = false;

    /**
     * @var array
     */
    protected $_js = array();

    /**
     * @var array
     */
    protected $_css = array();

    /**
     * @var array
     */
    protected static $_static_path = '/assets';

    /**
     * constructor
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path($path);
    }

    /**
     * gets a view variable
     *
     * @param string $var
     * @return string
     */
    public function __get($var)
    {
        if (!isset($this->$var)) {
            return null;
        }
        return $this->$var;
    }

    /**
     * escapes a view variable
     *
     * @param string
     * @return string
     */
    public function escape($string)
    {
        return htmlentities($string, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * sets or gets static path to js and css
     *
     * @param string $path
     * @return string
     */
    public static function staticPath($path = null)
    {
        if (!$path) {
            return self::$_static_path;
        }
        self::$_static_path = $path;
        return self::$_static_path;
    }

    /**
     * sets or gets path
     *
     * @param mixed $path
     * @return string
     */
    public function path($path = null)
    {
        if ($path !== null) {
            $this->_path = $path;
        }
        return $this->_path;
    }

    /**
     * sets or gets title
     *
     * @param mixed $title
     * @return string
     */
    public function title($title = null)
    {
        if ($title !== null) {
            $this->_title = Layout::getTitle($title);
        }
        return $this->_title;
    }

    /**
     * appends variables to this view
     *
     * @param array
     * @return void
     */
    public function addVars(array $args)
    {
        foreach ($args as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * are we in turbo mode?
     *
     * @return bool
     */
    public function isTurbo()
    {
        return App::getInstance()->getSetting(App::TURBO);
    }

    /**
     * sets the active controller for this view
     *
     * @param string $name
     * @return void
     */
    public function setActiveController($name)
    {
        $this->_active_controller = $name;
    }

    /**
     * sets action for this view
     *
     * @param string $action
     * @return void
     */
    public function setAction($name)
    {
        $this->_action = $name;
    }

    /**
     * adds javascript file for inclusion
     *
     * @param string
     * @return void
     */
    public function addJs($path)
    {
        $this->_js[] = $this->staticPath() . '/js/' . $path;
    }

    /**
     * adds css file for inclusion
     *
     * @param string
     * @return void
     */
    public function addCss($path)
    {
        $this->_css[] = $this->staticPath() . '/css/' . $path;
    }

    /**
     * gets js files included
     *
     * @return array
     */
    public function getJs()
    {
        return $this->_js;
    }

    /**
     * gets css files included
     *
     * @return array
     */
    public function getCss()
    {
        return $this->_css;
    }

    /**
     * disables this view
     *
     * @return void
     */
    public function disable()
    {
        $this->_disabled = true;
    }

    /**
     * renders this view using the controller and action specified
     *
     * @see App::runController()
     * @param string
     * @param string
     * @param array
     * @return void
     */
    public function render($controller, $action = null, $args = array())
    {
        if ($action === null || is_array($action)) {
            $args = (array) $action;
            $action = $controller;
            $controller = $this->_active_controller;
        }

        // this is pretty unintuitive, but in turbo mode once you have a view
        // set for a controller initially then all the queueing of views ends
        // up going through that view which means unless the html is reset the
        // queueing won't happen for the view that would be rendered here
        if ( $this->isTurbo()) {
            $this->_html = null;
        }

        App::getInstance()->runController($controller, $action, $args);
    }

    /**
     * buffers this view so HTML can be pulled out at a later time
     * currently used for the top most view in a layout to pull out CSS and JS and page title
     *
     * @return void
     */
    public function buffer()
    {
        if ($this->_disabled) {
            return;
        }

        if ($this->isTurbo()) {
            return;
        }

        ob_start();
        $this->output();
        $this->_html = ob_get_contents();
        ob_end_clean();
    }

    /**
     * generates a unique id based on the current view
     *
     * @param string $controller
     * @param string $action
     * @return string
     */
    public static function generateId($controller, $action)
    {
        return 'v' . substr(md5($controller . '::' . $action), 0, 7);
    }

    /**
     * gets an id for the current view
     *
     * @return string
     */
    public function getId()
    {
        return $this->generateId($this->_active_controller, $this->_action);
    }

    /**
     * gets html for this buffered view
     *
     * @return string
     */
    public function getHtml()
    {
        if ($this->isTurbo() && !$this instanceof Layout && !$this->_html) {
            App::getInstance()->queueView($this->_active_controller, $this->_action);
            $this->_html = '<div class="sonic_fragment" id="' . $this->getId() . '"></div>';
        }
        return $this->_html;
    }

    /**
     * outputs the view as json
     *
     * @param mixed $id id of view to output to (this is so exceptions can output into the view that caused them to begin with)
     * @return string
     */
    public function outputAsJson($id = null)
    {
        if (!$id) {
            $id = $this->getId();
        }

        ob_start();
        include $this->_path;
        $html = ob_get_contents();
        ob_end_clean();

        $data = array(
            'id' => $id,
            'content' => $html,
            'title' => $this->title(),
            'css' => $this->_css,
            'js' => $this->_js);

        $output = '<script>SonicTurbo.render(' . json_encode($data) . ');</script>';
        return $output;
    }

    /**
     * outputs this view to the page
     *
     * @param bool $json should we output json for turbo mode?
     * @param mixed $id should we use a specific view for turbo mode
     * @return void
     */
    public function output($json = false, $id = null)
    {
        if ($this->_disabled) {
            return;
        }

        if (!$json && !$this instanceof Layout && $this->getHtml() !== null) {
            echo $this->getHtml();
            return;
        }

        if ($json) {
            echo $this->outputAsJson($id);
            return;
        }

        include $this->_path;
    }
}
