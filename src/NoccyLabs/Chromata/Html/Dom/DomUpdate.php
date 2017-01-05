<?php

namespace NoccyLabs\Chromata\Html\Dom;

use NoccyLabs\Chromata\Html\Script\Fragment;

class DomUpdate
{
    // Mapping flags
    const EL_MAP            = 0x100; // map (create) the element on the client
    const EL_UNMAP          = 0x200; // unamp (remove) the element from the client

    // Dirty flags
    const DF_ATTR           = 0x01; // attributes are dirty
    const DF_VALUE          = 0x02; // values are dirty
    const DF_EVENT          = 0x04; // event listeners
    const DF_PARENT         = 0x80; // update the parent
    const DF_ELEM           = 0xFF; // the actual element need to be updated

    // Flag combinations, applied to filter flags on map/unmap etc.
    const UF_MAP_MASK       = self::DF_ATTR 
                            | self::DF_VALUE
                            | self::DF_ELEM
                            | self::DF_PARENT
                            | self::EL_MAP;
    const UF_UNMAP_MASK     = self::EL_UNMAP;

    /** @var Element $target The target element*/
    protected $target;
    /** @var int $dirty_flag Flags indicating what parts of the element is dirty */
    protected $flags;

    protected $dirty;

    public function __construct(Element $target)
    {
        $this->target = $target;
        $this->dirty = (object)[
            'attrs'     => [],      // dirty attributes
            'value'     => null,    // dirty value
            'events'    => [],      // dirty events,
            'parent'    => null,    // new parent
        ];
    }

    /**
     * Return modification flags
     *
     * @return int the flags
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Returns true if the element has been updated
     *
     * @return bool True if dirty
     */
    public function isDirty()
    {
        return ($this->flags > 0);
    }

    /**
     * Get the target element
     *
     * @return Element The element
     */
    public function getTarget()
    {
        return $this->target;
    }

    public function setAttribute($attr)
    {
        $this->flags |= self::DF_ATTR;
        $this->dirty->attrs[$attr] = true;
    }

    public function setValue($value)
    {
        $this->flags |= self::DF_VALUE;
        $this->dirty->value = $value;
    }

    public function addEventListener(EventListener $listener)
    {
        $this->flags |= self::DF_EVENT;
        $this->dirty->events[] = $listener;
    }

    public function setParent(Element $parent)
    {
        $this->flags |= self::DF_PARENT;
    }

    public function mapElement()
    {
        $this->flags |= self::DF_ELEM | self::EL_MAP;
        $this->flags &= self::UF_MAP_MASK;
        $this->dirty->value = $this->target->getValue();
        $this->dirty->attrs = array_merge($this->target->getAttributes(), $this->dirty->attrs);
    }

    public function unmapElement()
    {
        // clear all flags, we only need the unmap.
        $this->flags |= self::EL_UNMAP;
        $this->flags &= self::UF_MAP_MASK;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * Return the fragment to place on the queue
     *
     * @return array The fragment data
     */
    protected function createFragment()
    {
        if ($this->flags == 0) { return null; }

        //l_debug("dirty=%04x, attr=[%s]", $this->flags, join(",",array_keys($this->dirty->attrs)));

        $fragment = new Fragment();
        $id = $this->target->getId();

        if ($this->flags & self::EL_UNMAP) {
            // Unmap (remove) element
            $fragment->add('var el=document.getElementById(%s);', $this->target->getId());
            $fragment->add('el.parentElement.removeChild(el);');
            $this->clearDirtyState();
            return $fragment;
        } elseif ($this->flags & self::EL_MAP) {
            // Map (create) element
            $fragment->add('var el=document.createElement(%s);',$this->target->getName());
        } else {
            $fragment->add('var el=document.getElementById(%s);', $this->target->getId());
        }

        if ($this->flags & self::DF_ATTR) {
            $attrs = $this->target->getAttributes();
            foreach ($this->dirty->attrs as $attr=>$dirty) {
                if (!$dirty) continue;
                $value = $attrs[$attr];
                $fragment->add('el.setAttribute(%s,%s);', $attr, $value);
            }
        }

        if ($this->flags & self::DF_VALUE) {
            //$value_id = $this->target->getId().'_value';
            //$fragment->add('var sp=document.createElement("span");');
            //$fragment->add('sp.id=%s;', $value_id);
            $fragment->add('el.innerHTML=%s;', $this->dirty->value);
            /*if ($this->dirty->value) {
                $fragment->add('sp.appendChild(document.createTextNode(%s));', $this->dirty->value);
            }
            if ($this->flags & self::EL_MAP) {
                $fragment->add('el.appendChild(sp);');
            } else {
                $fragment->add('var old_sp=document.getElementById(%s);', $value_id);
                $fragment->add('if (old_sp) el.replaceChild(sp, old_sp); else el.appendChild(sp);');
            }
            */
        }

        if ($this->flags & self::DF_PARENT) {
            if ($this->dirty->parent == null) {
                $fragment->add('document.body.appendChild(el);');
            } else {
                $fragment->add('var pe=document.getElementById(%s);', $this->dirty->parent->getId());
                $fragment->add('pe.appendChild(el);');
            }
        }

        if ($this->flags & self::DF_EVENT) {
            foreach ($this->dirty->events as $listener) {
                // session.eventProxy.bind("load", "mousemove", "def2345");
                $fragment->add('Chromata.bindDomEvent(%s,%s,%s);', $this->target->getid(), $listener->getType(), $listener->getToken());
            }
        }


        $this->clearDirtyState();
        
        $children = $this->target->getChildren();
        foreach ($children as $child) {
            $fragment.=$child->getUpdate();
        }
        
        return $fragment;

        /*
        // OLD --------------------------------------------------------------------------------
        switch ($this->type) {
            case self::UT_DOM_INSERT: // (element,parent)
                $parent = (count($this->data)>0)?$this->data[0]:null;
                $fragment->add('var el=document.createElement(%s);',$this->target->getName());
                $fragment->add('el.setAttribute("id",%s);', $this->target->getId());
                $content = htmlspecialchars($this->target->getValue());
                $fragment->add('el.innerHTML="'.$content.'";');
                if ($parent === null) {
                    $fragment->add('document.body.appendChild(el);');
                } else {
                    $fragment->add('var pe=document.getElementById("'.$parent->getId().'");');
                    $fragment->add('pe.appendChild(el);');
                }
                return $fragment;
            case self::UT_DOM_REMOVE: // (id)
                $id = (count($this->data)>0)?$this->data[0]:$this->getId();
                $fragment->add('var el=document.getElementById(%s);', $id);
                $fragment->add('el.parentElement.removeChild(el);');
                return $fragment;
            case self::UT_DOM_UPDATE: // (element)
                list($target) = $this->data;
                $fragment->add('var el=document.getElementById("'.$target->getName().'");');
                $content = htmlspecialchars($target->getValue());
                $fragment->add('el.innerHTML="'.$content.'";');
                foreach ($target->getAttributes() as $attr=>$value) {
                    $fragment->add('el.setAttribute(%s,%s);', $attr, $value);
                }
                return $fragment;
            case self::UT_DOM_SETATTR: // (id, attr[])
                $attrs = (count($this->data)>0)?$this->data[0]:[];
                $fragment->add('var el=document.getElementById("'.$target->getId().'");');
                foreach ($attrs as $attr=>$value) {
                    $value = htmlspecialchars($value);
                    $fragment->add('el.setAttribute("'.$value.'","'.$value.'");');
                }
                $fragment->add('window.EventProxy.bindEvent(el,"'.$event.'","'.$token.'")');
                return $fragment;
            case self::UT_DOM_REPARENT: // (id)
                $child = (count($this->data)>0)?$this->data[0]:null;
                if ($child instanceof Element) {
                    $child = $child->getId();
                }
                $fragment->add('var el=document.getElementById(%s);', $this->target->getId());
                $fragment->add('var ch=document.getElementById(%s);', $child);
                $fragment->add('el.appendChild(ch);');
                //$fragment->add('ch.parentElement.remove(ch);');
                return $fragment;
            case self::UT_EVENT_LISTEN: // (id, event, token)
                list ($target, $event, $token) = $this->data;
                $fragment->add('var el=document.getElementById("'.$target->getId().'");');
                $fragment->add('window.EventProxy.bindEvent(el,"'.$event.'","'.$token.'")');
                return $fragment;                
            case self::UT_EVENT_UNLISTEN: // (token)
            case self::UT_HTML_INNER: // (id, html)
                $html = (count($this->data)>0)?$this->data[0]:'undefined';
                $fragment->add('document.getElementById("'.$target->getId().'").innerHTML=%s;', $html);
                return $fragment;
            case self::UT_HTML_OUTER: // (id, html)
                list ($target, $html) = $this->data;
                $html = htmlspecialchars($html);
                $fragment->add('document.getElementById("'.$target->getId().'").outerHTML="'.$html.'";');
                return $fragment;
        }
        */
    }

    protected function clearDirtyState()
    {
        $this->flags = 0;
        $this->dirty->attrs = [];
        $this->dirty->events = [];
    }

    public function getFragment()
    {
        return $this->createFragment();
    }

    public function getScript()
    {
        $fragment = $this->createFragment();
        if ($fragment instanceof Fragment) {
            return $fragment->getScript();
        }
        return $fragment;
    }

}
