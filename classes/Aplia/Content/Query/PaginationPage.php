<?php
namespace Aplia\Content\Query;

class PaginationPage
{
    public $offset = null;
    public $size = null;
    public $num = 0;
    public $paginator;

    public function __construct($offset, $size, $num, $paginator)
    {
        $this->offset = $offset;
        $this->size = $size;
        $this->num = $num;
        $this->paginator = $paginator;
    }

    public function getPreviousPage()
    {
        if ($this->paginator) {
            return $this->paginator->getPreviousPage($this);
        }
    }

    public function getNextPage()
    {
        if ($this->paginator) {
            return $this->paginator->getNextPage($this);
        }
    }

    public function getPreviousPageNumber()
    {
        if ($this->paginator) {
            return $this->paginator->getPreviousPageNumber($this->num);
        }
    }

    public function getNextPageNumber()
    {
        if ($this->paginator) {
            return $this->paginator->getNextPageNumber($this->num);
        }
    }

    // property access
    public function __get($name)
    {
        if ($name == 'prev') {
            return $this->getPreviousPage();
        } elseif ($name == 'next') {
            return $this->getNextPage();
        } elseif ($name == 'prevNum') {
            return $this->getPreviousPageNumber();
        } elseif ($name == 'nextNum') {
            return $this->getNextPageNumber();
        }
        throw new \Exception("No such property '$name'");
    }

    public function __isset($name)
    {
        return $name == 'prev' || $name == 'next' || $name == 'prevNum' || $name == 'nextNum';
    }

    public function __slots()
    {
        return array('prev', 'next', 'prevNum', 'nextNum');
    }

    // eZ template access
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
        return array_merge(array_keys(get_object_vars($this)), $this->__slots());
    }
}
