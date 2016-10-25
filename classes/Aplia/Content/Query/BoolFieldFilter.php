<?php
namespace Aplia\Content\Query;

use Aplia\Content\Query\FieldFilterBase;

class BoolFieldFilter extends FieldFilterBase
{
    public $queryParam = null;

    public function hasSelected()
    {
        if (is_array($this->selected)) {
            return $this->selected;
        }
        return $this->selected !== null;
    }

    public function resolveQuery($queryParams)
    {
        if ( !isset($queryParams[$this->queryParam]) ) {
            return;
        }

        $value = $queryParams[$this->queryParam];
        $selected = array();
        if ( is_array($value) ) {
            foreach ( $value as $v ) {
                $v = (bool)$v;
                if ( $v ) {
                    $selected[] = $v;
                }
            }
        } else {
            $value = is_numeric( $value ) ? (bool)$value : null;
            if ( $value !== null ) {
                $selected = array( $value );
            }
        }
        if ( $selected )
        {
            $this->selected = $selected;
        }
    }
}
