<?php

namespace App\Enums;

enum KnowledgeBaseVisibility: string
{
    case ItInternal = 'it_internal';
    case BusinessInternal = 'business_internal';
    case AllAuthenticated = 'all_authenticated';
}
