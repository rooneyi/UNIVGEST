<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Créer un utilisateur admin',
)]
class CreateAdminCommand extends Command
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
        $this->em = $em;
        $this->hasher = $hasher;
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email de l\'admin');
        $this->addArgument('password', InputArgument::REQUIRED, 'Mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Email invalide.</error>');
            return Command::FAILURE;
        }

        if (!preg_match('/^[^@]+@esisalama\.org$/', $email)) {
            $output->writeln('<error>L\'adresse email doit se terminer par @esisalama.org</error>');
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN','ROLE_GESTIONNAIRE']);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>Admin créé avec succès !</info>');
        return Command::SUCCESS;
    }
}
