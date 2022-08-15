<?php

declare(strict_types=1);

use Atakde\DiscordWebhook\Message\EmbedMessage;
use Atakde\DiscordWebhook\Message\MessageFactory;
use Atakde\DiscordWebhook\Message\TextMessage;

include __DIR__ . '/vendor/autoload.php';

function main(): void {
    $opts = getopt("", ["webhookUrl:"]);
    sendWebhook($opts["webhookUrl"], "kawaismp.ddns.net", 19132);
}

function sendWebhook($webhookUrl, $host, $port): void {
    $queryResult = query($host, (int)$port);

    /** @var EmbedMessage $embed */
    $embed = MessageFactory::create('embed');
    $embed
        ->setTitle(":desktop: There are {$queryResult["Players"]} players playing on the server.\n:arrow_up_small: Vote the server at https://bit.ly/kawaismpvote\n:sparkles: Join the fun at kawaismp.ddns.net 19132");

    send($webhookUrl, $embed);
}

function send($webhookUrl, TextMessage|EmbedMessage $message): void {
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $message->toJson());
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

    curl_exec($ch);
    var_dump(curl_getinfo($ch, CURLINFO_RESPONSE_CODE));
    curl_close($ch);
}

function query(string $host, int $port, int $timeout = 4): array {
    $socket = @fsockopen('udp://' . $host, $port, $errno, $errstr, $timeout);

    if ($errno and $socket !== false) {
        fclose($socket);
        throw new \RuntimeException($errstr, $errno);
    }

    if ($socket === false) {
        throw new \RuntimeException($errstr, $errno);
    }

    stream_set_timeout($socket, $timeout);
    stream_set_blocking($socket, true);

    $OFFLINE_MESSAGE_DATA_ID = \pack('c*', 0x00, 0xFF, 0xFF, 0x00, 0xFE, 0xFE, 0xFE, 0xFE, 0xFD, 0xFD, 0xFD, 0xFD, 0x12, 0x34, 0x56, 0x78);
    $command = \pack('cQ', 0x01, time());
    $command .= $OFFLINE_MESSAGE_DATA_ID;
    $command .= \pack('Q', 2);
    $length = \strlen($command);

    if ($length !== fwrite($socket, $command, $length)) {
        throw new \RuntimeException("Failed to write on socket.", E_WARNING);
    }

    $data = fread($socket, 4096);
    fclose($socket);

    if (empty($data)) {
        throw new \RuntimeException("Server failed to respond", E_WARNING);
    }
    if ($data[0] !== "\x1C") {
        throw new \RuntimeException("First byte is not ID_UNCONNECTED_PONG.", E_WARNING);
    }
    if (substr($data, 17, 16) !== $OFFLINE_MESSAGE_DATA_ID) {
        throw new \RuntimeException("Magic bytes do not match.");
    }
    $data = \substr($data, 35);
    $data = \explode(';', $data);

    return [
        'GameName' => $data[0] ?? null,
        'HostName' => $data[1] ?? null,
        'Protocol' => $data[2] ?? null,
        'Version' => $data[3] ?? null,
        'Players' => $data[4] ?? null,
        'MaxPlayers' => $data[5] ?? null,
        'ServerId' => $data[6] ?? null,
        'Map' => $data[7] ?? null,
        'GameMode' => $data[8] ?? null,
        'NintendoLimited' => $data[9] ?? null,
        'IPv4Port' => $data[10] ?? null,
        'IPv6Port' => $data[11] ?? null,
        'Extra' => $data[12] ?? null
    ];
}

main();