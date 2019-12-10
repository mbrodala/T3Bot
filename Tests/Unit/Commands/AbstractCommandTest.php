<?php
/**
 * T3Bot.
 *
 * @author Frank Nägler <frank.naegler@typo3.org>
 *
 * @link https://www.t3bot.de
 * @link https://wiki.typo3.org/T3Bot
 */
namespace T3Bot\Tests\Unit\Commands;

use Prophecy\Argument;
use Slack\Payload;
use Slack\RealTimeClient;
use T3Bot\Commands\AbstractCommand;
use T3Bot\Slack\Message;
use T3Bot\Tests\Unit\BaseCommandTestCase;

/**
 * Class AbstractCommandTest.
 */

/** @noinspection LongInheritanceChainInspection */
class AbstractCommandTest extends BaseCommandTestCase
{
    /**
     * @test
     */
    public function ensureSendResponseHandlingForStringResponse()
    {
        /** @var Payload $payload */
        $payload = new Payload([
            'text' => 'test message',
            'channel' => '#fntest',
        ]);
        /** @var RealTimeClient $client */
        $client = $this->prophesize(RealTimeClient::class);

        $client->apiCall('chat.postMessage', [
            'unfurl_links' => false,
            'unfurl_media' => false,
            'parse' => 'none',
            'text' => 'this is a test string',
            'channel' => '#fntest',
            'as_user' => true,
        ])->willReturn(true);

        /** @var AbstractCommand $stub */
        $stub = $this->getMockForAbstractClass(AbstractCommand::class, [$payload, $client->reveal()]);
        $stub->sendResponse('this is a test string');
    }

    /**
     * @test
     */
    public function ensureSendResponseHandlingForMessageResponse()
    {
        /** @var Payload $payload */
        $payload = new Payload([
            'text' => 'test message',
            'channel' => '#fntest',
        ]);
        /** @var RealTimeClient $client */
        $client = $this->prophesize(RealTimeClient::class);
        /* @noinspection PhpParamsInspection */
        $client->postMessage(Argument::any())->willReturn(true);

        $message = new Message(['icon_emoji' => 'foo']);
        $attachment = new Message\Attachment(['title' => 'Test']);
        $attachment->setTitle('Test');
        $attachment->setTitleLink('https://www.google.de');
        $attachment->setText('Test');
        $attachment->setFallback('Test');
        $attachment->setAuthorName('Test');
        $attachment->setAuthorLink('https://www.google.de');
        $attachment->setAuthorIcon('foo');
        $attachment->setImageUrl('https://www.google.de');
        $attachment->setThumbUrl('https://www.google.de');

        $message->setText('Test');
        $message->addAttachment($attachment);

        /** @var AbstractCommand $stub */
        $stub = $this->getMockForAbstractClass(AbstractCommand::class, [$payload, $client->reveal()]);
        $stub->sendResponse($message);

        static::assertEquals('foo', $message->getIconEmoji());
        $message->setIconEmoji('bar');
        static::assertEquals('bar', $message->getIconEmoji());

        $message->setAttachments([$attachment]);
        static::assertEquals([$attachment], $message->getAttachments());
    }
}
