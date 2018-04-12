<?php
require_once dirname(__DIR__) . DS . 'TwitterAPIExchange.php';
require_once dirname(__DIR__) . DS . 'apirobots.php';


class APIRobotTwitterNewfollowers extends APIRobots {

    var $_twitter  = NULL;
    
    var $_method   = 'GET';
    
    var $_url      = 'https://api.twitter.com/1.1/followers/ids.json';
    
    var $_userurl  = 'https://api.twitter.com/1.1/users/lookup.json';
    
    function __construct($folder = '', $plugin = array(), $config = array())
    {
        parent::__construct($folder. $plugin, $config);
        $this->_twitter = new TwitterAPIExchange($config);
        $this->validate();
    }
    
    function validate()
    {
        if (parent::validate())
        {
            return true;
        }
    }
    
    function execute($function='', $term='', $response='')
    {
        $ids = array();
        return false;
        $cursor = 0;
        $count = 5000;
        $followerids = $init = array();
        $init['next_cursor'] = -1;
        //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
        while(!isset($init['errors']) && $init['next_cursor'] != 0)
        {
            //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
            sleep(mt_rand(3,17));
            $getfields = array('screen_name'=>str_replace("@", "", $this->_folder), 'cursor'=>$cursor, 'count'=>$count);
            $postfields = array();
            if(!checkForErrorResponse($init = json_decode($this->_twitter->setGetfield($getfields)->buildOauth($this->_url, $this->_method)->performRequest(), true), 'twitter'))
            {
                die(print_r($init, true));
                foreach(getFieldResponse($init, 'ids') as $id)
                    $ids[$id] = $id;
                $cursor = $cursor + $count;
            }
            //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
        }    
        //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
        if (count($ids)>0)
        {
            //echo "\n:: ". __LINE__ . "::" . __CLASS__ . ":::" . __FUNCTION__;
            $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'robot' AND `username` = '" . str_replace("@", "", $this->_folder) . "'";
            if (!$robot = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
            {
                $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('usernames') . "` (`typal`, `robot-id`, `service-id`, `plugin-id`, `username`, `created`) VALUES('robot', '" . $this->_robots[$this->_folder]['id'] . "', '" . $this->_plugin['service-id'] . "', '" . $this->_plugin['id'] . "', '" . str_replace("@", "", $this->_folder) . "', UNIX_TIMESTAMP())";
                if ($GLOBALS['APIDB']->queryF($sql)) {
                    $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'robot' AND `username` = '" . str_replace("@", "", $this->_folder) . "'";
                    if (!$robot = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                        die("SQL Failed: $sql;");
                } else 
                    die("SQL Failed: $sql;");
            }
            $additionalunfollower = $additionalfollower = 0;
            $GLOBALS['APIDB']->queryF('START TRANSACTION');
            foreach($ids as $id)
            {
                $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'follower' AND `identity` = '" . $id . "'";
                if (!$follower = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                {
                    $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('usernames') . "` (`typal`, `robot-id`, `service-id`, `plugin-id`, `identity`, `created`) VALUES('follower', '" . $this->_robots[$this->_folder]['id'] . "', '" . $this->_plugin['service-id'] . "', '" . $this->_plugin['id'] . "', '" . $id . "', UNIX_TIMESTAMP())";
                    if ($GLOBALS['APIDB']->queryF($sql))
                    {
                        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'follower' AND `identity` = '" . $id . "'";
                        if (!$follower = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                            die("SQL Failed: $sql;");
                    } else
                        die("SQL Failed: $sql;");
                }
                $followerids[$follower['id']] = $follower['id'];
                $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('friends') . "` WHERE `username-id` = '".$robot['id'] . "' AND `friend-id` = '" . $follower['id'] . "'";
                if (!$friend = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                {
                    $additionalfollower++;
                    $sql = "INSERT INTO `" . $GLOBALS['APIDB']->prefix('friends') . "` (`robot-id`, `service-id`, `plugin-id`, `username-id`, `friend-id`, `identity`, `followed`, `created`) VALUES('" . $this->_robots[$this->_folder]['id'] . "', '" . $this->_plugin['service-id'] . "', '" . $this->_plugin['id'] . "', '" . $robot['id'] . "', '" . $follower['id'] . "',  '" . $id . "', UNIX_TIMESTAMP(), UNIX_TIMESTAMP())";
                    if ($GLOBALS['APIDB']->queryF($sql))
                    {
                        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('friends') . "` WHERE `username-id` = '".$robot['id'] . "' AND `friend-id` = '" . $follower['id'] . "'";
                        if (!$friend = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                            die("SQL Failed: $sql;");
                        $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `followed` = UNIX_TIMESTAMP() WHERE `id` = '" . $friend['id'] . "'";
                        if (!$GLOBALS['APIDB']->queryF($sql))
                            die("SQL Failed: $sql;");
                    } else
                        die("SQL Failed: $sql;");
                } elseif ($friend['unfollowed'] > $friend['followed']) {
                    $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('friends') . "` SET `thankyou` = 0, `followed` = UNIX_TIMESTAMP() WHERE `id` = '" . $friend['id'] . "'";
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die("SQL Failed: $sql;");
                    $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `followed` = UNIX_TIMESTAMP() WHERE `id` = '" . $friend['id'] . "'";
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die("SQL Failed: $sql;");
                    $additionalfollower++;
                }

            }
            if (count($followerids))
            {
                $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('friends') . "` WHERE `unfollowed` = 0 AND `username-id` = '".$robot['id'] . "' AND `friend-id` NOT IN('" . implode("', '", $followerids) . "')";
                while($unfriend = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
                {
                    $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('friends') . "` SET `fairwell` = 0, `unfollowed` = UNIX_TIMESTAMP() WHERE `id` = '" . $unfriend['id'] . "'";
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die("SQL Failed: $sql;");
                    $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `unfollowed` = UNIX_TIMESTAMP() WHERE `id` = '" . $unfriend['id'] . "'";
                    if (!$GLOBALS['APIDB']->queryF($sql))
                        die("SQL Failed: $sql;");
                    $additionalunfollower++;
                }
            }
            $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `following` = '".count($ids)."' WHERE `id` = '" . $robot['id'] . "'";
            if (!$GLOBALS['APIDB']->queryF($sql))
                die("SQL Failed: $sql;");
            $logid = $this->setLog($this->_robots[$this->_folder]['id'], $this->_plugin['service-id'], $this->_plugin['id'], $this->_folder, 'followings', "Additional Followers: $additionalfollower; Additional Unfollowers: $additionalunfollower!" , '');
            $GLOBALS['APIDB']->queryF('COMMIT');
        }
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
    
    
    function reset()
    {
        $sql = "SELECT * FROM `" . $GLOBALS['APIDB']->prefix('usernames') . "` WHERE `typal` = 'robot' AND `username` = '" . str_replace("@", "", $this->_folder) . "' AND `plugin-id` = '" . $this->_plugin['id'] . "' AND  `service-id` = '" . $this->_plugin['service-id'] . "' AND  `robot-id` = '" . $this->_robots[$this->_folder]['id'] . "' AND `reset` <= UNIX_TIMESTAMP() ORDER BY RAND()";
        while($robot = $GLOBALS['APIDB']->fetchArray($GLOBALS['APIDB']->queryF($sql)))
        {
            $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `sessions` = '" . $this->_robots[$this->_folder]['config']['thankfollowers']['sessions'] . "', `seconds` = '" . $this->_robots[$this->_folder]['config']['thankfollowers']['seconds'] . "', `every` = '" . $this->_robots[$this->_folder]['config']['thankfollowers']['seconds'] / $this->_robots[$this->_folder]['config']['thankfollowers']['sessions'] . "', `left` = '" . $this->_robots[$this->_folder]['config']['thankfollowers']['sessions'] . "' WHERE `id` = " . $robot['id'];
            if (!$GLOBALS['APIDB']->queryF($sql))
                die('SQL Failed '.$sql);
                
            $sql = "UPDATE `" . $GLOBALS['APIDB']->prefix('usernames') . "` SET `next` = UNIX_TIMESTAMP(), `left` = `sessions`, `reset` = UNIX_TIMESTAMP() + `seconds` WHERE `id` = " . $robot['id'];
            if (!$GLOBALS['APIDB']->queryF($sql))
                die('SQL Failed '.$sql);
        }
    }
}
