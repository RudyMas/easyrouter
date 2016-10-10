<?php
namespace RudyMas\Router;
use Exception;

/**
 * Class Router - This class is used to process clean URL's (http://<website>/arg1/arg2)
 *
 * @author      Rudy Mas <rudy.mas@rudymas.be>
 * @copyright   2016, rudymas.be. (http://www.rudymas.be/)
 * @license     https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version     0.3.1
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
     */
    private $routes = [];

    /**
     * function processURL()
     * This will process the URL and extract the parameters from it.
     */
    public function processURL()
    {
        $requestURI = explode('/', strtolower($_SERVER['REQUEST_URI']));
        $scriptName = explode('/', strtolower($_SERVER['SCRIPT_NAME']));
        $requestURI[0] = strtoupper($_SERVER['REQUEST_METHOD']);
        for ($x = 0; $x < sizeof($scriptName); $x++) {
            if ($requestURI[$x] == $scriptName[$x]) {
                unset($requestURI[$x]);
            }
        }
        $this->parameters = array_values($requestURI);
    }

    /**
     * function processBody()
     * This will process the body of a REST request
     */
    public function processBody()
    {
        $this->body = file_get_contents('php://input');
    }

    /**
     * function addRoute($route, $action)
     * This will add a route to the system
     *
     * @param   string  $method     The method of the request (GET/PUT/POST/...)
     * @param   string  $route      A route for the system (/blog/page/1)
     * @param   string  $action     The action script that has to be used
     * @return  boolean             Returns FALSE if route already exists, TRUE if it is added
     */
    public function addRoute($method, $route, $action = NULL)
    {
        $route = strtoupper($method) . '/' . trim($route, '/');
        if (in_array($route, $this->routes)) {
            return FALSE;
        } else {
            $this->routes[] = array('route' => $route, 'action' => $action);
            return TRUE;
        }
    }

    /**
     * function isRouteSet($route)
     * This will test if a route already exists and returns TRUE is it is set, FALSE if it isn't set
     *
     * @param   string  $method The method of the request (GET/PUT/POST/...)
     * @param   string  $route  The route to be tested
     * @return  boolean         Returns TRUE if it is set, FALSE if it isn't set
     */
    public function isRouteSet($method, $route)
    {
        $route = strtoupper($method) . '/' . trim($route, '/');
        foreach ($this->routes as $checkRoute) {
            if ($route == $checkRoute['route']) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * function execute()
     * This will process the URL and execute the controller and action when the URL is a correct route
     *
     * @throws Exception    Will throw an exception when the route isn't configured (Error Code 404)
     * @return boolean      Returns TRUE if page has been found
     */
    public function execute()
    {
        $this->processURL();
        $this->processBody();
        $variables = [];
        foreach ($this->routes as $value) {
            $testRoute = explode('/', $value['route']);
            if (count($this->parameters) == count($testRoute)) {
                for ($x = 0; $x < count($testRoute); $x++) {
                    if (preg_match("/^{(.+)}$/", $testRoute[$x])) {
                        $key = trim($testRoute[$x], '{}');
                        $variables[$key] = $this->parameters[$x];
                    } elseif ($testRoute[$x] != $this->parameters[$x]) {
                        break 1;
                    }
                    if ($x == count($testRoute) - 1) {
                        $functio2Execute = explode(':', $value['action']);
                        if (count($functio2Execute) == 2) {
                            $action = '\\Controller\\'.$functio2Execute[0].'Controller';
                            $controller = new $action(NULL);
                            $controller->{$functio2Execute[1].'Action'}($variables, $this->body);
                        } else {
                            $action = '\\Controller\\'.$functio2Execute[0].'Controller';
                            new $action($variables, $this->body);
                        }
                        return TRUE;
                    }
                }
            }
        }
        throw new Exception('Page couldn\'t be found!', 404);
    }

    /**
     * Getter for $parameters
     *
     * @return   array   Returns an array of the parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Getter for $body
     *
     * @return  mixed   Returns the body of the request
     */
    public function getBody()
    {
        return $this->body;
    }
}
/** End of File: EasyRouter.php **/