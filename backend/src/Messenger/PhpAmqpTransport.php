<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\DeliveryTagStamp;
use App\Message\ImportProductsMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class PhpAmqpTransport implements TransportInterface
{
    private const QUEUE = 'products_import';

    private ?AMQPStreamConnection $connection = null;

    private ?AMQPChannel $channel = null;

    public function __construct(private readonly string $dsn)
    {
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        if (!$message instanceof ImportProductsMessage) {
            throw new \InvalidArgumentException('Unsupported message');
        }
        $payload = json_encode(['jobId' => $message->jobId], JSON_THROW_ON_ERROR);
        $amqp = new AMQPMessage($payload, [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        $this->channel()->basic_publish($amqp, '', self::QUEUE);

        return $envelope;
    }

    public function get(): iterable
    {
        $message = $this->channel()->basic_get(self::QUEUE);
        if (!$message instanceof AMQPMessage) {
            return [];
        }
        $decoded = json_decode($message->getBody(), true);
        if (!is_array($decoded) || !isset($decoded['jobId']) || !is_string($decoded['jobId'])) {
            $this->channel()->basic_nack($message->getDeliveryTag(), false, false);

            return [];
        }
        return [new Envelope(new ImportProductsMessage($decoded['jobId']), [new DeliveryTagStamp($message->getDeliveryTag())])];
    }

    public function ack(Envelope $envelope): void
    {
        $stamp = $envelope->last(DeliveryTagStamp::class);
        if ($stamp instanceof DeliveryTagStamp) {
            $this->channel()->basic_ack($stamp->tag);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $stamp = $envelope->last(DeliveryTagStamp::class);
        if ($stamp instanceof DeliveryTagStamp) {
            $this->channel()->basic_nack($stamp->tag, false, false);
        }
    }

    public function close(): void
    {
        if ($this->channel instanceof AMQPChannel && $this->channel->is_open()) {
            $this->channel->close();
        }
        if ($this->connection instanceof AMQPStreamConnection && $this->connection->isConnected()) {
            $this->connection->close();
        }
        $this->channel = null;
        $this->connection = null;
    }

    private function channel(): AMQPChannel
    {
        if ($this->connection instanceof AMQPStreamConnection && $this->connection->isConnected() && $this->channel instanceof AMQPChannel && $this->channel->is_open()) {
            return $this->channel;
        }
        $parts = parse_url($this->dsn);
        if (!is_array($parts) || !isset($parts['host'])) {
            throw new \RuntimeException('Invalid RABBITMQ_DSN');
        }
        $vhost = '/';
        if (isset($parts['path'])) {
            $decoded = rawurldecode(ltrim((string) $parts['path'], '/'));
            $vhost = $decoded === '' ? '/' : $decoded;
        }
        $this->connection = new AMQPStreamConnection(
            (string) $parts['host'],
            (int) ($parts['port'] ?? 5672),
            isset($parts['user']) ? rawurldecode((string) $parts['user']) : 'guest',
            isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : 'guest',
            $vhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            130.0,
            null,
            false,
            0,
        );
        $channel = $this->connection->channel();
        $channel->queue_declare(self::QUEUE, false, true, false, false);
        $channel->basic_qos(0, 1, false);
        $this->channel = $channel;

        return $channel;
    }
}
