<?php


class APIRobots {
    
    
    var $_folder        =   NULL;
    
    var $_plugin        =   array();
    
    var $_config        =   array();
    
    var $_robots        =   array();
    
    var $_services      =   array();
    
    function __construct($folder = '', $plugin = array(), $config = array())
    {

        $robots = getDirListAsArray(dirname(__DIR__) . DS . 'include' . DS . 'robots');
        if (count($robots) > 0)
        {
            foreach($robots as $robotfolder)
            {
                $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('robots') . "` WHERE `folder` LIKE '$robotfolder'";
                list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                if ($count == 0)
                {
                    $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('robots') . "` (`folder`, `created`, `next`) VALUES('$robotfolder', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()) ";
                    if ($GLOBALS['APIDB']->queryF($sql))
                    {
                        $this->_robots[$robotfolder]['id'] = $GLOBALS['APIDB']->getInsertId();
                        $this->_robots[$robotfolder]['next'] = time();
                        if (is_file(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php'))
                            $this->_robots[$robotfolder]['config'] = @include(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php');
                    } else
                        die('SQL Failed: '. $sql);
                } elseif ($count == 1) {
                    $sql = "SELECT `id`, `next` FROM `" . $GLOBALS['APIDB']->prefix('robots') . "` WHERE `folder` LIKE '$robotfolder'";
                    list($id, $next) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                    $this->_robots[$robotfolder]['id'] = $id;
                    $this->_robots[$robotfolder]['next'] = $next;
                    if (is_file(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php'))
                        $this->_robots[$robotfolder]['config'] = @include(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php');
                        
                }
            }
        }
        
        if (isset($this->_robots[$folder]['config']['robots']))
            foreach(array_keys($this->_robots[$folder]['config']['robots']) as $service)
            {
                $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('services') . "` WHERE `type` LIKE '$service'";
                list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                if ($count == 0)
                {
                    $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('services') . "` (`type`, `created`, `action`) VALUES('$service', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()) ";
                    if ($GLOBALS['APIDB']->queryF($sql))
                    {
                        $this->_services[$service]['id'] = $GLOBALS['APIDB']->getInsertId();
                        $this->_services[$service]['action'] = time();
                        $this->_services[$service]['name'] = strtolower($service);
                    } else
                        die("SQL Failed: $sql;");
                } else {
                    $sql = "SELECT `id`, `action` FROM `" . $GLOBALS['APIDB']->prefix('services') . "` WHERE `type` LIKE '$service'";
                    list($id, $action) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                    $this->_services[$service]['id'] = $id;
                    $this->_services[$service]['action'] = $action;
                    $this->_services[$service]['name'] = strtolower($service);
                }
            }
        
        $this->_folder = $folder;
        $this->_plugin = $plugin;
        $this->_config = $config;
        
    }
    
    function execute($function = '', $term = '', $response = '')
    {
        return true;
    }
    
    function validate()
    {
        return true;
    }
    
    function mining()
    {
        return true;
    }
    
    function reset()
    {
        return true;
    }
    
    function setLog($robotid = 0, $serviceid = 0, $pluginid = 0, $username = '', $action = '', $content = '', $identity = '')
    {
        $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('logs') . "` (`robot-id`, `service-id`, `plugin-id`, `username`, `action`, `content`, `identity`, `when`) VALUES('$robotid', '$serviceid', '$pluginid', '$username', '$action', '" . $GLOBALS['APIDB']->escape($content) . "','$identity', UNIX_TIMESTAMP())";
        if (!$GLOBALS['APIDB']->queryF($sql))
            die("SQL Failed: $sql;");
        if (defined('CRONEXEC'))
        {
            echo "\n".ucfirst($action) . ": " . $content . "\n\n";
        }
        return $GLOBALS['APIDB']->getInsertId();
    }
    
    function logIdentity($identity = '')
    {
        $sql = "SELECT count(*) as `count` FROM `" . $GLOBALS['APIDB']->prefix('logs') . "`  WHERE `identity` LIKE '$identity'";
        list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
        if ($count == 0)
            return false;
        return true;
    }
}


class APIRobotsHandler {
    
    var $_next          =   NULL;
    
    var $_executed      =   NULL;
    
    var $_turn          =   NULL;
    
    var $_robots        =   array();
    
    var $_services      =   array();
    
    var $_plugins      =   array();
    
    function __destruct()
    {
        if (!is_null($this->_executed) && !is_null($this->_next))
        {
            $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('robots') . "` SET `next` = '" . (integer)$this->_next . "', `action` = UNIX_TIMESTAMP() WHERE `folder` LIKE '" . $this->_executed . "'";
            $GLOBALS['APIDB']->queryF($sql);
        }
    }
    
    function __construct()
    {
        
        $robots = getDirListAsArray(dirname(__DIR__) . DS . 'include' . DS . 'robots');        
        if (count($robots) > 0)
        {
            foreach($robots as $robotfolder)
            {
                $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('robots') . "` WHERE `folder` LIKE '$robotfolder'";
                list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                if ($count == 0)
                {
                    $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('robots') . "` (`folder`, `created`, `next`) VALUES('$robotfolder', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()) ";
                    if ($GLOBALS['APIDB']->queryF($sql))
                    {
                        $this->_robots[$robotfolder]['id'] = $GLOBALS['APIDB']->getInsertId();
                        $this->_robots[$robotfolder]['next'] = time();
                        if (is_file(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php'))
                            $this->_robots[$robotfolder]['config'] = @include(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php'); 
                    } else 
                        die('SQL Failed: '. $sql);
                } elseif ($count == 1) {
                    $sql = "SELECT `id`, `next` FROM `" . $GLOBALS['APIDB']->prefix('robots') . "` WHERE `folder` LIKE '$robotfolder'";
                    list($id, $next) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                    $this->_robots[$robotfolder]['id'] = $id;
                    $this->_robots[$robotfolder]['next'] = $next;
                    if (is_file(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php'))
                        $this->_robots[$robotfolder]['config'] = @include(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php');
                        
                }
            }
        }

        $sql = "SELECT `folder` FROM `" . $GLOBALS['APIDB']->prefix('robots') . "` WHERE `next` <= UNIX_TIMESTAMP() ORDER BY `next`, RAND() DESC LIMIT 1";
        list($folder) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
        $this->_turn = $folder;
        $this->validate();
    }
    
    function execute($folder = '', $service = '')
    {
        if (empty($folder))
            $folder = $this->_turn;
        
        // Gets Service
        if (empty($service) || !isset($this->_services[$service]))
        {
            $servicekeys = array_keys($this->_services);
            shuffle($servicekeys);
            shuffle($servicekeys);
            shuffle($servicekeys);
            shuffle($servicekeys);
            $service = $this->_services[$servicekeys[0]];
        } else 
            $service = $this->_services[$service];
        
        // Gets Plugin
        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('plugins') . "` WHERE `robot-id` = '" . $this->_robots[$folder]['id'] . "' AND `service-id` = '" . $service['id'] . "' AND 0 < UNIX_TIMESTAMP() ORDER BY RAND() LIMIT 1"; //  AND `next` < UNIX_TIMESTAMP()
        if ($plugin = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
        {
            $classname = '';
            if (file_exists(__DIR__ .  DS . 'robots' . DS . $plugin['file']))
            {
                require_once __DIR__ .  DS . 'robots' . DS . $plugin['file'];
                $classname = "APIRobot" . ucfirst($service['name']) . ucfirst($plugin['function']);
            }
            error_reporting(E_ALL);
            ini_set('display_errors', true);
            if (class_exists($classname))
            {
                if (defined('CRONEXEC'))
                    echo "\nLoading Class: " . $classname;
                $object = new $classname($folder, $plugin, (isset($this->_robots[$folder]['config'][$service['name']])?$this->_robots[$folder]['config'][$service['name']]:array()));
                if (is_object($object))
                {
                    if (defined('CRONEXEC'))
                        echo "\nExecuting: " . $classname."::execute()\n\n";
                    $object->execute();
                }
            } else {
                if (defined('CRONEXEC'))
                    echo "\nClass missing: " . $classname;
            }
        }
        
        $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('services') . "` SET `action` = UNIX_TIMESTAMP() WHERE 'service-id' = '" . $service['id'] . "'";
        if (!$GLOBALS['APIDB']->queryF($sql))
            die("SQL Failed: $sql;");
    }
    
    function validate()
    {
        if (count($this->_robots))
        {
            foreach($this->_robots as $folder => $values)
            {
                if (isset($values['config']) && !empty($values['config']))
                {
                    if (isset($values['config']['robots']) && !empty($values['config']['robots']))
                    {
                        foreach(array_keys($values['config']['robots']) as $service)
                        {
                            $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('services') . "` WHERE `type` LIKE '$service'";
                            list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                            if ($count == 0)
                            {
                                $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('services') . "` (`type`, `created`, `action`) VALUES('$service', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()) ";
                                if ($GLOBALS['APIDB']->queryF($sql))
                                {
                                    $this->_services[$service]['id'] = $GLOBALS['APIDB']->getInsertId();
                                    $this->_services[$service]['action'] = time();
                                    $this->_services[$service]['name'] = strtolower($service);
                                } else
                                    die("SQL Failed: $sql;");
                            } else {
                                $sql = "SELECT `id`, `action` FROM `" . $GLOBALS['APIDB']->prefix('services') . "` WHERE `type` LIKE '$service'";
                                list($id, $action) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                                $this->_services[$service]['id'] = $id;
                                $this->_services[$service]['action'] = $action;
                                $this->_services[$service]['name'] = strtolower($service);
                            }
                        }
                        foreach($values['config']['robots'] as $service => $plugins)
                        {
                            if (isset($values[$service]['username']) && !empty($values[$service]['username']))
                            {
                                $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'robot' AND `username` LIKE '".$values['config'][$service]['username']."' AND `robot-id` = '" . $values['id'] . "'";
                                list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                                if ($count == 0)
                                {
                                    $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('usernames') . "` (`typal`, `robot-id`, `service-id`, `username`, `created`) VALUES('robot', '".$values['id']."','".$this->_services[$service]['id']."','" . $values['config'][$service]['username'] . "', UNIX_TIMESTAMP())";
                                    if (!$GLOBALS['APIDB']->queryF($sql))
                                        die("SQL Failed: $sql;");
                                }
                            }
                            foreach($plugins as $key => $plugin)
                            {
                                if (!is_file(__DIR__ . DS . 'robots' . DS . ($funcfile="$service-$plugin.php"))) {
                                    if (isset($this->_robots[$folder]['config']['robots'][$service][$key]))
                                        unset($this->_robots[$folder]['config']['robots'][$service][$key]);
                                } else {
                                    $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('plugins') . "` WHERE `robot-id` = '" . $values['id'] . "' AND  `service-id` = '" . $this->_services[$service]['id'] . "' AND `function` LIKE '" . strtolower($plugin) . "'";
                                    list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                                    if ($count == 0)
                                    {
                                        $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('plugins') . "` (`robot-id`, `service-id`, `function`, `file`, `created`) VALUES('".$values['id']."', '" . $this->_services[$service]['id'] . "', '" . strtolower($plugin) . "','$funcfile', UNIX_TIMESTAMP()) ";
                                        if ($GLOBALS['APIDB']->queryF($sql))
                                        {
                                            $this->_plugins[$service][$plugin]['id'][$folder] = $GLOBALS['APIDB']->getInsertId();
                                            $this->_plugins[$service][$plugin]['file'] = $funcfile;
                                            $this->_plugins[$service][$plugin]['function'] = strtolower($plugin);
                                        } else
                                            die("SQL Failed: $sql;");
                                    } else {
                                        $sql = "SELECT `id`, `function`, `file` FROM `" . $GLOBALS['APIDB']->prefix('plugins') . "` WHERE `robot-id` = '" . $values['id'] . "' AND  `service-id` = '" . $this->_services[$service]['id'] . "' AND `function` LIKE '" . strtolower($plugin) . "'";
                                        list($id, $function, $file) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                                        $this->_plugins[$service][$plugin]['id'][$folder] = $id;
                                        $this->_plugins[$service][$plugin]['file'] = $file;
                                        $this->_plugins[$service][$plugin]['function'] = $function;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return true;
    }
    
    function reset()
    {
        // Gets Service
        foreach($this->_services as $service)
        {
            $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('plugins') . "` WHERE `robot-id` = '" . $this->_robots[$folder]['id'] . "' AND 'service-id' = '" . $service['id'] . "' AND `next` < UNIX_TIMESTAMP() ORDER BY RAND() LIMIT 1";
            if ($plugin = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
            {
                $classname = '';
                if (file_exists(__DIR__ .  DS . 'robots' . DS . $plugin['file']))
                {
                    require_once __DIR__ .  DS . 'robots' . DS . $plugin['file'];
                    $classname = "APIRobot" . ucfirst($service['name']) . ucfirst($plugin['function']);
                }
                if (class_exists($classname))
                {
                    $object = new $classname($folder, $plugin, (isset($this->_robots[$folder]['config'][$service['name']])?$this->_robots[$folder]['config'][$service['name']]:array()));
                    if (is_object($object))
                    {
                        $object->reset();
                    }
                }
            }
        }
    }
    
    
    function mining()
    {
        // Gets Service
        foreach($this->_services as $service)
        {
            $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('plugins') . "` WHERE `robot-id` = '" . $this->_robots[$folder]['id'] . "' AND 'service-id' = '" . $service['id'] . "' AND `next` < UNIX_TIMESTAMP() ORDER BY RAND() LIMIT 1";
            if ($plugin = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
            {
                $classname = '';
                if (file_exists(__DIR__ .  DS . 'robots' . DS . $plugin['file']))
                {
                    require_once __DIR__ .  DS . 'robots' . DS . $plugin['file'];
                    $classname = "APIRobot" . ucfirst($service['name']) . ucfirst($plugin['function']);
                }
                if (class_exists($classname))
                {
                    $object = new $classname($folder, $plugin, (isset($this->_robots[$folder]['config'][$service['name']])?$this->_robots[$folder]['config'][$service['name']]:array()));
                    if (is_object($object))
                    {
                        $object->mining();
                    }
                }
            }
        }
    }
}