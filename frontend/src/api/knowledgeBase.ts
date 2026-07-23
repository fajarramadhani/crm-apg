import { apiClient } from './client'
import type {
  KnowledgeBaseActivity,
  KnowledgeBaseArticle,
  KnowledgeBaseArticleDetail,
  KnowledgeBaseArticlePayload,
  KnowledgeBaseFilters,
  KnowledgeBaseRelationType,
  KnowledgeBaseTag,
  KnowledgeBaseVersion,
  LaravelCollection,
  LaravelResource,
} from '../types/knowledgeBase'

function queryString(filters: KnowledgeBaseFilters): string {
  const params = new URLSearchParams()
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== '') params.set(key, String(value))
  })
  const query = params.toString()
  return query ? `?${query}` : ''
}

const resource = async <T>(request: Promise<LaravelResource<T>>): Promise<T> => (await request).data
const collection = async <T>(request: Promise<LaravelCollection<T>>): Promise<T[]> => (await request).data

export const knowledgeBaseApi = {
  list: (filters: KnowledgeBaseFilters = {}) =>
    apiClient.get<LaravelCollection<KnowledgeBaseArticle>>(`/knowledge-base${queryString(filters)}`),
  get: (idOrSlug: number | string) =>
    resource(apiClient.get<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${idOrSlug}`)),
  create: (payload: KnowledgeBaseArticlePayload) =>
    resource(apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>('/knowledge-base', payload)),
  update: (id: number, payload: KnowledgeBaseArticlePayload) =>
    resource(apiClient.put<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}`, payload)),
  related: (id: number) =>
    collection(apiClient.get<LaravelCollection<KnowledgeBaseArticle>>(`/knowledge-base/${id}/related`)),
  versions: (id: number) =>
    collection(apiClient.get<LaravelCollection<KnowledgeBaseVersion>>(`/knowledge-base/${id}/versions`)),
  activity: (id: number) =>
    collection(apiClient.get<LaravelCollection<KnowledgeBaseActivity>>(`/knowledge-base/${id}/activity`)),
  submitReview: (id: number) =>
    resource(apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}/submit-review`)),
  publish: (id: number, changeSummary?: string) =>
    resource(
      apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}/publish`, {
        change_summary: changeSummary || null,
      }),
    ),
  reject: (id: number, reason: string) =>
    resource(apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}/reject`, { reason })),
  archive: (id: number) =>
    resource(apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}/archive`)),
  restore: (id: number) =>
    resource(apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}/restore`)),
  restoreVersion: (id: number, version: number) =>
    resource(
      apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(`/knowledge-base/${id}/restore-version/${version}`),
    ),
  feedback: (id: number, isHelpful: boolean, comment?: string) =>
    apiClient.post<LaravelResource<unknown>>(`/knowledge-base/${id}/feedback`, {
      is_helpful: isHelpful,
      comment: comment || null,
    }),
  tags: () => collection(apiClient.get<LaravelCollection<KnowledgeBaseTag>>('/knowledge-base-tags')),
  createTag: (name: string) =>
    resource(apiClient.post<LaravelResource<KnowledgeBaseTag>>('/knowledge-base-tags', { name })),
  updateTag: (id: number, payload: { name?: string; is_active?: boolean }) =>
    resource(apiClient.put<LaravelResource<KnowledgeBaseTag>>(`/knowledge-base-tags/${id}`, payload)),
  deleteTag: (id: number) => apiClient.delete<void>(`/knowledge-base-tags/${id}`),
  ticketArticles: (ticketId: number) =>
    collection(apiClient.get<LaravelCollection<KnowledgeBaseArticle>>(`/tickets/${ticketId}/knowledge-base`)),
  ticketRecommendations: (ticketId: number) =>
    collection(
      apiClient.get<LaravelCollection<KnowledgeBaseArticle>>(`/tickets/${ticketId}/knowledge-base/recommendations`),
    ),
  linkTicket: (ticketId: number, articleId: number, relationType: KnowledgeBaseRelationType) =>
    apiClient.post<{ message: string }>(`/tickets/${ticketId}/knowledge-base/link`, {
      article_id: articleId,
      relation_type: relationType,
    }),
  unlinkTicket: (ticketId: number, articleId: number) =>
    apiClient.delete<{ message: string }>(`/tickets/${ticketId}/knowledge-base/${articleId}`, {
      body: JSON.stringify({ article_id: articleId }),
      headers: { 'Content-Type': 'application/json' },
    }),
  createDraftFromTicket: (ticketId: number, payload: { title?: string; visibility?: string }) =>
    resource(
      apiClient.post<LaravelResource<KnowledgeBaseArticleDetail>>(
        `/tickets/${ticketId}/knowledge-base/create-draft`,
        payload,
      ),
    ),
}
