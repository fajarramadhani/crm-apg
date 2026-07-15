<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreBranchRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'branch';

    protected const UPDATE = false;
}
