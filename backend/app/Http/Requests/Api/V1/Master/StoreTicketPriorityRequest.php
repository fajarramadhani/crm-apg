<?php

namespace App\Http\Requests\Api\V1\Master;

final class StoreTicketPriorityRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'priority';

    protected const UPDATE = false;
}
