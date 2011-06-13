<?php

/**
 * BestOfBorgnana 
 * 
 * @uses SilverBotPlugin
 * @package 
 * @version $id$
 * @copyright 
 * @author Jared Mooring <jared.mooring@gmail.com> 
 * @license 
 */

require_once 'plugins/BestOfBorgnana/twitteroauth.php';

class BestOfBorgnana extends SilverBotPlugin
{
    protected $_reply = true;

    public function __construct()
    {
        $this->addTimer('checkMentions', '60 seconds', array($this, '_checkMentions'));
    }

    public function chn_bestofborgy($data)
    {
        $data = $data['data'];
        if(!empty($data)) {
            $this->_tweetit($data);
        }
        elseif(!empty($this->_lastMessage)) {
            $this->_reply = true;
            $this->_tweetit($this->_lastMessage);
        }
        else {
            $this->bot->reply('Sorry, nothing to tweet. Be funnier borgnana');
        }
    }

    public function chn_bestofborgydel($data)
    {
        $id = $data['data'];
        $this->_deleteIt($id);
    }

    public function chn_checkmentions($data)
    {
        $this->_checkMentions();
    }

    public function onUserChannelMsg($data)
    {
        if($data['username'] == $this->config['username'])
            $this->_lastMessage = $data['text'];
    }

    protected function _deleteIt($id)
    {
        $connection = $this->_getConnection();
        if($connection instanceof TwitterOAuth) {
            $content = $connection->post("statuses/destroy/$id", array('id' => $id));
            if(!empty($content->error))
                $this->bot->reply('Unable to delete status. Response: ' . $content->error);
            else
                $this->bot->reply('Successfully deleted ' . $id);
        }
        else {
            $this->bot->reply('Something bad happened. Blame keanu.');
        }
    }

    protected function _tweetIt($data)
    {
        $data = substr($data, 0, 140);
        $connection = $this->_getConnection();
        if($connection instanceof TwitterOAuth) {
            $content    = $connection->post("statuses/update", array('status' => $data));
            if(isset($content->id_str) && !empty($content->id_str)) {
                if($this->_reply)
                    $this->bot->reply('@borgnana just tweeted: ' . $content->id_str . ' with: ' . $data);
                $this->_lastMessage = '';
            }
            else
                $this->bot->reply("Can't tweet that. Something broke. Blame Dan Brown.");
        }
        else {
            $this->bot->reply('Something bad happened. Blame keanu.');
        }
    }

    protected function _getConnection()
    {
        $consumerKey      = $this->config['consumer_key'];
        $consumerSecret   = $this->config['consumer_secret'];
        $oauthToken       = $this->config['oauth_token'];
        $oauthTokenSecret = $this->config['oauth_token_secret'];

        try {
            $connection = new TwitterOAuth($consumerKey, $consumerSecret, $oauthToken, $oauthTokenSecret);
            return $connection;
        }
        catch(Exception $e) {
            return false;
        }
    }

    public function prv($data)
    {
        $username = $data['username'];
        if($username == $this->config['username']) {
            $text = $data['text'];
            $this->_reply = false;
            $this->_tweetIt('@' . $this->_replyTo . ' ' . $text);
        }
    }

    protected function _checkMentions()
    {
        $connection = $this->_getConnection();

        $lastmentionFile = $this->getDataDirectory() .'lastmention.txt';
        $this->_lastId = file_get_contents($lastmentionFile);
        $since = empty($this->_lastId) ? array() : array('since_id' => $this->_lastId);

        $content = $connection->get('statuses/mentions', $since);
        if(is_array($content)) {
            foreach($content as $c) {
                $this->_lastId  = $c->id;
                $this->_replyTo = $c->user->screen_name;
                $srch = array('@'. $this->config['username'], $this->config['username']);
                $txt  = str_replace($srch, '', $c->text);
                $this->bot->pm($this->config['username'], $txt);
            }
            file_put_contents($lastmentionFile, $this->_lastId);
        }
        return;
    }
}
