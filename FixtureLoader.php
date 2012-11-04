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
	 * @var array
	 */
	protected $dictionary;

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
		$this->dictionary = $this->buildDictionnary();

		// loop though each different entity type
		foreach ($this->fixtures as $model => $data)
		{
			$repos = $this->em->getRepository($this->bundle.':'.$model);

			$className = $repos->getClassName();

			// initialize the array for the current model
			$this->entities[$className] = array();

			// loop through each entity object
			foreach ($data as $objectName => $field)
			{
				$object = $this->buildObject($className, $field);

				// save the entitie in array for future reference
				$this->entities[$className][$objectName] = $object;
			}
		}
	}

	protected function buildObject($className, $fields)
	{
		$object = new $className;

		// loop through each entity field
		foreach ($fields as $name => $value)
		{
			// looking for the field $name into the dictionay entry for the current $className
			if (array_key_exists(strtolower($name), $this->dictionary[$className]['fields'])) {

				$type = $this->dictionary[$className]['fields'][strtolower($name)]['type'];

				if (self::TYPE_DATETIME === $type) {
					$value = new \DateTime($value);
				}

				// create the function name (ex: validUntil --> setValidUntil)
				$function = 'set'.ucwords($name);
				$object->$function($value);
			}
			// looking for the assoction $name into the dictionay entry for the current $className
			elseif (array_key_exists(strtolower($name), $this->dictionary[$className]['associations'])) {
				$type = $this->dictionary[$className]['associations'][strtolower($name)]['type'];

				if (is_array($value)) {
					// create the function name (ex: Children --> addChildren)
					$function = 'add'.ucwords($name);

					foreach ($value as $row)
					{
						// if the row is an array it means that we are facing a sub-object declaration
						if (is_array($row)) {
							// create the object
							$subOject = $this->buildObject($type, $row);
						}
						// otherwise the $row is a reference to an existing object
						else {
							// get the object reference
							$subOject = $this->entities[$type][$row];
						}

						$object->$function($subOject);
					}
				}
				else {
					// get the object reference
					$value = $this->entities[$type][$value];

					// create the function name (ex: parent --> setParent)
					$function = 'set'.ucwords($name);
					$object->$function($value);
				}
			}
			else {
				// type not found -> error
				throw new \Exception('Entity '.$className.' doesn\'t have a field named '.$name);
			}
		}

		$this->em->persist($object);
		$this->em->flush();

		return $object;
	}

	protected function buildDictionnary()
	{
		$dictionary = array();

		foreach (array_keys($this->fixtures) as $model)
		{
			$repos = $this->em->getRepository($this->bundle.':'.$model);

			$className = $repos->getClassName();

			$meta = $this->em->getClassMetadata($className);

			$dictionary[$className] = array(
				'fields'       => self::getTableFields($meta, $className),
				'associations' => self::getTableAssociations($meta, $className)
			);
		}

		return $dictionary;
	}

	static protected function getTableFields($meta, $className)
	{
		$fields = array();
		$fieldNames = $meta->getFieldNames();

		foreach ($fieldNames as $fieldName) {
			$fields[strtolower($fieldName)] = array(
				'type' => $meta->getTypeOfField($fieldName),
				'name' => $fieldName
			);
		}

		return $fields;
	}

	static protected function getTableAssociations($meta, $className)
	{
		$associations = array();
		$associationNames = $meta->getAssociationNames();

		foreach ($associationNames as $associationName) {
			$associations[strtolower($associationName)] = array(
				'type' => $meta->getAssociationTargetClass($associationName),
				'name' => $associationName
			);
		}

		return $associations;
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