<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateWorkingCalendarRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'calendar';

    protected const UPDATE = true;
}
