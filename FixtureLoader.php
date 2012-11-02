<?php

namespace Euzeo\FixturesBundle;

class FixtureLoader
{
	const TYPE_STRING   = 'string';
	const TYPE_DATETIME = 'datetime';

	/**
	 * @var array
	 */
	protected $fixtures;

	/**
	 * @var Doctrine\ORM\EntityManager
	 */
	protected $em;

	/**
	 * @var string
	 */
	protected $bundle;

	/**
	 * @var array
	 */
	protected $entities;

	/**
	 * [__construct description]
	 *
	 * @param array                       $fixtures [description]
	 * @param \Doctrine\ORM\EntityManager $em       [description]
	 * @param string                      $bundle   [description]
	 */
	public function __construct($fixtures, \Doctrine\ORM\EntityManager $em, $bundle)
	{
		$this->fixtures = $fixtures;
		$this->em       = $em;
		$this->bundle   = $bundle;
		$this->entities = array();
	}

	/**
	 * [load description]
	 * @return [type] [description]
	 */
	public function load()
	{
		// loop though each different entity type
		foreach ($this->fixtures as $model => $data)
		{
			$repos = $this->em->getRepository($this->bundle.':'.$model);

			$className = $repos->getClassName();

			// build dictionary for the current Entity model (column name, type)
			$fields = $this->getTableFields($className);

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

					if (array_key_exists(strtolower($fieldName), $fields)) {
						// check for the field type into the dictionary
						$fieldType = $fields[strtolower($fieldName)]['type'];

						if (self::TYPE_DATETIME === $fieldType) {
							$fieldValue = new \DateTime($fieldValue);
						}
					}
					elseif ($this->isComplexType($fieldName)) {
						$fieldValue = $this->entities[$fieldName][$fieldValue];
					}
					else {
						// type not found -> error
						throw new \Exception('Field '.$fieldName.' not found in dictionary');
					}

					$function = 'set'.$fieldName;
					$entity->$function($fieldValue);
				}

				$this->em->persist($entity);

				$this->entities[$model][$objectName] = $entity;
			}

			$this->em->flush();
		}
	}

	protected function getTableFields($className)
	{
		$meta = $this->em->getClassMetadata($className);
		$fieldNames = $meta->getFieldNames();

		$fields = array();
		foreach ($fieldNames as $fieldName) {
			$fields[strtolower($fieldName)] = array(
				'type' => $meta->getTypeOfField($fieldName),
				'name' => $fieldName
			);
		}

		return $fields;
	}

	protected function isComplexType($fieldName)
	{
		return array_key_exists($fieldName, $this->fixtures);
	}

	/**
	 * [resetDatabase description]
	 *
	 * @return [type] [description]
	 */
	public function resetDatabase()
	{
		$tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
		$classes = array();

		foreach ($this->fixtures as $model => $data)
		{
			$repos = $this->em->getRepository($this->bundle.':'.$model);

			$classes[] = $this->em->getClassMetadata($repos->getClassName());
		}

		$tool->dropSchema($classes);
		$tool->createSchema($classes);
	}
}