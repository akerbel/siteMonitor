<?php
namespace Monitor;

use Noodlehaus\Config;

/**
 * Interface TransportInterface
 * @package Monitor
 */
interface TransportInterface
{
    /**
     * TransportInterface constructor.
     *
     * @param Config $settings
     */
    public function __construct(Config $settings);

    /**
     * Отправка сообщения
     *
     * @param string $message Текст сообщения
     * @param string $subject Заголовок сообщения
     *
     * @return bool
     */
    public function send($message, $subject = null);
}
