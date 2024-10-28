<?php

namespace App\Services\GoogleAPI;

use Illuminate\Support\Facades\Facade;

class ClientFacade extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'googleClient';
    }
}
