<?php namespace KodiCMS\CMS\Loader;

use Cache;
use Carbon\Carbon;
use CMS;
use Illuminate\Support\Facades\App;
use KodiCMS\CMS\Contracts\ModuleContainerInterface;
use KodiCMS\CMS\Helpers\File;
use Illuminate\Routing\Router;

class ModuleContainer implements ModuleContainerInterface
{
	/**
	 * @var string
	 */
	protected $_path;

	/**
	 * @var string
	 */
	protected $_name;

	/**
	 * @var bool
	 */
	protected $_isRegistered = false;

	/**
	 * @var bool
	 */
	protected $_isBooted = false;

	/**
	 * @var string
	 */
	protected $_namespace = 'KodiCMS';

	/**
	 * This namespace is applied to the controller routes in your routes file.
	 *
	 * In addition, it is set as the URL generator's root namespace.
	 *
	 * @var string
	 */
	protected $_controllerNamespacePrefix = 'Http\\Controllers';

	/**
	 * @param string $moduleName
	 * @param null|string $modulePath
	 * @param null|string $namespace
	 */
	public function __construct($moduleName, $modulePath = null, $namespace = null)
	{
		if (empty($modulePath))
		{
			$modulePath = base_path('modules/' . $moduleName);
		}

		$this->_path = File::normalizePath($modulePath);
		$this->_name = $moduleName;
		if (!is_null($namespace))
		{
			$this->_namespace = $namespace;
		}
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->_name;
	}

	/**
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->_namespace . '\\' . $this->getName();
	}

	/**
	 * @return string
	 */
	public function getControllerNamespace()
	{
		return $this->getNamespace() . '\\' . $this->_controllerNamespacePrefix;
	}

	/**
	 * @param strimg|null $sub
	 * @return string
	 */
	public function getPath($sub = null)
	{
		$path = $this->_path;
		if (is_array($sub))
		{
			$sub = implode(DIRECTORY_SEPARATOR, $sub);
		}

		if (!is_null($sub))
		{
			$path .= DIRECTORY_SEPARATOR . $sub;
		}

		return $path;
	}

	/**
	 * @return string
	 */
	public function getLocalePath()
	{
		return $this->getPath(['resources', 'lang']);
	}

	/**
	 * @return string
	 */
	public function getViewsPath()
	{
		return $this->getPath(['resources', 'views']);
	}

	/**
	 * @return string
	 */
	public function getConfigPath()
	{
		return $this->getPath('config');
	}

	/**
	 * @return string
	 */
	public function getAssetsPackagesPath()
	{
		return $this->getPath(['resources', 'packages.php']);
	}

	/**
	 * @return string
	 */
	public function getRoutesPath()
	{
		return $this->getPath(['Http', 'routes.php']);
	}

	/**
	 * @return string
	 */
	public function getServiceProviderPath()
	{
		return $this->getPath(['Providers', 'ModuleServiceProvider.php']);
	}

	/**
	 * @return $this
	 */
	public function boot()
	{
		if (!$this->_isBooted)
		{
			$this->loadViews();
			$this->loadTranslations();
			$this->loadAssets();
			$this->_isBooted = true;
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function register()
	{
		if (!$this->_isRegistered)
		{
			$serviceProviderPath = $this->getServiceProviderPath();
			if (is_file($serviceProviderPath))
			{
				App::register($this->getNamespace() . '\Providers\ModuleServiceProvider');
			}

			$this->_isRegistered = true;
		}

		return $this;
	}

	/**
	 * @param Router $router
	 */
	public function loadRoutes(Router $router)
	{
		if(!CMS::isInstalled())
		{
			return;
		}

		$this->includeRoutes($router);
	}

	/**
	 * Register a config file namespace.
	 * @return void
	 */
	public function loadConfig()
	{
		if (!CMS::isInstalled())
		{
			return [];
		}

		$path = $this->getConfigPath();

		if (!is_dir($path)) return [];

		$configs = Cache::remember("moduleConfig::{$path}", Carbon::now()->addMinutes(10), function () use ($path)
		{
			$configs = [];
			foreach (new \DirectoryIterator($path) as $file)
			{
				if ($file->isDot() OR strpos($file->getFilename(), '.php') === false) continue;
				$key = $file->getBasename('.php');
				$configs[$key] = array_merge(require $file->getPathname(), app('config')->get($key, []));
			}

			return $configs;
		});

		return $configs;
	}

	/**
	 * @param Router $router
	 */
	protected function includeRoutes(Router $router)
	{
		$routesFile = $this->getRoutesPath();
		if (is_file($routesFile))
		{
			$router->group(['namespace' => $this->getControllerNamespace()], function ($router) use ($routesFile)
			{
				require $routesFile;
			});
		}
	}

	protected function loadAssets()
	{
		$packagesFile = $this->getAssetsPackagesPath();
		if (is_file($packagesFile))
		{
			require $packagesFile;
		}
	}

	/**
	 * Register a view file namespace.
	 *
	 * @return void
	 */
	protected function loadViews()
	{
		$namespace = strtolower($this->getName());

		if (is_dir($appPath = base_path() . '/resources/views/module/' . $namespace))
		{
			app('view')->addNamespace($namespace, $appPath);
		}

		app('view')->addNamespace($namespace, $this->getViewsPath());
	}

	/**
	 * Register a translation file namespace.
	 *
	 * @return void
	 */
	protected function loadTranslations()
	{
		$namespace = strtolower($this->getName());
		app('translator')->addNamespace($namespace, $this->getLocalePath());
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return (string)$this->getName();
	}
}