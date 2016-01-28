<?php
/**
 * T3Bot.
 *
 * @author Frank Nägler <typo3@naegler.net>
 *
 * @link http://www.t3bot.de
 * @link http://wiki.typo3.org/T3Bot
 */
namespace T3Bot\Controller;

use T3Bot\Slack\Message;

/**
 * Class SlackCommandController.
 */
class SlackCommandController
{
    const VERSION = '1.2.0';

    /** @var string current command */
    protected $command;

    /** @var array the params to the command */
    protected $params;

    /** @var string thats me, my name */
    protected $botName = '@T3Bot';

    /** @var string the username which talks to me */
    protected $username;

    /** @var string the mssage which was send */
    protected $message;

    /**
     * the constructor parse the request and set some
     * properties like username, message and params.
     */
    public function __construct()
    {
        $this->token = $_REQUEST['token'];
        $this->message = $_REQUEST['text'];
        $this->username = $_REQUEST['user_name'];
        $this->params = explode(' ', preg_replace('/\s+/', ' ', $this->message));

        // if the first word is the bot name, the second parameter is the command
        if (strtolower($this->params[0]) == strtolower($this->botName)) {
            // first remove the first word which is the bot name
            array_shift($this->params);
            $this->command = ucfirst(strtolower(array_shift($this->params)));
        } else {
            // the first word is the command and subcommand splitted by a colon
            // the rest are the params
            $parts = explode(':', $this->params[0]);
            $this->command = ucfirst(strtolower($parts[0]));
            $this->params[0] = $parts[1];
        }
    }

    /**
     * @param $response
     */
    protected function sendResponse($response)
    {
        if ($response instanceof Message) {
            echo $response->getJSON();
        } else {
            $result = new \stdClass();
            $result->text = $response;
            if (!empty($GLOBALS['config']['slack']['botAvatar'])) {
                $result->icon_emoji = $GLOBALS['config']['slack']['botAvatar'];
            }
            echo json_encode($result);
        }
    }

    /**
     * public method to start processing the request.
     */
    public function process()
    {
        if ($GLOBALS['config']['slack']['outgoingWebhookToken'] != $this->token) {
            exit;
        }
        switch ($this->command) {
            case 'Help':
                $this->sendResponse($this->getHelp());
            break;
            case 'Version':
                $this->sendResponse(self::VERSION);
            break;
            case 'Debug':
                if ($this->username == 'neoblack') {
                    $this->sendResponse(print_r($_REQUEST, true));
                }
            break;
            default:
                // each command is capsulated into a command class
                // try to find this command class and call the process method
                $commandClass = '\\T3Bot\\Commands\\'.$this->command.'Command';
                if (class_exists($commandClass)) {
                    /** @var \T3Bot\Commands\AbstractCommand $commandInstance */
                    $commandInstance = new $commandClass();
                    $this->sendResponse($commandInstance->process($this->params));
                } else {
                    // in case the command class not exists, try to scan
                    // the message and response with a nice text
                    $this->scanMessage();
                }
            break;
        }
    }

    /**
     * @return string
     */
    protected function getHelp()
    {
        $links = array(
            'My Homepage' => 'http://www.t3bot.de',
            'Github' => 'https://github.com/NeoBlack/T3Bot',
            'Help for Commands' => 'http://wiki.typo3.org/T3Bot',
        );
        $result = [];
        foreach ($links as $text => $link) {
            $result[] = ":link: <{$link}|{$text}>";
        }

        return implode(' | ', $result);
    }

    /**
     * scan message for keywords.
     */
    protected function scanMessage()
    {
        $message = strtolower($this->message);
        $cats = array(':smiley_cat:', ':smile_cat:', ':heart_eyes_cat:', ':kissing_cat:', ':smirk_cat:', ':scream_cat:', ':crying_cat_face:', ':joy_cat:' ,':pouting_cat:');

        $responses = array(
            'daddy' => 'My daddy is Frank Nägler aka @neoblack',
            'n8' => 'Good night @'.$this->username.'! :sleeping:',
            'nacht' => 'Good night @'.$this->username.'! :sleeping:',
            'night' => 'Good night @'.$this->username.'! :sleeping:',
            'hello' => 'Hello @'.$this->username.', nice to see you!',
            'hallo' => 'Hello @'.$this->username.', nice to see you!',
            'ciao' => 'Bye, bye @'.$this->username.', cu later alligator! :wave:',
            'cu' => 'Bye, bye @'.$this->username.', cu later alligator! :wave:',
            'thx' => 'You are welcome @'.$this->username.'!',
            'thank' => 'You are welcome @'.$this->username.'!',
            'drink' => 'Coffee or beer @'.$this->username.'?',
            'coffee' => 'Here is a :coffee: for you @'.$this->username.'!',
            'beer' => 'Here is a :t3beer: for you @'.$this->username.'!',
            'coke' => 'Coke is unhealthy @'.$this->username.'!',
            'cola' => 'Coke is unhealthy @'.$this->username.'!',
            'cookie' => 'Here is a :cookie: for you @'.$this->username.'!',
            'typo3' => ':typo3: TYPO3 CMS is the best open source CMS of the world!',
            'dark' => 'sure, we have cookies :cookie:',
            'cat' => 'ok, here is some cat content '.$cats[array_rand($cats)],
            'love' => 'I love you too, @'.$this->username.':kiss:',
        );
        foreach ($responses as $keyword => $response) {
            if (strpos($message, $keyword) !== false) {
                $this->sendResponse($response);
            }
        }
    }
}