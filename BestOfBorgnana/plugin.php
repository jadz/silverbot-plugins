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
            $replyTo = $this->_messages[0]['replyTo'];
            //echo 'Tweet would be: @' . $replyTo . ' ' . $text;
            $this->_tweetIt('@' . $this->_replyTo . ' ' . $text);
            array_shift($this->_messages);
        }
    }

    protected function _checkMentions()
    {
        $connection = $this->_getConnection();

        $lastmentionFile = $this->getDataDirectory() .'lastmention.txt';
        $this->_lastId = (string)file_get_contents($lastmentionFile);
        $this->_lastId = (string)$this->_lastId;
        $since = empty($this->_lastId) ? array() : array('since_id' => $this->_lastId);

        $content = $connection->get('statuses/mentions', $since);
        if(is_array($content)) {
            $messages = array();
            foreach($content as $c) {
                $srch  = array('@'. $this->config['username'], $this->config['username']);
                $text  = str_replace($srch, '', $c->text);

                $messages[] =
                array(
                    'lastId'  => (string)$c->id,
                    'replyTo' => $c->user->screen_name,
                    'text'    => $text,
                );
            }
            $this->_messages = $messages;
            foreach($this->_messages as $m) {
                $this->bot->pm($this->config['username'], $m['text']);
            }
            if(count($this->_messages) > 0)
                file_put_contents($lastmentionFile, (string)$this->_messages[0]['lastId']);

        }
        return;
    }
}
