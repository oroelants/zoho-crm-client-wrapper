<?php

namespace Wabel\Zoho\CRM\Commands;


use Logger\Formatters\DateTimeFormatter;
use Mouf\Utils\Log\Psr\MultiLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Wabel\Zoho\CRM\ZohoClient;

class ZohoCRMCommand extends Command
{
    /**
     * @var ZohoClient
     */
    private $zohoClient;


    /**
     *
     * @var MultiLogger
     */
    private $logger;

    /**
     * ZohoCRMCommand constructor.
     * @param ZohoClient $zohoClient
     */
    public function __construct(ZohoClient $zohoClient, MultiLogger $logger, string $name = null)
    {
        parent::__construct($name);
        $this->zohoClient = $zohoClient;
    }

    protected function configure()
    {
        $this
            ->setName('zohocrm:cmd')
            ->addArgument('action',InputArgument::OPTIONAL,'generate-access-token : Generate access token')
            ->addArgument('token',InputArgument::OPTIONAL,'Token')
            ->setDescription('Zoho CRM Command by using API v2')
            ->setHelp(<<<EOT
Use method from the Zoho Client
EOT
            );
        $this->addArgument('eventCode', InputArgument::REQUIRED, 'Specify the eventCode from Certain');
    }
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->logger->addLogger(new DateTimeFormatter(new ConsoleLogger($output)));
        if ($input->getArgument('action')) {
            switch ($input->getArgument('action')){
                case 'generate-access-token':
                    $this->generateAccessToken($input->getArgument('token'));
                    break;
            }
        }
    }

    public function generateAccessToken(string $grantAccessToken){
        $this->logger->info('Start - generate Access Token');
        $zohoTokenInformation =$this->zohoClient->generateAccessToken($grantAccessToken);
        $this->logger->debug('{information}',['information' => print_r($zohoTokenInformation,true)]);
        $this->logger->info('End - generate Access Token');
    }

}