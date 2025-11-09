<?php
// src/Command/ProcessOrdersCommand.php
namespace App\Command;

use App\Repository\OrderRepository;
use App\Service\KitchenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-orders',
    description: 'Background processor to auto-complete orders after X seconds'
)]
class ProcessOrdersCommand extends Command
{
    private OrderRepository $orderRepository;
    private KitchenService $kitchenService;

    public function __construct(
        OrderRepository $orderRepository,
        KitchenService $kitchenService
    ) {
        $this->orderRepository = $orderRepository;
        $this->kitchenService = $kitchenService;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('interval', 'i', InputOption::VALUE_OPTIONAL, 'Check interval in seconds', 10)
            ->addOption('auto-complete-after', 'a', InputOption::VALUE_OPTIONAL, 'Auto-complete orders after X seconds', 60);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $interval = (int) $input->getOption('interval');
        $autoCompleteAfter = (int) $input->getOption('auto-complete-after');

        $io->success('Order processor started!');
        $io->info(sprintf('Checking every %d seconds, auto-completing after %d seconds', $interval, $autoCompleteAfter));

        while (true) {
            try {
                $this->processOrders($io, $autoCompleteAfter);
                sleep($interval);
            } catch (\Exception $e) {
                $io->error('Error processing orders: ' . $e->getMessage());
                sleep($interval);
            }
        }

        return Command::SUCCESS;
    }

    private function processOrders(SymfonyStyle $io, int $autoCompleteAfter): void
    {
        $activeOrders = $this->kitchenService->getActiveOrders();
        $now = new \DateTimeImmutable();
        $completedCount = 0;

        foreach ($activeOrders as $order) {
            $orderAge = $now->getTimestamp() - $order->getCreatedAt()->getTimestamp();

            if ($orderAge >= $autoCompleteAfter) {
                $this->kitchenService->completeOrder($order);
                $completedCount++;
                $io->writeln(sprintf(
                    '[%s] Auto-completed order #%d (age: %ds)',
                    $now->format('Y-m-d H:i:s'),
                    $order->getId(),
                    $orderAge
                ));
            }
        }

        if ($completedCount > 0) {
            $kitchenStatus = $this->kitchenService->getKitchenStatus();
            $io->writeln(sprintf(
                'Kitchen status: %d/%d active orders, %d slots available',
                $kitchenStatus['active_orders'],
                $kitchenStatus['max_capacity'],
                $kitchenStatus['available_capacity']
            ));
        }
    }
}

// Run with: php bin/console app:process-orders
// Or with custom options: php bin/console app:process-orders --interval=5 --auto-complete-after=30