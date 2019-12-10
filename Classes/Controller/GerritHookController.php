<?php
/**
 * T3Bot.
 *
 * @author Frank Nägler <frank.naegler@typo3.org>
 *
 * @link https://www.t3bot.de
 * @link https://wiki.typo3.org/T3Bot
 */
namespace T3Bot\Controller;

use T3Bot\Slack\Message;
use T3Bot\Traits\GerritTrait;
use T3Bot\Traits\LoggerTrait;

/**
 * Class GerritHookController.
 */
class GerritHookController extends AbstractHookController
{
    use GerritTrait;
    use LoggerTrait;

    /**
     * public method to start processing the request.
     *
     * @param string $hook
     * @param string $input
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function process($hook, $input = 'php://input')
    {
        $this->getLogger()->debug(file_get_contents($input));
        $json = json_decode(file_get_contents($input));

        if (
            $json->project !== 'Packages/TYPO3.CMS' ||
            $json->token !== $this->configuration['gerrit']['webhookToken']
        ) {
            return;
        }
        $this->processHook($hook, $json);
    }

    /**
     * @param string $hook
     * @param \stdClass $json
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function processHook(string $hook, \stdClass $json)
    {
        $patchId = (int) $this->resolvePatchIdFromUrl($json->{'change-url'});
        $patchSet = property_exists($json, 'patchset') ? (int) $json->patchset : 0;

        $item = $this->queryGerrit('change:' . $patchId)[0];
        $created = substr($item->created, 0, 19);
        $text = "Branch: {$json->branch} | :calendar: {$created} | ID: {$item->_number}" . chr(10);
        $text .= ":link: <https://review.typo3.org/{$item->_number}|Goto Review>";
        if ($hook === 'patchset-created' && $patchSet === 1 && $json->branch === 'master') {
            $message = $this->buildMessage('[NEW] ' . $item->subject, $text);
            $this->sendMessageToChannel($hook, $message);
        } elseif ($hook === 'change-merged') {
            $message = $this->buildMessage(':white_check_mark: [MERGED] ' . $item->subject, $text, Message\Attachment::COLOR_GOOD);
            $this->sendMessageToChannel($hook, $message);
            $this->checkFiles($patchId, $json->commit);
        }
    }

    /**
     * @param string $title
     * @param string $text
     * @param string $color
     *
     * @return Message
     */
    protected function buildMessage(string $title, string $text, string $color = Message\Attachment::COLOR_NOTICE) : Message
    {
        $message = new Message();
        $message->setText(' ');
        $attachment = new Message\Attachment();

        $attachment->setColor($color);
        $attachment->setTitle($title);

        $attachment->setText($text);
        $attachment->setFallback($text);
        $message->addAttachment($attachment);
        return $message;
    }

    /**
     * @param int $patchId
     * @param int $commit
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function checkFiles($patchId, $commit)
    {
        $files = $this->getFilesForPatch($patchId, $commit);
        $rstFiles = [];
        if (!empty($files)) {
            foreach ($files as $fileName => $changeInfo) {
                if ($this->endsWith(strtolower($fileName), '.rst')) {
                    $rstFiles[$fileName] = $changeInfo;
                }
            }
            if (!empty($rstFiles)) {
                $message = new Message();
                $message->setText(' ');
                foreach ($rstFiles as $fileName => $changeInfo) {
                    $message->addAttachment($this->buildFileAttachment($fileName, $changeInfo));
                }
                $this->sendMessageToChannel('rst-merged', $message);
            }
        }
    }

    /**
     * @param string $fileName
     * @param \stdClass $changeInfo
     *
     * @return Message\Attachment
     */
    protected function buildFileAttachment(string $fileName, \stdClass $changeInfo) : Message\Attachment
    {
        $attachment = new Message\Attachment();
        $status = $changeInfo->status ?? 'default';
        $color = [
            'A' => Message\Attachment::COLOR_GOOD,
            'D' => Message\Attachment::COLOR_WARNING,
            'default' => Message\Attachment::COLOR_WARNING,
        ];
        $text = [
            'A' => 'A new documentation file has been added',
            'D' => 'A documentation file has been removed',
            'default' => 'A documentation file has been updated',
        ];
        $attachment->setColor($color[$status]);
        $attachment->setTitle($text[$status]);

        $text = ':link: <https://git.typo3.org/Packages/TYPO3.CMS.git/blob/HEAD:/' . $fileName . '|' . $fileName . '>';
        $attachment->setText($text);
        $attachment->setFallback($text);
        return $attachment;
    }

    /**
     * @param string $hook
     * @param Message $message
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function sendMessageToChannel(string $hook, Message $message)
    {
        if (is_array($this->configuration['gerrit'][$hook]['channels'])) {
            foreach ($this->configuration['gerrit'][$hook]['channels'] as $channel) {
                $this->postToSlack($message, $channel);
            }
        }
    }
}
