<?php
namespace Catalyst\Entity;

use Assert\Assertion;
use Catalyst\Exception\FileNotFoundException;
use Catalyst\Exception\MalformedProjectFileException;
use Catalyst\Interfaces\SaveableEntityInterface;
use Catalyst\Model\Uuid;
use Catalyst\Model\YoYo\Resource;
use Catalyst\Service\StorageService;
use Catalyst\Traits\JsonUnpacker;
use Ramsey\Uuid\UuidInterface;

/**
 * Class OLDYoYoProjectEntity
 * @package Catalyst\Entity
 * @deprecated
 */
class OLDYoYoProjectEntity {

    use JsonUnpacker;

    /** @var string */
    private $path;

    /** @var \stdClass */
    private $originalData;

    /** @var \Catalyst\Model\Uuid */
    public $id;

    /** @var bool */
    public $IsDnDProject = false;

    /** @var \Catalyst\Model\YoYo\Resource[] */
    public $resources;

    /** @var \Catalyst\Model\YoYo\Resource\GM\GMResource[] */
    private $_children = [];

    /** @var CatalystEntity */
    private $catalystEntity;

    /** @var array */
    public $script_order;

    /** @var StorageService */
    private $storageService;

    private function __construct()
    {

    }

    public function load(CatalystEntity $catalystEntity, StorageService $storageService)
    {
        throw new \Exception('old');
        $this->catalystEntity = $catalystEntity;
        $this->storageService = $storageService;

        try {
            Assertion::file($catalystEntity->getYypFilename());
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException('Project file ' . $catalystEntity->getYypFilename() . ' does not exist');
        }

        $projectContents = file_get_contents($catalystEntity->getYypFilename());
        $this->originalData = json_decode($projectContents);

        $this->originalData = $this->storageService->getJson();

        // Load all the resources into a map
        foreach ($this->originalData->resources as $resource) {
            try {
                $this->resources[$resource->Key] = new Resource($catalystEntity, $resource);
            } catch (FileNotFoundException $e) {
                //Ignore, probably a vendored file
            }
        }

        // Script order (todo for when installing)
        $this->script_order = $this->originalData->script_order;

        // This is somewhat the same, but unused for now, so skipperino
        /*
        foreach ($this->originalData->parentProject->alteredResources as $resource) {
            $this->resources[$resource->Key] = new Resource($depManEntity, $resource);
        }
        */

        // Add children
        foreach ($this->resources as $item) {
            if (isset($item->gmResource()->filterType) && $item->gmResource()->filterType == \Catalyst\Service\GMResourceService::GM_OPTIONS) {
                continue;
            }

            if (isset($item->gmResource()->children)) {
                foreach ($item->gmResource()->children as $childKey) {
                    if (!array_key_exists($childKey, $this->resources)) {
                        continue;//ignore because of installing stuff
                        throw new MalformedProjectFileException('Resource with GUID ' . $childKey . ' was not found, but appears to be a child of some resource.');
                    }
                    $item->gmResource()->addChild($this->resources[$childKey]->gmResource());
                }
            }

            if (isset($item->gmResource()->filterType) && $item->gmResource()->filterType == \Catalyst\Service\GMResourceService::GM_ROOT) {
                $this->_children = $item->gmResource()->getChildren();
            }
        }

        return $this;
    }

    public function gmFolderExists($foldername):bool
    {
        return $this->getGmFolderByName($foldername) instanceof Resource\GM\GMResource;
    }

    public function getGmFolderByName($foldername)
    {
        $folders = explode('/', $foldername);
        $children = $this->getChildren();
        while (count($folders)) {
            $looknow = array_shift($folders);
            $newChild = false;
            foreach ($children as $child) {
                //echo 'matching ' . (isset($child->folderName) ? $child->folderName : $child->name ) . ' ('.$child->isFolder().') vs ' . $looknow . PHP_EOL;
                if ($child->isFolder() && $child->folderName == $looknow) {
                    $newChild = $child;
                    break;
                }
            }
            if ($newChild == false) {
                return false;
            }
            $children = $newChild->getChildren();
        }
        $newChild->setFullName($foldername);
        return $newChild;
    }

    /**
     * @param $foldername
     * @return bool|Resource\GM\GMFolder
     * @throws \Exception
     */
    public function createGmFolder($foldername)
    {
        if ($this->gmFolderExists($foldername)) {
            //Already exists
            return $this->getGmFolderByName($foldername);
        }

        // Check parents
        $parentFolder = substr($foldername, 0, strrpos($foldername, '/', 0));
        if (!$this->gmFolderExists($parentFolder)) {
            $this->createGmFolder($parentFolder);
        }

        $folders = explode('/', $foldername);
        $children = $this->getChildren();
        while (count($folders) > 1) {
            $looknow = array_shift($folders);
            $newChild = false;
            foreach ($children as $child) {
                if ($child->isFolder() && $child->folderName == $looknow) {
                    $newChild = $child;
                    break;
                }
            }
            if ($newChild == false) {
                return false;
            }
            $children = $newChild->getChildren();
        }
        if (!$newChild instanceof Resource\GM\GMFolder) {
            throw new \InvalidArgumentException('Folder path is not a folder');
        }
        $newUuid = \Ramsey\Uuid\Uuid::uuid5(CatalystEntity::UUID_NS, $foldername);
        $newObj = Resource\GM\GMFolder::createNew($newUuid, $folders[0], $newChild->filterType, $foldername);

        $newFolder = Resource::createFolder($newObj);

        $this->addResource($newFolder);
        //$this->catalystEntity->addIgnore($newObj->getFilePath());
        $newChild->addChild($newObj);
        $newChild->markEdited();
        return $newObj;
    }

    /**
     * Return this project in YYP format
     */
    public function getJson()
    {
        $newObject = $this->originalData;
        $newObject->resources = array_values($this->resources);
        $newObject->script_order = $this->script_order;

        return str_replace("\n", "\r\n", json_encode($newObject, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function removeResource($id)
    {
        unset($this->resources[$id]);

        foreach ($this->_children as $key => $value) {
            if ((string) $value->id == (string) $id) {
                unset($this->_children[$key]);
            }
        }

    }

    public function addResource(Resource $resource)
    {
        foreach ($this->resources as $test) {
            if ($test->key() == $resource->key()) {
                var_dump($resource->key());
                throw new \Exception('ERROR: Resource with same key already exists in project: ');
            }
        }

        $this->resources[] = $resource;
    }

    /**
     * @return Resource[]
     */
    public function getResources():array
    {
        return $this->resources;
    }

    /**
     * @return \Catalyst\Model\YoYo\Resource\GM\GMResource[]
     */
    public function getChildren():array
    {
        return $this->_children;
    }

    public function save()
    {
        foreach ($this->resources as $resource) {
            if ($resource->gmResource()->isEdited()) {
                $resource->gmResource()->save();
            }
        }
        //$this->storageService->writeFile($this->catalystEntity->getYypFilename(), $this->getJson());
        return true;
    }
}