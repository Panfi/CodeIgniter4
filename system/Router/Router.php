<?php namespace CodeIgniter\Router;

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014 - 2016, British Columbia Institute of Technology
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	CodeIgniter Dev Team
 * @copyright	Copyright (c) 2014 - 2016, British Columbia Institute of Technology (http://bcit.ca/)
 * @license	http://opensource.org/licenses/MIT	MIT License
 * @link	http://codeigniter.com
 * @since	Version 3.0.0
 * @filesource
 */

use CodeIgniter\PageNotFoundException;

/**
 * Routing exception
 */
class RedirectException extends \Exception {}

/**
 * Request router.
 */
class Router implements RouterInterface
{
	/**
	 * A RouteCollection instance.
	 *
	 * @var RouteCollectionInterface
	 */
	protected $collection;

	/**
	 * Sub-directory that contains the requested controller class.
	 * Primarily used by 'autoRoute'.
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * The name of the controller class.
	 *
	 * @var string
	 */
	protected $controller;

	/**
	 * The name of the method to use.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * An array of binds that were collected
	 * so they can be sent to closure routes.
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * The name of the front controller.
	 *
	 * @var string
	 */
	protected $indexPage = 'index.php';

	/**
	 * Whether dashes in URI's should be converted
	 * to underscores when determining method names.
	 *
	 * @var bool
	 */
	protected $translateURIDashes = false;

	/**
	 * The route that was matched for this request.
	 * @var array|null
	 */
	protected $matchedRoute = null;

	//--------------------------------------------------------------------

	/**
	 * Stores a reference to the RouteCollection object.
	 *
	 * @param RouteCollectionInterface $routes
	 */
	public function __construct(RouteCollectionInterface $routes)
	{
		$this->collection = $routes;

		$this->controller = $this->collection->getDefaultController();
		$this->method     = $this->collection->getDefaultMethod();
	}

	//--------------------------------------------------------------------

	/**
	 * Scans the URI and attempts to match the current URI to the
	 * one of the defined routes in the RouteCollection.
	 *
	 * This is the main entry point when using the Router.
	 *
	 * @param null $uri
	 *
	 * @return mixed
	 */
	public function handle(string $uri = null)
	{
		// If we cannot find a URI to match against, then
		// everything runs off of it's default settings.
		if (empty($uri))
		{
			return strpos($this->controller, '\\') === false
				? $this->collection->getDefaultNamespace().$this->controller
				: $this->controller;
		}

		if ($this->checkRoutes($uri))
		{
			return $this->controller;
		}

		// Still here? Then we can try to match the URI against
		// Controllers/directories, but the application may not
		// want this, like in the case of API's.
		if ( ! $this->collection->shouldAutoRoute())
		{
			throw new PageNotFoundException("Can't find a route for '{$uri}'.");
		}

		$this->autoRoute($uri);

		return $this->controller;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the name of the matched controller.
	 *
	 * @return mixed
	 */
	public function controllerName()
	{
		return $this->controller;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the name of the method to run in the
	 * chosen container.
	 *
	 * @return mixed
	 */
	public function methodName(): string
	{
		return $this->method;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the 404 Override settings from the Collection.
	 * If the override is a string, will split to controller/index array.
	 */
	public function get404Override()
	{
		$route = $this->collection->get404Override();

		if (is_string($route))
		{
			$routeArray = explode('::', $route);

			return [
				$routeArray[0],             // Controller
				$routeArray[1] ?? 'index'   // Method
			];
		}

		if (is_callable($route))
		{
			return $route;
		}

		return null;
	}

	//--------------------------------------------------------------------


	/**
	 * Returns the binds that have been matched and collected
	 * during the parsing process as an array, ready to send to
	 * call_user_func_array().
	 *
	 * @return mixed
	 */
	public function params(): array
	{
		return $this->params;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the name of the sub-directory the controller is in,
	 * if any. Relative to APPPATH.'Controllers'.
	 *
	 * Only used when auto-routing is turned on.
	 *
	 * @return string
	 */
	public function directory(): string
	{
	    return ! empty($this->directory) ? $this->directory : '';
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the routing information that was matched for this
	 * request, if a route was defined.
	 *
	 * @return array|null
	 */
	public function getMatchedRoute()
	{
	    return $this->matchedRoute;
	}

	//--------------------------------------------------------------------



	/**
	 * Sets the value that should be used to match the index.php file. Defaults
	 * to index.php but this allows you to modify it in case your are using
	 * something like mod_rewrite to remove the page. This allows you to set
	 * it a blank.
	 *
	 * @param $page
	 *
	 * @return mixed
	 */
	public function setIndexPage($page): self
	{
		$this->indexPage = $page;

		return $this;
	}

	//--------------------------------------------------------------------

	/**
	 * Tells the system whether we should translate URI dashes or not
	 * in the URI from a dash to an underscore.
	 *
	 * @param bool|false $val
	 *
	 * @return $this
	 */
	public function setTranslateURIDashes($val = false): self
	{
		$this->translateURIDashes = (bool)$val;

		return $this;
	}

	//--------------------------------------------------------------------

	/**
	 * Compares the uri string against the routes that the
	 * RouteCollection class defined for us, attempting to find a match.
	 * This method will modify $this->controller, etal as needed.
	 *
	 * @param string $uri The URI path to compare against the routes
	 *
	 * @return bool Whether the route was matched or not.
	 */
	protected function checkRoutes(string $uri): bool
	{
		$routes = $this->collection->getRoutes();

		// Don't waste any time
		if (empty($routes))
		{
			return false;
		}

		// Loop through the route array looking for wildcards
		foreach ($routes as $key => $val)
		{
			// Does the RegEx match?
			if (preg_match('#^'.$key.'$#', $uri, $matches))
			{
				// Are we using Closures? If so, then we need
				// to collect the params into an array
				// so it can be passed to the controller method later.
				if ( ! is_string($val) && is_callable($val))
				{
					$this->controller = $val;

					// Remove the original string from the matches array
					array_shift($matches);

					$this->params = $matches;

					$this->matchedRoute = [$key, $val];

					return true;
				}
				// Are we using the default method for back-references?
				elseif (strpos($val, '$') !== false && strpos($key, '(') !== false)
				{
					$val = preg_replace('#^'.$key.'$#', $val, $uri);
				}

				// Is this route supposed to redirect to another?
				if ($this->collection->isRedirect($key))
				{
					throw new RedirectException($val, $this->collection->getRedirectCode($key));
				}

				$this->setRequest(explode('/', $val));

				$this->matchedRoute = [$key, $val];

				return true;
			}
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Attempts to match a URI path against Controllers and directories
	 * found in APPPATH/Controllers, to find a matching route.
	 *
	 * @param string $uri
	 */
	public function autoRoute(string $uri)
	{
		$segments = explode('/', $uri);

		$segments = $this->validateRequest($segments);

		// If we don't have any segments left - try the default controller;
		// WARNING: Directories get shifted out of tge segments array.
		if (empty($segments))
		{
			$this->setDefaultController();
		}
		// If not empty, then the first segment should be the controller
		else
		{
			$this->controller = ucfirst(array_shift($segments));
		}

		// Use the method name if it exists.
		// If it doesn't, no biggie - the default method name
		// has already been set.
		if (! empty($segments))
		{
			$this->method = array_shift($segments);
		}

		if (! empty($segments))
		{
			$this->params = $segments;
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Attempts to validate the URI request and determine the controller path.
	 *
	 * @param array $segments URI segments
	 *
	 * @return array URI segments
	 */
	protected function validateRequest(array $segments)
	{
		$c                  = count($segments);
		$directory_override = isset($this->directory);

		// Loop through our segments and return as soon as a controller
		// is found or when such a directory doesn't exist
		while ($c-- > 0)
		{
			$test = $this->directory.ucfirst($this->translateURIDashes === true
					? str_replace('-', '_', $segments[0])
					: $segments[0]
				);

			if ( ! file_exists(APPPATH.'Controllers/'.$test.'.php')
			     && $directory_override === false
			     && is_dir(APPPATH.'Controllers/'.$this->directory.$segments[0])
			)
			{
				$this->setDirectory(array_shift($segments), true);
				continue;
			}

			return $segments;
		}

		// This means that all segments were actually directories
		return $segments;
	}

	//--------------------------------------------------------------------

	/**
	 * Sets the sub-directory that the controller is in.
	 *
	 * @param string|null $dir
	 * @param bool|false  $append
	 */
	protected function setDirectory(string $dir = null, $append = false)
	{
		if ($append !== TRUE || empty($this->directory))
		{
			$this->directory = str_replace('.', '', trim($dir, '/')).'/';
		}
		else
		{
			$this->directory .= str_replace('.', '', trim($dir, '/')).'/';
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Set request route
	 *
	 * Takes an array of URI segments as input and sets the class/method
	 * to be called.
	 *
	 * @param    array $segments URI segments
	 */
	protected function setRequest(array $segments = [])
	{
		// If we don't have any segments - try the default controller;
		if (empty($segments))
		{
			$this->setDefaultController();

			return;
		}

		if ($this->translateURIDashes === true)
		{
			$segments[0] = str_replace('-', '_', $segments[0]);
			if (isset($segments[1]))
			{
				$segments[1] = str_replace('-', '_', $segments[1]);
			}
		}

		list($controller, $method) = array_pad(explode('::', $segments[0]), 2, null);

		$this->controller = $controller;

		// $this->method already contains the default method name,
		// so don't overwrite it with emptiness.
		if ( ! empty($method))
		{
			$this->method = $method;
		}

		array_shift($segments);

		$this->params = $segments;
	}

	//--------------------------------------------------------------------

	/**
	 * Sets the default controller based on the info set in the RouteCollection.
	 */
	protected function setDefaultController()
	{
		if (empty($this->controller))
		{
			throw new \RuntimeException('Unable to determine what should be displayed. A default route has not been specified in the routing file.');
		}

		// Is the method being specified?
		if (sscanf($this->controller, '%[^/]/%s', $class, $this->method) !== 2)
		{
			$this->method = 'index';
		}

		if (! file_exists(APPPATH.'Controllers/'.$this->directory.ucfirst($class).'.php'))
		{
			return;
		}

		$this->controller = ucfirst($class);

		log_message('info', 'Used the default controller.');
	}

	//--------------------------------------------------------------------
}
