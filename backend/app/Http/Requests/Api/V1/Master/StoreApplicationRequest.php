<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreApplicationRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'application';

    protected const UPDATE = false;
}
