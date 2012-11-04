<?php

namespace Euzeo\FixturesBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Yaml\Yaml;

class FixtureCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this
			->setName('euzeo:fixtures')
			->setDescription('Load db data from fixtures files')
			->addArgument('bundle', InputArgument::OPTIONAL, 'The bundle name')
			->addOption('debug', null, InputOption::VALUE_NONE, 'If set, enter debug mode')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// init
		$bundle = $input->getArgument('bundle');
		$kernel = $this->getContainer()->get('kernel');
		$path   = $kernel->locateResource('@'.$bundle.'/Data/fixtures/');

		if ($handle = opendir($path)) {
			$fixtures = array();

			// loop through each files in the fixtures folder
			while (false !== ($file = readdir($handle)))
			{
				if ('.yml' === substr($file, -4)) {
					$files[] = $file;
				}
			}
			closedir($handle);

			sort($files);

			for ($i=0; $i < count($files); $i++)
			{
				$fixtures  = array_merge($fixtures, Yaml::parse(file_get_contents($path.'/'.$files[$i])));
			}


			if (count($fixtures) > 0) {
				$em = $this->getContainer()->get('doctrine')->getEntityManager('default');

				$loader = new \Euzeo\FixturesBundle\FixtureLoader($fixtures, $em, $bundle);
				$loader->resetDatabase();
				$loader->load();
			}
		}
	}
}