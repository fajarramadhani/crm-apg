<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateSlaPolicyRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'sla';

    protected const UPDATE = true;
}
