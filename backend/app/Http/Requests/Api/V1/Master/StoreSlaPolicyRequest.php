<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreSlaPolicyRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'sla';

    protected const UPDATE = false;
}
