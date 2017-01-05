<?php

namespace NoccyLabs\Chromata\Object;

class ObjectManager
{
    protected $objects = [];

    public function add($object, $key=null, callable $refresh_func=null)
    {
        if (!($object instanceof RefreshAwareInterface || is_callable($refresh_func))) {
            l_error("Unable to add object of class %s without RefreshAwareInterface or a refresh func", get_class($object));
        }
        $obj = (object)[
            'object' => $object,
            'key' => $key?:spl_object_hash($object),
            'refresh' => $refresh_func,
        ];
        $this->objects[$obj->key] = $obj;
        return $this;
    }

    public function remove($object, $key=null)
    {
        $key = $key?:spl_object_hash($object);
        foreach ($this->objects as $index=>$obj) {
            if ($key == $obj->key) {
                unset($this->objects[$index]);
            }
        }
        return $this;
    }

    public function refresh()
    {
        foreach ($this->objects as $id=>$data) {
            if (is_callable($data->refresh)) {
                $ret = call_user_func($data->refresh, $data->object);
            } else {
                $ret = $data->object->refresh();
            }
            if ($ret === false) {
                unset($this->objects[$id]);
            }
            return $this;
        }
        return $this;
    }

}