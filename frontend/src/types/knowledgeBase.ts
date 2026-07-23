export type KnowledgeBaseStatus = 'draft' | 'in_review' | 'published' | 'rejected' | 'archived'
export type KnowledgeBaseVisibility = 'it_internal' | 'business_internal' | 'all_authenticated'

export interface KnowledgeBaseTag {
  id: number
  name: string
  slug: string
  is_active: boolean
}

export interface KnowledgeBaseArticle {
  id: number
  article_number: string
  slug: string
  title: string
  summary: string
  status: KnowledgeBaseStatus
  visibility: KnowledgeBaseVisibility
  category: { id: number; name: string } | null
  application: { id: number; name: string } | null
  author: { id: number; name: string }
  current_version: number
  view_count: number
  helpful_count: number
  not_helpful_count: number
  helpful_ratio: number | null
  published_at: string | null
  created_at: string
  updated_at: string
  tags: KnowledgeBaseTag[]
}

export interface KnowledgeBaseArticleDetail extends KnowledgeBaseArticle {
  content: string
  reviewer: { id: number; name: string } | null
  rejection_reason?: string | null
  tickets?: { id: number; ticket_number: string; title: string; relation_type: KnowledgeBaseRelationType }[]
}

export interface KnowledgeBaseVersion {
  id: number
  article_id: number
  version_number: number
  title: string
  summary: string
  content: string
  visibility: KnowledgeBaseVisibility
  category: { id: number; name: string } | null
  application: { id: number; name: string } | null
  change_summary: string | null
  creator: { id: number; name: string }
  created_at: string
}

export interface KnowledgeBaseActivity {
  id: number
  article_id: number
  actor: { id: number; name: string }
  action: string
  from_status: KnowledgeBaseStatus | null
  to_status: KnowledgeBaseStatus | null
  metadata?: Record<string, unknown>
  created_at: string
}

export interface KnowledgeBaseFilters {
  search?: string
  status?: KnowledgeBaseStatus | ''
  visibility?: KnowledgeBaseVisibility | ''
  category_id?: number | ''
  application_id?: number | ''
  tag?: number | ''
  sort_by?: 'published_at' | 'created_at' | 'updated_at' | 'title' | 'view_count' | 'helpful_count'
  sort_order?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export interface KnowledgeBaseArticlePayload {
  title: string
  summary: string
  content: string
  visibility: KnowledgeBaseVisibility
  category_id: number | null
  application_id: number | null
  tags: number[]
  status?: 'draft' | 'in_review'
  change_summary?: string
}

export type KnowledgeBaseRelationType = 'source' | 'related' | 'used_as_solution' | 'recommended'

export interface LaravelResource<T> {
  data: T
}

export interface LaravelCollection<T> {
  data: T[]
  links?: { first: string | null; last: string | null; prev: string | null; next: string | null }
  meta?: {
    current_page: number
    from: number | null
    last_page: number
    per_page: number
    to: number | null
    total: number
  }
}
