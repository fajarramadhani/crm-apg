import { TICKETS } from '../data'
import type { TicketRepository } from './ticketRepository'

export const mockTicketRepository: TicketRepository = {
  async listByRequester(requesterIds) {
    return TICKETS.filter((ticket) => requesterIds.includes(ticket.requesterId))
  },
}
