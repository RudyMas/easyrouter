<?php

namespace RudyMas\Router;

use Exception;
use Mobile_Detect;
use RudyMas\PDOExt\DBconnect;

/**
 * Class EasyRouter (PHP version 7.1)
 *
 * This class can be used to process clean URLs (http://<website>/arg1/arg2)
 * and process it according to the configured routes.
 *
 * @author      Rudy Mas <rudy.mas@rmsoft.be>
 * @copyright   2016-2017, rmsoft.be. (http://www.rmsoft.be/)
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version     0.8.2
 * @package     RudyMas\Router
 */
class EasyRouter
{
    /**
     * @var array $parameters
     * This contains the URL stripped down to an array of parameters
     * Example: http://www.test.be/user/5
     * Becomes:
     * Array
     * {
     *  [0] => GET
     *  [1] => user
     *  [2] => 5
     * }
     */
    private $parameters = [];

    /**
     * @var string $body
     * This contains the body of the request
     */
    private $body;

    /**
     * @var array $routes ;
     * This contains all the routes of the website
     * $routes[n]['route'] = the route to check against
     * $routes[n]['action'] = the controller to load
     * $routes[n]['args'] = array of argument(s) which you pass to the controller
     * $routes[n]['repositories'] = array of repositories which you pass to the action method
     * $routes[n]['device'] = when mobile detection is active, this will decide how website calls will be handled
     *                          auto = detects device an redirects according parameters set (DEFAULT)
     *                          mobile = always redirect to mobile app
     *                          web = always redirect to website (PHP)
     *                          api = API will aways be handled by the PHP API
     */
    private $routes = [];

    /**
     * @var string $default
     * The default route to be used
     */
    private $default = '/';

    /**
     * @var string
     * The default path to the mobile app
     * Default 'http://yourwebsite.com/m' (Also depending on the BASE_URL)
     */
    private $defaultMobileApp = '/m';

    /**
     * @var string
     * Is mobile detection active or not
     */
    private $mobileDetection = false;

    /**
     * @var string $db
     * Needed for injecting the database connection into the repository
     */
    private $db;

    /**
     * @var Mobile_Detect
     */
    private $detect;

    /**
     * EasyRouter constructor.
     * @param DBconnect|null $db
     */
    public function __construct(DBconnect $db = null)
    {
        $this->db = $db;
        $this->detect = new Mobile_Detect();
    }

    /**
     * function addRoute($route, $action)
     * This will add a route to the system
     *
     * @param string $method The method of the request (GET/PUT/POST/...)
     * @param string $route A route for the system (/blog/page/1)
     * @param string $action The action script that has to be used
     * @param array $args The arguments to pass to the controller
     * @param array $repositories The repositories to pass to the action method
     * @param string $device Route is for which device
     *                  auto = auto detect and redirect if needed
     *                  web / api = always use the PHP version
     *                  mobile = always redirect to the mobile app (forward URI)
     * @return bool Returns FALSE if route already exists, TRUE if it is added
     */
    public function addRoute(string $method,
                             string $route,
                             string $action,
                             array $args = [],
                             array $repositories = [],
                             string $device = 'auto'): bool
    {
        $route = strtoupper($method) . rtrim($route, '/');
        if ($this->isRouteSet($route)) {
            return FALSE;
        } else {
            $this->routes[] = array('route' => $route, 'action' => $action, 'args' => $args,
                'repositories' => $repositories, 'device' => $device);
            return TRUE;
        }
    }

    /**
     * @param string $page The page to redirect to
     */
    public function setDefault(string $page): void
    {
        $this->default = $page;
    }

    /**
     * @param string $linkMobileApp
     */
    public function setDefaultMobileApp(string $linkMobileApp): void
    {
        $this->defaultMobileApp = $linkMobileApp;
    }

    /**
     * @param bool $status
     */
    public function setMobileDetection(bool $status): void
    {
        $this->mobileDetection = strtolower($status);
    }

    /**
     * function execute()
     * This will process the URL and execute the controller and action when the URL is a correct route
     *
     * @throws Exception Will throw an exception when the route isn't configured (Error Code 404)
     * @return boolean Returns TRUE if page has been found
     */
    public function execute(): bool
    {
        $this->processURL();
        $this->processBody();
        $variables = [];
        foreach ($this->routes as $value) {
            $testRoute = explode('/', $value['route']);
            if (!(count($this->parameters) == count($testRoute))) {
                continue;
            }
            for ($x = 0; $x < count($testRoute); $x++) {
                if ($this->isItAVariable($testRoute[$x])) {
                    $key = trim($testRoute[$x], '{}');
                    $variables[$key] = str_replace('__', '/', $this->parameters[$x]);
                } elseif (strtolower($testRoute[$x]) != strtolower($this->parameters[$x])) {
                    break 1;
                }
                if ($x == count($testRoute) - 1) {
                    $this->processMobile($value, $this->parameters);
                    $function2Execute = explode(':', $value['action']);
                    if (count($function2Execute) == 2) {
                        $action = '\\Controller\\' . $function2Execute[0] . 'Controller';
                        $controller = new $action($value['args']);
                        $arguments = [];
                        if (!empty($value['repositories'])) {
                            foreach ($value['repositories'] as $repositoryToLoad) {
                                $repository = '\\Repository\\' . $repositoryToLoad . 'Repository';
                                $arguments[] = new $repository($this->db, null);
                            }
                        }
                        $arguments[] = $variables;
                        $arguments[] = $this->body;
                        call_user_func_array([$controller, $function2Execute[1] . 'Action'], $arguments);
                    } else {
                        $action = '\\Controller\\' . $function2Execute[0] . 'Controller';
                        new $action($value['args'], $variables, $this->body);
                    }
                    return TRUE;
                }
            }
        }
        header('Location: ' . $this->default);
        exit;
    }

    /**
     * function processURL()
     * This will process the URL and extract the parameters from it.
     */
    private function processURL(): void
    {
        $defaultPath = '';
        $basePath = explode('?', urldecode($_SERVER['REQUEST_URI']));
        $requestURI = explode('/', rtrim($basePath[0], '/'));
        $requestURI[0] = strtoupper($_SERVER['REQUEST_METHOD']);
        $scriptName = explode('/', $_SERVER['SCRIPT_NAME']);
        $sizeofRequestURI = sizeof($requestURI);
        $sizeofScriptName = sizeof($scriptName);
        for ($x = 0; $x < $sizeofRequestURI && $x < $sizeofScriptName; $x++) {
            if (strtolower($requestURI[$x]) == strtolower($scriptName[$x])) {
                $defaultPath .= '/' . $requestURI[$x];
                unset($requestURI[$x]);
            }
        }
        $this->default = $defaultPath . $this->default;
        if (!$this->isFullUrl($this->defaultMobileApp)) {
            $this->defaultMobileApp = $defaultPath . $this->defaultMobileApp;
        }
        $this->parameters = array_values($requestURI);
    }

    /**
     * function processBody()
     * This will process the body of a REST request
     */
    private function processBody(): void
    {
        $this->body = file_get_contents('php://input');
    }

    /**
     * function processMobile()
     * This will check if the user is using a mobile device and if needed, redirect him to the mobile app
     *
     * @param array $value
     * @param array $parameters
     */
    private function processMobile(array $value, array $parameters): void
    {
        switch ($value['device']) {
            case 'mobile':
                $path = '';
                for ($x = 1; $x < count($parameters); $x++) {
                    $path .= '/' . $parameters[$x];
                }
                header('Location: ' . $this->defaultMobileApp . $path);
                break;
            case 'auto':
                if ($this->detect->isMobile()) {
                    header('Location: ' . $this->defaultMobileApp);
                }
                break;
            default:
        }
    }

    /**
     * function isRouteSet($route)
     * This will test if a route already exists and returns TRUE if it is set, FALSE if it isn't set
     *
     * @param string $newRoute The new route to be tested
     * @return bool Returns TRUE if it is set, FALSE if it isn't set
     */
    private function isRouteSet(string $newRoute): bool
    {
        return in_array($newRoute, $this->routes);
    }

    /**
     * function isItAVariable($input)
     * Checks if this part of the route is a variable
     *
     * @param string $input Part of the route to be tested
     * @return bool Return TRUE is a variable, FALSE if not
     */
    private function isItAVariable(string $input): bool
    {
        return preg_match("/^{(.+)}$/", $input);
    }

    /**
     * function isFullUrl($input)
     * Checks if it is a full URL or not
     *
     * @param string $input
     * @return bool
     */
    private function isFullUrl(string $input): bool
    {
        return preg_match("/^http[s]?:\/\//", $input);
    }

    /**
     * Getter for $parameters
     *
     * @return array Returns an array of the parameters
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Getter for $body
     *
     * @return mixed Returns the body of the request
     */
    public function getBody()
    {
        return $this->body;
    }
}

/** End of File: EasyRouter.php **/