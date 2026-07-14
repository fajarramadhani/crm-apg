import type { Ticket } from '../types'

export interface TicketRepository {
  listByRequester(requesterIds: string[]): Promise<Ticket[]>
}
