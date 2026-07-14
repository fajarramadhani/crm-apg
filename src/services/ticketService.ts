import { mockTicketRepository } from '../repositories/mockTicketRepository'
import type { TicketRepository } from '../repositories/ticketRepository'

export function createTicketService(repository: TicketRepository = mockTicketRepository) {
  return {
    listMyTickets: () => repository.listByRequester(['u1', 'u9']),
  }
}

export const ticketService = createTicketService()
