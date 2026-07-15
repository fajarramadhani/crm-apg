<?php

namespace App\Http\Requests\Api\V1\Master;

final class UpdateTicketPriorityRequest extends AbstractMasterDataRequest
{
    protected const ENTITY = 'priority';

    protected const UPDATE = true;
}
