<?php
/**
 * T3Bot.
 *
 * @author Frank Nägler <frank.naegler@typo3.org>
 *
 * @link https://www.t3bot.de
 * @link https://wiki.typo3.org/T3Bot
 */
namespace T3Bot\Commands;

use Slack\Payload;
use Slack\RealTimeClient;
use T3Bot\Slack\Message;

/**
 * Class ChannelCommand.
 *
 * @property string commandName
 * @property array helpCommands
 */
class ChannelCommand extends AbstractCommand
{
    /**
     * AbstractCommand constructor.
     *
     * @param Payload $payload
     * @param RealTimeClient $client
     * @param array|null $configuration
     */
    public function __construct(Payload $payload, RealTimeClient $client, array $configuration = null)
    {
        $this->commandName = 'channel';
        $this->helpCommands = [
            'help' => 'shows this help'
        ];
        parent::__construct($payload, $client, $configuration);
    }

    /**
     * @return bool|string
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function process()
    {
        return false;
    }

    /**
     * @param array $data
     * @example $data:
     * [
     *  "type": "channel_created",
     *  "channel": {
     *      "id": "C024BE91L",
     *      "name": "fun",
     *      "created": 1360782804,
     *      "creator": "U024BE7LH"
     *  }
     * ]
     */
    public function processChannelCreated(array $data)
    {
        $channel = $data['channel'];
        $message = new Message();
        $message->setText(sprintf(
            '<@%s> opened channel #%s, join it <#%s>',
            $channel['creator'],
            $channel['name'],
            $channel['id']
        ));
        $this->sendResponse($message, null, $this->configuration['slack']['channels']['channelCreated']);
    }
}
