<?php

namespace webhubworks\verifiedelements\base;

/**
 * All notifications should implement this class.
 * @see Notification
 */
interface NotificationInterface
{
    /**
     * Sends the notification to the recipient via email.
     *
     * @return bool Whether the message was sent successfully
     */
    public function send(): bool;
}
