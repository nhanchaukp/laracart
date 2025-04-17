<?php

namespace NhanChauKP\LaraCart\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for the LaraCart service.
 *
 * Provides a static interface to the LaraCart service.
 */
class LaraCart extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laracart';
    }
}
