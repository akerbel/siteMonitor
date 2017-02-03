<?php

require_once './vendor/autoload.php';

use Noodlehaus\Config;
use GuzzleHttp\Client;

// Получаем аргументы команды
$options = getopt('l:s::');
var_dump($options);
$link = $options['l'];

// Загружаем конфиг
if (!empty($options['s'])) {
    $settings = Config::load('./settings/'.$options['s']);
} else {
    $settings = Config::load('./settings/default.json');
}

// Создаем HTTP клиент
$httpClient = new Client();

// Создаем экземпляр транспорта сообщений
$messageTransportClassName = 'Monitor\Transport\\'.$settings->get('message_transport');
$messageTransport = new $messageTransportClassName($settings);

// Тут храним информацию об уже отправленных сообщениях
$sent = [];
// Тут храним время, когда обнаружено падение сайта
$failedTime = null;

while (1) {

    try {
        // Дергаем заголовки
        // Ответы с ошибкой вызовут Exception.
        $res = $httpClient->request('HEAD', $link);

        // На случай если получим ответ 2**, который не вызовет Exception.
        if ($res->getStatusCode() != 200) {
            throw new \Exception('Status code isnot 200', $res->getStatusCode());
        }

        echo $res->getStatusCode()."\n";

        // Если до этого сайт лежал, а теперь поднялся
        if ($failedTime !== null) {

            // Если уже отправляли сообщения о падении
            if (!empty($sent)) {
                // то отправляем письмо о починке
                if ($messageTransport->send(
                        "Сервер поднялся!\r\n" .
                        "link: $link\r\n" .
                        'Was failed from: ' . getFormattedTime($failedTime) . "\r\n",
                        'Server UP!'
                    )
                ) {
                    echo "The positive message was sent\n";
                    // Обнуляем данные об отправленных сообщениях
                    $sent = [];
                    // Обнуляем время падения
                    $failedTime = null;
                } else {
                    echo "The positive message wasn`t sent\n";
                }
            } else {
                // Обнуляем время падения
                $failedTime = null;
            }

        }

    } catch (\Exception $e) {

        // Если это первый ответ с ошибкой, то просто запоминаем время его получения.
        if ($failedTime === null) {

            $failedTime = time();

        // Если это НЕ первый ответ с ошибкой
        } else {

            $time = time();

            // Считаем сколько времени сайт лежит
            $difference = $time - $failedTime;

            // Если количество уже отправленных сообщений меньше количества возможных сообщений в конфиге
            // и если время простоя больше времени отправки следующего письма
            if ( (count($sent) < count($settings->get('time'))) and ($difference >= $settings->get('time')[count($sent)]) ) {

                // отсылаем письмо о падении
                if ($messageTransport->send(
                            "The server is down!\r\n".
                            "link: $link\r\n".
                            "code: {$e->getCode()}\r\n".
                            "message: {$e->getMessage()}\r\n".
                            'Is failed from: '.getFormattedTime($failedTime)."\r\n".
                            'Down time: '.getFormattedInterval($difference)."\r\n".
                            'Notification #'.(count($sent)+1)."\r\n",
                            'Server down'
                        )
                ){
                    // Запоминаем время отправки письма
                    echo "The negative message was sent\n";
                    $sent[] = $time;
                } else {
                    echo "The negative message wasn`t sent\n";
                }

            }

        }

    }

    sleep($settings->get('delay'));

}

/**
 * Отформатировать юникс время в читаемую дату-время
 *
 * @param $timestamp
 * @return string
 */
function getFormattedTime($timestamp) {
    return \DateTime::createFromFormat('U', $timestamp)->format('H:i:s d.m.Y');
}

/**
 * Отформатировать юникс время в читаемый интервал времени
 *
 * @param $timestamp
 * @return string
 */
function getFormattedInterval($timestamp) {
    return (new \DateInterval('PT'.$timestamp.'S'))->format('%d days %H hours %i minutes');
}
