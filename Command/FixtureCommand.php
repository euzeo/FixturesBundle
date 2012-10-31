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
	/**
	 * @var EntityManager
	 */
	protected $em;
	
	/**
	 * @var array In this array we'll store all entity objects
	 */
	protected $entities;
	
	/**
	 * @var string
	 */
	protected $bundle;

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
		$this->bundle = $input->getArgument('bundle');
		$this->em = $this->getContainer()->get('doctrine')->getEntityManager('default');        
		$this->entities = array();
		$this->isDebug = TRUE === $input->getOption('debug');
		

		$kernel = $this->getContainer()->get('kernel');
		$path = $kernel->locateResource('@'.$this->bundle.'/Data/fixtures/');		

		if ($this->isDebug) {
			$output->writeln('Reading yml files from folder '.$path);
		}

		if ($handle = opendir($path)) {
			// loop through each files in the fixtures folder
			while (false !== ($entry = readdir($handle)))
			{
				if ( substr($entry, -4) === '.yml' ) {
					if ($this->isDebug) $output->writeln('Found '.$entry);

					$this->loadFixtureFrom($path.$entry);
				}
			}

			closedir($handle);
		}
	}

	protected function loadFixtureFrom($file)
	{
		$this->fixtures  = Yaml::parse(file_get_contents($file));
		
		// loop though each different entity type 
		foreach ($this->fixtures as $model => $data)
		{
			$repos = $this->em->getRepository($this->bundle.':'.$model);

			$className = $repos->getClassName();
			
			// initialize the array for the current model
			$this->entities[$model] = array();

			// loop through each entity object 
			foreach ($data as $objectName => $field) 
			{
				// create the entity object
				$entity = new $className;

				// loop through each entity field
				foreach ($field as $fieldName => $fieldValue)
				{
					// format $fieldName to match the property inside the Entity
					$fieldName = ucwords($fieldName);				

					if ($this->isComplexType($fieldName)) {
						// we need to fetch the object first
						$fieldValue = $this->entities[$fieldName][$fieldValue];    					
					}
					
					$function = 'set'.$fieldName;
					$entity->$function($fieldValue);
				}

				//$this->em->persist($entity);

				$this->entities[$model][$objectName] = $entity;
			}
			
			//$this->em->flush();
		}

		if ($this->isDebug) {
			var_dump($entities);
		}
	}

	protected function isComplexType($fieldName)
	{
		// TODO: reflect the field inside the entity to know the exact type

		return array_key_exists($fieldName, $this->fixtures);
	}
}