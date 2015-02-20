<?php

namespace RMS\PushNotificationsBundle\Service\OS;

use RMS\PushNotificationsBundle\Exception\InvalidMessageTypeException;
use RMS\PushNotificationsBundle\Message\WindowsphoneMessage;
use RMS\PushNotificationsBundle\Message\MessageInterface;
use Buzz\Browser,
    Buzz\Client\Curl;

class MicrosoftNotification implements OSNotificationServiceInterface
{
    /**
     * Browser object
     *
     * @var \Buzz\Browser
     */
    protected $browser;

    /**
     * Whether the fake server is enabled or not.
     * @var bool
     */
    protected $fakeServerEnabled;

    /**
     * URL of the fake server
     * @var string
     */
    protected $fakeServerUrl;

    public function __construct($fakeServerEnabled, $fakeServerUrl)
    {
        $this->browser = new Browser(new Curl());
        $this->browser->getClient()->setVerifyPeer(false);
        $this->fakeServerEnabled = $fakeServerEnabled;
        $this->fakeServerUrl = $fakeServerUrl;
    }

    public function send(MessageInterface $message, array $extraOptions = [])
    {
        if (!$message instanceof WindowsphoneMessage) {
            throw new InvalidMessageTypeException(sprintf("Message type '%s' not supported by MPNS", get_class($message)));
        }

        $headers = array(
            'Content-Type: text/xml',
            'X-WindowsPhone-Target: ' . $message->getTarget(),
            'X-NotificationClass: ' . $message->getNotificationClass()
        );

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><wp:Notification xmlns:wp="WPNotification" />');

        $msgBody = $message->getMessageBody();

        if ($message->getTarget() == WindowsphoneMessage::TYPE_TOAST) {
            $toast = $xml->addChild('wp:Toast');
            $toast->addChild('wp:Text1', htmlspecialchars($msgBody['text1'], ENT_XML1|ENT_QUOTES));
            $toast->addChild('wp:Text2', htmlspecialchars($msgBody['text2'], ENT_XML1|ENT_QUOTES));
        }

        $url = $message->getDeviceIdentifier();
        if (true === $this->fakeServerEnabled) {
            $url = $this->fakeServerUrl;
        }

        $response = $this->browser->post($url, $headers, $xml->asXML());

        return $response->isSuccessful();
    }
}
