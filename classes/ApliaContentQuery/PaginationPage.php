<?php
namespace ApliaContentQuery;

class PaginationPage
{
    public $offset = null;
    public $size = null;
    public $num = 0;

    public function __construct($offset, $size, $num)
    {
        $this->offset = $offset;
        $this->size = $size;
        $this->num = $num;
    }

    public function hasAttribute($key)
    {
        return isset($this->$key);
    }

    public function attribute($key)
    {
        return $this->$key;
    }

    public function attributes($key)
    {
        return array_keys( get_object_vars($this) );
    }
}
