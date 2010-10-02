<?php
/**
 * combined core files to speed up your application (with comments stripped)
 *
 * includes App.php, Request.php, Router.php, Controller.php, View.php, Layout.php, Exception.php
 *
 * @category Sonic
 * @package Core
 * @author Craig Campbell
 * @link http://www.sonicframework.com
 * @license http://www.apache.org/licenses/LICENSE-2.0.html
 * @version 1.0.2 beta
 *
 * last commit: f332930829a39f4104a2cc191c6f73e862937d6c
 * generated: 2010-10-01 20:46:51 EST
 */
namespace Sonic;
class App{const WEB='www';const COMMAND_LINE='cli';protected static $_instance;protected $_request;protected $_delegate;protected $_paths=array();protected $_controllers=array();protected $_queued=array();protected $_layout_processed=false;protected $_output_started=false;protected $_configs=array();protected static $_included=array();protected $_base_path;protected $_environment;const MODE=0;const AUTOLOAD=1;const CONFIG_FILE=2;const DEVS=3;const FAKE_PDO=4;const DISABLE_CACHE=5;const TURBO=6;const TURBO_PLACEHOLDER=7;const DEFAULT_SCHEMA=8;protected $_settings=array(self::MODE=>self::WEB, self::AUTOLOAD=>false, self::CONFIG_FILE=>'ini', self::DEVS=>array('dev','development'), self::FAKE_PDO=>false, self::DISABLE_CACHE=>false, self::TURBO=>false);private function __construct() {} public static function getInstance(){if(self::$_instance===null){self::$_instance=new App();}return self::$_instance;}public function autoloader($class_name){$path=str_replace('\\','/',$class_name).'.php';return $this->includeFile($path);}public static function includeFile($path){$app=self::getInstance();if(isset($app->_included[$path])){return;}include $path;$app->_included[$path]=true;}public function autoload(){spl_autoload_register(array($this,'autoloader'));}public function addSetting($key,$value){$this->_settings[$key]=$value;}public function getSetting($name){if(!isset($this->_settings[$name])){return null;}return $this->_settings[$name];}public static function getConfig($path=null){$app=self::getInstance();$environment=$app->getEnvironment();$cache_key= 'config_'.$path.'_'.$environment;if(isset($app->_configs[$cache_key])){return $app->_configs[$cache_key];}if($path===null){$type=$app->getSetting(self::CONFIG_FILE);$path=$app->getPath('configs').'/app.'.$type;}if(!self::isDev()&&!$app->getSetting(self::DISABLE_CACHE)&&($config=apc_fetch($cache_key))){$app->_configs[$cache_key]=$config;return $config;}$app->includeFile('Sonic/Config.php');$app->includeFile('Sonic/Util.php');$config=new Config($path,$environment,$type);$app->_configs[$cache_key]=$config;if(!$app->getSetting(self::DISABLE_CACHE)){apc_store($cache_key,$config,Util::toSeconds('24 hours'));}return $config;}public static function getMemcache($pool='default'){return Cache\Factory::getMemcache($pool);}public static function isDev(){$app=self::getInstance();return in_array($app->getEnvironment(),$app->getSetting(self::DEVS));}public function getEnvironment(){if($this->_environment!==null){return $this->_environment;}if($environment=getenv('ENVIRONMENT')){$this->_environment=$environment;return $environment;}throw new Exception('ENVIRONMENT variable is not set! check your apache config');}public function getRequest(){if(!$this->_request){$this->_request=new Request();}return $this->_request;}public function getBasePath(){if($this->_base_path){return $this->_base_path;}if($this->getSetting(self::MODE)==self::COMMAND_LINE){$this->_base_path=str_replace('/libs','',get_include_path());return $this->_base_path;}$this->_base_path=str_replace('/public_html','',$this->getRequest()->getServer('DOCUMENT_ROOT'));return $this->_base_path;}public function getPath($dir=null){$cache_key= 'path_'.$dir;if(isset($this->_paths[$cache_key])){return $this->_paths[$cache_key];}$base_path=$this->getBasePath();if($dir!==null){$base_path .='/'.$dir;}$this->_paths[$cache_key]=$base_path;return $this->_paths[$cache_key];}public function disableLayout(){$this->_layout_processed=true;}public function getController($name){if(isset($this->_controllers[$name])){return $this->_controllers[$name];}include $this->getPath('controllers').'/'.$name.'.php';$class_name='\Controllers\\'.$name;$this->_controllers[$name]=new $class_name();$this->_controllers[$name]->name($name);$this->_controllers[$name]->request($this->getRequest());return $this->_controllers[$name];}protected function _runController($controller_name,$action,$args=array(),$json=false,$id=null){$this->getRequest()->addParams($args);$controller=$this->getController($controller_name);$controller->setView($action);$view=$controller->getView();$view->setAction($action);$view->addVars($args);$can_run=$json||!$this->getSetting(self::TURBO);if($this->_delegate){$this->_delegate->actionWasCalled($controller,$action);}if($can_run&&!$controller->hasCompleted($action)){$this->_runAction($controller,$action);}if($this->_processLayout($controller,$view,$args)){return;}if($this->_delegate){$this->_delegate->viewStartedRendering($view,$json);}$view->output($json,$id);if($this->_delegate){$this->_delegate->viewFinishedRendering($view,$json);}} protected function _processLayout(Controller $controller,View $view,$args){if($this->_layout_processed){return false;}if(!$controller->hasLayout()){return false;}if(count($this->_controllers)!=1&&!isset($args['exception'])){return false;}$this->_layout_processed=true;$layout=$controller->getLayout();$layout->topView($view);if($this->_delegate){$this->_delegate->layoutStartedRendering($layout);}$layout->output();if($this->_delegate){$this->_delegate->layoutFinishedRendering($layout);}return true;}protected function _runAction(Controller $controller,$action){if($this->_delegate){$this->_delegate->actionStartedRunning($controller,$action);}$controller->$action();$controller->actionComplete($action);if($this->_delegate){$this->_delegate->actionFinishedRunning($controller,$action);}} public function runController($controller_name,$action,$args=array(),$json=false){try{$this->_runController($controller_name,$action,$args,$json);}catch (\Exception $e){$this->_handleException($e,$controller_name,$action);return;}} public function outputStarted($started=null){if($started){$this->_output_started=true;}return $this->_output_started;}public function queueView($controller,$name){$this->_queued[]=array($controller,$name);}public function processViewQueue(){if(!$this->getSetting(self::TURBO)){return;}while (count($this->_queued)){foreach ($this->_queued as $key=>$queue){$this->runController($queue[0],$queue[1],array(),true);unset($this->_queued[$key]);}}}protected function _robotnikWins(){if($this->getRequest()->isAjax()||isset($_COOKIE['noturbo'])||isset($_COOKIE['bot'])){return true;}if(isset($_GET['noturbo'])){setcookie('noturbo',true,time() + 86400);return true;}if(strpos($_SERVER['HTTP_USER_AGENT'],'Googlebot')!==false){setcookie('bot',true,time() + 86400);return true;}return false;}protected function _handleException(\Exception $e,$controller=null,$action=null){if($this->_delegate){$this->_delegate->appCaughtException($e,$controller,$action);}if(!$e instanceof \Sonic\Exception){$e=new \Sonic\Exception($e->getMessage(),\Sonic\Exception::INTERNAL_SERVER_ERROR,$e);}if(!$this->outputStarted()){header($e->getHttpCode());}$json=false;$id=null;if($this->getSetting(self::TURBO)&&$this->_layout_processed){$json=true;$id=View::generateId($controller,$action);}$completed=false;if($controller!==null&&$action!==null){$req=$this->getRequest();$first_controller=$req->getControllerName();$first_action=$req->getAction();$completed=$this->getController($first_controller)->hasCompleted($first_action);}$args=array( 'exception'=>$e, 'top_level_exception'=>!$completed, 'from_controller'=>$controller, 'from_action'=>$action );return $this->_runController('main','error',$args,$json,$id);}public function setDelegate($delegate){$this->includeFile('Sonic/App/Delegate.php');$this->autoloader($delegate);$this->_delegate=new $delegate;if(!$this->_delegate instanceof \Sonic\App\Delegate){throw new \Exception('app delegate ofclass '.get_class($delegate).' must be instance of \Sonic\App\Delegate');}$this->_delegate->setApp($this);return $this;}public function start($mode=self::WEB){if($this->_delegate){$this->_delegate->appStartedLoading($mode);}$this->addSetting(self::MODE,$mode);$this->_included['Sonic/Exception.php']=true;$this->_included['Sonic/Request.php']=true;$this->_included['Sonic/Router.php']=true;$this->_included['Sonic/Controller.php']=true;$this->_included['Sonic/View.php']=true;$this->_included['Sonic/Layout.php']=true;if($this->getSetting(self::AUTOLOAD)){$this->autoload();}if($mode!=self::WEB){return;}if($this->getSetting(self::TURBO)&&$this->_robotnikWins()){$this->addSetting(self::TURBO,false);}if($this->_delegate){$this->_delegate->appFinishedLoading();}try{$controller=$this->getRequest()->getControllerName();$action=$this->getRequest()->getAction();}catch (\Exception $e){return $this->_handleException($e);}if($this->_delegate){$this->_delegate->appStartedRunning();}$this->runController($controller,$action);if($this->_delegate){$this->_delegate->appFinishedRunning();}}}use \Sonic\Exception;class Request{const POST='POST';const GET='GET';const PARAM='PARAM';protected $_caches=array();protected $_params=array();protected $_router;protected $_controller;protected $_controller_name;protected $_action;protected $_subdomain;public function getBaseUri(){if(isset($this->_caches['base_uri'])){return $this->_caches['base_uri'];}if(($uri=$this->getServer('REDIRECT_URL'))!==null){$this->_caches['base_uri']=$uri=='/' ? $uri : rtrim($uri,'/');return $this->_caches['base_uri'];}$bits=explode('?',$this->getServer('REQUEST_URI'));$this->_caches['base_uri']=$bits[0]=='/' ? $bits[0] : rtrim($bits[0],'/');return $this->_caches['base_uri'];}public function getServer($name){if(!isset($_SERVER[$name])){return null;}return $_SERVER[$name];}public function setSubdomain($subdomain){$this->_subdomain=$subdomain;}public function getRouter(){if($this->_router===null){$this->_router=new Router($this,$this->_subdomain);}return $this->_router;}public function getControllerName(){if($this->_controller_name!==null){return $this->_controller_name;}$this->_controller_name=$this->getRouter()->getController();if(!$this->_controller_name){throw new Exception('page not found at '.$this->getBaseUri(),Exception::NOT_FOUND);}return $this->_controller_name;}public function getAction(){if($this->_action!==null){return $this->_action;}$this->_action=$this->getRouter()->getAction();if(!$this->_action){throw new Exception('page not found at '.$this->getBaseUri(),Exception::NOT_FOUND);}return $this->_action;}public function addParams(array $params){foreach ($params as $key=>$value){$this->addParam($key,$value);}} public function addParam($key,$value){$this->_params[$key]=$value;return $this;}public function getParam($name,$type=self::PARAM){switch ($type){case self::POST: if(isset($_POST[$name])){return $_POST[$name];}break;case self::GET: if(isset($_GET[$name])){return $_GET[$name];}break;default: if(isset($this->_params[$name])){return $this->_params[$name];}break;}return null;}public function getParams($type=self::PARAM){if($type===self::POST){return $_POST;}if($type===self::GET){return $_GET;}return $this->_params;}public function getPost($name=null){if($name===null){return $this->getParams(self::POST);}return $this->getParam($name,self::POST);}public function isPost(){return $this->getServer('REQUEST_METHOD')=='POST';}public function isAjax(){return $this->getServer('HTTP_X_REQUESTED_WITH')=='XMLHttpRequest';}} class Router{protected $_routes;protected $_request;protected $_match;protected $_subdomain;public function __construct(Request $request,$subdomain=null){$this->_request=$request;$this->_subdomain=$subdomain;}public function getRoutes(){if($this->_routes===null){$filename='routes.'.(!$this->_subdomain ? 'php' : $this->_subdomain.'.php');$path=App::getInstance()->getPath('configs').'/'.$filename;include $path;$this->_routes=$routes;}return $this->_routes;}protected function _setMatch($match){if($match===null){$this->_match=array(null,null);return $this;}$this->_match=$match;return $this;}protected function _getMatch(){if($this->_match!==null){return $this->_match;}$base_uri=$this->_request->getBaseUri();if($base_uri==='/'&&!$this->_subdomain){$this->_match=array('main','index');return $this->_match;}$routes=$this->getRoutes();if(isset($routes[$base_uri])){$this->_setMatch($routes[$base_uri]);return $this->_match;}$route_keys=array_keys($routes);$len=count($route_keys);$base_bits=explode('/',$base_uri);$match=false;for ($i=0;$i < $len;++$i){if($this->_matches(explode('/',$route_keys[$i]),$base_bits)){$match=true;break;}} if($match){$this->_setMatch($routes[$route_keys[$i]]);return $this->_match;}$this->_setMatch(null);return $this->_match;}protected function _matches($route_bits,$url_bits){$route_bit_count=count($route_bits);if($route_bit_count!==count($url_bits)){return false;}$match=true;$params=array();for ($i=1;$i < $route_bit_count;++$i){if($route_bits[$i][0]===':'){$param=substr($route_bits[$i],1);$params[$param]=$url_bits[$i];continue;}if($route_bits[$i]!=$url_bits[$i]){return false;}} $this->_request->addParams($params);return true;}public function getController(){$match=$this->_getMatch();return $match[0];}public function getAction(){$match=$this->_getMatch();return $match[1];}} class Controller{protected $_name;protected $_view_name;protected $_view;protected $_layout;protected $_layout_name=Layout::MAIN;protected $_request;protected $_actions_completed=array();protected $_input_filter;public function __get($var){if($var==='view'){return $this->getView();}if($var==='layout'){return $this->getLayout();}throw new Exception('only views and layouts are magic');}final public function name($name=null){if($name!==null){$this->_name=$name;}return $this->_name;}final public function setView($name){if($this->_view_name!==$name){$this->_view_name=$name;$this->_view===null ?: $this->getView()->path($this->getViewPath());}$this->_layout_name=Layout::MAIN;return $this;}public function request(Request $request=null){if($request!==null){$this->_request=$request;}return $this->_request;}public function actionComplete($action){$this->_actions_completed[$action]=true;return $this;}public function getActionsCompleted(){return array_keys($this->_actions_completed);}public function hasCompleted($action){return isset($this->_actions_completed[$action]);}public function disableLayout(){$this->_layout_name=null;return $this;}public function disableView(){$this->getView()->disable();}public function hasLayout(){return $this->_layout_name!==null;}public function setLayout($name){$this->_layout_name=$name;}public function getLayout(){if($this->_layout!==null){return $this->_layout;}$layout_dir=App::getInstance()->getPath('views/layouts');$layout=new Layout($layout_dir.'/'.$this->_layout_name.'.phtml');$this->_layout=$layout;return $this->_layout;}final public function getViewPath(){return App::getInstance()->getPath('views').'/'.$this->_name.'/'.$this->_view_name.'.phtml';}public function getView(){if($this->_view!==null){return $this->_view;}$this->_view=new View($this->getViewPath());$this->_view->setAction($this->_view_name);$this->_view->setActiveController($this->_name);return $this->_view;}protected function _redirect($location){if(App::getInstance()->getSetting(App::TURBO)){$this->getView()->addTurboData('redirect',$location);return;}header('location: '.$location);exit;}protected function _json(array $data){header('Content-Type: application/json');echo json_encode($data);exit();}public function filter($name){if($this->_input_filter!==null){return $this->_input_filter->filter($name);}App::getInstance()->includeFile('Sonic/InputFilter.php');$this->_input_filter=new InputFilter($this->request());return $this->_input_filter->filter($name);}public function __toString(){return get_class($this);}} class View{protected $_active_controller;protected $_action;protected $_path;protected $_title;protected $_html;protected $_disabled=false;protected $_js=array();protected $_css=array();protected $_turbo_data=array();protected $_turbo_placeholder='';protected static $_static_path='/assets';public function __construct($path){$this->path($path);}public function __get($var){if(!isset($this->$var)){return null;}return $this->$var;}public function escape($string){return htmlentities($string,ENT_QUOTES,'UTF-8',false);}public static function staticPath($path=null){if(!$path){return self::$_static_path;}self::$_static_path=$path;return self::$_static_path;}public function path($path=null){if($path!==null){$this->_path=$path;}return $this->_path;}public function title($title=null){if($title!==null){$this->_title=Layout::getTitle($title);}return $this->_title;}public function addVars(array $args){foreach ($args as $key=>$value){$this->$key=$value;}} public function isTurbo(){return App::getInstance()->getSetting(App::TURBO);}public function setActiveController($name){$this->_active_controller=$name;}public function setAction($name){$this->_action=$name;}public function addJs($path){if($this->_isAbsolute($path)){$this->_js[]=$path;return;}$this->_js[]=$this->staticPath().'/js/'.$path;}public function addCss($path){if($this->_isAbsolute($path)){$this->_css[]=$path;return;}$this->_css[]=$this->staticPath().'/css/'.$path;}protected function _isAbsolute($path){if(!isset($path[7])){return false;}return $path[0].$path[1].$path[2].$path[3].$path[4]=='http:';}public function getJs(){return $this->_js;}public function getCss(){return $this->_css;}public function disable(){$this->_disabled=true;}public function render($controller,$action=null,$args=array()){if($action===null||is_array($action)){$args=(array) $action;$action=$controller;$controller=$this->_active_controller;}if( $this->isTurbo()){$this->_html=null;}App::getInstance()->runController($controller,$action,$args);}public function buffer(){if($this->_disabled){return;}if($this->isTurbo()){return;}ob_start();$this->output();$this->_html=ob_get_contents();ob_end_clean();}public function turboPlaceholder($html=null){if($html){$this->_turbo_placeholder=$html;}return $this->_turbo_placeholder;}public static function generateId($controller,$action){return 'v'.substr(md5($controller.'::'.$action),0,7);}public function getId(){return $this->generateId($this->_active_controller,$this->_action);}public function getHtml(){if($this->isTurbo()&&!$this instanceof Layout&&!$this->_html){App::getInstance()->queueView($this->_active_controller,$this->_action);$placeholder=$this->_turbo_placeholder ?: App::getInstance()->getSetting(App::TURBO_PLACEHOLDER);$this->_html='<div class="sonic_fragment" id="'.$this->getId().'">'.$placeholder.'</div>';}return $this->_html;}public function addTurboData($key,$value){$this->_turbo_data[$key]=$value;}public function outputAsJson($id=null){if(!$id){$id=$this->getId();}ob_start();include $this->_path;$html=ob_get_contents();ob_end_clean();$data=array( 'id'=>$id, 'content'=>$html, 'title'=>$this->title(), 'css'=>$this->_css, 'js'=>$this->_js) + $this->_turbo_data;$output='<script>SonicTurbo.render('.json_encode($data).');</script>';return $output;}public function output($json=false,$id=null){if($this->_disabled){return;}App::getInstance()->outputStarted(true);if(!$json&&!$this instanceof Layout&&$this->getHtml()!==null){echo $this->getHtml();return;}if($json){echo $this->outputAsJson($id);return;}include $this->_path;}public function __toString(){return (string) $this->_action;}} class Layout extends View{const MAIN='main';protected $_top_view;protected static $_title_pattern;public function output(){if($this->topView()!==null){$this->topView()->buffer();}parent::output();}public function topView(View $view=null){if($this->_top_view===null&&$view!==null){$this->_top_view=$view;}return $this->_top_view;}public function setTitlePattern($pattern){self::$_title_pattern=$pattern;return $this->getTitle($this->topView()->title());}public function getTitle($string){if(!self::$_title_pattern){return $string;}return str_replace('${title}',$string,self::$_title_pattern);}public function noTurboUrl(){$uri=$_SERVER['REQUEST_URI'];if(strpos($uri,'?')!==false){return $uri.'&amp;noturbo=1';}return $uri.'?noturbo=1';}public function turbo(){while (ob_get_level()){ob_end_flush();}flush();return App::getInstance()->processViewQueue();}} class Exception extends \Exception{const INTERNAL_SERVER_ERROR=0;const NOT_FOUND=1;const FORBIDDEN=2;const UNAUTHORIZED=3;public function getDisplayMessage(){switch ($this->code){case self::NOT_FOUND: return 'The page you were looking for could not be found.';break;case self::FORBIDDEN: return 'You do not have permission to view this page.';break;case self::UNAUTHORIZED: return 'This page requires login.';break;default: return 'Some kind of error occured.';break;}} public function getHttpCode(){switch ($this->code){case self::NOT_FOUND: return 'HTTP/1.1 404 Not Found';break;case self::FORBIDDEN: return 'HTTP/1.1 403 Forbidden';break;case self::UNAUTHORIZED: return 'HTTP/1.1 401 Unauthorized';break;default: return 'HTTP/1.1 500 Internal Server Error';break;}}}
