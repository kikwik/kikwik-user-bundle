<?php


namespace Kikwik\UserBundle\Command;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserEditCommand extends BaseCommand
{
    private $passwordHasher;


    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, string $userClass, string $userIdentifierField)
    {
        parent::__construct($entityManager, $userClass, $userIdentifierField);
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setName('kikwik:user:edit')
            ->setDescription('Edit a user.')
            ->setDefinition(array(
                new InputArgument('username', InputArgument::REQUIRED, 'The user unique identifier'),
                new InputOption('roles', null, InputOption::VALUE_OPTIONAL, 'The new roles (optional, comma separated)'),
                new InputOption('password', null, InputOption::VALUE_OPTIONAL, 'The new password (optional)'),
                new InputOption('isEnabled',null,InputOption::VALUE_OPTIONAL, 'Access enabled or not',true),
            ));
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Delete a '.$this->userClass);

        $this->askForUsernameArgument($input, $output, true);
        $this->askForRolesOption($input, $output);
        $this->askForPasswordOption($input, $output);
        $user = $this->entityManager->getRepository($this->userClass)->findOneBy([$this->userIdentifierField => $input->getArgument('username')]);
        $this->askForIsEnabledOption($input, $output, $user->isEnabled());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');
        $newPassword = $input->getOption('password');
        $newRoles = $input->getOption('roles');
        $isEnabled = $input->getOption('isEnabled');

        $user = $this->entityManager->getRepository($this->userClass)->findOneBy([$this->userIdentifierField => $username]);

        if($newPassword)
        {
            $user->setPassword($this->passwordHasher->hashPassword($user,$newPassword));
        }

        if($newRoles)
        {
            $roles = explode(',',str_replace(' ','',$newRoles));
            $roles = array_diff($roles, ['ROLE_USER']);
            $user->setRoles($roles);
        }

        $user->setIsEnabled($isEnabled);
        $user->setUpdatedBy('kikwik:user:edit');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('User '.$username.' successfully edited'."\n".'roles: '.implode(', ',$user->getRoles()));

        return 0;
    }
}