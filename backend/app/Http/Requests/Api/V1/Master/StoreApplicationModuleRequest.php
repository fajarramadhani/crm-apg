<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreApplicationModuleRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'module';

    protected const UPDATE = false;
}
