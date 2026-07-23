<?php

namespace App\Enums;

enum KnowledgeBaseArticleStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Published = 'published';
    case Rejected = 'rejected';
    case Archived = 'archived';
}
