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
	 * EntityManager
	 */
	protected $em;

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
        $this->bundle = $input->getArgument('bundle');

        $this->em = $this->getContainer()->get('doctrine')->getEntityManager('default');

        $kernel = $this->getContainer()->get('kernel');
		$path = $kernel->locateResource('@'.$this->bundle.'/Data/fixtures/');

		$this->isDebug = TRUE === $input->getOption('debug');

		if ($this->isDebug) {
			$output->writeln('Reading yml files from folder '.$path);
		}

        if ($handle = opendir($path))
        {
		    while (false !== ($entry = readdir($handle)))
		    {
		        if ( substr($entry, -4) === '.yml' )
		        {
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

    	// in this array we'll store all entity object
    	$entities = array();
		
    	foreach ($this->fixtures as $model => $data)
    	{
    		$repos = $this->em->getRepository($this->bundle.':'.$model);

    		$className = $repos->getClassName();
    		$entity = new $className;

    		$entities[$model] = array();

    		foreach ($data as $objectName => $field) 
    		{
    			$entity = new $className;

    			foreach ($field as $fieldName => $fieldValue)
    			{
    				$fieldName = ucwords($fieldName);
					$function = 'set'.$fieldName;

    				if ($this->isComplexType($fieldName))
    				{
    					// we need to fetch the object first
    					$complexObject = $entities[$fieldName][$fieldValue];

    					$entity->$function($complexObject);
    				}
    				else {
    					$entity->$function($fieldValue);
    				}
    			}

    			//$this->em->persist($entity);
				//$this->em->flush();

				$entities[$model][$objectName] = $entity;
    		}
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