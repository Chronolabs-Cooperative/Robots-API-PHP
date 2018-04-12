<?php
require_once dirname(__DIR__) . DS . 'TwitterAPIExchange.php';
require_once dirname(__DIR__) . DS . 'apirobots.php';


class APIRobotTwitterThankfollowers extends APIRobots {
    
    var $_responses = array();
    
    var $_resource = 'thankyou.diz';
    
    var $_twitter  = NULL;
    
    var $_method   = 'GET';
    
    var $_sessions = 11;
    
    var $_seconds  = 8444;
    
    var $_url      = 'https://api.twitter.com/1.1/statuses/update.json';
    
    var $_userurl  = 'https://api.twitter.com/1.1/users/lookup.json';
    
    function __construct($folder = '', $plugin = array(), $config = array())
    {
        parent::__construct($folder. $plugin, $config);
        $this->_twitter = new TwitterAPIExchange($config);
        $this->validate();
    }
    
    
    function validate()
    {
        @parent::validate();
    
        if (file_exists(dirname(dirname(__DIR__)) . DS . 'include' . DS . 'robots' . DS . $this->_folder . DS . $this->_resource))
        {
            $buffers = cleanWhitespaces(file(dirname(dirname(__DIR__)) . DS . 'include' . DS . 'robots' . DS . $this->_folder . DS . $this->_resource));
            if (count($buffers))
            {
                $this->_responses = array();
                foreach($buffers as $buffy)
                {
                    $this->_responses[] = $buffy;
                }
            }
        }
    
    }
    
    function execute($function='', $term='', $response='')
    {
        $ids = array();
        $GLOBALS['APIDB']->queryF('START TRANSACTION');
        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'robot' AND `username` = '" . str_replace("@", "", $this->_folder) . "' AND `next` < UNIX_TIMESTAMP() AND `left` > 0";
        if ($robot = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
        {
            $thankuser = array();
            $attempts = 0;
            while(count($thankuser)==0||$attempts<20)
            {
                $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('friends') . "` WHERE `username-id` = '".$robot['id'] . "' AND `thankyou` = '0' AND `followed` < UNIX_TIMESTAMP() ORDER BY RAND()";
                if ($thank = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                {
                    $attempts++;
                    $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'follower' AND `id` = '" . $thank['friend-id'] . "' AND `mining` > 0";
                    if ($thankuser = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                    {
                        if (strlen($thankuser['username'])==0)
                            $thankuser = array();
                        else 
                            continue;
                    }
                }
            }
            if (isset($thankuser['username']) && strlen($thankuser['username'])>0)
            {
                
                $getfields = array('status'=>$response = sprintf($this->_response[mt_rand(0, count($this->_response) - 1)], $thankuser['username']), 'auto_populate_reply_metadata' => true);
                $postfields = array();
                if(count($init = json_decode($this->_twitter->buildOauth($this->_url, "POST")->setPostfields($getfields)->performRequest(), true))>0)
                {
                    if (strlen($init['id'])>0)
                    {
                        $logid = $this->setLog($this->_robots[$this->_folder]['id'], $this->_plugin['service-id'], $this->_plugin['id'], $status['user']['screen_name'], 'thankyou', "Reponse: ".$response, $status['id']);
                        $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `left` = `left` - 1, `last-id` = '".$status['id']."', `log-id` = '" . $logid . "' WHERE `id` = " . $robot['id'];
                        if (!$GLOBALS['APIDB']->queryF($sql))
                            die('SQL Failed '.$sql);
                        $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('friends') . "` SET `thankyou` = UNIX_TIMESTAMP(), `last-id` = '".$status['id']."', `log-id` = '" . $logid . "' WHERE `id` = " . $thank['id'];
                        if (!$GLOBALS['APIDB']->queryF($sql))
                            die('SQL Failed '.$sql);
                            
                    }
                }
            }
        }
        
        $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('plugins') . "` SET `sessions` = '" . $this->_sessions . "', `seconds` = '" . $this->_seconds . "', `every` = '" . $this->_seconds / $this->_sessions . "', `next` = UNIX_TIMESTAMP() + '" . $this->_seconds / $this->_sessions ."', `left` = `left` - 1 WHERE `id` = '" . $this->_plugin['id'] . "'";
        if (!$GLOBALS['APIDB']->queryF($sql))
            die("SQL Failed: $sql;");
            
        $GLOBALS['APIDB']->queryF('COMMIT');
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
    }
}
