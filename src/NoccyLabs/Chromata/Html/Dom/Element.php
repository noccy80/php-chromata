<?php

namespace NoccyLabs\Chromata\Html\Dom;

class Element
{
    /** @var string $id The element ID; updated from attrs */
    protected $id;
    /** @var string $name The tag/element name */
    protected $name;
    /** @var bool $mappable If false, the element DOM will never be mapped to the client */
    protected $mappable = true;
    /** @var string $value InnerHTML value for element */
    protected $value = null;
    /** @var Element[] $children The children of the element */
    protected $children = [];
    /** @var String[] $attr Element attributes */
    protected $attr = [];
    /** @var mixed $res Resources (strings, data etc) */
    protected $res = [];
    /** @var DomUpdate $update DOM update tracker */
    protected $update;

    use EventTargetTrait;

    public function __construct($name, $value=null, $attr=null, $id=null)
    {
        $this->update = new DomUpdate($this);

        $this->name = $name;
        $this->value = $value;
        $this->attr = (array)$attr;
        $this->setId($this->generateId());

        $this->update->mapElement();
    }

    public function generateId()
    {
        $id = $this->name."_".spl_object_hash($this);
        return $id;
    }

    public function setId($id)
    {
        /*
        $old = $this->id;
        if ($id == $old) {
            // No need to rebuild the dom if the ID is unchanged
            return;
        }
        */
        $this->id = $id;
        $this->attr['id'] = $id;
        $this->update->setAttribute('id', $id);
        /*
        // Insert the new element (this)
        $this->addUpdate(Update::UT_DOM_INSERT);
        // Re-map children
        foreach ($this->children as $child) {
            $this->addUpdate(Update::UT_DOM_REPARENT, $child);
        }
        // Delete old element
        $this->addUpdate(Update::UT_DOM_REMOVE, $old);
        */

        return $this;
    }

    /**
     * Get the ID of the element.
     * All elements have an Id, which will be randomly generated unless set.
     * As elements are updated when they are created, if you want a custom Id
     * you should pass it 
     *
     */
    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setValue($value)
    {
        $this->value = $value;
        $this->update->setValue($value);
        return $this;
    }

    public function setParent(Element $element)
    {
        $this->parent = $element;
        $this->update->setParent($element);
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setAttribute($attr, $value)
    {
        $this->attr[$attr] = $value;
        $this->update->setAttribute($attr);
        return $this;
    }

    public function getAttribute($attr)
    {
        return (array_key_exists($attr,$this->attr)?$this->attr[$attr]:null);
    }

    public function getAttributes()
    {
        return $this->attr;
    }

    public function appendChild(Element $child)
    {
        $this->children[] = $child;
        $child->setParent($this);
        //$this->addUpdate(Update::UT_DOM_REPARENT, $child);
        return $this;
    }

    protected function updateEventListener(EventListener $listener)
    {
        $this->update->addEventListener($listener);
    }

    public function getChildren()
    {
        return (array)$this->children;
    }

    public function findById($id)
    {
        if ($this->id == $id) { return $this; }
        foreach ($this->children as $child) {
            if (($found = $child->findById($id))) { return $found; }
        }
        return false;
    }

    public function getUpdate()
    {
        $script = $this->update->getScript();
        foreach ($this->children as $child) {
            $script.=$child->getUpdate();
        }
        return $script;
    }
}
