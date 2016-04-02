<?php

namespace App\Ninja\Notifications;

use Davibennun\LaravelPushNotification\Facades\PushNotification;
use Illuminate\Http\Request;

/**
 * Class PushFactory
 * @package App\Ninja\Notifications
 */

class PushFactory
{
    /**
     * PushFactory constructor.
     *
     * @param $this->certificate - Development or production.
     *
     * Static variables defined in routes.php
     *
     * IOS_PRODUCTION_PUSH
     * IOS_DEV_PUSH
     */

    public function __construct()
    {
        $this->certificate = IOS_DEV_PUSH;
    }

    /**
     * customMessage function
     *
     * Send a message with a nested custom payload to perform additional trickery within application
     *
     * @access public
     *
     * @param $token
     * @param $message
     * @param $messageArray
     *
     * @return void
     */
    public function customMessage($token, $message, $messageArray)
    {
        $customMessage = PushNotification::Message($message, $messageArray);

        $this->message($token, $customMessage);
    }

    /**
     * message function
     *
     * Send a plain text only message to a single device.
     *
     * @access public
     *
     * @param $token - device token
     * @param $message - user specific message
     *
     * @return void
     *
     */

    public function message($token, $message)
    {
        PushNotification::app($this->certificate)
            ->to($token)
            ->send($message);
    }

    /**
     * getFeedback function
     *
     * Returns an array of expired/invalid tokens to be removed from iOS PUSH notifications.
     *
     * We need to run this once ~ 24hrs
     *
     * @access public
     *
     * @param string $token - A valid token (can be any valid token)
     * @param string $message - Nil value for message
     *
     * @return array
     */
    public function getFeedback($token, $message = '')
    {

        $feedback = PushNotification::app($this->certificate)
            ->to($token)
            ->send($message);

        return $feedback->getFeedback();
    }

}