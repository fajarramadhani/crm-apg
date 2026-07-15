<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateHolidayRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'holiday';

    protected const UPDATE = true;
}
