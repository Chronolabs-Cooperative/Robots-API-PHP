<?php
require_once dirname(__DIR__) . DS . 'TwitterAPIExchange.php';
require_once dirname(__DIR__) . DS . 'apirobots.php';

class APIRobotTwitterReplier extends APIRobots {
    
    var $_keywords = array();
    
    var $_resource = 'replies.diz';
    
    var $_twitter  = NULL; 
    
    var $_method   = 'GET';

    var $_url      = 'https://api.twitter.com/1.1/statuses/update.json';
    
    var $_userurl  = 'https://api.twitter.com/1.1/users/lookup.json';
    
    var $_search   = 'https://api.twitter.com/1.1/search/tweets.json';
    
    function __construct($folder = '', $plugin = array(), $config = array())
    {
        parent::__construct($folder, $plugin, $config);
        $this->_twitter = new TwitterAPIExchange($config);
        $this->validate();
    }
    
    function validate()
    {
        @parent::validate();
    
        if (file_exists(dirname(dirname(__DIR__)) . DS . 'include' . DS . 'robots' . DS . $this->_folder . DS . $this->_resource))
        {
            $buffers = file(dirname(dirname(__DIR__)) . DS . 'include' . DS . 'robots' . DS . $this->_folder . DS . $this->_resource);
            if (count($buffers)) {
                $this->_keywords = array();
                foreach($buffers as $buffy)
                {
                    $parts = explode("|", trim(str_replace(array("\n", "\r"), "", $buffy)));
                    if (isset($parts[0]) && isset($parts[1]) && isset($parts[2]) && isset($parts[3]))
                    {
                        $this->_keywords[$parts[0]]['sessions'] = $parts[1];
                        $this->_keywords[$parts[0]]['seconds'] = $parts[2];
                        $this->_keywords[$parts[0]]['response'] = $parts[3];
                    }
                }
                
                if (count($this->_keywords))
                {
                    $sql = "DELETE FROM `" . $GLOBALS['APIDB']->prefix('keywords') . "` WHERE `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "' AND `service-id` = '"  . $this->_plugin['service-id'] . "' AND `plugin-id` = '"  . $this->_plugin['id'] . "' AND `keyword` NOT IN ('" .implode("', '", array_keys($this->_keywords)) . "')";
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die("SQL Failed: $sql;");
                    
                    foreach($this->_keywords as $keyword => $values)
                    {
                        $sql = "SELECT count(*) FROM `" . $GLOBALS['APIDB']->prefix('keywords') . "` WHERE `keyword` LIKE '$keyword' AND `plugin-id` = '" . $this->_plugin['id'] . "' AND  `service-id` = '" . $this->_plugin['service-id'] . "' AND  `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "'  ";
                        list($count) = $GLOBALS['APIDB']->fetchRow($GLOBALS['APIDB']->queryF($sql));
                        if ($count == 0)
                        {
                            $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('keywords') . "` (`keyword`, `robot-id`, `service-id`, `plugin-id`, `sessions`, `seconds`, `every`, `left`, `reset`, `created`) VALUES('$keyword', '" . $this->_robots[$this->_folder]['id'] . "','"  . $this->_plugin['service-id'] . "','"  . $this->_plugin['id'] . "','" . $values['sessions'] . "','" . $values['seconds'] . "','" . $values['seconds'] / $values['sessions'] . "','" . $values['sessions'] . "', UNIX_TIMESTAMP() + " . $values['seconds'] .", UNIX_TIMESTAMP()) ";
                            if ($GLOBALS['APIDB']->queryF($sql))
                            {
                                $this->_keywords[$keyword]['id'] = $GLOBALS['APIDB']->getInsertId();
                            } else 
                                die("SQL Failed: $sql;");
                        } elseif ($count == 1) {
                            $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('keywords') . "` WHERE `keyword` LIKE '$keyword' AND `plugin-id` = '" . $this->_plugin['id'] . "' AND  `service-id` = '" . $this->_plugin['service-id'] . "' AND  `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "'  ";
                            $keywd = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql));
                            $this->_keywords[$keyword]['id'] = $keywd['id'];
                            if ($this->_keywords[$keyword]['sessions'] != $keywd['sessions'] || $this->_keywords[$keyword]['seconds'] != $keywd['seconds'])
                            {
                                $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('keywords') . "` SET `sessions` = '" . $this->_keywords[$keyword]['sessions'] . "', `seconds` = '" . $this->_keywords[$keyword]['seconds'] . "', `every` = '" . $this->_keywords[$keyword]['seconds'] / $this->_keywords[$keyword]['sessions'] . "' WHERE `id` = '" . $values['id'] . "'";
                                if (!$GLOBALS['APIDB']->queryF($sql))
                                    die("SQL Failed: $sql;");
                            }
                            if (is_file(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php'))
                                $this->_robots[$robotfolder]['config'] = @include(dirname(__DIR__) . DS . 'include' . DS . 'robots' . DS . $robotfolder . DS . 'settings.php');
                                
                        }
                    }
                }
            }
        }
        return true;
    }
        
    function execute($function='', $term='', $response='')
    {
        //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('keywords') . "` WHERE `plugin-id` = '" . $this->_plugin['id'] . "' AND  `service-id` = '" . $this->_plugin['service-id'] . "' AND  `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "' AND `next` < UNIX_TIMESTAMP() AND `left` > 0 AND LENGTH(`keyword`) > 0 ORDER BY RAND()";;
        $results = $GLOBALS['APIDB']->queryF($sql);
        $actioned = false;
        while(($keywd = $GLOBALS['APIDB']->fetchArray($results)) &&  $actioned == false)
        {
            $lastid = '';
            if (strlen($keywd['keyword']) && $actioned == false)
            {
                //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
                $search = json_decode($this->_twitter->setGetfield(("q=" . urlencode($keywd['keyword']) . (!empty($keywd['last-id'])&&intval($keywd['last-id'])>0?'&since_id='.$keywd['last-id']:'')))->buildOauth($this->_search, $this->_method)->performRequest(), true);
                
                if (!checkForErrorResponse($search, 'twitter'))
                foreach($search['statuses'] as $status)
                {
                    if (!$this->logIdentity($status['id']))
                        if ((empty($keywd['last-id']) || $status['id']>$keywd['last-id']) && $actioned == false)
                        {
                            $this->_twitter = new TwitterAPIExchange($this->_config);
                            $getfields = array('status'=>$response = sprintf($this->_keywords[$keywd['keyword']]['response'], $status['user']['screen_name']), 'in_reply_to_status_id' => $status['id'], 'auto_populate_reply_metadata' => true);
                            $postfields = array();
                            if(!checkForErrorResponse($init = json_decode($this->_twitter->buildOauth($this->_url, "POST")->setPostfields($getfields)->performRequest(), true), 'twitter'))
                            {
                                //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
                                $logid = $this->setLog($this->_robots[$this->_folder]['id'], $this->_plugin['service-id'], $this->_plugin['id'], $status['user']['screen_name'], 'reply', "Tweet: " . $status['text'] . "\nReponse: ".$response, $init['str_id']);
                                $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('keywords') . "` SET `total` = `total` + 1, `left` = `left` - 1, `last-id` = '".$init['str_id']."', `log-id` = '" . $logid . "' WHERE `id` = " . $keywd['id'];
                                if (!$GLOBALS['APIDB']->queryF($sql))
                                    die('SQL Failed '.$sql);
                                $actioned = time();
                                $lastid = $status['id'];
                            }
                        }
                    else 
                        $lastid = $status['id'];
                }
                if (isset($keywd['id']) && !empty($keywd['id']) && $actioned == false && !empty($lastid))
                {
                    $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('keywords') . "` SET `next` = UNIX_TIMESTAMP() + `every`, `last-id` = '$lastid' WHERE `id` = " . $keywd['id'];
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die('SQL Failed '.$sql);
                } elseif (isset($keywd['id']) && !empty($keywd['id']) && $actioned != false)
                {
                    $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('keywords') . "` SET `next` = UNIX_TIMESTAMP() + `every` WHERE `id` = " . $keywd['id'];
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die('SQL Failed '.$sql);
                }
            }                    
        }
        $sql = "SELECT SUM(`sessions`) as `sessions`, AVG(`seconds`) as `seconds`, AVG(`seconds`) / SUM(`sessions`) as `every`, SUM(`left`) as `left`, AVG(`seconds`) / SUM(`sessions`) + UNIX_TIMESTAMP() as `next`, AVG(`seconds`) + UNIX_TIMESTAMP() as `reset` FROM `" . $GLOBALS['APIDB']->prefix('keywords') . "` WHERE `plugin-id` = '" . $this->_plugin['id'] . "' AND  `service-id` = '" . $this->_plugin['service-id'] . "' AND  `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "' GROUP BY `service-id`";
        if ($vars = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
        {
            $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('plugins') . "` SET `sessions` = '" . $vars['sessions'] . "', `seconds` = '" . $vars['seconds'] . "', `every` = '" . $vars['every'] . "', `next` = '" . $vars['next'] . "', `left` = '" . $vars['left'] . "', `reset` = '" . $vars['reset'] . "', `action` = UNIX_TIMESTAMP(), `last` = '".intval($actioned)."' WHERE `id` = '" . $this->_plugin['id'] . "'";
            if (!$GLOBALS['APIDB']->queryF($sql))
                die("SQL Failed: $sql;");
        }
        return true;           
    }
    
    
    function mining()
    {
        $GLOBALS['APIDB']->queryF('START TRANSACTION');
        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `mining` < UNIX_TIMESTAMP() AND `service-id` = '" . $this->_plugin['service-id'] . "' LIMIT 50";
        while($user = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
        {
            $getfields = array('screen_name'=>$user['username'], 'user_id'=>$user['identity']);
            $postfields = array();
            if(count($init = json_decode($this->_twitter->setGetfield($getfields)->buildOauth(sprintf($this->_userurl, $status['id']), $this->_method)->performRequest(), true))>0)
            {
                $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `username` = '" . $init['screen_name'] . "', `avatar` = '" . $init['profile_image_url'] . "', `name` = '" . $GLOBALS['APIDB']->escape($init['name']) . "', `mining` = UNIX_TIMESTAMP() + " . mt_rand(3600*24*7*3, 3600*24*7*4*5) . " WHERE `id` = '". $user['id']."'";
                if (!$GLOBALS['APIDB']->queryF($sql))
                    die("SQL Failed: $sql;");
            }
        }
        $GLOBALS['APIDB']->queryF('COMMIT');
        return true;
    }
    
    function reset()
    {
        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('keywords') . "` WHERE `plugin-id` = '" . $this->_plugin['id'] . "' AND  `service-id` = '" . $this->_plugin['service-id'] . "' AND  `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "' AND `reset` <= UNIX_TIMESTAMP() ORDER BY RAND()";
        while($keywd = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
        {
            $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('keywords') . "` SET `next` = UNIX_TIMESTAMP(), `left` = `sessions`, `reset` = UNIX_TIMESTAMP() + `seconds` WHERE `id` = " . $keywd['id'];
            if (!$GLOBALS['APIDB']->queryF($sql))
                die('SQL Failed '.$sql);                
        }
        return true;
    }
}
