<?php

namespace Ndinhbang\QueryCache\Mixins;

use Illuminate\Database\Eloquent\Model;

class HasManyThroughMixin
{
    public function getThroughParent(): \Closure
    {
        return function (): Model {
            /** @var \Illuminate\Database\Eloquent\Relations\HasManyThrough $this */
            return $this->throughParent;
        };
    }

    public function getFarParent(): \Closure
    {
        return function (): Model {
            /** @var \Illuminate\Database\Eloquent\Relations\HasManyThrough $this */
            return $this->farParent;
        };
    }
}
