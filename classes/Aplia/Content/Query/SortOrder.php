<?php
namespace Aplia\Content\Query;

class SortOrder
{
    public $sortMap = array();
    public $default = null;
    public $originalIdentifier = null;
    public $identifier = null;
    public $order = 0;
    public $sortArray = null;

    public function __construct($sortMap, $default)
    {
        $this->sortMap = $sortMap;
        $this->default = $default;
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

    public function decode($sortText) {
        if ( $sortText )
        {
            $order = $sortText[0] == '-' ? 0 : 1;
            if ( $sortText[0] == '-' ) {
                $sortText = substr($sortText, 1);
            }
            if ( isset( $this->sortMap[$sortText] ) ) {
                $sortEntry = $this->sortMap[$sortText];
                if (is_object($sortEntry) && $sortEntry instanceof \Closure) {
                    $sortArray = call_user_func_array($this->sortMap[$sortText], array($sortText, $order));
                } else if (is_string($sortEntry)) {
                    $sortArray = array(array($sortEntry, $order));
                } else {
                    $sortArray = $sortEntry;
                }
                $identifier = ($order ? '' : '-') . $sortText;
                return array('order' => $order, 'identifier' => $identifier, 'array' => $sortArray);
            }
        }
        return null;
    }

    public function resolveQuery($queryValue=null)
    {
        $this->originalIdentifier = $queryValue;
        $result = $this->decode($queryValue);
        if ($result === null) {
            $result = $this->decode($this->default);
        }

        if ( $result !== null ) {
            $this->identifier = $result['identifier'];
            $this->order = $result['order'];
            $this->sortArray = $result['array'];
        }
    }

    public function resolveArray(array $array=null)
    {
        $this->sortArray = $array;
    }
}
