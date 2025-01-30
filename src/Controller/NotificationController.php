<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NotificationController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private string $botToken;

    public function __construct(HttpClientInterface $httpClient, ParameterBagInterface $params)
    {
        $this->httpClient = $httpClient;
        $this->botToken = $params->get('telegram.bot_token');
    }

    #[Route('/send-notification', methods: ['POST'])]
    public function sendNotification(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['notifications']) || !is_array($data['notifications'])) {
            return $this->json(['error' => 'Format invalide, un tableau de notifications est requis.'], Response::HTTP_BAD_REQUEST);
        }

        $notifications = $data['notifications'];
        $batchSize = 30; // Limite de 30 messages par seconde
        $batchDelay = 1000000 / $batchSize; // Délai entre les lots de messages (en microsecondes)
        $curlHandles = [];
        $mh = curl_multi_init(); // Initialisation de multi-cURL

        // Ajouter tous les messages à la file d'attente multi-cURL
        foreach ($notifications as $index => $notification) {
            if (isset($notification['chat_id'], $notification['message'])) {
                $ch = curl_init("https://api.telegram.org/bot{$this->botToken}/sendMessage");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'chat_id' => (string) $notification['chat_id'],
                    'text' => $notification['message'],
                ]);
                curl_multi_add_handle($mh, $ch);
                $curlHandles[] = $ch;
            }

            // Si nous avons atteint la taille du lot (30 messages), on attend avant d'envoyer les suivants
            if (($index + 1) % $batchSize == 0) {
                // Exécuter les requêtes en parallèle
                $this->executeMultiCurl($mh, $curlHandles);

                // Attendre un délai avant d'envoyer le prochain lot de messages
                usleep($batchDelay); // Attendre le temps nécessaire pour ne pas dépasser 30 messages par seconde
            }
        }

        // Exécuter les requêtes restantes après la boucle
        if (count($curlHandles) % $batchSize != 0) {
            $this->executeMultiCurl($mh, $curlHandles);
        }

        // Fermer les handles
        curl_multi_close($mh);

        return $this->json(['status' => 'Messages envoyés'], Response::HTTP_OK);
    }

    private function executeMultiCurl($mh, &$curlHandles)
    {
        $running = null;
        // Exécuter le multi-cURL
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        // Récupérer les réponses des cURL individuels
        foreach ($curlHandles as $ch) {
            $response = curl_multi_getcontent($ch); // Récupérer la réponse de chaque cURL
            $data = json_decode($response, true);

            // Vérifier s'il y a des erreurs (par exemple, trop de requêtes)
            if (isset($data['error_code']) && $data['error_code'] == 429) {
                // Si trop de requêtes, on attend un peu avant de réessayer
                sleep(1);  // Attendre 1 seconde avant de réessayer
            }

            // Si nécessaire, tu peux faire des actions supplémentaires avec la réponse ici.
        }

        // Supprimer chaque handle et fermer le cURL
        foreach ($curlHandles as $ch) {
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
    }
}

   


