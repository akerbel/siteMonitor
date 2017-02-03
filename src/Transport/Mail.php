<?php
namespace Monitor\Transport;

use Monitor\TransportInterface;
use Noodlehaus\Config;

/**
 * Class Mail.
 * @package Monitor\Transport
 */
class Mail implements TransportInterface
{
    /**
     * @var string Адресаты через завятую
     */
    private $to;

    /**
     * @var string Отправитель
     */
    private $from;

    /**
     * Mail constructor.
     *
     * @param Config $settings
     *
     * @throws \Exception Если в конфиге нет адресатов
     */
    public function __construct(Config $settings)
    {
        if (empty($settings->get('emails'))) {
            throw new \Exception('No emails in the settings');
        }
        $this->to = implode(', ', $settings->get('emails'));

        $this->from = $settings->get('from');
    }

    /**
     * Отправка сообщения
     *
     * @param string $message Текст сообщения
     * @param string $subject Заголовок сообщения
     * 
     * @return bool
     */
    public function send($message, $subject = 'A message from siteMonitor')
    {
        return mail(
            $this->to,
            $subject,
            wordwrap(
                $message, 70, "\r\n"
            ),
            'From: ' . $this->from . "\r\n"
        );
    }

}
