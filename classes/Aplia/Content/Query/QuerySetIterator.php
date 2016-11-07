<?php
namespace Aplia\Content\Query;

class QuerySetIterator implements \Iterator
{
    public function __construct($querySet, $paginator)
    {
        $this->querySet = $querySet;
        $this->paginator = $paginator;
        $this->count = $paginator->count;
        $this->pageCount = $paginator->pageCount;
        $this->iterator = null;
        $this->page = null;
    }

    public function current()
    {
        if (!$this->iterator || !$this->iterator->valid()) {
            throw new \Exception("Cannot access current() on QuerySetIterator, it is not valid");
        }
        return $this->iterator->current();
    }

    public function key()
    {
        if (!$this->iterator || !$this->iterator->valid()) {
            throw new \Exception("Cannot access key() on QuerySetIterator, it is not valid");
        }
        return $this->iterator->key();
    }

    public function next()
    {
        $this->iterator->next();
        if (!$this->iterator->valid()) {
            $this->page++;
            if ($this->page > $this->pageCount) {
                return;
            }
            $querySet = $this->querySet->copy()->page($this->page);
            $this->iterator = new \ArrayIterator($querySet->items());
        }
    }

    public function rewind()
    {
        if ($this->count === null) {
            $this->count = $this->querySet->count();
        }
        if (!$this->count) {
            $this->pageCount = 0;
        }
        $this->page = 1;
        $querySet = $this->querySet->copy()->page($this->page);
        $this->iterator = new \ArrayIterator($querySet->items());
    }

    public function valid()
    {
        return $this->iterator->valid();
    }
}
