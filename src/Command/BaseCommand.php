<?php


namespace Kikwik\UserBundle\Command;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand extends Command 
{
    /**
     * @var string
     */
    protected $userClass;

    /**
     * @var string
     */
    protected $userIdentifierField;

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    protected $entityManager;

    public function __construct(string $userClass, string $userIdentifierField, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->userClass = $userClass;
        $this->userIdentifierField = $userIdentifierField;
        $this->entityManager = $entityManager;
    }

    protected function askForUsernameArgument(InputInterface $input, OutputInterface $output, $mustExists)
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->getArgument('username'))
        {
            $question = $mustExists ? 'Please choose an existing username' : 'Please choose a username';

            $input->setArgument('username', $io->ask($question.' ('.$this->userIdentifierField.')',null,function ($value) use ($mustExists){
                if (!$value) {
                    throw new \RuntimeException('Username can not be empty');
                }

                $user = $this->entityManager->getRepository($this->userClass)->findOneBy([$this->userIdentifierField => $value]);
                if($mustExists)
                {
                    if(!$user)
                    {
                        throw new \RuntimeException('User '.$value.' does not exists');
                    }
                }
                else
                {
                    if($user)
                    {
                        throw new \RuntimeException('User '.$value.' already exists');
                    }
                }

                return (string) $value;
            }));
        }
    }

    protected function askForPasswordArgument(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->getArgument('password'))
        {
            $input->setArgument('password', $io->ask('Please choose a password',null,function ($value){
                if (!$value) {
                    throw new \RuntimeException('Password can not be empty');
                }

                return (string) $value;
            }));
        }
    }

    protected function askForPasswordOption(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $input->setOption('password',$io->ask('Please choose a password (leave blank if you don\'t want to change it)',null));
    }

    protected function askForRolesOption(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->entityManager->getRepository($this->userClass)->findOneBy([$this->userIdentifierField => $input->getArgument('username')]);

        $input->setOption('roles',$io->ask('Please enter the new user roles (comma separated, enter ROLE_USER to delete all extra roles)',implode(', ',$user->getRoles())));
    }
}