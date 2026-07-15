<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreTicketCategoryRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'category';

    protected const UPDATE = false;
}
