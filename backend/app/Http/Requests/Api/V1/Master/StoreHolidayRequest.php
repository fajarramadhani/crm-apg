<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreHolidayRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'holiday';

    protected const UPDATE = false;
}
