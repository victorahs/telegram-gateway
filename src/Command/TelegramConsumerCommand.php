<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use App\Service\TelegramService;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:consume-telegram',  // Le nom de la commande doit être défini ici
    description: 'Commande pour consommer les messages Telegram.'
)]
class TelegramConsumerCommand extends Command
{
   

    private $redis;
    private TelegramService $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
        $this->redis = RedisAdapter::createConnection('redis://localhost');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $batchSize = 30; // Limite de 30 messages par seconde

        while (true) {
            $messages = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $message = $this->redis->lpop('telegram_queue');
                if ($message) {
                    $messages[] = json_decode($message, true);
                }
            }

            // Envoi des messages via le service Telegram
            foreach ($messages as $message) {
                $this->telegramService->sendMessage($message);
            }

            // Respecter la limite de 30 messages par seconde
            sleep(1);
        }

        return Command::SUCCESS;
    }
}