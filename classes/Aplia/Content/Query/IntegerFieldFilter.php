<?php
namespace Aplia\Content\Query;

class IntegerFieldFilter extends FieldFilterBase
{
    public $queryParam = null;

    public function resolveQuery($queryParams)
    {
        if ( !isset($queryParams[$this->queryParam]) ) {
            return;
        }

        $value = $queryParams[$this->queryParam];
        $selected = array();
        if ( is_array($value) ) {
            foreach ( $value as $v ) {
                $v = (int)$v;
                if ( $v ) {
                    $selected[] = $v;
                }
            }
        } else {
            $value = is_numeric( $value ) ? (int)$value : null;
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
