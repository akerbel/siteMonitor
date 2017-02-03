<?php

// Создаем дочерний процесс
$child_pid = pcntl_fork();
if ($child_pid) {
    // Выходим из родительского, привязанного к консоли, процесса
    exit();
}
// Делаем основным процессом дочерний.
posix_setsid();

$baseDir = dirname(__FILE__);

// Переопределяем вывод
ini_set('error_log',$baseDir.'/error.log');
fclose(STDIN);
fclose(STDOUT);
fclose(STDERR);
$STDIN = fopen('/dev/null', 'r');
$STDOUT = fopen($baseDir.'/application.log', 'ab');
$STDERR = fopen($baseDir.'/daemon.log', 'ab');

require_once $baseDir.'/vendor/autoload.php';

use Noodlehaus\Config;
use GuzzleHttp\Client;

// Получаем аргументы команды
$options = getopt('l:s::');

$link = $options['l'];

// Загружаем конфиг
if (!empty($options['s'])) {
    $settings = Config::load($baseDir.'/settings/'.$options['s']);
} else {
    $settings = Config::load($baseDir.'/settings/default.json');
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

        echo getFormattedTime(time()).' - '.$link.' - Status: '.$res->getStatusCode()."\n";

        // Если до этого сайт лежал, а теперь поднялся
        if ($failedTime !== null) {

            // Если уже отправляли сообщения о падении
            if (!empty($sent)) {
                // то отправляем письмо о починке
                if ($messageTransport->send(
                        "Server UP!\r\n" .
                        "link: $link\r\n" .
                        'Was failed from: ' . getFormattedTime($failedTime) . "\r\n",
                        'Server UP!'
                    )
                ) {
                    echo getFormattedTime(time())." - $link - Status: {$res->getStatusCode()} The positive message was sent\n";
                    // Обнуляем данные об отправленных сообщениях
                    $sent = [];
                    // Обнуляем время падения
                    $failedTime = null;
                } else {
                    echo getFormattedTime(time())." - $link - Status: {$res->getStatusCode()} The positive message wasn`t sent\n";
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
                    echo getFormattedTime(time())." - $link - Status: {$e->getCode()} The negative message was sent\n";
                    $sent[] = $time;
                } else {
                    echo getFormattedTime(time())." - $link - Status: {$e->getCode()} The negative message wasn`t sent\n";
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
