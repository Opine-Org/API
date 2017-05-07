<?php
/**
 * Opine\Framework
 *
 * Copyright (c)2013, 2014 Ryan Mahoney, https://github.com/Opine-Org <ryan@virtuecenter.com>
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
 */
namespace Opine;

use Opine\Container\Service as Container;
use Opine\Cache\Service as Cache;
use Opine\Config\Service as Config;
use Exception as BaseException;
use Whoops;

class Exception extends BaseException
{
}

class Framework
{
    private $container;

    private static function environment()
    {
        return empty(getenv('OPINE_ENV')) ? 'dev' : getenv('OPINE_ENV');
    }

    private static function project()
    {
        return empty(getenv('OPINE_PROJECT')) ? 'project' : getenv('OPINE_PROJECT');
    }

    private function errors($environment)
    {
        if ($environment == 'production') {
            return;
        }
        $run = new Whoops\Run();
        $handler = new Whoops\Handler\PrettyPageHandler();
        $run->pushHandler($handler);
        $run->pushHandler(function ($exception, $inspector, $run) {
            $inspector->getFrames()->map(function ($frame) {
                return $frame;
            });
        });
        $run->register();
    }

    public function __construct($noCache = false)
    {
        $root = $this->root();
        $cache = new Cache($root);
        $environment = self::environment();
        if ($environment == 'dev') {
          $config = new Config($root);
          $config->cacheSet();
          $container = Container::instance($root, $config, $root.'/../config/containers/container.yml');
          $container->get('build')->project($root);
        }
        $project = self::project();
        $cachePrefix = $project . $environment;
        $this->errors($environment);
        $cacheResult = json_decode($cache->get($cachePrefix . '-opine'), true);
        $containerData = [];
        if ($noCache === false && isset($cacheResult['container'])) {
            $containerData = $cacheResult['container'];
        }
        $config = new Config($root);
        if ($cacheResult['config'] !== false) {
            $configData = $cacheResult['config'];
            if (isset($configData[$environment])) {
                $config->cacheSet($configData[$environment]);
            } elseif (isset($configData['default'])) {
                $config->cacheSet($configData['default']);
            }
        } else {
            $config->cacheSet();
        }
        $this->container = Container::instance($root, $config, $root.'/../config/containers/container.yml', $noCache, $containerData);
        $this->container->set('cache', $cache);
        $this->cache($cacheResult);
    }

    public function root()
    {
        $root = (empty($_SERVER['DOCUMENT_ROOT']) ? getcwd() : $_SERVER['DOCUMENT_ROOT']);
        if (substr($root, -6, 6) != 'public' && file_exists($root.'/public')) {
            $root .= '/public';
        }

        return $root;
    }

    public function frontController()
    {
        // if there is a token header, attempt to read and cache values for request
        $this->processToken();

        // populate the post container
        if (isset($_POST) && !empty($_POST)) {
            $this->container->get('post')->populate($_POST);
        }

        // process the request
        http_response_code(200);
        try {
            $response = $this->container->get('route')->run($_SERVER['REQUEST_METHOD'], $this->pathDetermine());
            echo $response;
        } catch (Exception $e) {
            if (http_response_code() == 200) {
                http_response_code(500);
            }
            echo $e->getMessage();
        }
    }

    private function processToken ()
    {
        // see if there is any authorization header provided
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return;
        }

        // remove the word "Bearer" from token
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);

        // get the user service from the service container
        $userService = $this->container->get('userService');

        // attempt to decode the token
        $tokenSession = $userService->decodeJWT($token);
        if (empty($tokenSession)) {
            return;
        }

        // put the token into application memory for future reference
        $userService->setTokenSession($tokenSession);
    }

    private function pathDetermine()
    {
        $path = $_SERVER['REQUEST_URI'];
        if (substr_count($path, '?') > 0) {
            $path = substr($path, 0, strpos($path, '?'));
        }
        return $path;
    }

    public function cache(array &$cacheResult)
    {
        $this->container->get('bundleModel')->cacheSet($cacheResult['bundles']);
        $this->container->get('topic')->cacheSet($cacheResult['topics']);
        $this->container->get('route')->cacheSet($cacheResult['routes']);
    }
}
