<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateApplicationRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'application';

    protected const UPDATE = true;
}
