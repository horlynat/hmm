<?php

namespace App\Command;

use App\Repository\InvoiceRepository;
use App\Service\ProjectNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Relance par email les clients dont une facture est en retard de paiement —
 * à planifier via un cron système (ex: quotidien). Throttlée à une relance
 * au plus tous les 7 jours par facture, voir InvoiceRepository::findOverdueForReminder().
 */
#[AsCommand(name: 'app:invoices:remind-overdue', description: 'Relance par email les factures en retard de paiement.')]
class RemindOverdueInvoicesCommand extends Command
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly ProjectNotifier $projectNotifier,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $invoices = $this->invoiceRepository->findOverdueForReminder();
        if ([] === $invoices) {
            $io->success('Aucune facture en retard à relancer.');

            return Command::SUCCESS;
        }

        foreach ($invoices as $invoice) {
            $this->projectNotifier->invoiceOverdueReminder($invoice);
            $invoice->markReminderSent();
        }
        $this->entityManager->flush();

        $io->success(sprintf('%d facture(s) relancée(s) par email.', \count($invoices)));

        return Command::SUCCESS;
    }
}
