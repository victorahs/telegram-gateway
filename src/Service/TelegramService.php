<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Predis\Client;
use App\Service\RedisService;


class TelegramService
{
    private HttpClientInterface $httpClient;
    private string $botToken;
    private Client $redis;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params, RedisService $redisService)
    {
        $this->httpClient = $httpClient;
        $this->botToken = $params->get('telegram.bot_token');
        $this->redis = $redisService->getRedis();
    }

    public function sendMessage(array $message): bool
    {
        if (!isset($message['chat_id'], $message['message'])) {
            return false;
        }

        $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$this->botToken}/sendMessage", [
            'json' => [
                'chat_id' => $message['chat_id'],
                'text' => $message['message'],
            ]
        ]);

        $statusCode = $response->getStatusCode();
        $responseData = $response->toArray(false);

        if ($statusCode === 429) {
            // Gestion du rate limit : attendre le temps recommandé
            $retryAfter = $responseData['parameters']['retry_after'] ?? 1;
            sleep($retryAfter);

            // Réinsérer le message en file d'attente pour réessai
            $this->redis->rpush('telegram_queue', json_encode($message));

            return false;
        }

        return true;
    }
}
