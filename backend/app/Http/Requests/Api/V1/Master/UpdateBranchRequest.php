<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateBranchRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'branch';

    protected const UPDATE = true;
}
