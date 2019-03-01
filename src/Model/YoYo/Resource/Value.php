<?php
namespace GMDepMan\Model\YoYo\Resource;

use Assert\Assertion;
use GMDepMan\Entity\DepManEntity;
use GMDepMan\Model\YoYo\Resource\GM\GMResource;
use GMDepMan\Model\YoYo\Resource\GM\GMResourceTypes;
use GMDepMan\Traits\JsonUnpacker;

class Value {
    use JsonUnpacker {
        unpack as protected traitUnpack;
    }

    /** @var \GMDepMan\Model\Uuid */
    public $id;

    /** @var string */
    public $resourcePath;

    /** @var string */
    public $resourceType;

    /** @var \GMDepMan\Model\YoYo\Resource\GM\GMResource */
    private $resource;

    public function unpack($originalData, DepManEntity $depmanEntity)
    {
        $this->traitUnpack($originalData, $depmanEntity);

        if ($this->resourceType == 'GMResource') {
            return; //Ignore this layer
        }

        Assertion::keyExists(
            GMResourceTypes::TYPEMAP,
            $this->resourceType,
            'Type "' . $this->resourceType . '" not found in typemap'
        );

        $className = GMResourceTypes::TYPEMAP[$this->resourceType];
        $this->resource = new $className($depmanEntity->getProjectPath() . '/' . $this->resourcePath, $depmanEntity);
    }

    public function getGmResource():GMResource
    {
        return $this->resource;
    }
}