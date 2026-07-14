import { authRepository } from '../repositories/authRepository'

export const authService = {
  login: (email: string, password: string) => authRepository.login(email.trim().toLowerCase(), password),
  currentUser: () => authRepository.me(),
  logout: () => authRepository.logout(),
}
