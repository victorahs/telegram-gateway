<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use App\Service\RedisService;
use Predis\Client;

class NotificationController extends AbstractController
{
    private Client $redis;

    public function __construct(RedisService $redisService)
    {
        // Connexion à Redis
        $this->redis = $redisService->getRedis();
    }

    #[Route('/send-notification', methods: ['POST'])]
    public function sendNotification(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['notifications']) || !is_array($data['notifications'])) {
            return $this->json(['error' => 'Format invalide, un tableau de notifications est requis.'], Response::HTTP_BAD_REQUEST);
        }

        foreach ($data['notifications'] as $notification) {
            if (isset($notification['chat_id'], $notification['message'])) {
                // Ajouter la notification dans la file Redis
                $this->redis->rpush('telegram_queue', json_encode($notification));
            }
        }

        return $this->json(['status' => 'Messages en file d’attente'], Response::HTTP_ACCEPTED);
    }
}
