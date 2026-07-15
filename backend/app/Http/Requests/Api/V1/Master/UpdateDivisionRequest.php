<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateDivisionRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'division';

    protected const UPDATE = true;
}
