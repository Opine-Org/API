<?php
namespace Opine;

use Opine\Container\Service as Container;
use Opine\Config\Service as Config;

class CommandLine
{
    public function run()
    {
        if (!isset($_SERVER['argv'][1])) {
            die('no command supplied');
        }
        if (empty(getenv('OPINE_ENV'))) {
            die('OPINE_ENV should be set on command line, even if only to: defualt');
        }
        $root = $this->root();
        $config = new Config($root);
        $config->cacheSet();
        $container = Container::instance($root, $config, $root.'/../config/containers/container.yml');
        $this->routing($container);
        switch ($_SERVER['argv'][1]) {
            case 'help':
                echo
                    'The available commands are:', "\n",
                    'build', "\n",
                    'check', "\n",
                    'container-build', "\n",
                    'queue-peek', "\n",
                    'topics-show', "\n",
                    'version', "\n",
                    'worker', "\n";
                break;

            case 'build':
                $container->get('build')->project($root);
                break;

            case 'queue-peek':
                $container->get('queue')->peekReady();
                break;

            case 'worker':
                set_time_limit(0);
                $container->get('worker')->work();
                break;

            case 'check':
                $container->get('build')->environmentCheck($root);
                break;

            case 'topics-show':
                $container->get('topic')->show();
                break;

            case 'container-build':
                $container->get('build')->container($root);
                break;

            case 'version':
                echo file_get_contents($root.'/../vendor/opine/framework/version.txt'), "\n";
                break;

            default:
                echo 'Unknown command', "\n";
                break;
        }
    }

    private function root()
    {
        $root = getcwd();
        if (substr($root, -6, 6) != 'public' && file_exists($root.'/public')) {
            $root .= '/public';
        }

        return $root;
    }
}
